-- Migration: Add session_id column to project_dev_prompts table
-- This migration adds session_id to link prompts to AI conversation sessions

-- Step 1: Add session_id column
ALTER TABLE project_dev_prompts 
ADD COLUMN session_id VARCHAR(36) AFTER response_text;

-- Step 2: Update all existing records to use session_id c16c12a2-95ca-49e2-9d65-86fa1a3bf0a3
UPDATE project_dev_prompts 
SET session_id = 'c16c12a2-95ca-49e2-9d65-86fa1a3bf0a3' 
WHERE session_id IS NULL;

-- Step 3: Add index for session_id
ALTER TABLE project_dev_prompts 
ADD INDEX idx_session (session_id);

-- Step 4: Add foreign key constraint
ALTER TABLE project_dev_prompts 
ADD CONSTRAINT fk_project_dev_prompts_session 
FOREIGN KEY (session_id) REFERENCES ai_sessions(id) ON DELETE SET NULL;