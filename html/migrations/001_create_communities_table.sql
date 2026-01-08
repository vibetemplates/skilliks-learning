-- Migration: Add Multi-Community Support
-- Date: 2025-07-17
-- Description: Creates communities table and related tables for multi-community support

-- =====================================================
-- COMMUNITIES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `communities` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `logo_url` VARCHAR(500),
    `banner_url` VARCHAR(500),
    `settings` JSON,
    `is_active` BOOLEAN DEFAULT TRUE,
    `is_public` BOOLEAN DEFAULT FALSE,
    `requires_approval` BOOLEAN DEFAULT TRUE,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    INDEX idx_slug (`slug`),
    INDEX idx_is_active (`is_active`),
    INDEX idx_is_public (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COMMUNITY MEMBERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `community_members` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `community_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `role` ENUM('member', 'moderator', 'admin', 'owner') DEFAULT 'member',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `invited_by` INT UNSIGNED,
    `is_active` BOOLEAN DEFAULT TRUE,
    `last_active` TIMESTAMP NULL,
    FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY unique_community_member (`community_id`, `user_id`),
    INDEX idx_user_id (`user_id`),
    INDEX idx_community_id (`community_id`),
    INDEX idx_role (`role`),
    INDEX idx_is_active (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COMMUNITY INVITATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `community_invitations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `community_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(100) NOT NULL UNIQUE,
    `role` ENUM('member', 'moderator', 'admin') DEFAULT 'member',
    `invited_by` INT UNSIGNED NOT NULL,
    `invited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `accepted_at` TIMESTAMP NULL,
    `accepted_by_user_id` INT UNSIGNED,
    `status` ENUM('pending', 'accepted', 'expired', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`accepted_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX idx_token (`token`),
    INDEX idx_email (`email`),
    INDEX idx_status (`status`),
    INDEX idx_community_id (`community_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADD DEFAULT_COMMUNITY_ID TO USERS TABLE
-- =====================================================
ALTER TABLE `users` 
ADD COLUMN `default_community_id` INT UNSIGNED DEFAULT NULL AFTER `reset_token_expires`,
ADD FOREIGN KEY (`default_community_id`) REFERENCES `communities`(`id`) ON DELETE SET NULL,
ADD INDEX idx_default_community (`default_community_id`);

-- =====================================================
-- CREATE DEFAULT COMMUNITY FOR EXISTING DATA
-- =====================================================
-- Insert a default community for existing data
INSERT INTO `communities` (`name`, `slug`, `description`, `is_active`, `is_public`, `requires_approval`, `created_by`)
SELECT 
    'Default Community',
    'default',
    'Original community - all existing projects and courses',
    TRUE,
    TRUE,
    FALSE,
    (SELECT `id` FROM `users` WHERE `id` IN (SELECT `user_id` FROM `user_roles` WHERE `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'administrator')) LIMIT 1)
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `communities` WHERE `slug` = 'default');

-- Get the default community ID
SET @default_community_id = (SELECT `id` FROM `communities` WHERE `slug` = 'default');

-- Add all existing users as members of the default community
INSERT INTO `community_members` (`community_id`, `user_id`, `role`, `is_active`)
SELECT 
    @default_community_id,
    u.`id`,
    CASE 
        WHEN EXISTS (SELECT 1 FROM `user_roles` ur JOIN `roles` r ON ur.`role_id` = r.`id` WHERE ur.`user_id` = u.`id` AND r.`name` = 'administrator')
        THEN 'admin'
        WHEN EXISTS (SELECT 1 FROM `user_roles` ur JOIN `roles` r ON ur.`role_id` = r.`id` WHERE ur.`user_id` = u.`id` AND r.`name` = 'project_manager')
        THEN 'moderator'
        ELSE 'member'
    END,
    TRUE
FROM `users` u
WHERE NOT EXISTS (SELECT 1 FROM `community_members` WHERE `user_id` = u.`id` AND `community_id` = @default_community_id);

-- Set default community for all users
UPDATE `users` SET `default_community_id` = @default_community_id WHERE `default_community_id` IS NULL;

-- =====================================================
-- COMMUNITY SETTINGS TABLE (for extensibility)
-- =====================================================
CREATE TABLE IF NOT EXISTS `community_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `community_id` INT UNSIGNED NOT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('string', 'boolean', 'number', 'json') DEFAULT 'string',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
    UNIQUE KEY unique_community_setting (`community_id`, `setting_key`),
    INDEX idx_setting_key (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COMMUNITY ANNOUNCEMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `community_announcements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `community_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `is_pinned` BOOLEAN DEFAULT FALSE,
    `published_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_community_published (`community_id`, `published_at`),
    INDEX idx_priority (`priority`),
    INDEX idx_is_pinned (`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;