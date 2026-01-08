<?php
/**
 * Projects API Endpoint
 * 
 * Handles:
 * GET /api/v1/projects?community_id={id} - List all projects in a community
 * GET /api/v1/projects/{id} - Get project details
 * GET /api/v1/projects/{id}/skills - Get project skills
 * GET /api/v1/projects/{id}/members - Get project members
 */

require_once 'BaseAPI.php';
require_once __DIR__ . '/../../classes/Project.php';

class ProjectsAPI extends BaseAPI {
    
    public function handleRequest() {
        // Parse the URI to determine the action
        $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $projectId = null;
        $action = null;
        
        // Check if we have /api/v1/projects/{id} or /api/v1/projects/{id}/{action}
        if (count($pathParts) >= 4 && is_numeric($pathParts[3])) {
            $projectId = (int)$pathParts[3];
            if (isset($pathParts[4])) {
                $action = $pathParts[4];
            }
        }
        
        if ($this->method !== 'GET') {
            $this->sendError(405, 'Method not allowed');
        }
        
        if ($projectId) {
            switch ($action) {
                case 'skills':
                    $this->getProjectSkills($projectId);
                    break;
                case 'members':
                    $this->getProjectMembers($projectId);
                    break;
                case null:
                    $this->getProjectDetails($projectId);
                    break;
                default:
                    $this->sendError(404, 'Endpoint not found');
            }
        } else {
            $this->listProjects();
        }
    }
    
    /**
     * List projects in a community
     * GET /api/v1/projects?community_id={id}
     * 
     * Query params:
     * - community_id: Required - Community ID to filter projects
     * - status: Optional - Filter by status (planning, active, completed, archived)
     * - page: page number (default: 1)
     * - limit: items per page (default: 20, max: 100)
     */
    protected function listProjects() {
        $communityId = $this->getParam('community_id');
        if (!$communityId) {
            $this->sendError(400, 'community_id parameter is required');
        }
        
        // Check if user has access to this community
        if (!$this->checkCommunityAccess($communityId)) {
            $this->sendError(403, 'Access denied to this community');
        }
        
        $status = $this->getParam('status', '');
        $page = max(1, (int)$this->getParam('page', 1));
        $limit = min(100, max(1, (int)$this->getParam('limit', 20)));
        $offset = ($page - 1) * $limit;
        
        try {
            $conditions = ['p.community_id = ?'];
            $params = [$communityId];
            
            if ($status && in_array($status, ['planning', 'active', 'completed', 'archived'])) {
                $conditions[] = 'p.status = ?';
                $params[] = $status;
            }
            
            $whereClause = implode(' AND ', $conditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM projects p WHERE $whereClause";
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get projects
            $query = "
                SELECT 
                    p.*,
                    COUNT(DISTINCT pm.user_id) as member_count,
                    CONCAT(u.first_name, ' ', u.last_name) as manager_name
                FROM projects p
                LEFT JOIN project_members pm ON p.id = pm.project_id
                LEFT JOIN users u ON p.project_manager_id = u.id
                WHERE $whereClause
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get member status for authenticated users
            if ($this->isAuthenticated && !empty($projects)) {
                $projectIds = array_column($projects, 'id');
                $placeholders = str_repeat('?,', count($projectIds) - 1) . '?';
                $memberQuery = "
                    SELECT project_id, role 
                    FROM project_members 
                    WHERE user_id = ? AND project_id IN ($placeholders)
                ";
                $memberParams = array_merge([$this->userId], $projectIds);
                $stmt = $this->db->prepare($memberQuery);
                $stmt->execute($memberParams);
                $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $membershipMap = [];
                foreach ($memberships as $membership) {
                    $membershipMap[$membership['project_id']] = $membership['role'];
                }
                
                foreach ($projects as &$project) {
                    $project['user_role'] = $membershipMap[$project['id']] ?? null;
                }
            }
            
            $response = [
                'projects' => $projects,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
            $this->sendSuccess($response);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch projects: ' . $e->getMessage());
        }
    }
    
    /**
     * Get project details
     * GET /api/v1/projects/{id}
     */
    protected function getProjectDetails($projectId) {
        try {
            $query = "
                SELECT 
                    p.*,
                    COUNT(DISTINCT pm.user_id) as member_count,
                    CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                    c.name as community_name
                FROM projects p
                LEFT JOIN project_members pm ON p.id = pm.project_id
                LEFT JOIN users u ON p.project_manager_id = u.id
                LEFT JOIN communities c ON p.community_id = c.id
                WHERE p.id = ?
                GROUP BY p.id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                $this->sendError(404, 'Project not found');
            }
            
            // Check if user has access to this project's community
            if (!$this->checkCommunityAccess($project['community_id'])) {
                $this->sendError(403, 'Access denied');
            }
            
            // Get project categories
            $stmt = $this->db->prepare("
                SELECT pc.id, pc.name, pc.slug
                FROM project_categories pc
                JOIN project_category_links pcl ON pc.id = pcl.category_id
                WHERE pcl.project_id = ?
            ");
            $stmt->execute([$projectId]);
            $project['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get project surveys
            $stmt = $this->db->prepare("
                SELECT ps.*, s.name as survey_name
                FROM project_surveys ps
                JOIN surveys s ON ps.survey_id = s.id
                WHERE ps.project_id = ?
            ");
            $stmt->execute([$projectId]);
            $project['surveys'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get user's role if authenticated
            if ($this->isAuthenticated) {
                $stmt = $this->db->prepare("
                    SELECT role FROM project_members 
                    WHERE project_id = ? AND user_id = ?
                ");
                $stmt->execute([$projectId, $this->userId]);
                $membership = $stmt->fetch(PDO::FETCH_ASSOC);
                $project['user_role'] = $membership ? $membership['role'] : null;
            }
            
            $this->sendSuccess($project);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch project details: ' . $e->getMessage());
        }
    }
    
    /**
     * Get project skills
     * GET /api/v1/projects/{id}/skills
     */
    protected function getProjectSkills($projectId) {
        try {
            // First check if project exists and user has access
            $stmt = $this->db->prepare("SELECT community_id FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                $this->sendError(404, 'Project not found');
            }
            
            if (!$this->checkCommunityAccess($project['community_id'])) {
                $this->sendError(403, 'Access denied');
            }
            
            // Get project skills
            $query = "
                SELECT 
                    s.id,
                    s.name,
                    s.description,
                    sc.name as category_name,
                    ps.proficiency_required,
                    ps.is_required
                FROM project_skills ps
                JOIN skills s ON ps.skill_id = s.id
                LEFT JOIN skill_categories sc ON s.category_id = sc.id
                WHERE ps.project_id = ?
                ORDER BY ps.is_required DESC, sc.display_order, s.name
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$projectId]);
            $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group skills by category
            $groupedSkills = [];
            foreach ($skills as $skill) {
                $category = $skill['category_name'] ?? 'Other';
                if (!isset($groupedSkills[$category])) {
                    $groupedSkills[$category] = [];
                }
                $groupedSkills[$category][] = $skill;
            }
            
            $response = [
                'project_id' => $projectId,
                'skills' => $skills,
                'skills_by_category' => $groupedSkills,
                'total_skills' => count($skills),
                'required_skills' => count(array_filter($skills, function($s) { return $s['is_required']; }))
            ];
            
            $this->sendSuccess($response);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch project skills: ' . $e->getMessage());
        }
    }
    
    /**
     * Get project members
     * GET /api/v1/projects/{id}/members
     */
    protected function getProjectMembers($projectId) {
        try {
            // First check if project exists and user has access
            $stmt = $this->db->prepare("SELECT community_id FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                $this->sendError(404, 'Project not found');
            }
            
            if (!$this->checkCommunityAccess($project['community_id'])) {
                $this->sendError(403, 'Access denied');
            }
            
            // Get project members
            $query = "
                SELECT 
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.email,
                    u.profile_photo,
                    u.github_username,
                    pm.role,
                    pm.joined_at,
                    pm.hours_per_week,
                    pm.contribution_notes
                FROM project_members pm
                JOIN users u ON pm.user_id = u.id
                WHERE pm.project_id = ?
                ORDER BY 
                    CASE pm.role 
                        WHEN 'project_manager' THEN 1
                        WHEN 'tech_lead' THEN 2
                        WHEN 'developer' THEN 3
                        WHEN 'designer' THEN 4
                        WHEN 'tester' THEN 5
                        ELSE 6
                    END,
                    pm.joined_at
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$projectId]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get member skills
            foreach ($members as &$member) {
                $stmt = $this->db->prepare("
                    SELECT s.name
                    FROM user_skills us
                    JOIN skills s ON us.skill_id = s.id
                    WHERE us.user_id = ?
                    LIMIT 5
                ");
                $stmt->execute([$member['id']]);
                $member['top_skills'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            $response = [
                'project_id' => $projectId,
                'members' => $members,
                'total_members' => count($members),
                'roles_breakdown' => array_count_values(array_column($members, 'role'))
            ];
            
            $this->sendSuccess($response);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch project members: ' . $e->getMessage());
        }
    }
}

// Initialize and handle request
$api = new ProjectsAPI();
$api->handleRequest();