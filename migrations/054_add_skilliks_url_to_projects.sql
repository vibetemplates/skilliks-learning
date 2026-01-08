-- Migration: Add skilliks_url column to projects table
-- Purpose: Store the URL of the development environment where the application sends prompts to update software

ALTER TABLE projects 
ADD COLUMN skilliks_url VARCHAR(500) NULL AFTER git_repository_url;

-- Add index for faster lookups if needed
CREATE INDEX idx_skilliks_url ON projects(skilliks_url);