-- Migration: Add like_count column to comments table
-- Date: 2025-01-29
-- Issue: The comment_likes API expects a like_count column in the comments table for performance optimization

-- Add like_count column to comments table
ALTER TABLE comments 
ADD COLUMN like_count INT UNSIGNED DEFAULT 0 AFTER edited;

-- Update existing comments with their current like count
UPDATE comments c
SET like_count = (
    SELECT COUNT(*) 
    FROM comment_likes cl 
    WHERE cl.comment_id = c.id
);

-- Add index for performance when sorting by popularity
CREATE INDEX idx_like_count ON comments(like_count DESC);