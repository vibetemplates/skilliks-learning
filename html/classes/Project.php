<?php
/**
 * Project Class
 * 
 * Handles project management operations
 */

class Project {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new project
     */
    public function create($data) {
        try {
            // Get the user's default_community_id
            $userStmt = $this->db->prepare("SELECT default_community_id FROM users WHERE id = ?");
            $userStmt->execute([$data['created_by']]);
            $user = $userStmt->fetch();
            $community_id = $user['default_community_id'] ?? 1; // Default to 1 if not found
            
            // Get default category if not provided
            $project_category_id = !empty($data['project_category_id']) ? intval($data['project_category_id']) : null;
            if (!$project_category_id) {
                $catStmt = $this->db->prepare("SELECT id FROM project_categories WHERE name = 'General Projects' LIMIT 1");
                $catStmt->execute();
                $cat = $catStmt->fetch();
                $project_category_id = $cat ? $cat['id'] : null;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO projects (community_id, project_category_id, name, description, thumbnail_url, course_code, team_size_limit, git_repository_url, video_url, video_embed_code, slug, visibility, dev_system_url, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([
                $community_id,
                $project_category_id,
                $data['name'],
                !empty($data['description']) ? $data['description'] : null,
                !empty($data['thumbnail_url']) ? $data['thumbnail_url'] : 'https://images.skilliks.ai/pending.jpg',
                !empty($data['course_code']) ? $data['course_code'] : null,
                $data['team_size_limit'] ?? 10,
                !empty($data['git_repository_url']) ? $data['git_repository_url'] : null,
                !empty($data['video_url']) ? $data['video_url'] : null,
                !empty($data['video_embed_code']) ? $data['video_embed_code'] : null,
                !empty($data['slug']) ? $data['slug'] : null,
                $data['visibility'] ?? 'public',
                !empty($data['dev_system_url']) ? $data['dev_system_url'] : null,
                $data['created_by']
            ]);
            
            if ($result) {
                $projectId = $this->db->lastInsertId();
                // Automatically add creator as member
                $this->joinProject($projectId, $data['created_by']);
                return $projectId;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Project create error: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("Data: " . json_encode($data));
            throw $e; // Re-throw to see the actual error
        }
    }
    
    /**
     * Find project by ID with creator and member count info
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(DISTINCT pm.user_id) as member_count,
                       COALESCE(p.vote_count, 0) as vote_count,
                       pc.name as category_name, pc.description as category_description,
                       pc.skill_level as category_skill_level
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
                LEFT JOIN project_categories pc ON p.project_category_id = pc.id
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get all projects with member counts
     */
    public function findAll($userId = null) {
        try {
            // Check if user is admin
            $isAdmin = false;
            if ($userId) {
                $adminCheck = $this->db->prepare("SELECT COUNT(*) FROM global_admins WHERE user_id = ?");
                $adminCheck->execute([$userId]);
                $isAdmin = $adminCheck->fetchColumn() > 0;
            }
            
            $sql = "
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(pm.user_id) as member_count,
                       COUNT(CASE WHEN pm.completion_status = 'working' THEN 1 END) as working_count,
                       COUNT(CASE WHEN pm.completion_status = 'completed' THEN 1 END) as completed_count
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
            ";
            
            // Filter planning projects unless admin or creator
            if (!$isAdmin && $userId) {
                $sql .= " WHERE (p.status != 'planning' OR p.created_by = ?)";
            } elseif (!$isAdmin && !$userId) {
                // No user logged in, hide all planning projects
                $sql .= " WHERE p.status != 'planning'";
            }
            
            $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            
            if (!$isAdmin && $userId) {
                $stmt->execute([$userId]);
            } else {
                $stmt->execute();
            }
            
            $projects = $stmt->fetchAll();
            
            // Fetch skills for each project
            foreach ($projects as &$project) {
                $project['skills'] = $this->getProjectSkills($project['id']);
            }
            
            return $projects;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get all active projects
     */
    public function findActiveProjects($userId = null) {
        try {
            // Check if user is admin
            $isAdmin = false;
            if ($userId) {
                $adminCheck = $this->db->prepare("SELECT COUNT(*) FROM global_admins WHERE user_id = ?");
                $adminCheck->execute([$userId]);
                $isAdmin = $adminCheck->fetchColumn() > 0;
            }
            
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(DISTINCT pm.user_id) as member_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'working' THEN pm.user_id END) as working_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'completed' THEN pm.user_id END) as completed_count,
                       COALESCE(p.vote_count, 0) as vote_count
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
                WHERE p.status = 'active'
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute();
            $projects = $stmt->fetchAll();
            
            // Fetch skills for each project
            foreach ($projects as &$project) {
                $project['skills'] = $this->getProjectSkills($project['id']);
            }
            
            return $projects;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get user's projects (all projects where user is a member)
     */
    public function findMyProjects($userId, $communityId = null) {
        $communityId = $communityId ?: getCurrentCommunityId();
        
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(DISTINCT pm.user_id) as member_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'working' THEN pm.user_id END) as working_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'completed' THEN pm.user_id END) as completed_count,
                       COALESCE(p.vote_count, 0) as vote_count
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
                WHERE p.id IN (
                    SELECT project_id FROM project_members WHERE user_id = ? AND status = 'approved'
                )
                AND p.community_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$userId, $communityId]);
            $projects = $stmt->fetchAll();
            
            // Fetch skills for each project
            foreach ($projects as &$project) {
                $project['skills'] = $this->getProjectSkills($project['id']);
            }
            
            return $projects;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get all pending (planning) projects
     */
    public function findPendingProjects($userId = null, $communityId = null) {
        $communityId = $communityId ?: getCurrentCommunityId();
        
        try {
            // All users can see pending projects now
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(DISTINCT pm.user_id) as member_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'working' THEN pm.user_id END) as working_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'completed' THEN pm.user_id END) as completed_count,
                       COALESCE(p.vote_count, 0) as vote_count
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
                WHERE p.status = 'planning' AND p.community_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$communityId]);
            $projects = $stmt->fetchAll();
            
            // Fetch skills for each project
            foreach ($projects as &$project) {
                $project['skills'] = $this->getProjectSkills($project['id']);
            }
            
            return $projects;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get projects available for a user to join (not already a member)
     */
    public function findAvailableForUser($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(pm.user_id) as member_count,
                       COUNT(CASE WHEN pm.completion_status = 'working' THEN 1 END) as working_count,
                       COUNT(CASE WHEN pm.completion_status = 'completed' THEN 1 END) as completed_count
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
                WHERE p.id NOT IN (
                    SELECT project_id FROM project_members WHERE user_id = ? AND status = 'approved'
                )
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$userId]);
            $projects = $stmt->fetchAll();
            
            // Fetch skills for each project
            foreach ($projects as &$project) {
                $project['skills'] = $this->getProjectSkills($project['id']);
            }
            
            return $projects;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get projects where user is a member
     */
    public function findByMember($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(pm.user_id) as member_count
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
                WHERE p.id IN (
                    SELECT project_id FROM project_members WHERE user_id = ? AND status = 'approved'
                )
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Check if user is a member of the project
     */
    public function isMember($projectId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM project_members 
                WHERE project_id = ? AND user_id = ? AND status = 'approved'
            ");
            $stmt->execute([$projectId, $userId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Add user to project
     */
    public function joinProject($projectId, $userId) {
        try {
            // Check if already a member
            if ($this->isMember($projectId, $userId)) {
                return ['success' => false, 'error' => 'Already a member of this project.'];
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO project_members (project_id, user_id, join_date, status) 
                VALUES (?, ?, NOW(), 'approved')
            ");
            $result = $stmt->execute([$projectId, $userId]);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to join project.'];
            }
        } catch (PDOException $e) {
            error_log("Project joinProject error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove user from project
     */
    public function leaveProject($projectId, $userId) {
        try {
            // Don't allow creator to leave their own project
            $project = $this->findById($projectId);
            if ($project && $project['created_by'] == $userId) {
                return ['success' => false, 'error' => 'Project creator cannot leave the project.'];
            }
            
            $stmt = $this->db->prepare("
                DELETE FROM project_members 
                WHERE project_id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$projectId, $userId]);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to leave project.'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database error occurred.'];
        }
    }
    
    /**
     * Get project members
     */
    public function getMembers($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id as user_id, u.first_name, u.last_name, u.email, pm.join_date, pm.completion_status
                FROM project_members pm
                JOIN users u ON pm.user_id = u.id
                WHERE pm.project_id = ? AND pm.status = 'approved'
                ORDER BY pm.join_date ASC
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Update project details
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE projects 
                SET name = ?, description = ?, thumbnail_url = ?, course_code = ?, team_size_limit = ?, 
                    git_repository_url = ?, video_url = ?, video_embed_code = ?, 
                    project_manager_id = ?, slug = ?, project_category_id = ?, dev_system_url = ?, test_url = ?, 
                    skilliks_api_key = ?, skilliks_system_url = ?, skilliks_agent_api = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['name'],
                !empty($data['description']) ? $data['description'] : null,
                !empty($data['thumbnail_url']) ? $data['thumbnail_url'] : null,
                !empty($data['course_code']) ? $data['course_code'] : null,
                $data['team_size_limit'] ?? 10,
                !empty($data['git_repository_url']) ? $data['git_repository_url'] : null,
                !empty($data['video_url']) ? $data['video_url'] : null,
                !empty($data['video_embed_code']) ? $data['video_embed_code'] : null,
                !empty($data['project_manager_id']) ? intval($data['project_manager_id']) : null,
                !empty($data['slug']) ? $data['slug'] : null,
                !empty($data['project_category_id']) ? intval($data['project_category_id']) : null,
                !empty($data['dev_system_url']) ? $data['dev_system_url'] : null,
                !empty($data['test_url']) ? $data['test_url'] : null,
                !empty($data['skilliks_api_key']) ? $data['skilliks_api_key'] : null,
                !empty($data['skilliks_system_url']) ? $data['skilliks_system_url'] : null,
                !empty($data['skilliks_agent_api']) ? $data['skilliks_agent_api'] : null,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Project update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get project statistics
     */
    public function getStats($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM project_members WHERE project_id = ?) as members,
                    (SELECT COUNT(*) FROM tasks WHERE project_id = ?) as tasks,
                    (SELECT COUNT(*) FROM features WHERE project_id = ?) as features,
                    (SELECT COUNT(*) FROM tasks WHERE project_id = ? AND status = 'done') as completed_tasks
            ");
            $stmt->execute([$projectId, $projectId, $projectId, $projectId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return ['members' => 0, 'tasks' => 0, 'features' => 0, 'completed_tasks' => 0];
        }
    }
    
    /**
     * Search projects by name or description
     */
    public function search($query) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(pm.user_id) as member_count
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id
                WHERE p.name LIKE ? OR p.description LIKE ?
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $searchTerm = "%{$query}%";
            $stmt->execute([$searchTerm, $searchTerm]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get skills required for a project
     */
    public function getProjectSkills($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, ps.importance_level
                FROM project_skills ps
                JOIN skills s ON ps.skill_id = s.id
                WHERE ps.project_id = ? AND s.is_active = 1
                ORDER BY 
                    CASE ps.importance_level
                        WHEN 'required' THEN 1
                        WHEN 'preferred' THEN 2
                        WHEN 'optional' THEN 3
                    END,
                    s.name ASC
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get recommended courses based on project skills
     */
    public function getRecommendedCourses($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT c.id, c.title, c.short_description, c.difficulty_level, c.duration_hours,
                       COUNT(DISTINCT cs.skill_id) as matching_skills,
                       GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as matched_skill_names
                FROM courses c
                JOIN course_skills cs ON c.id = cs.course_id
                JOIN skills s ON cs.skill_id = s.id
                JOIN project_skills ps ON cs.skill_id = ps.skill_id
                WHERE ps.project_id = ?
                AND c.status = 'published'
                GROUP BY c.id
                ORDER BY 
                    COUNT(DISTINCT CASE WHEN ps.importance_level = 'required' THEN cs.skill_id END) DESC,
                    matching_skills DESC,
                    c.title ASC
                LIMIT 5
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Project getRecommendedCourses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get required courses assigned to a project
     */
    public function getRequiredCourses($projectId) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.id, c.title, c.short_description, c.difficulty_level, c.duration_hours,
                       pc.assignment_type, pc.notes
                FROM project_course_assignments pc
                JOIN courses c ON pc.course_id = c.id
                WHERE pc.project_id = ?
                AND pc.assignment_type = 'required'
                ORDER BY c.title ASC
            ");
            $stmt->execute([$projectId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Project getRequiredCourses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a project and all related data
     */
    public function delete($projectId) {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Delete in order to respect foreign key constraints
            // Skip file uploads and comments tables since they don't exist yet
            
            // 1. Delete project votes
            $stmt = $this->db->prepare("DELETE FROM project_votes WHERE project_id = ?");
            $stmt->execute([$projectId]);
            
            // 2. Delete task assignments
            $stmt = $this->db->prepare("DELETE ta FROM task_assignments ta JOIN tasks t ON ta.task_id = t.id WHERE t.project_id = ?");
            $stmt->execute([$projectId]);
            
            // 3. Delete tasks
            $stmt = $this->db->prepare("DELETE FROM tasks WHERE project_id = ?");
            $stmt->execute([$projectId]);
            
            // 4. Delete feature votes
            $stmt = $this->db->prepare("DELETE fv FROM feature_votes fv JOIN features f ON fv.feature_id = f.id WHERE f.project_id = ?");
            $stmt->execute([$projectId]);
            
            // 5. Delete features
            $stmt = $this->db->prepare("DELETE FROM features WHERE project_id = ?");
            $stmt->execute([$projectId]);
            
            // 6. Delete project skills
            $stmt = $this->db->prepare("DELETE FROM project_skills WHERE project_id = ?");
            $stmt->execute([$projectId]);
            
            // 7. Delete project course assignments
            $stmt = $this->db->prepare("DELETE FROM project_course_assignments WHERE project_id = ?");
            $stmt->execute([$projectId]);
            
            // 8. Delete project members
            $stmt = $this->db->prepare("DELETE FROM project_members WHERE project_id = ?");
            $stmt->execute([$projectId]);
            
            // 9. Finally, delete the project itself
            $stmt = $this->db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            
            // Commit transaction
            $this->db->commit();
            
            return true;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->db->rollback();
            error_log("Project delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get projects by category
     */
    public function findByCategory($categoryId, $status = 'active', $userId = null) {
        try {
            $sql = "
                SELECT p.*, 
                       u.first_name as creator_first_name, u.last_name as creator_last_name,
                       COUNT(DISTINCT pm.user_id) as member_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'working' THEN pm.user_id END) as working_count,
                       COUNT(DISTINCT CASE WHEN pm.completion_status = 'completed' THEN pm.user_id END) as completed_count,
                       COALESCE(p.vote_count, 0) as vote_count,
                       pc.name as category_name, pc.description as category_description,
                       pc.skill_level as category_skill_level
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
                LEFT JOIN project_categories pc ON p.project_category_id = pc.id
                WHERE p.project_category_id = :category_id
            ";
            
            $params = ['category_id' => $categoryId];
            
            if ($status) {
                $sql .= " AND p.status = :status";
                $params['status'] = $status;
            }
            
            // Don't filter by community - show all projects in the category
            
            $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $projects = $stmt->fetchAll();
            
            // Fetch skills for each project (same as findActiveProjects)
            foreach ($projects as &$project) {
                $project['skills'] = $this->getProjectSkills($project['id']);
            }
            
            return $projects;
            
        } catch (PDOException $e) {
            error_log("Find by category error: " . $e->getMessage());
            return [];
        }
    }
}
?>