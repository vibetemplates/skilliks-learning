<?php
/**
 * Task Class
 * 
 * Handles task management operations
 */

class Task {
    private $db;
    public $lastError = null;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new task
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
                INSERT INTO tasks (community_id, title, description, project_id, assignee_id, reporter_id, priority, type, status, due_date, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'todo', ?, NOW())
            ");
            
            $params = [
                $project['community_id'],  // Add community_id from project
                $data['title'],
                $data['description'] ?? null,
                $data['project_id'],  // This is required, cannot be null
                (!empty($data['assigned_to'])) ? $data['assigned_to'] : null,  // Convert empty string to null
                $data['created_by'],   // Maps to reporter_id (required)
                $data['priority'] ?? 'medium',
                $data['type'] ?? 'task',
                (!empty($data['due_date'])) ? $data['due_date'] : null  // Convert empty string to null
            ];
            
            error_log("Task creation attempt with params: " . print_r($params, true));
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return $this->db->lastInsertId();
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Task creation failed - Statement error: " . print_r($errorInfo, true));
                return false;
            }
        } catch (PDOException $e) {
            error_log("Task creation PDO exception: " . $e->getMessage());
            error_log("Task creation data: " . print_r($data, true));
            error_log("PDO Error Code: " . $e->getCode());
            error_log("SQL State: " . ($e->errorInfo[0] ?? 'Unknown'));
            
            // Store the error in the database connection for tasks.php to access
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Find task by ID
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, 
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                       assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name,
                       p.name as project_name
                FROM tasks t
                LEFT JOIN users creator ON t.reporter_id = creator.id
                LEFT JOIN users assignee ON t.assignee_id = assignee.id
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get tasks for a project
     */
    public function findByProject($projectId, $status = null) {
        try {
            $sql = "
                SELECT t.*, 
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                       assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name
                FROM tasks t
                LEFT JOIN users creator ON t.reporter_id = creator.id
                LEFT JOIN users assignee ON t.assignee_id = assignee.id
                WHERE t.project_id = ?
            ";
            
            $params = [$projectId];
            
            if ($status) {
                $sql .= " AND t.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY t.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get tasks assigned to a user
     */
    public function findByAssignee($userId, $status = null) {
        try {
            $sql = "
                SELECT t.*, 
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                       p.name as project_name
                FROM tasks t
                LEFT JOIN users creator ON t.reporter_id = creator.id
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.assignee_id = ?
            ";
            
            $params = [$userId];
            
            if ($status) {
                $sql .= " AND t.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY t.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get all tasks (for global task view) with assignment counts
     */
    public function findAll($status = null) {
        try {
            $sql = "
                SELECT t.*, 
                       creator.first_name as creator_first_name, creator.last_name as creator_last_name,
                       assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name,
                       p.name as project_name,
                       COUNT(ta.id) as assignment_count,
                       GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as assigned_users
                FROM tasks t
                LEFT JOIN users creator ON t.reporter_id = creator.id
                LEFT JOIN users assignee ON t.assignee_id = assignee.id
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.unassigned_at IS NULL
                LEFT JOIN users u ON ta.user_id = u.id
            ";
            
            $params = [];
            
            if ($status) {
                $sql .= " WHERE t.status = ?";
                $params[] = $status;
            }
            
            $sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Update task status
     */
    public function updateStatus($id, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Update task assignment
     */
    public function updateAssignment($id, $assignedTo) {
        try {
            $stmt = $this->db->prepare("UPDATE tasks SET assignee_id = ?, updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$assignedTo, $id]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Update task details
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE tasks 
                SET title = ?, description = ?, priority = ?, type = ?, due_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['priority'] ?? 'medium',
                $data['type'] ?? 'task',
                $data['due_date'] ?? null,
                $id
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Delete a task
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get task statistics for a project
     */
    public function getProjectStats($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
                FROM tasks 
                WHERE project_id = ?
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return ['total' => 0, 'todo' => 0, 'in_progress' => 0, 'done' => 0];
        }
    }
    
    /**
     * Get task statistics for a user
     */
    public function getUserStats($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
                FROM tasks 
                WHERE assignee_id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return ['total' => 0, 'todo' => 0, 'in_progress' => 0, 'done' => 0];
        }
    }
    
    /**
     * Check if user can access task (is member of project or task is assigned to them)
     */
    public function canAccess($taskId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.id
                FROM tasks t
                LEFT JOIN project_members pm ON t.project_id = pm.project_id
                LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.user_id = ? AND ta.unassigned_at IS NULL
                WHERE t.id = ? 
                AND (t.assignee_id = ? OR pm.user_id = ? OR t.project_id IS NULL OR ta.id IS NOT NULL)
            ");
            $stmt->execute([$userId, $taskId, $userId, $userId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Assign task to user (supports multiple assignments)
     */
    public function assignTo($taskId, $userId, $gitBranch = null) {
        try {
            // Check if user is already assigned
            $stmt = $this->db->prepare("
                SELECT id FROM task_assignments 
                WHERE task_id = ? AND user_id = ? AND unassigned_at IS NULL
            ");
            $stmt->execute([$taskId, $userId]);
            if ($stmt->fetch()) {
                return false; // Already assigned
            }
            
            // Add new assignment
            $stmt = $this->db->prepare("
                INSERT INTO task_assignments (task_id, user_id, assigned_by, git_branch) 
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$taskId, $userId, $userId, $gitBranch]);
            
            if ($result) {
                // Update task's updated_at timestamp
                $stmt = $this->db->prepare("UPDATE tasks SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$taskId]);
            }
            
            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Unassign task (only if current user is assigned to it)
     */
    public function unassign($taskId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE task_assignments 
                SET unassigned_at = NOW() 
                WHERE task_id = ? AND user_id = ? AND unassigned_at IS NULL
            ");
            $result = $stmt->execute([$taskId, $userId]);
            
            if ($result) {
                // Update task's updated_at timestamp
                $stmt = $this->db->prepare("UPDATE tasks SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$taskId]);
            }
            
            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get all assignments for a task
     */
    public function getTaskAssignments($taskId) {
        try {
            $stmt = $this->db->prepare("
                SELECT ta.*, u.first_name, u.last_name, u.email
                FROM task_assignments ta
                JOIN users u ON ta.user_id = u.id
                WHERE ta.task_id = ? AND ta.unassigned_at IS NULL
                ORDER BY ta.assigned_at
            ");
            $stmt->execute([$taskId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Update Git branch for an assignment
     */
    public function updateGitBranch($taskId, $userId, $gitBranch) {
        try {
            $stmt = $this->db->prepare("
                UPDATE task_assignments 
                SET git_branch = ? 
                WHERE task_id = ? AND user_id = ? AND unassigned_at IS NULL
            ");
            return $stmt->execute([$gitBranch, $taskId, $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Check if user is assigned to task
     */
    public function isUserAssigned($taskId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM task_assignments 
                WHERE task_id = ? AND user_id = ? AND unassigned_at IS NULL
            ");
            $stmt->execute([$taskId, $userId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get assignment count for a task
     */
    public function getAssignmentCount($taskId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM task_assignments 
                WHERE task_id = ? AND unassigned_at IS NULL
            ");
            $stmt->execute([$taskId]);
            $result = $stmt->fetch();
            return $result ? $result['count'] : 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
}
?>