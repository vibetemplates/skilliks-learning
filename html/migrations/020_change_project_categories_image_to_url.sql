-- Migration: Change project_categories thumbnail_image to thumbnail_url
-- Purpose: Align with courses and programs tables that use URL-based image storage

-- Step 1: Add new thumbnail_url column
ALTER TABLE project_categories 
ADD COLUMN thumbnail_url VARCHAR(500) DEFAULT NULL AFTER description;

-- Step 2: Copy data from thumbnail_image to thumbnail_url
UPDATE project_categories 
SET thumbnail_url = thumbnail_image 
WHERE thumbnail_image IS NOT NULL;

-- Step 3: Drop the old thumbnail_image column
ALTER TABLE project_categories 
DROP COLUMN thumbnail_image;

-- Note: This migration converts from file path storage to URL storage
-- Admin will need to update any existing file paths to proper URLs