-- Migration to change pid column in project_prompt_responses to support UUIDs
-- This allows storing both integer PIDs (for Claude) and UUID session IDs (for Skilliks)

-- First, create a new column to store the string values
ALTER TABLE project_prompt_responses 
ADD COLUMN pid_string VARCHAR(50) DEFAULT NULL AFTER pid;

-- Copy existing integer PIDs to the new column as strings
UPDATE project_prompt_responses 
SET pid_string = CAST(pid AS CHAR) 
WHERE pid IS NOT NULL;

-- Drop the old integer column
ALTER TABLE project_prompt_responses 
DROP COLUMN pid;

-- Rename the new column to pid
ALTER TABLE project_prompt_responses 
CHANGE COLUMN pid_string pid VARCHAR(50) DEFAULT NULL;

-- The column will now support:
-- - Integer PIDs from Claude (e.g., "12345")
-- - UUID session IDs from Skilliks (e.g., "fbafeb3e-3cc5-4a04-a729-d770cd07752b")