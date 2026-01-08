<?php
/**
 * Sprint Class
 * 
 * Handles sprint management for projects
 */

class Sprint {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new sprint
     */
    public function create($data) {
        try {
            // Get community_id from project
            $stmt = $this->db->prepare("SELECT community_id FROM projects WHERE id = ?");
            $stmt->execute([$data['project_id']]);
            $project = $stmt->fetch();
            
            if (!$project) {
                error_log("Project not found for sprint creation");
                return false;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO sprints (community_id, project_id, name, goal, start_date, end_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $project['community_id'],
                $data['project_id'],
                $data['name'],
                $data['goal'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['status'] ?? 'planning',
                $data['created_by']
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating sprint: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a sprint
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach (['name', 'goal', 'start_date', 'end_date', 'status'] as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return true;
            }
            
            $values[] = $id;
            $sql = "UPDATE sprints SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Error updating sprint: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a sprint
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM sprints WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting sprint: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find sprint by ID (alias for getById)
     */
    public function findById($id) {
        return $this->getById($id);
    }
    
    /**
     * Get sprint by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, 
                       u.first_name as creator_first_name, 
                       u.last_name as creator_last_name,
                       p.name as project_name,
                       (SELECT COUNT(*) FROM sprint_work_items WHERE sprint_id = s.id) as work_item_count,
                       (SELECT COUNT(*) FROM work_items wi 
                        JOIN sprint_work_items swi ON wi.id = swi.work_item_id 
                        WHERE swi.sprint_id = s.id AND wi.status = 'done') as completed_items
                FROM sprints s
                LEFT JOIN users u ON s.created_by = u.id
                LEFT JOIN projects p ON s.project_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting sprint: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all sprints for a project
     */
    public function getByProject($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, 
                       u.first_name as creator_first_name, 
                       u.last_name as creator_last_name,
                       (SELECT COUNT(*) FROM sprint_work_items WHERE sprint_id = s.id) as work_item_count,
                       (SELECT COUNT(*) FROM work_items wi 
                        JOIN sprint_work_items swi ON wi.id = swi.work_item_id 
                        WHERE swi.sprint_id = s.id AND wi.status = 'done') as completed_items,
                       (SELECT SUM(wi.story_points) FROM work_items wi 
                        JOIN sprint_work_items swi ON wi.id = swi.work_item_id 
                        WHERE swi.sprint_id = s.id) as total_story_points,
                       (SELECT SUM(wi.story_points) FROM work_items wi 
                        JOIN sprint_work_items swi ON wi.id = swi.work_item_id 
                        WHERE swi.sprint_id = s.id AND wi.status = 'done') as completed_story_points
                FROM sprints s
                LEFT JOIN users u ON s.created_by = u.id
                WHERE s.project_id = ?
                ORDER BY s.start_date DESC
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting project sprints: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active sprint for a project
     */
    public function getActiveSprint($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM sprints 
                WHERE project_id = ? AND status = 'active'
                ORDER BY start_date DESC
                LIMIT 1
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting active sprint: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get active sprints for a project
     */
    public function getActiveSprintsByProject($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, 
                       u.first_name as creator_first_name, 
                       u.last_name as creator_last_name
                FROM sprints s
                LEFT JOIN users u ON s.created_by = u.id
                WHERE s.project_id = ? 
                AND s.status IN ('planning', 'active')
                ORDER BY s.start_date ASC
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting active sprints: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add work item to sprint
     */
    public function addWorkItem($sprintId, $workItemId, $userId) {
        try {
            // First add to sprint_work_items table
            $stmt = $this->db->prepare("
                INSERT INTO sprint_work_items (sprint_id, work_item_id, added_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE added_at = CURRENT_TIMESTAMP
            ");
            $result = $stmt->execute([$sprintId, $workItemId, $userId]);
            
            // Also update work_items table for quick reference
            if ($result) {
                $stmt = $this->db->prepare("UPDATE work_items SET sprint_id = ? WHERE id = ?");
                $stmt->execute([$sprintId, $workItemId]);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error adding work item to sprint: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove work item from sprint
     */
    public function removeWorkItem($sprintId, $workItemId) {
        try {
            // Remove from sprint_work_items table
            $stmt = $this->db->prepare("
                DELETE FROM sprint_work_items 
                WHERE sprint_id = ? AND work_item_id = ?
            ");
            $result = $stmt->execute([$sprintId, $workItemId]);
            
            // Also update work_items table
            if ($result) {
                $stmt = $this->db->prepare("UPDATE work_items SET sprint_id = NULL WHERE id = ? AND sprint_id = ?");
                $stmt->execute([$workItemId, $sprintId]);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error removing work item from sprint: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get work items in a sprint
     */
    public function getWorkItems($sprintId) {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       u1.first_name as assignee_first_name, 
                       u1.last_name as assignee_last_name,
                       u2.first_name as reporter_first_name, 
                       u2.last_name as reporter_last_name,
                       swi.added_at as added_to_sprint_at,
                       u3.first_name as added_by_first_name,
                       u3.last_name as added_by_last_name
                FROM work_items wi
                JOIN sprint_work_items swi ON wi.id = swi.work_item_id
                LEFT JOIN users u1 ON wi.assignee_id = u1.id
                LEFT JOIN users u2 ON wi.reporter_id = u2.id
                LEFT JOIN users u3 ON swi.added_by = u3.id
                WHERE swi.sprint_id = ?
                ORDER BY wi.priority DESC, wi.created_at ASC
            ");
            $stmt->execute([$sprintId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting sprint work items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get available work items for a project (not in any sprint)
     */
    public function getAvailableWorkItems($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       u1.first_name as assignee_first_name, 
                       u1.last_name as assignee_last_name,
                       u2.first_name as reporter_first_name, 
                       u2.last_name as reporter_last_name
                FROM work_items wi
                LEFT JOIN users u1 ON wi.assignee_id = u1.id
                LEFT JOIN users u2 ON wi.reporter_id = u2.id
                WHERE wi.project_id = ? 
                    AND wi.id NOT IN (SELECT work_item_id FROM sprint_work_items)
                    AND wi.status != 'done'
                ORDER BY wi.priority DESC, wi.created_at ASC
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting available work items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate sprint progress
     */
    public function calculateProgress($sprintId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN wi.status = 'done' THEN 1 ELSE 0 END) as completed_items,
                    SUM(wi.story_points) as total_points,
                    SUM(CASE WHEN wi.status = 'done' THEN wi.story_points ELSE 0 END) as completed_points
                FROM work_items wi
                JOIN sprint_work_items swi ON wi.id = swi.work_item_id
                WHERE swi.sprint_id = ?
            ");
            $stmt->execute([$sprintId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error calculating sprint progress: " . $e->getMessage());
            return [
                'total_items' => 0,
                'completed_items' => 0,
                'total_points' => 0,
                'completed_points' => 0
            ];
        }
    }
    
    /**
     * Check if user can manage sprints for a project
     */
    public function canManageSprints($projectId, $userId) {
        try {
            // Check if user is project creator or project manager
            $stmt = $this->db->prepare("
                SELECT id FROM projects 
                WHERE id = ? AND (created_by = ? OR project_manager_id = ?)
            ");
            $stmt->execute([$projectId, $userId, $userId]);
            if ($stmt->fetch()) {
                return true;
            }
            
            // Check if user is admin
            $userObj = new User();
            return $userObj->isProjectManagerOrAdmin($userId);
        } catch (PDOException $e) {
            error_log("Error checking sprint permissions: " . $e->getMessage());
            return false;
        }
    }
}