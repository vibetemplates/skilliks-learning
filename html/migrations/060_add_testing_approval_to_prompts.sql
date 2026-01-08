-- Migration: Add testing and approval workflow to prompts
-- Date: 2025-09-12
-- Description: Adds testing/approval columns and project_prompt_responses table for execution history

-- Add testing and approval columns to project_dev_prompts
ALTER TABLE project_dev_prompts 
ADD COLUMN is_tested BOOLEAN DEFAULT FALSE AFTER response_text,
ADD COLUMN is_approved BOOLEAN DEFAULT FALSE AFTER is_tested,
ADD COLUMN tested_by INT NULL AFTER is_approved,
ADD COLUMN tested_at TIMESTAMP NULL AFTER tested_by,
ADD COLUMN approved_by INT NULL AFTER tested_at,
ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by,
ADD INDEX idx_is_tested (is_tested),
ADD INDEX idx_is_approved (is_approved),
ADD CONSTRAINT fk_tested_by FOREIGN KEY (tested_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- Update status enum to include 'test-ready'
ALTER TABLE project_dev_prompts 
MODIFY COLUMN status ENUM('pending', 'executing', 'test-ready', 'completed', 'failed', 'cancelled') DEFAULT 'pending';

-- Create table to track multiple executions of the same prompt
CREATE TABLE IF NOT EXISTS project_prompt_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prompt_id INT NOT NULL,
    execution_number INT NOT NULL DEFAULT 1,
    status ENUM('executing', 'completed', 'failed') NOT NULL,
    response_text LONGTEXT,
    error_message TEXT,
    pid INT,
    log_file_name VARCHAR(255),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prompt_id (prompt_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    CONSTRAINT fk_prompt_response FOREIGN KEY (prompt_id) REFERENCES project_dev_prompts(id) ON DELETE CASCADE
);