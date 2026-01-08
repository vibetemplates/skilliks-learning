-- Migration: Add pid column to project_dev_prompts table
-- Purpose: Store the process ID returned from the development API

ALTER TABLE project_dev_prompts 
ADD COLUMN pid INT UNSIGNED DEFAULT NULL AFTER log_file_name,
ADD INDEX idx_pid (pid);

-- Add comment to the column
ALTER TABLE project_dev_prompts 
MODIFY COLUMN pid INT UNSIGNED DEFAULT NULL COMMENT 'Process ID returned from development system API';