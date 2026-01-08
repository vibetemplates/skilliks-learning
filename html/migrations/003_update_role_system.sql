-- Migration: Update Role System for Community-Specific Roles
-- Date: 2025-07-17
-- Description: Separates global site admin role from community-specific roles

-- =====================================================
-- UPDATE USERS TABLE
-- =====================================================
-- Rename existing 'role' column to 'global_role' and simplify to just track site admins
ALTER TABLE `users` 
CHANGE COLUMN `role` `global_role` ENUM('user', 'admin') DEFAULT 'user' COMMENT 'Global site role - admin can manage entire site';

-- Update existing data: 
-- - Current 'admin' users remain 'admin' 
-- - Everyone else becomes 'user'
UPDATE `users` 
SET `global_role` = CASE 
    WHEN `global_role` = 'admin' THEN 'admin'
    ELSE 'user'
END;

-- =====================================================
-- UPDATE COMMUNITY_MEMBERS TABLE
-- =====================================================
-- Update the role enum to include all community-specific roles
ALTER TABLE `community_members` 
MODIFY COLUMN `role` ENUM('member', 'moderator', 'admin', 'owner') DEFAULT 'member' 
COMMENT 'Role within this specific community';

-- Add index for better performance on role queries
ALTER TABLE `community_members` ADD INDEX idx_role_active (`role`, `is_active`);

-- =====================================================
-- CREATE GLOBAL ADMINS TABLE (for tracking site admins explicitly)
-- =====================================================
CREATE TABLE IF NOT EXISTS `global_admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `granted_by` INT UNSIGNED,
    `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY unique_admin (`user_id`),
    INDEX idx_user_id (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Populate global_admins table with existing admins
INSERT INTO `global_admins` (`user_id`, `notes`)
SELECT `id`, 'Migrated from original admin role' 
FROM `users` 
WHERE `global_role` = 'admin';

-- =====================================================
-- UPDATE COMMUNITY ROLES FOR EXISTING DATA
-- =====================================================
-- Update community_members roles based on previous global roles
UPDATE `community_members` cm
JOIN `users` u ON cm.`user_id` = u.`id`
SET cm.`role` = CASE
    -- If they were a global admin and are currently marked as admin in community, make them community admin
    WHEN u.`global_role` = 'admin' AND cm.`role` IN ('admin', 'owner') THEN cm.`role`
    -- If they had project_manager role globally, make them moderator in their communities
    WHEN cm.`role` = 'moderator' THEN 'moderator'
    -- Everyone else is a regular member
    ELSE 'member'
END;

-- =====================================================
-- ADD PERMISSIONS TABLE FOR FINE-GRAINED CONTROL
-- =====================================================
CREATE TABLE IF NOT EXISTS `community_permissions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default permissions for each role
INSERT INTO `community_permissions` (`community_id`, `role`, `permission`) 
SELECT c.id, 'owner', perm.permission
FROM `communities` c
CROSS JOIN (
    SELECT 'manage_community' as permission UNION ALL
    SELECT 'manage_members' UNION ALL
    SELECT 'manage_roles' UNION ALL
    SELECT 'delete_community' UNION ALL
    SELECT 'manage_projects' UNION ALL
    SELECT 'manage_courses' UNION ALL
    SELECT 'approve_members' UNION ALL
    SELECT 'create_announcements'
) perm;

-- Admin permissions (everything except delete_community)
INSERT INTO `community_permissions` (`community_id`, `role`, `permission`) 
SELECT c.id, 'admin', perm.permission
FROM `communities` c
CROSS JOIN (
    SELECT 'manage_community' as permission UNION ALL
    SELECT 'manage_members' UNION ALL
    SELECT 'manage_roles' UNION ALL
    SELECT 'manage_projects' UNION ALL
    SELECT 'manage_courses' UNION ALL
    SELECT 'approve_members' UNION ALL
    SELECT 'create_announcements'
) perm;

-- Moderator permissions
INSERT INTO `community_permissions` (`community_id`, `role`, `permission`) 
SELECT c.id, 'moderator', perm.permission
FROM `communities` c
CROSS JOIN (
    SELECT 'manage_projects' as permission UNION ALL
    SELECT 'manage_courses' UNION ALL
    SELECT 'approve_members' UNION ALL
    SELECT 'create_announcements'
) perm;

-- Member permissions (basic access)
INSERT INTO `community_permissions` (`community_id`, `role`, `permission`) 
SELECT c.id, 'member', perm.permission
FROM `communities` c
CROSS JOIN (
    SELECT 'view_community' as permission UNION ALL
    SELECT 'create_projects' UNION ALL
    SELECT 'join_projects' UNION ALL
    SELECT 'create_tasks' UNION ALL
    SELECT 'comment'
) perm;

-- =====================================================
-- CREATE AUDIT LOG FOR ROLE CHANGES
-- =====================================================
CREATE TABLE IF NOT EXISTS `role_change_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- UPDATE VIEWS/QUERIES TO USE NEW STRUCTURE
-- =====================================================
-- Create a view for easy access to user's global admin status
CREATE OR REPLACE VIEW `user_is_global_admin` AS
SELECT 
    u.id as user_id,
    u.email,
    CASE WHEN ga.id IS NOT NULL THEN 1 ELSE 0 END as is_global_admin
FROM `users` u
LEFT JOIN `global_admins` ga ON u.id = ga.user_id;

-- Create a view for user's community roles
CREATE OR REPLACE VIEW `user_community_roles` AS
SELECT 
    cm.user_id,
    cm.community_id,
    c.name as community_name,
    cm.role,
    cm.joined_at,
    cm.is_active
FROM `community_members` cm
JOIN `communities` c ON cm.community_id = c.id
WHERE cm.is_active = 1 AND c.is_active = 1;