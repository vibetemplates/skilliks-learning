<?php
/**
 * Apply Role System Migration - Simple Version
 */

require_once dirname(__DIR__) . '/config/database.php';

echo "=== Applying Role System Migration (Simple) ===\n\n";

$db = Database::getInstance()->getConnection();

// Step 1: Update users table
echo "1. Updating users table...\n";
try {
    $db->exec("ALTER TABLE `users` CHANGE COLUMN `role` `global_role` ENUM('user', 'admin') DEFAULT 'user'");
    echo "   ✓ Changed role to global_role\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S22') {
        echo "   - Column already renamed\n";
    } else {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
}

// Update existing data
try {
    $db->exec("UPDATE `users` SET `global_role` = CASE WHEN `global_role` = 'admin' THEN 'admin' ELSE 'user' END");
    echo "   ✓ Updated user roles\n";
} catch (PDOException $e) {
    echo "   ✗ Error updating roles: " . $e->getMessage() . "\n";
}

// Step 2: Create global_admins table
echo "\n2. Creating global_admins table...\n";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `global_admins` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `granted_by` INT UNSIGNED,
        `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `notes` TEXT,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        UNIQUE KEY unique_admin (`user_id`),
        INDEX idx_user_id (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ Created global_admins table\n";
} catch (PDOException $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Populate global_admins
try {
    $db->exec("INSERT IGNORE INTO `global_admins` (`user_id`, `notes`)
               SELECT `id`, 'Migrated from original admin role' 
               FROM `users` 
               WHERE `global_role` = 'admin'");
    $count = $db->query("SELECT COUNT(*) FROM global_admins")->fetchColumn();
    echo "   ✓ Added $count global admins\n";
} catch (PDOException $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Step 3: Create permissions table
echo "\n3. Creating permissions table...\n";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `community_permissions` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `community_id` INT UNSIGNED NOT NULL,
        `role` ENUM('member', 'moderator', 'admin', 'owner') NOT NULL,
        `permission` VARCHAR(100) NOT NULL,
        `granted` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
        UNIQUE KEY unique_permission (`community_id`, `role`, `permission`),
        INDEX idx_community_role (`community_id`, `role`),
        INDEX idx_permission (`permission`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ Created community_permissions table\n";
} catch (PDOException $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Step 4: Create role change log table
echo "\n4. Creating role change log table...\n";
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `role_change_log` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `community_id` INT UNSIGNED,
        `old_role` VARCHAR(50),
        `new_role` VARCHAR(50),
        `changed_by` INT UNSIGNED NOT NULL,
        `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `reason` TEXT,
        `change_type` ENUM('global', 'community') NOT NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX idx_user_id (`user_id`),
        INDEX idx_community_id (`community_id`),
        INDEX idx_changed_at (`changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ Created role_change_log table\n";
} catch (PDOException $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Step 5: Add default permissions
echo "\n5. Adding default permissions...\n";
try {
    // Get all communities
    $communities = $db->query("SELECT id FROM communities")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($communities as $communityId) {
        // Owner permissions
        $ownerPerms = ['manage_community', 'manage_members', 'manage_roles', 'delete_community', 
                       'manage_projects', 'manage_courses', 'approve_members', 'create_announcements'];
        foreach ($ownerPerms as $perm) {
            $db->exec("INSERT IGNORE INTO community_permissions (community_id, role, permission) 
                       VALUES ($communityId, 'owner', '$perm')");
        }
        
        // Admin permissions (same as owner minus delete)
        $adminPerms = ['manage_community', 'manage_members', 'manage_roles', 
                       'manage_projects', 'manage_courses', 'approve_members', 'create_announcements'];
        foreach ($adminPerms as $perm) {
            $db->exec("INSERT IGNORE INTO community_permissions (community_id, role, permission) 
                       VALUES ($communityId, 'admin', '$perm')");
        }
        
        // Moderator permissions
        $modPerms = ['manage_projects', 'manage_courses', 'approve_members', 'create_announcements'];
        foreach ($modPerms as $perm) {
            $db->exec("INSERT IGNORE INTO community_permissions (community_id, role, permission) 
                       VALUES ($communityId, 'moderator', '$perm')");
        }
        
        // Member permissions
        $memberPerms = ['view_community', 'create_projects', 'join_projects', 'create_tasks', 'comment'];
        foreach ($memberPerms as $perm) {
            $db->exec("INSERT IGNORE INTO community_permissions (community_id, role, permission) 
                       VALUES ($communityId, 'member', '$perm')");
        }
    }
    
    $count = $db->query("SELECT COUNT(*) FROM community_permissions")->fetchColumn();
    echo "   ✓ Added $count permission entries\n";
} catch (PDOException $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Step 6: Summary
echo "\n=== Migration Summary ===\n";

// Check results
$globalAdmins = $db->query("SELECT COUNT(*) FROM global_admins")->fetchColumn();
echo "- Global admins: $globalAdmins\n";

$stmt = $db->query("SELECT role, COUNT(*) as count FROM community_members GROUP BY role");
echo "- Community roles:\n";
while ($row = $stmt->fetch()) {
    echo "  • {$row['role']}: {$row['count']}\n";
}

echo "\n✓ Role system migration completed!\n";
?>