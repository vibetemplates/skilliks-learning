-- Add test_url column to projects table for running/testing the application
ALTER TABLE projects
ADD COLUMN test_url VARCHAR(500) DEFAULT NULL AFTER dev_system_url;

-- Add index for better query performance when filtering by test_url existence
ALTER TABLE projects
ADD INDEX idx_test_url (test_url);