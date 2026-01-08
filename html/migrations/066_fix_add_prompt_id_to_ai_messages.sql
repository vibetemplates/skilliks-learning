-- Migration: Fix - Add prompt_id column to ai_messages table
-- This migration adds prompt_id to ai_messages table and assigns existing records to prompt_id 27

-- Step 1: Add prompt_id column without constraints first
ALTER TABLE ai_messages 
ADD COLUMN prompt_id INT UNSIGNED AFTER session_id;

-- Step 2: Update all existing records to prompt_id 27
UPDATE ai_messages 
SET prompt_id = 27 
WHERE prompt_id IS NULL;

-- Step 3: Add index for prompt_id
ALTER TABLE ai_messages 
ADD INDEX idx_prompt (prompt_id);

-- Step 4: Add foreign key constraint
ALTER TABLE ai_messages 
ADD CONSTRAINT fk_ai_messages_prompt 
FOREIGN KEY (prompt_id) REFERENCES project_dev_prompts(id) ON DELETE CASCADE;