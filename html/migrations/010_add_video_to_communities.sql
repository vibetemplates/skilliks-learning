-- Migration: Add video URL and embed code to communities table
-- Date: 2025-07-19
-- Description: Adds fields for community introduction videos (YouTube, Screencast, etc.)

-- Add video URL column
ALTER TABLE `communities` 
ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL AFTER `banner_url`,
ADD COLUMN `video_embed_code` TEXT DEFAULT NULL AFTER `video_url`;

-- Add indexes for performance
ALTER TABLE `communities`
ADD INDEX idx_video_url (`video_url`(255));