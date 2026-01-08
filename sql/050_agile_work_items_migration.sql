-- =====================================================
-- Agile/Scrum Work Items Migration
-- This migration converts the existing tasks system to a more flexible work item model
-- supporting epics, stories, tasks, bugs, and spikes with proper agile hierarchy
-- =====================================================

-- First, create new tables without dropping existing ones

-- Core work item table
CREATE TABLE IF NOT EXISTS work_items (
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
    labels JSON DEFAULT '[]',
    position INT DEFAULT 0,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME,
    
    -- Indexes
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assignee (assignee_id),
    INDEX idx_reporter (reporter_id),
    INDEX idx_sprint (sprint_id),
    INDEX idx_project (project_id),
    INDEX idx_community (community_id),
    INDEX idx_project_status (project_id, status),
    INDEX idx_sprint_status (sprint_id, status),
    
    -- Foreign Keys
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent/child relationships (epic→story, story→task, epic→bug, etc.)
CREATE TABLE IF NOT EXISTS work_item_relations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL,
    child_id BIGINT UNSIGNED NOT NULL,
    relation VARCHAR(20) NOT NULL DEFAULT 'contains',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY unique_parent_child (parent_id, child_id),
    INDEX idx_parent (parent_id),
    INDEX idx_child (child_id),
    INDEX idx_relation (relation),
    
    -- Foreign Keys
    FOREIGN KEY (parent_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (child_id) REFERENCES work_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acceptance criteria for stories (and possibly bugs)
CREATE TABLE IF NOT EXISTS acceptance_criteria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_item_id BIGINT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    must_pass BOOLEAN DEFAULT TRUE,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at DATETIME,
    completed_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_work_item (work_item_id),
    INDEX idx_must_pass (must_pass),
    INDEX idx_completed (is_completed),
    
    -- Foreign Keys
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update sprints table to add goal if it doesn't exist
ALTER TABLE sprints 
ADD COLUMN IF NOT EXISTS goal TEXT AFTER name;

-- Work item assignments (migrated from task_assignments)
CREATE TABLE IF NOT EXISTS work_item_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_item_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unassigned_at DATETIME,
    git_branch VARCHAR(255),
    
    -- Indexes
    UNIQUE KEY unique_active_assignment (work_item_id, user_id, unassigned_at),
    INDEX idx_work_item (work_item_id),
    INDEX idx_user (user_id),
    INDEX idx_assigned_by (assigned_by),
    
    -- Foreign Keys
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sprint work items (replacing sprint_tasks)
CREATE TABLE IF NOT EXISTS sprint_work_items (
    sprint_id INT UNSIGNED NOT NULL,
    work_item_id BIGINT UNSIGNED NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT UNSIGNED NOT NULL,
    
    -- Indexes
    PRIMARY KEY (sprint_id, work_item_id),
    INDEX idx_work_item (work_item_id),
    
    -- Foreign Keys
    FOREIGN KEY (sprint_id) REFERENCES sprints(id) ON DELETE CASCADE,
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work item comments (extending existing comments system)
CREATE TABLE IF NOT EXISTS work_item_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_item_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_work_item (work_item_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    
    -- Foreign Keys
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work item attachments (extending existing file system)
CREATE TABLE IF NOT EXISTS work_item_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_item_id BIGINT UNSIGNED NOT NULL,
    file_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_work_item (work_item_id),
    INDEX idx_file (file_id),
    
    -- Foreign Keys
    FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES file_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Data Migration from tasks to work_items
-- =====================================================

-- Insert existing tasks into work_items
INSERT INTO work_items (
    id,
    type,
    title,
    description,
    status,
    priority,
    story_points,
    estimate_hours,
    actual_hours,
    assignee_id,
    reporter_id,
    sprint_id,
    project_id,
    community_id,
    labels,
    position,
    due_date,
    created_at,
    updated_at,
    completed_at
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
    NULL as story_points, -- Will need to be populated later
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
SELECT 
    parent_task_id,
    id,
    'contains'
FROM tasks
WHERE parent_task_id IS NOT NULL;

-- Migrate task assignments
INSERT INTO work_item_assignments (
    work_item_id,
    user_id,
    assigned_by,
    assigned_at,
    unassigned_at,
    git_branch
)
SELECT 
    task_id,
    user_id,
    assigned_by,
    assigned_at,
    unassigned_at,
    git_branch
FROM task_assignments;

-- Migrate sprint associations
INSERT INTO sprint_work_items (sprint_id, work_item_id, added_by)
SELECT 
    st.sprint_id,
    st.task_id,
    COALESCE(t.reporter_id, 1) as added_by
FROM sprint_tasks st
JOIN tasks t ON st.task_id = t.id;

-- =====================================================
-- Update sequences to continue from highest ID
-- =====================================================
SELECT @max_id := MAX(id) FROM work_items;
SET @sql = CONCAT('ALTER TABLE work_items AUTO_INCREMENT = ', COALESCE(@max_id + 1, 1));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Create views for backward compatibility (temporary)
-- =====================================================
CREATE OR REPLACE VIEW tasks_view AS
SELECT 
    wi.id,
    wi.project_id,
    NULL as feature_id,
    (SELECT parent_id FROM work_item_relations WHERE child_id = wi.id LIMIT 1) as parent_task_id,
    wi.title,
    wi.description,
    CASE 
        WHEN wi.type = 'story' THEN 'feature'
        ELSE wi.type
    END as type,
    CASE 
        WHEN wi.priority = 'highest' THEN 'critical'
        ELSE wi.priority
    END as priority,
    wi.status,
    wi.assignee_id,
    wi.reporter_id,
    wi.due_date,
    wi.estimate_hours as estimated_hours,
    wi.actual_hours,
    wi.labels,
    wi.position,
    wi.created_at,
    wi.updated_at,
    wi.completed_at,
    wi.community_id
FROM work_items wi;

-- =====================================================
-- Add helpful indexes for common queries
-- =====================================================
CREATE INDEX idx_epic_stories ON work_item_relations(parent_id) 
WHERE parent_id IN (SELECT id FROM work_items WHERE type = 'epic');

CREATE INDEX idx_story_tasks ON work_item_relations(parent_id) 
WHERE parent_id IN (SELECT id FROM work_items WHERE type = 'story');

-- =====================================================
-- Create stored procedures for common operations
-- =====================================================
DELIMITER //

CREATE PROCEDURE GetWorkItemHierarchy(IN root_id BIGINT)
BEGIN
    WITH RECURSIVE work_item_tree AS (
        SELECT wi.*, 0 as level, CAST(wi.id AS CHAR(200)) as path
        FROM work_items wi
        WHERE wi.id = root_id
        
        UNION ALL
        
        SELECT wi.*, wit.level + 1, CONCAT(wit.path, '/', wi.id)
        FROM work_items wi
        JOIN work_item_relations wir ON wi.id = wir.child_id
        JOIN work_item_tree wit ON wir.parent_id = wit.id
    )
    SELECT * FROM work_item_tree ORDER BY path;
END//

CREATE PROCEDURE GetSprintWorkItems(IN sprint_id_param INT)
BEGIN
    SELECT 
        wi.*,
        GROUP_CONCAT(DISTINCT ac.text SEPARATOR '||') as acceptance_criteria,
        COUNT(DISTINCT wir_child.child_id) as child_count,
        COUNT(DISTINCT wia.user_id) as assignee_count
    FROM work_items wi
    LEFT JOIN sprint_work_items swi ON wi.id = swi.work_item_id
    LEFT JOIN acceptance_criteria ac ON wi.id = ac.work_item_id
    LEFT JOIN work_item_relations wir_child ON wi.id = wir_child.parent_id
    LEFT JOIN work_item_assignments wia ON wi.id = wia.work_item_id AND wia.unassigned_at IS NULL
    WHERE swi.sprint_id = sprint_id_param OR wi.sprint_id = sprint_id_param
    GROUP BY wi.id;
END//

DELIMITER ;

-- =====================================================
-- Notes for next steps:
-- 1. Update all foreign key references in other tables (time_entries, git_branches, etc.)
-- 2. Update PHP classes and files to use work_items instead of tasks
-- 3. Create new UI components for epic/story/task hierarchy
-- 4. Implement story point estimation UI
-- 5. Add acceptance criteria management
-- 6. After successful migration and testing, drop old tasks tables
-- =====================================================