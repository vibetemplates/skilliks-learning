<?php
/**
 * Communities API Endpoint
 * 
 * Handles:
 * GET /api/v1/communities - List all communities (public or user's communities)
 * GET /api/v1/communities/{id} - Get specific community details
 * POST /api/v1/communities - Create new community (requires auth)
 * PUT /api/v1/communities/{id} - Update community (requires admin)
 */

require_once 'BaseAPI.php';
require_once __DIR__ . '/../../classes/Community.php';

class CommunitiesAPI extends BaseAPI {
    
    public function handleRequest() {
        // Get community ID from path if present
        $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $communityId = null;
        
        // Check if we have /api/v1/communities/{id}
        if (count($pathParts) >= 4 && is_numeric($pathParts[3])) {
            $communityId = (int)$pathParts[3];
        }
        
        switch ($this->method) {
            case 'GET':
                if ($communityId) {
                    $this->getCommunity($communityId);
                } else {
                    $this->listCommunities();
                }
                break;
                
            case 'POST':
                $this->createCommunity();
                break;
                
            case 'PUT':
                if (!$communityId) {
                    $this->sendError(400, 'Community ID required');
                }
                $this->updateCommunity($communityId);
                break;
                
            case 'DELETE':
                $this->sendError(405, 'Method not allowed');
                break;
                
            default:
                $this->sendError(405, 'Method not allowed');
        }
    }
    
    /**
     * List communities
     * GET /api/v1/communities
     * 
     * Query params:
     * - filter: all|mine|public (default: public)
     * - page: page number (default: 1)
     * - limit: items per page (default: 20, max: 100)
     * - search: search term
     */
    protected function listCommunities() {
        $filter = $this->getParam('filter', 'public');
        $page = max(1, (int)$this->getParam('page', 1));
        $limit = min(100, max(1, (int)$this->getParam('limit', 20)));
        $search = $this->getParam('search', '');
        $offset = ($page - 1) * $limit;
        
        try {
            $conditions = ['1=1'];
            $params = [];
            
            // Apply filter
            if ($filter === 'mine' && $this->isAuthenticated) {
                $conditions[] = "c.id IN (SELECT community_id FROM community_members WHERE user_id = ? AND is_active = 1)";
                $params[] = $this->userId;
            } elseif ($filter === 'public') {
                $conditions[] = "c.is_public = 1";
            }
            // 'all' shows all communities (public and private if authenticated)
            
            // Apply search
            if ($search) {
                $conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
            
            $whereClause = implode(' AND ', $conditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM communities c WHERE $whereClause";
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get communities
            $query = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT cm.user_id) as member_count,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM communities c
                LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.is_active = 1
                LEFT JOIN users u ON c.created_by = u.id
                WHERE $whereClause
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check membership status if authenticated
            if ($this->isAuthenticated) {
                $communityIds = array_column($communities, 'id');
                if (!empty($communityIds)) {
                    $placeholders = str_repeat('?,', count($communityIds) - 1) . '?';
                    $memberQuery = "
                        SELECT community_id, role, is_active 
                        FROM community_members 
                        WHERE user_id = ? AND community_id IN ($placeholders)
                    ";
                    $memberParams = array_merge([$this->userId], $communityIds);
                    $stmt = $this->db->prepare($memberQuery);
                    $stmt->execute($memberParams);
                    $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Index memberships by community_id
                    $membershipMap = [];
                    foreach ($memberships as $membership) {
                        $membershipMap[$membership['community_id']] = $membership;
                    }
                    
                    // Add membership info to communities
                    foreach ($communities as &$community) {
                        if (isset($membershipMap[$community['id']])) {
                            $community['membership'] = $membershipMap[$community['id']];
                        } else {
                            $community['membership'] = null;
                        }
                    }
                }
            }
            
            // Format response
            $response = [
                'communities' => $communities,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
            $this->sendSuccess($response);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch communities: ' . $e->getMessage());
        }
    }
    
    /**
     * Get single community
     * GET /api/v1/communities/{id}
     */
    protected function getCommunity($communityId) {
        try {
            $query = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT cm.user_id) as member_count,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM communities c
                LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.is_active = 1
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = ?
                GROUP BY c.id
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$communityId]);
            $community = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$community) {
                $this->sendError(404, 'Community not found');
            }
            
            // Check if user has access to private community
            if (!$community['is_public'] && !$this->checkCommunityAccess($communityId)) {
                $this->sendError(403, 'Access denied');
            }
            
            // Add membership info if authenticated
            if ($this->isAuthenticated) {
                $stmt = $this->db->prepare("
                    SELECT role, is_active 
                    FROM community_members 
                    WHERE user_id = ? AND community_id = ?
                ");
                $stmt->execute([$this->userId, $communityId]);
                $membership = $stmt->fetch(PDO::FETCH_ASSOC);
                $community['membership'] = $membership ?: null;
            }
            
            // Get recent members
            $stmt = $this->db->prepare("
                SELECT 
                    u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email, u.profile_photo,
                    cm.role, cm.joined_at
                FROM community_members cm
                JOIN users u ON cm.user_id = u.id
                WHERE cm.community_id = ? AND cm.is_active = 1
                ORDER BY cm.joined_at DESC
                LIMIT 10
            ");
            $stmt->execute([$communityId]);
            $community['recent_members'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendSuccess($community);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch community');
        }
    }
    
    /**
     * Create new community
     * POST /api/v1/communities
     */
    protected function createCommunity() {
        $this->requireAuth();
        
        // Validate required fields
        $this->validateRequired(['name']);
        
        $name = $this->getParam('name');
        $description = $this->getParam('description', '');
        $isPublic = (bool)$this->getParam('is_public', true);
        $requiresApproval = (bool)$this->getParam('requires_approval', false);
        
        try {
            // Check if name already exists
            $stmt = $this->db->prepare("SELECT id FROM communities WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                $this->sendError(400, 'Community name already exists');
            }
            
            // Create community
            $stmt = $this->db->prepare("
                INSERT INTO communities (name, description, is_public, requires_approval, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $isPublic ? 1 : 0, $requiresApproval ? 1 : 0, $this->userId]);
            $communityId = $this->db->lastInsertId();
            
            // Add creator as owner
            $stmt = $this->db->prepare("
                INSERT INTO community_members (community_id, user_id, role, is_active, joined_at)
                VALUES (?, ?, 'owner', 1, NOW())
            ");
            $stmt->execute([$communityId, $this->userId]);
            
            // Fetch and return the created community
            $this->getCommunity($communityId);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to create community');
        }
    }
    
    /**
     * Update community
     * PUT /api/v1/communities/{id}
     */
    protected function updateCommunity($communityId) {
        $this->requireAuth();
        
        // Check if user is admin/owner
        if (!$this->checkCommunityAccess($communityId, 'admin')) {
            $this->sendError(403, 'Access denied');
        }
        
        $updates = [];
        $params = [];
        
        // Build update query based on provided fields
        if (isset($this->input['name'])) {
            $updates[] = 'name = ?';
            $params[] = $this->input['name'];
        }
        if (isset($this->input['description'])) {
            $updates[] = 'description = ?';
            $params[] = $this->input['description'];
        }
        if (isset($this->input['is_public'])) {
            $updates[] = 'is_public = ?';
            $params[] = $this->input['is_public'] ? 1 : 0;
        }
        if (isset($this->input['requires_approval'])) {
            $updates[] = 'requires_approval = ?';
            $params[] = $this->input['requires_approval'] ? 1 : 0;
        }
        
        if (empty($updates)) {
            $this->sendError(400, 'No fields to update');
        }
        
        try {
            $params[] = $communityId;
            $query = "UPDATE communities SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            // Return updated community
            $this->getCommunity($communityId);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to update community');
        }
    }
    
}

// Initialize and handle request
$api = new CommunitiesAPI();
$api->handleRequest();