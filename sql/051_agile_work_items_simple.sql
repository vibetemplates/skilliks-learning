-- Simple version of work items migration
-- First create basic tables then migrate data

-- Drop tables if they exist (for testing)
DROP TABLE IF EXISTS work_item_attachments;
DROP TABLE IF EXISTS work_item_comments;
DROP TABLE IF EXISTS sprint_work_items;
DROP TABLE IF EXISTS work_item_assignments;
DROP TABLE IF EXISTS acceptance_criteria;
DROP TABLE IF EXISTS work_item_relations;
DROP TABLE IF EXISTS work_items;

-- Create work_items table
CREATE TABLE work_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(30) NOT NULL DEFAULT 'todo',
    priority VARCHAR(15) DEFAULT 'medium',
    story_points INT,
    estimate_hours DECIMAL(5,1),
    actual_hours DECIMAL(5,1),
    assignee_id INT UNSIGNED,
    reporter_id INT UNSIGNED NOT NULL,
    sprint_id INT UNSIGNED,
    project_id INT UNSIGNED NOT NULL,
    community_id INT UNSIGNED NOT NULL,
    labels TEXT,
    position INT DEFAULT 0,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_project (project_id),
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create work_item_relations table
CREATE TABLE work_item_relations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    child_id BIGINT UNSIGNED NOT NULL,
    relation VARCHAR(20) NOT NULL DEFAULT 'contains',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_parent_child (parent_id, child_id),
    INDEX idx_parent (parent_id),
    INDEX idx_child (child_id),
    FOREIGN KEY (parent_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (child_id) REFERENCES work_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create acceptance_criteria table
CREATE TABLE acceptance_criteria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_item_id BIGINT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    must_pass BOOLEAN DEFAULT TRUE,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at DATETIME,
    completed_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_item (work_item_id),
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add goal column to sprints if missing
ALTER TABLE sprints ADD COLUMN IF NOT EXISTS goal TEXT AFTER name;

-- Now migrate data from tasks
INSERT INTO work_items (
    id, type, title, description, status, priority,
    story_points, estimate_hours, actual_hours,
    assignee_id, reporter_id, sprint_id, project_id,
    community_id, labels, position, due_date,
    created_at, updated_at, completed_at
)
SELECT 
    t.id,
    CASE 
        WHEN t.type = 'feature' THEN 'story'
        WHEN t.type = 'improvement' THEN 'story'
        ELSE t.type
    END as type,
    t.title,
    t.description,
    t.status,
    CASE 
        WHEN t.priority = 'critical' THEN 'highest'
        ELSE t.priority
    END as priority,
    NULL as story_points,
    t.estimated_hours,
    t.actual_hours,
    t.assignee_id,
    t.reporter_id,
    (SELECT sprint_id FROM sprint_tasks WHERE task_id = t.id LIMIT 1) as sprint_id,
    t.project_id,
    t.community_id,
    t.labels,
    t.position,
    t.due_date,
    t.created_at,
    t.updated_at,
    t.completed_at
FROM tasks t;

-- Migrate parent-child relationships
INSERT INTO work_item_relations (parent_id, child_id, relation)
SELECT parent_task_id, id, 'contains'
FROM tasks
WHERE parent_task_id IS NOT NULL;

-- Update auto-increment
SELECT @max_id := MAX(id) FROM work_items;
SET @sql = CONCAT('ALTER TABLE work_items AUTO_INCREMENT = ', COALESCE(@max_id + 1, 1));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;