<?php
/**
 * Community Model Class
 * 
 * Handles all community-related operations including membership management
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';

class Community {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new community
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO communities (name, slug, description, logo_url, banner_url, settings, 
                    is_active, is_public, requires_approval, monthly_price, display_member_count, created_by) 
                    VALUES (:name, :slug, :description, :logo_url, :banner_url, :settings,
                    :is_active, :is_public, :requires_approval, :monthly_price, :display_member_count, :created_by)";
            
            $stmt = $this->db->prepare($sql);
            
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }
            
            // Set defaults
            $data['settings'] = isset($data['settings']) ? json_encode($data['settings']) : null;
            $data['is_active'] = isset($data['is_active']) ? $data['is_active'] : true;
            $data['is_public'] = isset($data['is_public']) ? $data['is_public'] : false;
            $data['requires_approval'] = isset($data['requires_approval']) ? $data['requires_approval'] : true;
            
            $stmt->execute([
                ':name' => $data['name'],
                ':slug' => $data['slug'],
                ':description' => $data['description'] ?? null,
                ':logo_url' => $data['logo_url'] ?? null,
                ':banner_url' => $data['banner_url'] ?? null,
                ':settings' => $data['settings'],
                ':is_active' => $data['is_active'],
                ':is_public' => $data['is_public'],
                ':requires_approval' => $data['requires_approval'],
                ':monthly_price' => $data['monthly_price'] ?? null,
                ':display_member_count' => $data['display_member_count'] ?? null,
                ':created_by' => $data['created_by']
            ]);
            
            $communityId = $this->db->lastInsertId();
            
            // Add creator as owner
            $this->addMember($communityId, $data['created_by'], 'owner');
            
            return $communityId;
            
        } catch (PDOException $e) {
            error_log("Community creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get community by ID
     */
    public function getById($id) {
        $sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name, u.email as creator_email,
                (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND is_active = 1) as member_count
                FROM communities c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $community = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($community && $community['settings']) {
                $community['settings'] = json_decode($community['settings'], true);
        }
        
        return $community;
    }
    
    /**
     * Get community by slug
     */
    public function getBySlug($slug) {
        $sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name, u.email as creator_email,
                (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND is_active = 1) as member_count
                FROM communities c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.slug = :slug AND c.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        
        $community = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($community && $community['settings']) {
            $community['settings'] = json_decode($community['settings'], true);
        }
        
        return $community;
    }
    
    /**
     * Update community
     */
    public function update($id, $data) {
        try {
            $updates = [];
            $params = [':id' => $id];
            
            $allowedFields = ['name', 'slug', 'description', 'logo_url', 'banner_url', 
                             'settings', 'is_active', 'is_public', 'requires_approval', 'monthly_price', 'display_member_count'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'settings') {
                        $data[$field] = json_encode($data[$field]);
                    }
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $sql = "UPDATE communities SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Community update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete community (soft delete)
     */
    public function delete($id) {
        try {
            // Check if community has active projects
            $sql = "SELECT COUNT(*) FROM projects WHERE community_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $activeProjects = $stmt->fetchColumn();
            
            if ($activeProjects > 0) {
                return ['success' => false, 'message' => 'Cannot delete community with active projects'];
            }
            
            // Check if community has active courses
            $sql = "SELECT COUNT(*) FROM courses WHERE community_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $activeCourses = $stmt->fetchColumn();
            
            if ($activeCourses > 0) {
                return ['success' => false, 'message' => 'Cannot delete community with active courses'];
            }
            
            // Soft delete
            $result = $this->update($id, ['is_active' => false]);
            
            if ($result) {
                logActivity('community_deleted', 'community', $id, "Deleted community");
            }
            
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Error deleting community: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Get all communities (with optional filters)
     */
    public function getAll($filters = []) {
        $sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name, u.email as creator_email,
                (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND is_active = 1) as member_count,
                (SELECT COUNT(*) FROM projects WHERE community_id = c.id) as project_count,
                (SELECT COUNT(*) FROM courses WHERE community_id = c.id) as course_count
                FROM communities c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['is_active'])) {
            $sql .= " AND c.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }
        
        if (isset($filters['is_public'])) {
            $sql .= " AND c.is_public = :is_public";
            $params[':is_public'] = $filters['is_public'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (c.name LIKE :search OR c.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY c.id ASC";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
            if (isset($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get communities for a user
     */
    public function getUserCommunities($userId) {
        $sql = "SELECT c.*, cm.role, cm.joined_at,
                (SELECT COUNT(*) FROM community_members WHERE community_id = c.id AND is_active = 1) as member_count
                FROM communities c
                INNER JOIN community_members cm ON c.id = cm.community_id
                WHERE cm.user_id = :user_id AND cm.is_active = 1 AND c.is_active = 1
                ORDER BY cm.joined_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Add member to community
     */
    public function addMember($communityId, $userId, $role = 'member', $invitedBy = null) {
        try {
            $sql = "INSERT INTO community_members (community_id, user_id, role, invited_by, is_active) 
                    VALUES (:community_id, :user_id, :role, :invited_by, 1)
                    ON DUPLICATE KEY UPDATE role = :role_update, is_active = 1";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':community_id' => $communityId,
                ':user_id' => $userId,
                ':role' => $role,
                ':role_update' => $role,
                ':invited_by' => $invitedBy
            ]);
            
        } catch (PDOException $e) {
            error_log("Add member error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove member from community
     */
    public function removeMember($communityId, $userId) {
        $sql = "UPDATE community_members SET is_active = 0 
                WHERE community_id = :community_id AND user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $userId
        ]);
    }
    
    /**
     * Update member role
     */
    public function updateMemberRole($communityId, $userId, $newRole) {
        $sql = "UPDATE community_members SET role = :role 
                WHERE community_id = :community_id AND user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':role' => $newRole,
            ':community_id' => $communityId,
            ':user_id' => $userId
        ]);
    }
    
    /**
     * Get community members
     */
    public function getMembers($communityId, $filters = []) {
        $sql = "SELECT cm.*, CONCAT(u.first_name, ' ', u.last_name) as name, u.email, u.profile_photo as avatar_url,
                u.github_username, u.student_id
                FROM community_members cm
                INNER JOIN users u ON cm.user_id = u.id
                WHERE cm.community_id = :community_id AND cm.is_active = 1";
        
        $params = [':community_id' => $communityId];
        
        if (isset($filters['role'])) {
            $sql .= " AND cm.role = :role";
            $params[':role'] = $filters['role'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY cm.joined_at DESC";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            if (isset($filters['offset'])) {
                $sql .= " OFFSET :offset";
            }
        }
        
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if (isset($filters['limit'])) {
            $stmt->bindValue(':limit', $filters['limit'], PDO::PARAM_INT);
            if (isset($filters['offset'])) {
                $stmt->bindValue(':offset', $filters['offset'], PDO::PARAM_INT);
            }
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Check if user is member of community
     */
    public function isMember($communityId, $userId) {
        $sql = "SELECT role FROM community_members 
                WHERE community_id = :community_id AND user_id = :user_id AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $userId
        ]);
        
        $result = $stmt->fetch();
        return $result ? $result['role'] : false;
    }
    
    /**
     * Check if user can manage community
     */
    public function canManage($communityId, $userId) {
        $role = $this->isMember($communityId, $userId);
        return in_array($role, ['admin', 'owner', 'moderator']);
    }
    
    /**
     * Generate unique slug from name
     */
    private function generateSlug($name) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        
        // Check if slug exists and make unique if necessary
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     */
    private function slugExists($slug) {
        $sql = "SELECT COUNT(*) FROM communities WHERE slug = :slug";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get community statistics
     */
    public function getStatistics($communityId) {
        $stats = [];
        
        // Member count
        $sql = "SELECT COUNT(*) FROM community_members WHERE community_id = :id AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $communityId]);
        $stats['members'] = $stmt->fetchColumn();
        
        // Project count
        $sql = "SELECT COUNT(*) FROM projects WHERE community_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $communityId]);
        $stats['projects'] = $stmt->fetchColumn();
        
        // Course count
        $sql = "SELECT COUNT(*) FROM courses WHERE community_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $communityId]);
        $stats['courses'] = $stmt->fetchColumn();
        
        // Task count
        $sql = "SELECT COUNT(*) FROM tasks WHERE community_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $communityId]);
        $stats['tasks'] = $stmt->fetchColumn();
        
        // Online members (active in last 5 minutes)
        $sql = "SELECT COUNT(DISTINCT cm.user_id) 
                FROM community_members cm
                INNER JOIN users u ON cm.user_id = u.id
                WHERE cm.community_id = :community_id 
                AND cm.is_active = 1 
                AND u.last_login > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':community_id' => $communityId]);
        $stats['active_members'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Get user's role in community
     */
    public function getUserRole($communityId, $userId) {
        try {
            $sql = "SELECT role FROM community_members 
                    WHERE community_id = :community_id 
                    AND user_id = :user_id 
                    AND is_active = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'community_id' => $communityId,
                'user_id' => $userId
            ]);
            
            $result = $stmt->fetch();
            return $result ? $result['role'] : null;
        } catch (PDOException $e) {
            error_log("Error getting user role: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if user has permission in community
     */
    public function hasPermission($communityId, $userId, $permission) {
        try {
            // First check if user is global admin
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM global_admins WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            if ($stmt->fetchColumn() > 0) {
                return true; // Global admins have all permissions
            }
            
            // Get user's role in the community
            $role = $this->getUserRole($communityId, $userId);
            if (!$role) {
                return false; // Not a member
            }
            
            // Check permission for role
            $sql = "SELECT COUNT(*) FROM community_permissions 
                    WHERE community_id = :community_id 
                    AND role = :role 
                    AND permission = :permission 
                    AND granted = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'community_id' => $communityId,
                'role' => $role,
                'permission' => $permission
            ]);
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error checking permission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all permissions for a role in a community
     */
    public function getRolePermissions($communityId, $role) {
        try {
            $sql = "SELECT permission FROM community_permissions 
                    WHERE community_id = :community_id 
                    AND role = :role 
                    AND granted = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'community_id' => $communityId,
                'role' => $role
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error fetching permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user can perform admin actions in community
     */
    public function isAdmin($communityId, $userId) {
        $role = $this->getUserRole($communityId, $userId);
        return in_array($role, ['admin', 'owner']);
    }
    
    /**
     * Check if user can moderate in community
     */
    public function isModerator($communityId, $userId) {
        $role = $this->getUserRole($communityId, $userId);
        return in_array($role, ['moderator', 'admin', 'owner']);
    }
}
?>