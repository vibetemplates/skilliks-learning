-- Add test notes, developer notes, and rating fields to project_dev_prompts table
ALTER TABLE project_dev_prompts
ADD COLUMN test_notes TEXT DEFAULT NULL AFTER response_text,
ADD COLUMN developer_notes TEXT DEFAULT NULL AFTER test_notes,
ADD COLUMN rating INT UNSIGNED DEFAULT NULL AFTER developer_notes,
ADD COLUMN test_notes_updated_at TIMESTAMP NULL DEFAULT NULL AFTER rating,
ADD COLUMN developer_notes_updated_at TIMESTAMP NULL DEFAULT NULL AFTER test_notes_updated_at,
ADD COLUMN rating_updated_at TIMESTAMP NULL DEFAULT NULL AFTER developer_notes_updated_at,
ADD COLUMN test_notes_updated_by INT UNSIGNED DEFAULT NULL AFTER rating_updated_at,
ADD COLUMN developer_notes_updated_by INT UNSIGNED DEFAULT NULL AFTER test_notes_updated_by,
ADD COLUMN rating_updated_by INT UNSIGNED DEFAULT NULL AFTER developer_notes_updated_by;

-- Add foreign key constraints for the updated_by fields
ALTER TABLE project_dev_prompts
ADD CONSTRAINT fk_test_notes_updated_by FOREIGN KEY (test_notes_updated_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_developer_notes_updated_by FOREIGN KEY (developer_notes_updated_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_rating_updated_by FOREIGN KEY (rating_updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Add check constraint for rating (1-5 stars)
ALTER TABLE project_dev_prompts
ADD CONSTRAINT chk_rating_range CHECK (rating IS NULL OR (rating >= 1 AND rating <= 5));

-- Add indexes for better query performance
ALTER TABLE project_dev_prompts
ADD INDEX idx_rating (rating),
ADD INDEX idx_test_notes_updated (test_notes_updated_at),
ADD INDEX idx_developer_notes_updated (developer_notes_updated_at);