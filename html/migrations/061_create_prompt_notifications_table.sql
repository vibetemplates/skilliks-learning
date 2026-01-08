-- Migration: Create prompt notifications table for tracking browser notifications
-- This table stores pending notifications for completed/failed prompts

CREATE TABLE IF NOT EXISTS prompt_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prompt_id INT NOT NULL,
    status ENUM('completed', 'failed') NOT NULL,
    notified BOOLEAN DEFAULT FALSE,
    notified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    
    UNIQUE KEY unique_prompt (prompt_id),
    KEY idx_notified (notified),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add notification preferences to users table if columns don't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS notification_enabled BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS notification_sound BOOLEAN DEFAULT TRUE;