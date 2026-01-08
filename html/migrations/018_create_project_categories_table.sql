-- Migration: Create project_categories table
-- Description: Creates a table to categorize projects by type and skill level

CREATE TABLE IF NOT EXISTS project_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail_image VARCHAR(500),
    skill_level ENUM('beginner', 'intermediate', 'advanced', 'all') DEFAULT 'all',
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_display_order (display_order),
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add project_category_id column to projects table
ALTER TABLE projects 
ADD COLUMN project_category_id INT UNSIGNED NULL AFTER id,
ADD INDEX idx_project_category (project_category_id),
ADD CONSTRAINT fk_projects_category 
    FOREIGN KEY (project_category_id) 
    REFERENCES project_categories(id) 
    ON DELETE RESTRICT;

-- Insert initial 'General Projects' category
INSERT INTO project_categories (name, description, skill_level, display_order) 
VALUES ('General Projects', 'Default category for all general projects', 'all', 1);

-- Update all existing projects to belong to 'General Projects' category
UPDATE projects 
SET project_category_id = (SELECT id FROM project_categories WHERE name = 'General Projects' LIMIT 1)
WHERE project_category_id IS NULL;