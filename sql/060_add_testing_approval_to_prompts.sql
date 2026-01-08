-- Migration: Add testing and approval workflow to project_dev_prompts
-- Date: 2025-09-12
-- Description: Adds is_tested, is_approved columns and test-ready status, creates responses tracking table

-- Add is_tested and is_approved columns to project_dev_prompts
ALTER TABLE project_dev_prompts 
ADD COLUMN is_tested BOOLEAN DEFAULT FALSE AFTER status,
ADD COLUMN is_approved BOOLEAN DEFAULT FALSE AFTER is_tested,
ADD COLUMN tested_by INT(10) UNSIGNED NULL AFTER is_approved,
ADD COLUMN tested_at TIMESTAMP NULL AFTER tested_by,
ADD COLUMN approved_by INT(10) UNSIGNED NULL AFTER tested_at,
ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by;

-- Add foreign key constraints
ALTER TABLE project_dev_prompts
ADD CONSTRAINT fk_prompt_tested_by FOREIGN KEY (tested_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_prompt_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- Update status enum to include 'test-ready'
ALTER TABLE project_dev_prompts 
MODIFY COLUMN status ENUM('pending', 'executing', 'test-ready', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending';

-- Create project_prompt_responses table to track each execution
CREATE TABLE IF NOT EXISTS project_prompt_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prompt_id INT(10) UNSIGNED NOT NULL,
    execution_number INT NOT NULL DEFAULT 1,
    pid VARCHAR(50) NULL,
    log_file_name VARCHAR(255) NULL,
    response_text LONGTEXT NULL,
    error_message TEXT NULL,
    status ENUM('executing', 'success', 'failed') NOT NULL DEFAULT 'executing',
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (prompt_id) REFERENCES project_dev_prompts(id) ON DELETE CASCADE,
    INDEX idx_prompt_execution (prompt_id, execution_number),
    INDEX idx_response_status (status),
    INDEX idx_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for performance
ALTER TABLE project_dev_prompts
ADD INDEX idx_is_tested (is_tested),
ADD INDEX idx_is_approved (is_approved),
ADD INDEX idx_test_ready_status (status, is_tested, is_approved);

-- Add comment to table
ALTER TABLE project_prompt_responses 
COMMENT = 'Tracks individual executions of prompts for history and debugging';