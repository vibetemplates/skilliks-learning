-- Migration to remove foreign key constraint on session_id column in project_dev_prompts table
-- This allows storing external session IDs from tools like Skilliks without requiring ai_sessions records

-- Drop the foreign key constraint
ALTER TABLE project_dev_prompts DROP FOREIGN KEY fk_project_dev_prompts_session;

-- Keep the column but allow it to store any session ID value
-- The column remains VARCHAR(255) and nullable as before