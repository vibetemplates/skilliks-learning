-- Add completion status to project members table
ALTER TABLE project_members 
ADD COLUMN completion_status ENUM('working', 'completed') DEFAULT 'working' AFTER status,
ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER completion_status,
ADD INDEX idx_completion_status (completion_status);

-- Update existing members to 'working' status
UPDATE project_members SET completion_status = 'working' WHERE completion_status IS NULL;