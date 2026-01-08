-- Migration: Add dev_system_url column to projects table
-- Purpose: Store development environment API URL for each project

ALTER TABLE projects 
ADD COLUMN dev_system_url VARCHAR(500) 
AFTER visibility;

-- Add index for better query performance when filtering by dev_system_url
CREATE INDEX idx_projects_dev_system_url ON projects(dev_system_url);

-- Add comment to the column
ALTER TABLE projects 
MODIFY COLUMN dev_system_url VARCHAR(500) 
COMMENT 'Development environment API URL for the project';