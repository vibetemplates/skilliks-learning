-- Create free_community_emails table for auto-approval of free community members
-- This table will be populated by an external process

CREATE TABLE IF NOT EXISTS `free_community_emails` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `firstname` VARCHAR(100) NOT NULL,
    `lastname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `invitedby` VARCHAR(255) DEFAULT NULL COMMENT 'Email or name of person who invited this user',
    `joineddate` DATETIME DEFAULT NULL COMMENT 'Date when the user joined',
    `question1` TEXT DEFAULT NULL COMMENT 'First screening question',
    `answer1` TEXT DEFAULT NULL COMMENT 'Answer to first question',
    `question2` TEXT DEFAULT NULL COMMENT 'Second screening question',
    `answer2` TEXT DEFAULT NULL COMMENT 'Answer to second question',
    `question3` TEXT DEFAULT NULL COMMENT 'Third screening question',
    `answer3` TEXT DEFAULT NULL COMMENT 'Answer to third question',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processed` TINYINT(1) DEFAULT 0 COMMENT 'Whether this record has been processed for auto-approval',
    `processed_at` DATETIME DEFAULT NULL COMMENT 'When this record was processed',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_email` (`email`),
    KEY `idx_processed` (`processed`),
    KEY `idx_joineddate` (`joineddate`),
    KEY `idx_invitedby` (`invitedby`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External list of emails for auto-approval in free communities';

-- Add index for faster lookups when checking if an email should be auto-approved
ALTER TABLE `free_community_emails` ADD INDEX `idx_email_processed` (`email`, `processed`);