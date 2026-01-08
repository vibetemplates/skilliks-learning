-- Migration: Add Community Auto-Approvals
-- Date: 2025-07-18
-- Description: Creates table for storing auto-approval rules for communities

-- =====================================================
-- COMMUNITY AUTO-APPROVALS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `community_auto_approvals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `community_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `username` VARCHAR(100) DEFAULT NULL,
    `description` TEXT,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_community_id (`community_id`),
    INDEX idx_email (`email`),
    INDEX idx_username (`username`),
    INDEX idx_is_active (`is_active`),
    CONSTRAINT check_email_or_username CHECK (`email` IS NOT NULL OR `username` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;