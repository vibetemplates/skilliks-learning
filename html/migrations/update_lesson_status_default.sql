-- Migration: Update lesson status default value to 'published'
-- This ensures new lessons are published by default and visible to students

ALTER TABLE lessons 
MODIFY COLUMN status ENUM('draft', 'published', 'archived') DEFAULT 'published';