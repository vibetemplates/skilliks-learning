-- Add project survey functionality
-- This migration adds support for project surveys that determine architecture, tech stack, and requirements

-- First, update the surveys table to include 'project' as a survey type
ALTER TABLE surveys MODIFY COLUMN type ENUM('skills', 'feedback', 'assessment', 'project');

-- Create project_surveys table to link surveys to projects and store AI-generated recommendations
CREATE TABLE IF NOT EXISTS project_surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    survey_id INT NOT NULL,
    architecture_recommendations TEXT,
    tech_stack_recommendations TEXT,
    claude_md_content TEXT,
    requirements_md_content TEXT,
    generated_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_project_survey (project_id, survey_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project_survey_attributes table to store extracted project attributes
CREATE TABLE IF NOT EXISTS project_survey_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_survey_id INT NOT NULL,
    attribute_type ENUM('complexity', 'team_size', 'timeline', 'budget', 'technology', 'deployment', 'security', 'scalability', 'integration', 'other'),
    attribute_name VARCHAR(100),
    attribute_value TEXT,
    confidence_score DECIMAL(3,2), -- 0.00 to 1.00
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_survey_id) REFERENCES project_surveys(id) ON DELETE CASCADE,
    KEY idx_project_survey_type (project_survey_id, attribute_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;