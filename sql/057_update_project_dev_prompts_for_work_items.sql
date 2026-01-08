-- Migration: Update project_dev_prompts to link with work items and sprints
-- Purpose: Connect generated prompts to their source work items and sprints

ALTER TABLE project_dev_prompts
ADD COLUMN work_item_id BIGINT UNSIGNED DEFAULT NULL AFTER parent_prompt_id,
ADD COLUMN sprint_id INT UNSIGNED DEFAULT NULL AFTER work_item_id,
ADD COLUMN prompt_order INT NOT NULL DEFAULT 1 AFTER sprint_id,
ADD CONSTRAINT fk_dev_prompts_work_item 
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_dev_prompts_sprint 
    FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE SET NULL,
ADD INDEX idx_work_item_id (work_item_id),
ADD INDEX idx_sprint_id (sprint_id),
ADD INDEX idx_prompt_order (sprint_id, prompt_order);

-- Add comments
ALTER TABLE project_dev_prompts
MODIFY COLUMN work_item_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Source work item for this prompt',
MODIFY COLUMN sprint_id INT UNSIGNED DEFAULT NULL COMMENT 'Sprint this prompt belongs to',
MODIFY COLUMN prompt_order INT NOT NULL DEFAULT 1 COMMENT 'Order of prompt execution within sprint';