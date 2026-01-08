-- Migration: Fix comment_likes table foreign key constraint
-- Date: 2025-01-29
-- Issue: comment_likes table was incorrectly referencing course_comments instead of comments table

-- Drop and recreate the comment_likes table with correct structure
DROP TABLE IF EXISTS comment_likes;

CREATE TABLE comment_likes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    comment_id INT(10) UNSIGNED NOT NULL,
    user_id INT(10) UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_comment (user_id, comment_id),
    KEY idx_comment_id (comment_id),
    KEY idx_user_id (user_id),
    CONSTRAINT comment_likes_comment_fk FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT comment_likes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: This migration drops and recreates the table, so any existing likes will be lost
-- In production, you would want to backup the data first