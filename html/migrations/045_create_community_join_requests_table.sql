-- Create table for community join requests
-- This table tracks pending requests to join communities that require approval

CREATE TABLE IF NOT EXISTS `community_join_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `community_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `request_message` TEXT,
    `status` ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reviewed_by` INT UNSIGNED,
    `reviewed_at` TIMESTAMP NULL,
    `review_notes` TEXT,
    
    -- Foreign key constraints
    FOREIGN KEY (`community_id`) REFERENCES `communities`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    -- Unique constraint to prevent duplicate pending requests
    UNIQUE KEY unique_pending_request (`community_id`, `user_id`, `status`),
    
    -- Indexes for performance
    INDEX idx_community_status (`community_id`, `status`),
    INDEX idx_user_status (`user_id`, `status`),
    INDEX idx_requested_at (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to table
ALTER TABLE `community_join_requests` 
COMMENT = 'Tracks join requests for communities that require approval';