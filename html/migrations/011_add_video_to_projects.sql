-- Migration: Add video support to projects
-- Date: 2025-07-19
-- Description: Adds video_url and video_embed_code columns to projects table for introduction videos

-- Add video columns to projects table
ALTER TABLE projects 
ADD COLUMN video_url VARCHAR(500) NULL COMMENT 'Video URL (YouTube, Vimeo, Screencast, etc.)',
ADD COLUMN video_embed_code TEXT NULL COMMENT 'Custom embed code for video',
ADD INDEX idx_video_url (video_url);

-- Update the updated_at timestamp for modified rows
UPDATE projects SET updated_at = CURRENT_TIMESTAMP WHERE 1=1;