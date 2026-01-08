-- Add visibility column to projects table
-- Date: 2025-08-19

-- Add visibility column to projects table
ALTER TABLE projects 
ADD COLUMN visibility ENUM('public', 'private') NOT NULL DEFAULT 'public' AFTER status;

-- Add index for better query performance when filtering by visibility
CREATE INDEX idx_projects_visibility ON projects(visibility);

-- Update existing projects to be public by default
UPDATE projects SET visibility = 'public' WHERE visibility IS NULL;