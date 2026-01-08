-- Add sub_type column to surveys table if it doesn't exist
ALTER TABLE surveys 
ADD COLUMN IF NOT EXISTS sub_type VARCHAR(50) NULL 
COMMENT 'Sub-type for surveys: general, requirements, tech-stack, design-notes'
AFTER type;

-- Add index for sub_type
ALTER TABLE surveys ADD INDEX IF NOT EXISTS idx_sub_type (sub_type);
ALTER TABLE surveys ADD INDEX IF NOT EXISTS idx_type_subtype (type, sub_type);