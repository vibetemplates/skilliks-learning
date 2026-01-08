<?php

class ProjectCategory {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function findAll($activeOnly = true, $communityId = null) {
        $communityId = $communityId ?: getCurrentCommunityId();
        
        $sql = "SELECT * FROM project_categories WHERE community_id = :community_id";
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " ORDER BY display_order ASC, name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['community_id' => $communityId]);
        return $stmt->fetchAll();
    }
    
    public function findById($id) {
        $sql = "SELECT * FROM project_categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    public function findByName($name) {
        $sql = "SELECT * FROM project_categories WHERE name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => $name]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $sql = "INSERT INTO project_categories (community_id, name, description, thumbnail_url, skill_level, display_order, status) 
                VALUES (:community_id, :name, :description, :thumbnail_url, :skill_level, :display_order, :status)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'community_id' => $data['community_id'] ?? getCurrentCommunityId(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'skill_level' => $data['skill_level'] ?? 'all',
            'display_order' => $data['display_order'] ?? 0,
            'status' => $data['status'] ?? 'active'
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        foreach (['name', 'description', 'thumbnail_url', 'skill_level', 'display_order', 'status'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE project_categories SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        // Check if category has projects
        $checkSql = "SELECT COUNT(*) as count FROM projects WHERE project_category_id = :id";
        $stmt = $this->db->prepare($checkSql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception("Cannot delete category with existing projects");
        }
        
        $sql = "DELETE FROM project_categories WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    public function getProjectCount($categoryId, $communityId = null) {
        $communityId = $communityId ?: getCurrentCommunityId();
        
        $sql = "SELECT COUNT(*) as count FROM projects WHERE project_category_id = :category_id AND community_id = :community_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'category_id' => $categoryId,
            'community_id' => $communityId
        ]);
        $result = $stmt->fetch();
        return $result['count'];
    }
    
    public function getProjectsByCategory($categoryId, $status = null, $communityId = null) {
        $communityId = $communityId ?: getCurrentCommunityId();
        
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM project_members WHERE project_id = p.id) as member_count
                FROM projects p 
                WHERE p.project_category_id = :category_id AND p.community_id = :community_id";
        
        if ($status) {
            $sql .= " AND p.status = :status";
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $params = [
            'category_id' => $categoryId,
            'community_id' => $communityId
        ];
        if ($status) {
            $params['status'] = $status;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getAllByCommunity($communityId) {
        $sql = "SELECT * FROM project_categories WHERE community_id = :community_id AND status = 'active' ORDER BY display_order ASC, name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['community_id' => $communityId]);
        return $stmt->fetchAll();
    }
}