-- Migration: Add project_votes table for voting functionality
-- This table tracks user votes on projects

CREATE TABLE IF NOT EXISTS project_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('up', 'down') NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_project_user_vote (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project_votes (project_id),
    INDEX idx_user_votes (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add vote_count column to projects table if it doesn't exist
ALTER TABLE projects ADD COLUMN IF NOT EXISTS vote_count INT DEFAULT 0 AFTER status;

-- Create feature_votes table for completeness
CREATE TABLE IF NOT EXISTS feature_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('up', 'down') NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_feature_user_vote (feature_id, user_id),
    FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_feature_votes (feature_id),
    INDEX idx_user_votes (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add vote_count column to features table if it doesn't exist
ALTER TABLE features ADD COLUMN IF NOT EXISTS vote_count INT DEFAULT 0 AFTER status;