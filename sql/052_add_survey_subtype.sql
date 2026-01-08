-- =====================================================
-- Add sub_type column to surveys table for project survey positioning
-- =====================================================

-- Add sub_type column after type
ALTER TABLE surveys 
ADD COLUMN sub_type VARCHAR(50) NULL AFTER type;

-- Add index for sub_type
ALTER TABLE surveys 
ADD INDEX idx_sub_type (sub_type);

-- Add composite index for type and sub_type
ALTER TABLE surveys 
ADD INDEX idx_type_subtype (type, sub_type);

-- Update existing project surveys to have 'general' sub_type
UPDATE surveys 
SET sub_type = 'general' 
WHERE type = 'project' AND sub_type IS NULL;

-- Add comments for documentation
ALTER TABLE surveys 
MODIFY COLUMN sub_type VARCHAR(50) NULL COMMENT 'Sub-type for surveys: general, requirements, tech-stack, design-notes';

-- Create a constraint comment (MySQL doesn't support CHECK constraints well)
-- Valid sub_types for project surveys: general, requirements, tech-stack, design-notes