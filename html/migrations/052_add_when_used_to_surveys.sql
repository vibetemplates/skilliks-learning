-- Migration: Add when_used column to surveys table
-- Description: Adds when_used column to describe what type of project uses this survey
-- Date: 2025-08-19

-- Add when_used column to surveys table
ALTER TABLE surveys
ADD COLUMN when_used TEXT NULL AFTER sub_type;

-- Add index for better query performance on when_used field
ALTER TABLE surveys
ADD INDEX idx_when_used (when_used(100));

-- Update existing surveys with default when_used descriptions based on sub_type
UPDATE surveys
SET when_used = CASE
    WHEN sub_type = 'general' THEN 'Use for all standard projects requiring basic configuration and setup information'
    WHEN sub_type = 'requirements' THEN 'Use for projects that need detailed requirements gathering and specification'
    WHEN sub_type = 'tech-stack' THEN 'Use for projects requiring technology stack selection and architecture decisions'
    WHEN sub_type = 'design-notes' THEN 'Use for projects with complex design requirements or UI/UX focus'
    WHEN type = 'skills' THEN 'Use for assessing team member skills and competencies'
    ELSE 'General purpose survey for project configuration'
END
WHERE when_used IS NULL;