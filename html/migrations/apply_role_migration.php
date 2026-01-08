<?php
/**
 * Apply Role System Migration
 * 
 * Updates the role system to separate global admins from community-specific roles
 */

require_once dirname(__DIR__) . '/config/database.php';

echo "=== Applying Role System Migration ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    echo "1. Checking current role structure...\n";
    
    // Check if migration already applied
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'global_role'");
    if ($stmt->fetch()) {
        echo "   ✓ Migration already applied (global_role column exists)\n";
        exit(0);
    }
    
    echo "2. Reading migration file...\n";
    $migration = file_get_contents(__DIR__ . '/003_update_role_system.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $migration)));
    $statementCount = 0;
    
    echo "3. Executing migration statements...\n";
    
    foreach ($statements as $statement) {
        if (!empty($statement) && stripos($statement, '--') !== 0) {
            try {
                // Skip comments and CREATE VIEW statements (handle separately)
                if (stripos($statement, 'CREATE OR REPLACE VIEW') !== false) {
                    // Handle views separately due to delimiter issues
                    $viewStatements[] = $statement;
                    continue;
                }
                
                $db->exec($statement);
                $statementCount++;
                echo ".";
                
                // Show progress for major changes
                if (stripos($statement, 'ALTER TABLE') !== false) {
                    preg_match('/ALTER TABLE `?(\w+)`?/i', $statement, $matches);
                    if (isset($matches[1])) {
                        echo "\n   ✓ Updated table: {$matches[1]}\n";
                    }
                } elseif (stripos($statement, 'CREATE TABLE') !== false) {
                    preg_match('/CREATE TABLE[^`]*`?(\w+)`?/i', $statement, $matches);
                    if (isset($matches[1])) {
                        echo "\n   ✓ Created table: {$matches[1]}\n";
                    }
                }
                
            } catch (PDOException $e) {
                // Skip if table/column already exists
                if ($e->getCode() != '42S01' && $e->getCode() != '42S21') {
                    throw $e;
                }
            }
        }
    }
    
    echo "\n\n4. Creating views...\n";
    
    // Create views manually
    try {
        $db->exec("CREATE OR REPLACE VIEW `user_is_global_admin` AS
                   SELECT 
                       u.id as user_id,
                       u.email,
                       CASE WHEN ga.id IS NOT NULL THEN 1 ELSE 0 END as is_global_admin
                   FROM `users` u
                   LEFT JOIN `global_admins` ga ON u.id = ga.user_id");
        echo "   ✓ Created view: user_is_global_admin\n";
    } catch (PDOException $e) {
        echo "   - View user_is_global_admin already exists or error: " . $e->getMessage() . "\n";
    }
    
    try {
        $db->exec("CREATE OR REPLACE VIEW `user_community_roles` AS
                   SELECT 
                       cm.user_id,
                       cm.community_id,
                       c.name as community_name,
                       cm.role,
                       cm.joined_at,
                       cm.is_active
                   FROM `community_members` cm
                   JOIN `communities` c ON cm.community_id = c.id
                   WHERE cm.is_active = 1 AND c.is_active = 1");
        echo "   ✓ Created view: user_community_roles\n";
    } catch (PDOException $e) {
        echo "   - View user_community_roles already exists or error: " . $e->getMessage() . "\n";
    }
    
    // Commit transaction
    $db->commit();
    
    echo "\n5. Verifying migration results...\n";
    
    // Check global admins
    $stmt = $db->query("SELECT COUNT(*) as count FROM global_admins");
    $globalAdmins = $stmt->fetch();
    echo "   - Global admins: {$globalAdmins['count']}\n";
    
    // Check community roles distribution
    $stmt = $db->query("SELECT role, COUNT(*) as count FROM community_members GROUP BY role");
    echo "   - Community roles:\n";
    while ($row = $stmt->fetch()) {
        echo "     • {$row['role']}: {$row['count']}\n";
    }
    
    // Check permissions
    $stmt = $db->query("SELECT COUNT(DISTINCT permission) as count FROM community_permissions");
    $perms = $stmt->fetch();
    echo "   - Unique permissions defined: {$perms['count']}\n";
    
    echo "\n✓ Role system migration completed successfully!\n";
    echo "\nSummary:\n";
    echo "- Users now have 'global_role' (user/admin) for site-wide access\n";
    echo "- Community-specific roles are stored in community_members table\n";
    echo "- Global admins are tracked in global_admins table\n";
    echo "- Permissions system created for fine-grained access control\n";
    echo "- Audit log created for tracking role changes\n";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
?>