-- Add community_id to project_categories table
ALTER TABLE project_categories 
ADD COLUMN community_id INT UNSIGNED NOT NULL AFTER id,
ADD CONSTRAINT fk_project_categories_community 
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
ADD INDEX idx_community_id (community_id);

-- Update existing categories to belong to community 1
UPDATE project_categories SET community_id = 1 WHERE community_id = 0;