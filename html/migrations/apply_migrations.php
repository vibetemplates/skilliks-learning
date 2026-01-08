<?php
/**
 * Apply Database Migrations
 * 
 * This script applies the community migrations to the database
 */

// Include database configuration
require_once dirname(__DIR__) . '/config/database.php';

echo "=== Applying Database Migrations ===\n\n";

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Check if communities table already exists
    $checkTable = "SHOW TABLES LIKE 'communities'";
    $stmt = $db->query($checkTable);
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Communities table already exists\n";
        
        // Check if default community exists
        $checkDefault = "SELECT id FROM communities WHERE slug = 'default'";
        $stmt = $db->query($checkDefault);
        $defaultExists = $stmt->fetch();
        
        if ($defaultExists) {
            echo "✓ Default community already exists (ID: {$defaultExists['id']})\n";
        }
    } else {
        echo "Creating communities tables...\n";
        
        // Read and execute first migration
        $migration1 = file_get_contents(__DIR__ . '/001_create_communities_table.sql');
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $migration1)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && stripos($statement, 'DELIMITER') === false) {
                try {
                    $db->exec($statement);
                    echo ".";
                } catch (PDOException $e) {
                    // Check if it's a duplicate key/table exists error
                    if ($e->getCode() != '42S01' && $e->getCode() != '42000') {
                        echo "\n✗ Error: " . $e->getMessage() . "\n";
                        echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    }
                }
            }
        }
        echo "\n✓ Communities tables created successfully\n";
    }
    
    // Check if community_id column exists in projects table
    $checkColumn = "SHOW COLUMNS FROM projects LIKE 'community_id'";
    $stmt = $db->query($checkColumn);
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✓ community_id column already exists in projects table\n";
    } else {
        echo "\nAdding community_id to existing tables...\n";
        
        // Get default community ID
        $stmt = $db->query("SELECT id FROM communities WHERE slug = 'default'");
        $defaultComm = $stmt->fetch();
        
        if (!$defaultComm) {
            // Create default community if it doesn't exist
            $createDefault = "INSERT INTO communities (name, slug, description, is_active, is_public, requires_approval, created_by)
                            VALUES ('Default Community', 'default', 'Original community - all existing projects and courses', 1, 1, 0, 
                                    (SELECT id FROM users WHERE role = 'admin' LIMIT 1))";
            $db->exec($createDefault);
            $defaultId = $db->lastInsertId();
            echo "✓ Created default community (ID: $defaultId)\n";
        } else {
            $defaultId = $defaultComm['id'];
        }
        
        // Read second migration
        $migration2 = file_get_contents(__DIR__ . '/002_add_community_id_to_existing_tables.sql');
        
        // Replace the placeholder with actual default community ID
        $migration2 = str_replace('@default_community_id', $defaultId, $migration2);
        
        // Split and execute statements
        $statements = array_filter(array_map('trim', explode(';', $migration2)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && stripos($statement, 'SET @') === false) {
                try {
                    // Skip if it's trying to add a column that already exists
                    if (stripos($statement, 'ALTER TABLE') !== false && stripos($statement, 'ADD COLUMN') !== false) {
                        // Extract table and column name
                        preg_match('/ALTER TABLE `?(\w+)`?\s+ADD COLUMN `?(\w+)`?/i', $statement, $matches);
                        if (isset($matches[1]) && isset($matches[2])) {
                            $table = $matches[1];
                            $column = $matches[2];
                            
                            // Check if column already exists
                            $checkCol = "SHOW COLUMNS FROM `$table` LIKE '$column'";
                            $stmt = $db->query($checkCol);
                            if ($stmt->fetch()) {
                                echo ".";
                                continue; // Skip this statement
                            }
                        }
                    }
                    
                    $db->exec($statement);
                    echo ".";
                } catch (PDOException $e) {
                    // Ignore duplicate key/column errors
                    if ($e->getCode() != '42S21' && $e->getCode() != '42000' && $e->getCode() != '42S01') {
                        echo "\n✗ Error: " . $e->getMessage() . "\n";
                        echo "Statement: " . substr($statement, 0, 100) . "...\n";
                    } else {
                        echo ".";
                    }
                }
            }
        }
        echo "\n✓ Added community_id to all tables\n";
    }
    
    // Add all existing users to default community if not already members
    echo "\nEnsuring all users are members of default community...\n";
    
    $stmt = $db->query("SELECT id FROM communities WHERE slug = 'default'");
    $defaultComm = $stmt->fetch();
    
    if ($defaultComm) {
        $addUsers = "INSERT IGNORE INTO community_members (community_id, user_id, role, is_active)
                     SELECT ?, u.id, 
                            CASE 
                                WHEN u.role = 'admin' THEN 'admin'
                                WHEN u.role = 'project_manager' THEN 'moderator'
                                ELSE 'member'
                            END,
                            1
                     FROM users u
                     WHERE NOT EXISTS (
                         SELECT 1 FROM community_members cm 
                         WHERE cm.user_id = u.id AND cm.community_id = ?
                     )";
        
        $stmt = $db->prepare($addUsers);
        $stmt->execute([$defaultComm['id'], $defaultComm['id']]);
        $added = $stmt->rowCount();
        
        echo "✓ Added $added users to default community\n";
        
        // Update users default_community_id if null
        $updateUsers = "UPDATE users SET default_community_id = ? WHERE default_community_id IS NULL";
        $stmt = $db->prepare($updateUsers);
        $stmt->execute([$defaultComm['id']]);
        $updated = $stmt->rowCount();
        
        echo "✓ Updated default community for $updated users\n";
    }
    
    // Show summary
    echo "\n=== Migration Summary ===\n";
    
    // Count communities
    $stmt = $db->query("SELECT COUNT(*) as count FROM communities WHERE is_active = 1");
    $communities = $stmt->fetch();
    echo "Communities: {$communities['count']}\n";
    
    // Count community members
    $stmt = $db->query("SELECT COUNT(*) as count FROM community_members WHERE is_active = 1");
    $members = $stmt->fetch();
    echo "Community Members: {$members['count']}\n";
    
    // Check if projects have community_id
    $stmt = $db->query("SHOW COLUMNS FROM projects LIKE 'community_id'");
    $hasCommId = $stmt->fetch();
    echo "Projects table has community_id: " . ($hasCommId ? 'Yes' : 'No') . "\n";
    
    // Check if courses have community_id
    $stmt = $db->query("SHOW COLUMNS FROM courses LIKE 'community_id'");
    $hasCommId = $stmt->fetch();
    echo "Courses table has community_id: " . ($hasCommId ? 'Yes' : 'No') . "\n";
    
    echo "\n✓ All migrations completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Error trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>