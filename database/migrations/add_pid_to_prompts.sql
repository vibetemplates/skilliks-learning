-- Add pid column to project_dev_prompts table
ALTER TABLE project_dev_prompts 
ADD COLUMN pid VARCHAR(50) NULL AFTER log_file_name,
ADD INDEX idx_pid (pid);

-- Update any existing 'executing' prompts to 'failed' since we don't have their PIDs
UPDATE project_dev_prompts 
SET status = 'failed', 
    error_message = 'Process lost - no PID recorded',
    updated_at = NOW()
WHERE status = 'executing';