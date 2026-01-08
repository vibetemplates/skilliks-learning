<?php
/**
 * Verify Database Migrations
 * 
 * This script verifies that all migrations were applied correctly
 */

require_once dirname(__DIR__) . '/config/database.php';

echo "=== Verifying Database Migrations ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Check communities table
    echo "1. Checking communities table structure:\n";
    $stmt = $db->query("DESCRIBE communities");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['id', 'name', 'slug', 'description', 'is_active', 'is_public', 'requires_approval', 'created_by'];
    $foundColumns = array_column($columns, 'Field');
    
    foreach ($requiredColumns as $col) {
        if (in_array($col, $foundColumns)) {
            echo "   ✓ Column '$col' exists\n";
        } else {
            echo "   ✗ Column '$col' is missing\n";
        }
    }
    
    // 2. Check community_members table
    echo "\n2. Checking community_members table:\n";
    $stmt = $db->query("DESCRIBE community_members");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['id', 'community_id', 'user_id', 'role', 'joined_at', 'is_active'];
    $foundColumns = array_column($columns, 'Field');
    
    foreach ($requiredColumns as $col) {
        if (in_array($col, $foundColumns)) {
            echo "   ✓ Column '$col' exists\n";
        } else {
            echo "   ✗ Column '$col' is missing\n";
        }
    }
    
    // 3. Check if community_id was added to key tables
    echo "\n3. Checking community_id in other tables:\n";
    $tables = ['projects', 'courses', 'tasks', 'features', 'sprints', 'activities', 'notifications'];
    
    foreach ($tables as $table) {
        // Check if table exists first
        $checkTable = $db->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->fetch()) {
            $stmt = $db->query("SHOW COLUMNS FROM $table LIKE 'community_id'");
            if ($stmt->fetch()) {
                echo "   ✓ Table '$table' has community_id column\n";
            } else {
                echo "   ✗ Table '$table' is missing community_id column\n";
            }
        } else {
            echo "   - Table '$table' does not exist (might not be created yet)\n";
        }
    }
    
    // 4. Check default community
    echo "\n4. Checking default community:\n";
    $stmt = $db->query("SELECT * FROM communities WHERE slug = 'default'");
    $defaultComm = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($defaultComm) {
        echo "   ✓ Default community exists:\n";
        echo "     - ID: {$defaultComm['id']}\n";
        echo "     - Name: {$defaultComm['name']}\n";
        echo "     - Active: " . ($defaultComm['is_active'] ? 'Yes' : 'No') . "\n";
        echo "     - Public: " . ($defaultComm['is_public'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "   ✗ Default community is missing\n";
    }
    
    // 5. Check community members count
    echo "\n5. Community statistics:\n";
    $stmt = $db->query("SELECT c.name, COUNT(cm.id) as member_count 
                        FROM communities c 
                        LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.is_active = 1
                        WHERE c.is_active = 1
                        GROUP BY c.id");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$row['name']}: {$row['member_count']} members\n";
    }
    
    // 6. Check users have default_community_id
    echo "\n6. Checking users table:\n";
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'default_community_id'");
    if ($stmt->fetch()) {
        echo "   ✓ Users table has default_community_id column\n";
        
        // Count users without default community
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE default_community_id IS NULL");
        $result = $stmt->fetch();
        if ($result['count'] == 0) {
            echo "   ✓ All users have a default community set\n";
        } else {
            echo "   ✗ {$result['count']} users don't have a default community\n";
        }
    } else {
        echo "   ✗ Users table is missing default_community_id column\n";
    }
    
    echo "\n✓ Verification complete!\n";
    
} catch (Exception $e) {
    echo "\n✗ Verification failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>