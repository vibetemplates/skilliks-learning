<?php
/**
 * BlogCategory Class
 * 
 * Handles blog category operations
 */

class BlogCategory {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new category
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO blog_categories (
                community_id, name, slug, description, color, icon, parent_id, display_order
            ) VALUES (
                :community_id, :name, :slug, :description, :color, :icon, :parent_id, :display_order
            )";
            
            $stmt = $this->db->prepare($sql);
            
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name'], $data['community_id']);
            }
            
            $stmt->execute([
                ':community_id' => $data['community_id'],
                ':name' => $data['name'],
                ':slug' => $data['slug'],
                ':description' => $data['description'] ?? null,
                ':color' => $data['color'] ?? '#6c757d',
                ':icon' => $data['icon'] ?? null,
                ':parent_id' => $data['parent_id'] ?? null,
                ':display_order' => $data['display_order'] ?? 0
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("BlogCategory create error: " . $e->getMessage());
            throw new Exception("Failed to create category");
        }
    }
    
    /**
     * Update a category
     */
    public function update($id, $data) {
        try {
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['name', 'slug', 'description', 'color', 'icon', 'parent_id', 'display_order', 'is_active'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                return true;
            }
            
            $sql = "UPDATE blog_categories SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("BlogCategory update error: " . $e->getMessage());
            throw new Exception("Failed to update category");
        }
    }
    
    /**
     * Get a category by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM blog_categories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("BlogCategory getById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all categories for a community
     */
    public function getByCommunity($communityId, $activeOnly = true) {
        try {
            $sql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM blog_post_categories pc 
                     JOIN blog_posts p ON pc.post_id = p.id 
                     WHERE pc.category_id = c.id AND p.status = 'published') as post_count
                    FROM blog_categories c
                    WHERE c.community_id = ?";
            
            if ($activeOnly) {
                $sql .= " AND c.is_active = 1";
            }
            
            $sql .= " ORDER BY c.display_order, c.name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$communityId]);
            
            $categories = $stmt->fetchAll();
            
            // Build hierarchy if there are parent-child relationships
            return $this->buildHierarchy($categories);
            
        } catch (PDOException $e) {
            error_log("BlogCategory getByCommunity error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a category
     */
    public function delete($id) {
        try {
            // Check if category has posts
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM blog_post_categories WHERE category_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                throw new Exception("Cannot delete category with associated posts");
            }
            
            $stmt = $this->db->prepare("DELETE FROM blog_categories WHERE id = ?");
            return $stmt->execute([$id]);
            
        } catch (PDOException $e) {
            error_log("BlogCategory delete error: " . $e->getMessage());
            throw new Exception("Failed to delete category");
        }
    }
    
    /**
     * Build category hierarchy
     */
    private function buildHierarchy($categories, $parentId = null) {
        $result = [];
        
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $category['children'] = $this->buildHierarchy($categories, $category['id']);
                $result[] = $category;
            }
        }
        
        return $result;
    }
    
    /**
     * Generate a unique slug
     */
    private function generateSlug($name, $communityId) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            $stmt = $this->db->prepare("SELECT 1 FROM blog_categories WHERE slug = ? AND community_id = ?");
            $stmt->execute([$slug, $communityId]);
            
            if (!$stmt->fetch()) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Create default categories for a community
     */
    public function createDefaults($communityId) {
        $defaults = [
            ['name' => 'Announcements', 'description' => 'Official community announcements', 'color' => '#dc3545', 'icon' => 'bi-megaphone', 'display_order' => 1],
            ['name' => 'Tutorials', 'description' => 'How-to guides and tutorials', 'color' => '#28a745', 'icon' => 'bi-book', 'display_order' => 2],
            ['name' => 'Discussion', 'description' => 'General discussion topics', 'color' => '#17a2b8', 'icon' => 'bi-chat-dots', 'display_order' => 3],
            ['name' => 'Resources', 'description' => 'Helpful resources and links', 'color' => '#ffc107', 'icon' => 'bi-link-45deg', 'display_order' => 4],
            ['name' => 'Projects', 'description' => 'Project updates and showcases', 'color' => '#6f42c1', 'icon' => 'bi-folder', 'display_order' => 5]
        ];
        
        foreach ($defaults as $category) {
            $category['community_id'] = $communityId;
            $this->create($category);
        }
    }
}