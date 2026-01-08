-- Add created_by column to surveys table
ALTER TABLE surveys 
ADD COLUMN created_by INT UNSIGNED NULL AFTER requires_completion,
ADD CONSTRAINT fk_surveys_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add index for better performance when querying by creator
ALTER TABLE surveys ADD INDEX idx_created_by (created_by);