-- Add project_manager_id to projects table
ALTER TABLE projects 
ADD COLUMN project_manager_id INT UNSIGNED DEFAULT NULL AFTER created_by,
ADD CONSTRAINT fk_project_manager 
    FOREIGN KEY (project_manager_id) 
    REFERENCES users(id) 
    ON DELETE SET NULL;

-- Add index for project manager lookups
ALTER TABLE projects 
ADD INDEX idx_project_manager (project_manager_id);