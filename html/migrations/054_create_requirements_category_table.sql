-- Create requirements_category table
CREATE TABLE IF NOT EXISTS requirements_category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    is_standard BOOLEAN DEFAULT FALSE COMMENT 'TRUE for standard categories available to all projects',
    project_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL for standard categories, project ID for project-specific categories',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_standard_name (name, is_standard, project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_is_standard (is_standard)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some standard categories
INSERT INTO requirements_category (name, is_standard) VALUES 
    ('User Authentication', TRUE),
    ('Roles and Privileges', TRUE),
    ('Design Elements', TRUE),
    ('Data Management', TRUE),
    ('Security', TRUE),
    ('Performance', TRUE),
    ('Integration', TRUE),
    ('Reporting', TRUE);

-- Add category_id column to project_requirements table
ALTER TABLE project_requirements 
ADD COLUMN category_id INT DEFAULT NULL AFTER project_id,
ADD CONSTRAINT fk_requirements_category 
    FOREIGN KEY (category_id) REFERENCES requirements_category(id) ON DELETE SET NULL,
ADD INDEX idx_category_id (category_id);