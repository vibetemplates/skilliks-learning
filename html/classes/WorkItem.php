<?php
/**
 * WorkItem Class
 * 
 * Handles agile work item management operations (epics, stories, tasks, bugs, spikes)
 */

class WorkItem {
    private $db;
    public $lastError = null;
    
    // Work item types
    const TYPE_EPIC = 'epic';
    const TYPE_STORY = 'story';
    const TYPE_TASK = 'task';
    const TYPE_BUG = 'bug';
    const TYPE_SPIKE = 'spike';
    
    // Status values
    const STATUS_TODO = 'todo';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_DONE = 'done';
    
    // Priority values
    const PRIORITY_HIGHEST = 'highest';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';
    
    // Relation types
    const RELATION_CONTAINS = 'contains';
    const RELATION_BLOCKS = 'blocks';
    const RELATION_DUPLICATES = 'duplicates';
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new work item
     */
    public function create($data) {
        try {
            // Get community_id from the project
            $projectStmt = $this->db->prepare("SELECT community_id FROM projects WHERE id = ?");
            $projectStmt->execute([$data['project_id']]);
            $project = $projectStmt->fetch();
            
            if (!$project) {
                throw new Exception("Project not found");
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO work_items (
                    type, title, description, prompt, status, approval_status, priority, 
                    story_points, estimate_hours, assignee_id, reporter_id, 
                    sprint_id, project_id, community_id, labels, due_date
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $params = [
                $data['type'] ?? self::TYPE_TASK,
                $data['title'],
                $data['description'] ?? null,
                $data['prompt'] ?? null,
                $data['status'] ?? self::STATUS_TODO,
                $data['approval_status'] ?? 'draft',
                $data['priority'] ?? self::PRIORITY_MEDIUM,
                (!empty($data['story_points'])) ? $data['story_points'] : null,
                (!empty($data['estimate_hours'])) ? $data['estimate_hours'] : null,
                (!empty($data['assignee_id'])) ? $data['assignee_id'] : null,
                $data['reporter_id'],
                (!empty($data['sprint_id'])) ? $data['sprint_id'] : null,
                $data['project_id'],
                $project['community_id'],
                json_encode($data['labels'] ?? []),
                (!empty($data['due_date'])) ? $data['due_date'] : null
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                $workItemId = $this->db->lastInsertId();
                
                // Add parent relation if specified
                if (!empty($data['parent_id'])) {
                    $this->addRelation($data['parent_id'], $workItemId, self::RELATION_CONTAINS);
                }
                
                // Add to sprint if sprint_id is provided
                if (!empty($data['sprint_id'])) {
                    $this->addToSprint($workItemId, $data['sprint_id'], $data['reporter_id']);
                }
                
                return $workItemId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("WorkItem creation exception: " . $e->getMessage());
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Find work item by ID
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                       assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name,
                       p.name as project_name,
                       s.name as sprint_name,
                       parent_rel.parent_id as parent_id,
                       parent.title as parent_title,
                       parent.type as parent_type
                FROM work_items wi
                LEFT JOIN users creator ON wi.reporter_id = creator.id
                LEFT JOIN users assignee ON wi.assignee_id = assignee.id
                LEFT JOIN projects p ON wi.project_id = p.id
                LEFT JOIN sprints s ON wi.sprint_id = s.id
                LEFT JOIN work_item_relations parent_rel ON wi.id = parent_rel.child_id AND parent_rel.relation = 'contains'
                LEFT JOIN work_items parent ON parent_rel.parent_id = parent.id
                WHERE wi.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("WorkItem findById error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get work items for a project
     */
    public function findByProject($projectId, $filters = []) {
        try {
            $sql = "
                SELECT wi.*, 
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                       assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name,
                       s.name as sprint_name,
                       s.status as sprint_status,
                       s.end_date as sprint_end_date,
                       CASE 
                           WHEN s.status = 'completed' OR (s.end_date IS NOT NULL AND s.end_date < CURDATE()) THEN 'done'
                           ELSE wi.status
                       END as display_status,
                       COUNT(DISTINCT wir.child_id) as child_count,
                       COUNT(DISTINCT ac.id) as criteria_count,
                       SUM(CASE WHEN ac.is_completed = 1 THEN 1 ELSE 0 END) as criteria_completed
                FROM work_items wi
                LEFT JOIN users creator ON wi.reporter_id = creator.id
                LEFT JOIN users assignee ON wi.assignee_id = assignee.id
                LEFT JOIN sprints s ON wi.sprint_id = s.id
                LEFT JOIN work_item_relations wir ON wi.id = wir.parent_id
                LEFT JOIN acceptance_criteria ac ON wi.id = ac.work_item_id
                WHERE wi.project_id = ? AND wi.sprint_id IS NULL
            ";
            
            $params = [$projectId];
            
            // Apply filters
            if (!empty($filters['type'])) {
                $sql .= " AND wi.type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND wi.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['sprint_id'])) {
                $sql .= " AND wi.sprint_id = ?";
                $params[] = $filters['sprint_id'];
            }
            
            if (!empty($filters['assignee_id'])) {
                $sql .= " AND wi.assignee_id = ?";
                $params[] = $filters['assignee_id'];
            }
            
            $sql .= " GROUP BY wi.id ORDER BY wi.position ASC, wi.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem findByProject error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get child work items
     */
    public function getChildren($parentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                       assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name,
                       wir.relation
                FROM work_items wi
                JOIN work_item_relations wir ON wi.id = wir.child_id
                LEFT JOIN users creator ON wi.reporter_id = creator.id
                LEFT JOIN users assignee ON wi.assignee_id = assignee.id
                WHERE wir.parent_id = ?
                ORDER BY wi.position ASC, wi.created_at ASC
            ");
            $stmt->execute([$parentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem getChildren error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add relation between work items
     */
    public function addRelation($parentId, $childId, $relation = self::RELATION_CONTAINS) {
        try {
            // Validate relationship type based on work item types
            if (!$this->isValidRelation($parentId, $childId, $relation)) {
                throw new Exception("Invalid relationship between work item types");
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO work_item_relations (parent_id, child_id, relation)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE relation = VALUES(relation)
            ");
            return $stmt->execute([$parentId, $childId, $relation]);
        } catch (Exception $e) {
            error_log("WorkItem addRelation error: " . $e->getMessage());
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Validate if a relation is valid between two work item types
     */
    private function isValidRelation($parentId, $childId, $relation) {
        // Get types of both work items
        $stmt = $this->db->prepare("SELECT type FROM work_items WHERE id IN (?, ?)");
        $stmt->execute([$parentId, $childId]);
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($types) !== 2) {
            return false;
        }
        
        $parentType = $types[0];
        $childType = $types[1];
        
        // Define valid parent-child relationships
        $validRelations = [
            self::TYPE_EPIC => [self::TYPE_STORY, self::TYPE_BUG, self::TYPE_SPIKE],
            self::TYPE_STORY => [self::TYPE_TASK, self::TYPE_BUG],
            self::TYPE_SPIKE => [self::TYPE_TASK]
        ];
        
        if ($relation === self::RELATION_CONTAINS) {
            return isset($validRelations[$parentType]) && 
                   in_array($childType, $validRelations[$parentType]);
        }
        
        // Blocks and duplicates can be between any types
        return true;
    }
    
    /**
     * Update work item
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            // Build dynamic update query
            $allowedFields = [
                'title', 'description', 'status', 'priority', 
                'story_points', 'estimate_hours', 'actual_hours',
                'assignee_id', 'sprint_id', 'due_date', 'labels',
                'type', 'parent_id', 'prompt'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    if ($field === 'labels' && is_array($data[$field])) {
                        $params[] = json_encode($data[$field]);
                    } else {
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($fields)) {
                return true; // Nothing to update
            }
            
            // Update completed_at if status changes to done
            if (isset($data['status']) && $data['status'] === self::STATUS_DONE) {
                $fields[] = "completed_at = NOW()";
            } elseif (isset($data['status']) && $data['status'] !== self::STATUS_DONE) {
                $fields[] = "completed_at = NULL";
            }
            
            $params[] = $id;
            
            $sql = "UPDATE work_items SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("WorkItem update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete work item (and all its relations due to CASCADE)
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM work_items WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("WorkItem delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add work item to sprint
     */
    public function addToSprint($workItemId, $sprintId, $addedBy) {
        try {
            // Update work_item sprint_id
            $stmt = $this->db->prepare("UPDATE work_items SET sprint_id = ? WHERE id = ?");
            $stmt->execute([$sprintId, $workItemId]);
            
            // Add to sprint_work_items for tracking
            $stmt = $this->db->prepare("
                INSERT INTO sprint_work_items (sprint_id, work_item_id, added_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE added_by = VALUES(added_by)
            ");
            return $stmt->execute([$sprintId, $workItemId, $addedBy]);
        } catch (PDOException $e) {
            error_log("WorkItem addToSprint error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get acceptance criteria for a work item
     */
    public function getAcceptanceCriteria($workItemId) {
        try {
            $stmt = $this->db->prepare("
                SELECT ac.*, u.first_name, u.last_name
                FROM acceptance_criteria ac
                LEFT JOIN users u ON ac.completed_by = u.id
                WHERE ac.work_item_id = ?
                ORDER BY ac.id ASC
            ");
            $stmt->execute([$workItemId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem getAcceptanceCriteria error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add acceptance criteria
     */
    public function addAcceptanceCriteria($workItemId, $text, $mustPass = true) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO acceptance_criteria (work_item_id, text, must_pass)
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$workItemId, $text, $mustPass]);
        } catch (PDOException $e) {
            error_log("WorkItem addAcceptanceCriteria error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update acceptance criteria completion
     */
    public function updateAcceptanceCriteria($criteriaId, $isCompleted, $completedBy = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE acceptance_criteria 
                SET is_completed = ?, 
                    completed_at = IF(? = 1, NOW(), NULL),
                    completed_by = IF(? = 1, ?, NULL)
                WHERE id = ?
            ");
            return $stmt->execute([
                $isCompleted, 
                $isCompleted, 
                $isCompleted, 
                $completedBy, 
                $criteriaId
            ]);
        } catch (PDOException $e) {
            error_log("WorkItem updateAcceptanceCriteria error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get work item hierarchy (epic -> stories -> tasks)
     */
    public function getHierarchy($rootId) {
        try {
            $stmt = $this->db->prepare("CALL GetWorkItemHierarchy(?)");
            $stmt->execute([$rootId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem getHierarchy error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get sprint work items with full details
     */
    public function getSprintWorkItems($sprintId) {
        try {
            $stmt = $this->db->prepare("CALL GetSprintWorkItems(?)");
            $stmt->execute([$sprintId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem getSprintWorkItems error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Approve a work item for backlog
     */
    public function approve($workItemId, $approvedBy) {
        try {
            $stmt = $this->db->prepare("
                UPDATE work_items 
                SET approval_status = 'approved', 
                    approved_by = ?, 
                    approved_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$approvedBy, $workItemId]);
        } catch (PDOException $e) {
            error_log("WorkItem approve error: " . $e->getMessage());
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Reject a work item
     */
    public function reject($workItemId, $rejectedBy, $reason) {
        try {
            $stmt = $this->db->prepare("
                UPDATE work_items 
                SET approval_status = 'rejected', 
                    approved_by = ?, 
                    approved_at = NOW(),
                    rejection_reason = ?
                WHERE id = ?
            ");
            return $stmt->execute([$rejectedBy, $reason, $workItemId]);
        } catch (PDOException $e) {
            error_log("WorkItem reject error: " . $e->getMessage());
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Update backlog priority
     */
    public function updateBacklogPriority($workItemId, $priority) {
        try {
            $stmt = $this->db->prepare("
                UPDATE work_items 
                SET backlog_priority = ?
                WHERE id = ?
            ");
            return $stmt->execute([$priority, $workItemId]);
        } catch (PDOException $e) {
            error_log("WorkItem updateBacklogPriority error: " . $e->getMessage());
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Get product backlog for a project
     */
    public function getProductBacklog($projectId) {
        try {
            $stmt = $this->db->prepare("
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
                WHERE wi.project_id = ?
                  AND wi.approval_status = 'approved' 
                  AND wi.sprint_id IS NULL
                  AND wi.status != 'done'
                ORDER BY wi.backlog_priority DESC, wi.created_at ASC
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem getProductBacklog error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get sprint backlog
     */
    public function getSprintBacklog($sprintId) {
        try {
            $stmt = $this->db->prepare("
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
                INNER JOIN sprints s ON swi.sprint_id = s.id
                LEFT JOIN users reporter ON wi.reporter_id = reporter.id
                LEFT JOIN users assignee ON wi.assignee_id = assignee.id
                WHERE swi.sprint_id = ?
                  AND wi.approval_status = 'approved'
                ORDER BY wi.position ASC, wi.priority DESC
            ");
            $stmt->execute([$sprintId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem getSprintBacklog error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate completion percentage based on child items or acceptance criteria
     */
    public function calculateCompletion($workItemId) {
        try {
            $workItem = $this->findById($workItemId);
            if (!$workItem) {
                return 0;
            }
            
            // For epics and stories, calculate based on child items
            if (in_array($workItem['type'], [self::TYPE_EPIC, self::TYPE_STORY])) {
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN wi.status = ? THEN 1 ELSE 0 END) as completed
                    FROM work_items wi
                    JOIN work_item_relations wir ON wi.id = wir.child_id
                    WHERE wir.parent_id = ?
                ");
                $stmt->execute([self::STATUS_DONE, $workItemId]);
                $result = $stmt->fetch();
                
                if ($result['total'] > 0) {
                    return round(($result['completed'] / $result['total']) * 100);
                }
            }
            
            // For other types, calculate based on acceptance criteria
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
                FROM acceptance_criteria
                WHERE work_item_id = ?
            ");
            $stmt->execute([$workItemId]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                return round(($result['completed'] / $result['total']) * 100);
            }
            
            // If no children or criteria, base on status
            return $workItem['status'] === self::STATUS_DONE ? 100 : 0;
        } catch (PDOException $e) {
            error_log("WorkItem calculateCompletion error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get project statistics
     */
    public function getProjectStats($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    type,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                    SUM(story_points) as total_points,
                    SUM(CASE WHEN status = 'done' THEN story_points ELSE 0 END) as completed_points
                FROM work_items
                WHERE project_id = ?
                GROUP BY type
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("WorkItem getProjectStats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user can access work item
     */
    public function canAccess($workItemId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.id
                FROM work_items wi
                LEFT JOIN project_members pm ON wi.project_id = pm.project_id
                WHERE wi.id = ? 
                AND (wi.assignee_id = ? OR pm.user_id = ? OR wi.reporter_id = ?)
            ");
            $stmt->execute([$workItemId, $userId, $userId, $userId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("WorkItem canAccess error: " . $e->getMessage());
            return false;
        }
    }
}
?>