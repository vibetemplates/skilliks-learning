-- Create projects_prompts table for generating prompts from sprint work items
-- Date: 2025-08-19

-- Create projects_prompts table
CREATE TABLE IF NOT EXISTS projects_prompts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sprint_id INT UNSIGNED NOT NULL,
    work_item_id BIGINT UNSIGNED NOT NULL,
    prompt_order INT NOT NULL DEFAULT 1,
    prompt_type VARCHAR(50) NOT NULL DEFAULT 'implementation',
    prompt_title VARCHAR(255) NOT NULL,
    prompt_content TEXT NOT NULL,
    context TEXT,
    expected_outcome TEXT,
    dependencies JSON DEFAULT '[]',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at DATETIME,
    executed_by INT UNSIGNED,
    execution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_sprint (sprint_id),
    INDEX idx_work_item (work_item_id),
    INDEX idx_status (status),
    INDEX idx_prompt_order (sprint_id, prompt_order),
    
    -- Foreign Keys
    FOREIGN KEY (sprint_id) REFERENCES project_sprints(id) ON DELETE CASCADE,
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for finding all prompts for a sprint in order
CREATE INDEX idx_sprint_prompts_ordered ON projects_prompts (sprint_id, prompt_order, id);

-- Add comments to document the table
ALTER TABLE projects_prompts COMMENT = 'Stores generated prompts for implementing work items in a sprint';