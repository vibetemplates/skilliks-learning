-- Migration: Add Skilliks development system fields to projects table
-- Description: Adds skilliks_system_url and skilliks_agent_api columns to support Skilliks Coder integration

ALTER TABLE projects 
ADD COLUMN skilliks_system_url VARCHAR(255) DEFAULT NULL COMMENT 'URL for Skilliks Coder development system',
ADD COLUMN skilliks_agent_api VARCHAR(255) DEFAULT NULL COMMENT 'API key for Skilliks Coder agent';

-- Add index for quick lookups
ALTER TABLE projects ADD INDEX idx_skilliks_system (skilliks_system_url);