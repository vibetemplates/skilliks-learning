-- Add skilliks_api_key column to projects table
-- This column will store the API key for each project to use when sending prompts to servers

ALTER TABLE projects
ADD COLUMN skilliks_api_key VARCHAR(255) DEFAULT NULL
AFTER test_url;

-- Add index for faster lookups
CREATE INDEX idx_skilliks_api_key ON projects(skilliks_api_key);