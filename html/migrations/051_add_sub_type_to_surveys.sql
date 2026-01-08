-- Migration: Add sub_type column to surveys table
-- Description: Adds sub_type column to support different survey configurations
-- Date: 2025-08-19

-- Add sub_type column to surveys table
ALTER TABLE surveys
ADD COLUMN sub_type VARCHAR(50) NULL AFTER type;

-- Add index for better query performance
ALTER TABLE surveys
ADD INDEX idx_type_subtype (type, sub_type);

-- Update existing project surveys with default sub_types based on their names
UPDATE surveys
SET sub_type = CASE
    WHEN name LIKE '%General%' THEN 'general'
    WHEN name LIKE '%Requirements%' THEN 'requirements'
    WHEN name LIKE '%Tech%Stack%' OR name LIKE '%Technology%' THEN 'tech-stack'
    WHEN name LIKE '%Design%' OR name LIKE '%Architecture%' THEN 'design-notes'
    ELSE NULL
END
WHERE type = 'project';