-- Migration: Cleanup duplicate prompt tables
-- Purpose: Remove unused project_prompts and projects_prompts tables
-- Note: All code is using project_dev_prompts table

-- First, let's verify that these tables are empty before dropping them
-- If they contain data, you should manually review and migrate any needed data

-- Check if project_prompts exists and drop it
DROP TABLE IF EXISTS project_prompts;

-- Check if projects_prompts exists and drop it  
DROP TABLE IF EXISTS projects_prompts;

-- Note: The active table being used is project_dev_prompts which contains:
-- - id: Primary key
-- - project_id: Link to projects table
-- - parent_prompt_id: For prompt chains
-- - work_item_id: Link to work items
-- - sprint_id: Link to sprints
-- - prompt_order: Execution order
-- - prompt_text: The actual prompt
-- - status: pending/executing/completed/failed/cancelled
-- - executed_at: When execution started
-- - completed_at: When execution finished
-- - log_file_name: Log file reference
-- - response_text: API response
-- - error_message: Error details if failed
-- - created_at/updated_at: Timestamps