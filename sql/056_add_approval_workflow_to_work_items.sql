-- Migration: Add approval workflow to work_items
-- Purpose: Enable approval process for work items before they enter the backlog

-- Add approval workflow fields to work_items
ALTER TABLE work_items 
ADD COLUMN approval_status ENUM('draft', 'approved', 'rejected') NOT NULL DEFAULT 'draft' AFTER status,
ADD COLUMN approved_by INT UNSIGNED DEFAULT NULL AFTER reporter_id,
ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER created_at,
ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER approved_at,
ADD COLUMN backlog_priority INT NOT NULL DEFAULT 0 AFTER position,
ADD INDEX idx_approval_status (approval_status),
ADD INDEX idx_backlog_priority (backlog_priority),
ADD CONSTRAINT fk_work_items_approved_by FOREIGN KEY (approved_by) 
    REFERENCES users(id) ON DELETE SET NULL;

-- Create view for product backlog (approved items not in sprint)
CREATE OR REPLACE VIEW product_backlog AS
SELECT wi.*, 
       p.name as project_name,
       p.slug as project_slug,
       reporter.first_name as reporter_first_name,
       reporter.last_name as reporter_last_name,
       assignee.first_name as assignee_first_name,
       assignee.last_name as assignee_last_name,
       approver.first_name as approver_first_name,
       approver.last_name as approver_last_name
FROM work_items wi
INNER JOIN projects p ON wi.project_id = p.id
LEFT JOIN users reporter ON wi.reporter_id = reporter.id
LEFT JOIN users assignee ON wi.assignee_id = assignee.id
LEFT JOIN users approver ON wi.approved_by = approver.id
WHERE wi.approval_status = 'approved' 
AND wi.sprint_id IS NULL
AND wi.status != 'done'
ORDER BY wi.backlog_priority DESC, wi.created_at ASC;

-- Create view for sprint backlog (approved items in sprint)
CREATE OR REPLACE VIEW sprint_backlog AS
SELECT wi.*, 
       p.name as project_name,
       p.slug as project_slug,
       p.dev_system_url,
       s.id as sprint_id,
       s.name as sprint_name,
       s.status as sprint_status,
       s.start_date as sprint_start_date,
       s.end_date as sprint_end_date,
       reporter.first_name as reporter_first_name,
       reporter.last_name as reporter_last_name,
       assignee.first_name as assignee_first_name,
       assignee.last_name as assignee_last_name,
       swi.added_at as added_to_sprint_at,
       swi.added_by as added_to_sprint_by
FROM work_items wi
INNER JOIN projects p ON wi.project_id = p.id
INNER JOIN sprint_work_items swi ON wi.id = swi.work_item_id
INNER JOIN project_sprints s ON swi.sprint_id = s.id
LEFT JOIN users reporter ON wi.reporter_id = reporter.id
LEFT JOIN users assignee ON wi.assignee_id = assignee.id
WHERE wi.approval_status = 'approved'
ORDER BY s.id, wi.position ASC, wi.priority DESC;

-- Update existing work items to approved status (for backwards compatibility)
UPDATE work_items 
SET approval_status = 'approved', 
    approved_by = reporter_id,
    approved_at = created_at
WHERE approval_status = 'draft';

-- Add comment to approval_status column
ALTER TABLE work_items 
MODIFY COLUMN approval_status ENUM('draft', 'approved', 'rejected') NOT NULL DEFAULT 'draft' 
COMMENT 'Approval status for backlog entry';