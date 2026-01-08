-- Migration: Create project_dev_prompts table
-- Purpose: Store prompts sent to development tools for project feature implementation

CREATE TABLE project_dev_prompts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    parent_prompt_id INT UNSIGNED DEFAULT NULL,
    prompt_text TEXT NOT NULL COMMENT 'The prompt text sent to the development tool',
    status ENUM('pending', 'executing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Current status of the prompt execution',
    executed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Time when prompt execution started',
    completed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Time when prompt execution completed',
    log_file_name VARCHAR(255) DEFAULT NULL COMMENT 'Name of temporary log file on development server',
    response_text LONGTEXT DEFAULT NULL COMMENT 'Response received from the coding tool',
    error_message TEXT DEFAULT NULL COMMENT 'Error message if prompt failed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    CONSTRAINT fk_project_dev_prompts_project FOREIGN KEY (project_id) 
        REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_dev_prompts_parent FOREIGN KEY (parent_prompt_id) 
        REFERENCES project_dev_prompts(id) ON DELETE SET NULL,
    
    -- Indexes for better performance
    INDEX idx_project_dev_prompts_project_id (project_id),
    INDEX idx_project_dev_prompts_parent_id (parent_prompt_id),
    INDEX idx_project_dev_prompts_status (status),
    INDEX idx_project_dev_prompts_executed_at (executed_at),
    INDEX idx_project_dev_prompts_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to the table
ALTER TABLE project_dev_prompts COMMENT 'Stores prompts sent to development tools for implementing project features';