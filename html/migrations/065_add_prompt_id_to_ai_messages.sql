-- Migration: Add prompt_id column to ai_messages table
-- This migration adds prompt_id to ai_messages table to link messages directly to prompts

-- Add prompt_id column to ai_messages table
ALTER TABLE ai_messages 
ADD COLUMN prompt_id INT UNSIGNED AFTER session_id;

-- Add index for prompt_id
ALTER TABLE ai_messages 
ADD INDEX idx_prompt (prompt_id);

-- Add foreign key constraint
ALTER TABLE ai_messages 
ADD CONSTRAINT fk_ai_messages_prompt 
FOREIGN KEY (prompt_id) REFERENCES project_dev_prompts(id) ON DELETE CASCADE;