-- Migration: Add project_votes table
-- Date: 2025-07-25
-- Description: Adds project voting functionality

-- =====================================================
-- CREATE PROJECT_VOTES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS project_votes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    vote_type ENUM('up', 'down') DEFAULT 'up',
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_project_vote (user_id, project_id),
    INDEX idx_project_id (project_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add vote_count column to projects table for performance
ALTER TABLE projects 
ADD COLUMN vote_count INT UNSIGNED DEFAULT 0 AFTER member_count;

-- Update existing projects with their vote counts
UPDATE projects p
SET vote_count = (
    SELECT COUNT(*) 
    FROM project_votes pv 
    WHERE pv.project_id = p.id AND pv.vote_type = 'up'
) - (
    SELECT COUNT(*) 
    FROM project_votes pv 
    WHERE pv.project_id = p.id AND pv.vote_type = 'down'
);

-- Add vote_count column to features table for performance
ALTER TABLE features 
ADD COLUMN vote_count INT DEFAULT 0 AFTER status;

-- Update existing features with their vote counts
UPDATE features f
SET vote_count = (
    SELECT COUNT(*) 
    FROM feature_votes fv 
    WHERE fv.feature_id = f.id AND fv.vote_type = 'up'
) - (
    SELECT COUNT(*) 
    FROM feature_votes fv 
    WHERE fv.feature_id = f.id AND fv.vote_type = 'down'
);