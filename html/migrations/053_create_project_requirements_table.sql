-- Migration: Create project_requirements table
-- Description: Creates table for managing project requirements with MoSCoW prioritization
-- Date: 2025-08-19

-- Create project_requirements table
CREATE TABLE IF NOT EXISTS project_requirements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority ENUM('must_have', 'should_have', 'could_have', 'nice_to_have') DEFAULT 'should_have',
    status ENUM('pending', 'in_progress', 'completed', 'deferred') DEFAULT 'pending',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    CONSTRAINT fk_project_requirements_project 
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_requirements_user 
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    -- Indexes for performance
    INDEX idx_project_id (project_id),
    INDEX idx_priority (priority),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comments for documentation
ALTER TABLE project_requirements
    COMMENT = 'Stores project requirements using MoSCoW prioritization method';