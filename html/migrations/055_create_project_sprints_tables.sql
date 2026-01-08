-- Create project_sprints table for managing sprints
CREATE TABLE IF NOT EXISTS project_sprints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    goal TEXT COMMENT 'Sprint goal/objective',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('planning', 'active', 'completed', 'cancelled') DEFAULT 'planning',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_project_id (project_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sprint_work_items table to link work items to sprints
CREATE TABLE IF NOT EXISTS sprint_work_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sprint_id INT UNSIGNED NOT NULL,
    work_item_id INT UNSIGNED NOT NULL,
    added_by INT UNSIGNED NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sprint_work_item (sprint_id, work_item_id),
    FOREIGN KEY (sprint_id) REFERENCES project_sprints(id) ON DELETE CASCADE,
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id),
    INDEX idx_sprint_id (sprint_id),
    INDEX idx_work_item_id (work_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add sprint_id column to work_items table for direct reference (optional, for performance)
ALTER TABLE work_items 
ADD COLUMN sprint_id INT UNSIGNED DEFAULT NULL AFTER status,
ADD CONSTRAINT fk_work_items_sprint 
    FOREIGN KEY (sprint_id) REFERENCES project_sprints(id) ON DELETE SET NULL,
ADD INDEX idx_sprint_id (sprint_id);