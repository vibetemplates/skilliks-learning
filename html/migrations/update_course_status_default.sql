-- Migration: Update course status default value to 'published'
-- This ensures new courses are published by default and visible to users

ALTER TABLE courses 
MODIFY COLUMN status ENUM('draft', 'published', 'archived') DEFAULT 'published';