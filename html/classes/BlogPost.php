<?php
/**
 * BlogPost Class
 * 
 * Handles all blog post related operations
 */

class BlogPost {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new blog post
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO blog_posts (
                community_id, author_id, title, slug, excerpt, content,
                featured_image, video_url, video_embed_code, status, visibility,
                allow_comments, tags, meta_description, published_at
            ) VALUES (
                :community_id, :author_id, :title, :slug, :excerpt, :content,
                :featured_image, :video_url, :video_embed_code, :status, :visibility,
                :allow_comments, :tags, :meta_description, :published_at
            )";
            
            $stmt = $this->db->prepare($sql);
            
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['title'], $data['community_id']);
            }
            
            // Set published_at if publishing
            if ($data['status'] === 'published' && empty($data['published_at'])) {
                $data['published_at'] = date('Y-m-d H:i:s');
            }
            
            $stmt->execute([
                ':community_id' => $data['community_id'],
                ':author_id' => $data['author_id'],
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':excerpt' => $data['excerpt'] ?? null,
                ':content' => $data['content'],
                ':featured_image' => $data['featured_image'] ?? null,
                ':video_url' => $data['video_url'] ?? null,
                ':video_embed_code' => $data['video_embed_code'] ?? null,
                ':status' => $data['status'] ?? 'draft',
                ':visibility' => $data['visibility'] ?? 'community',
                ':allow_comments' => $data['allow_comments'] ?? 1,
                ':tags' => $data['tags'] ?? null,
                ':meta_description' => $data['meta_description'] ?? null,
                ':published_at' => $data['published_at'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("BlogPost create error: " . $e->getMessage());
            throw new Exception("Failed to create blog post");
        }
    }
    
    /**
     * Update an existing blog post
     */
    public function update($id, $data) {
        try {
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = [
                'title', 'slug', 'excerpt', 'content', 'featured_image',
                'video_url', 'video_embed_code', 'status', 'visibility',
                'allow_comments', 'tags', 'meta_description', 'is_featured', 'is_pinned'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Handle published_at
            if (isset($data['status']) && $data['status'] === 'published') {
                $stmt = $this->db->prepare("SELECT published_at FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                $current = $stmt->fetch();
                
                if (empty($current['published_at'])) {
                    $updateFields[] = "published_at = :published_at";
                    $params[':published_at'] = date('Y-m-d H:i:s');
                }
            }
            
            if (empty($updateFields)) {
                return true;
            }
            
            $sql = "UPDATE blog_posts SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("BlogPost update error: " . $e->getMessage());
            throw new Exception("Failed to update blog post");
        }
    }
    
    /**
     * Get a single blog post by ID
     */
    public function getById($id, $incrementView = false) {
        try {
            $sql = "SELECT bp.*, CONCAT(u.first_name, ' ', u.last_name) as author_name, u.profile_photo as author_avatar,
                    c.name as community_name, c.slug as community_slug
                    FROM blog_posts bp
                    JOIN users u ON bp.author_id = u.id
                    JOIN communities c ON bp.community_id = c.id
                    WHERE bp.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            
            if ($post && $incrementView) {
                $this->incrementViewCount($id);
            }
            
            return $post;
        } catch (PDOException $e) {
            error_log("BlogPost getById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get a blog post by slug and community
     */
    public function getBySlug($slug, $communityId, $incrementView = false) {
        try {
            $sql = "SELECT bp.*, CONCAT(u.first_name, ' ', u.last_name) as author_name, u.profile_photo as author_avatar,
                    c.name as community_name, c.slug as community_slug
                    FROM blog_posts bp
                    JOIN users u ON bp.author_id = u.id
                    JOIN communities c ON bp.community_id = c.id
                    WHERE bp.slug = ? AND bp.community_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$slug, $communityId]);
            $post = $stmt->fetch();
            
            if ($post && $incrementView) {
                $this->incrementViewCount($post['id']);
            }
            
            return $post;
        } catch (PDOException $e) {
            error_log("BlogPost getBySlug error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get blog posts with filters
     */
    public function getList($filters = []) {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['community_id'])) {
                $where[] = "bp.community_id = :community_id";
                $params[':community_id'] = $filters['community_id'];
            }
            
            if (!empty($filters['author_id'])) {
                $where[] = "bp.author_id = :author_id";
                $params[':author_id'] = $filters['author_id'];
            }
            
            if (!empty($filters['status'])) {
                $where[] = "bp.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['category_id'])) {
                $where[] = "EXISTS (SELECT 1 FROM blog_post_categories bpc WHERE bpc.post_id = bp.id AND bpc.category_id = :category_id)";
                $params[':category_id'] = $filters['category_id'];
            }
            
            if (!empty($filters['no_category'])) {
                $where[] = "NOT EXISTS (SELECT 1 FROM blog_post_categories bpc WHERE bpc.post_id = bp.id)";
            }
            
            if (!empty($filters['search'])) {
                $where[] = "(bp.title LIKE :search OR bp.content LIKE :search OR bp.tags LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            // Default to published posts unless specifically requested
            if (empty($filters['include_drafts'])) {
                $where[] = "bp.status = 'published'";
            }
            
            $orderBy = "bp.is_pinned DESC, ";
            if (!empty($filters['order_by'])) {
                switch ($filters['order_by']) {
                    case 'popular':
                        $orderBy .= "bp.view_count DESC";
                        break;
                    case 'trending':
                        $orderBy .= "bp.like_count DESC, bp.comment_count DESC";
                        break;
                    case 'oldest':
                        $orderBy .= "bp.published_at ASC";
                        break;
                    default:
                        $orderBy .= "bp.published_at DESC";
                }
            } else {
                $orderBy .= "bp.published_at DESC";
            }
            
            $limit = !empty($filters['limit']) ? (int)$filters['limit'] : 20;
            $offset = !empty($filters['offset']) ? (int)$filters['offset'] : 0;
            
            $sql = "SELECT bp.*, CONCAT(u.first_name, ' ', u.last_name) as author_name, u.profile_photo as author_avatar,
                    c.name as community_name, c.slug as community_slug,
                    COUNT(DISTINCT bpl.id) as actual_like_count,
                    COUNT(DISTINCT com.id) as actual_comment_count,
                    MAX(com.created_at) as last_comment_at,
                    (SELECT CONCAT(u2.first_name, ' ', u2.last_name) 
                     FROM comments com2 
                     JOIN users u2 ON com2.user_id = u2.id 
                     WHERE com2.commentable_id = bp.id 
                     AND com2.commentable_type = 'blog_post' 
                     AND com2.status = 'active'
                     ORDER BY com2.created_at DESC 
                     LIMIT 1) as last_commenter_name
                    FROM blog_posts bp
                    JOIN users u ON bp.author_id = u.id
                    JOIN communities c ON bp.community_id = c.id
                    LEFT JOIN blog_post_likes bpl ON bp.id = bpl.post_id
                    LEFT JOIN comments com ON bp.id = com.commentable_id AND com.commentable_type = 'blog_post' AND com.status = 'active'
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY bp.id
                    ORDER BY $orderBy
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("BlogPost getList error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get featured posts
     */
    public function getFeatured($communityId, $limit = 5) {
        return $this->getList([
            'community_id' => $communityId,
            'is_featured' => true,
            'limit' => $limit
        ]);
    }
    
    /**
     * Delete a blog post
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("BlogPost delete error: " . $e->getMessage());
            throw new Exception("Failed to delete blog post");
        }
    }
    
    /**
     * Check if user can edit a blog post
     */
    public function canEdit($postId, $userId) {
        try {
            // Check if user is the author
            $stmt = $this->db->prepare("SELECT author_id, community_id FROM blog_posts WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();
            
            if (!$post) {
                return false;
            }
            
            if ($post['author_id'] == $userId) {
                return true;
            }
            
            // Check if user is admin of the community
            $stmt = $this->db->prepare("
                SELECT role FROM community_members 
                WHERE community_id = ? AND user_id = ? AND status = 'approved'
            ");
            $stmt->execute([$post['community_id'], $userId]);
            $member = $stmt->fetch();
            
            return $member && $member['role'] === 'admin';
            
        } catch (PDOException $e) {
            error_log("BlogPost canEdit error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle like on a blog post
     */
    public function toggleLike($postId, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Check if already liked
            $stmt = $this->db->prepare("SELECT id FROM blog_post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            
            if ($stmt->fetch()) {
                // Unlike
                $stmt = $this->db->prepare("DELETE FROM blog_post_likes WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$postId, $userId]);
                $liked = false;
            } else {
                // Like
                $stmt = $this->db->prepare("INSERT INTO blog_post_likes (post_id, user_id) VALUES (?, ?)");
                $stmt->execute([$postId, $userId]);
                $liked = true;
            }
            
            $this->db->commit();
            return $liked;
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("BlogPost toggleLike error: " . $e->getMessage());
            throw new Exception("Failed to toggle like");
        }
    }
    
    /**
     * Check if user has liked a post
     */
    public function hasLiked($postId, $userId) {
        try {
            $stmt = $this->db->prepare("SELECT 1 FROM blog_post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            return $stmt->fetch() ? true : false;
        } catch (PDOException $e) {
            error_log("BlogPost hasLiked error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment view count
     */
    private function incrementViewCount($postId, $userId = null) {
        try {
            // Update view count
            $stmt = $this->db->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?");
            $stmt->execute([$postId]);
            
            // Log the view
            $stmt = $this->db->prepare("
                INSERT INTO blog_post_views (post_id, user_id, ip_address, user_agent) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $postId,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("BlogPost incrementViewCount error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate a unique slug
     */
    private function generateSlug($title, $communityId) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            $stmt = $this->db->prepare("SELECT 1 FROM blog_posts WHERE slug = ? AND community_id = ?");
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
     * Get post categories
     */
    public function getCategories($postId) {
        try {
            $sql = "SELECT c.* FROM blog_categories c
                    JOIN blog_post_categories pc ON c.id = pc.category_id
                    WHERE pc.post_id = ?
                    ORDER BY c.display_order";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$postId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("BlogPost getCategories error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set post categories
     */
    public function setCategories($postId, $categoryIds) {
        try {
            $this->db->beginTransaction();
            
            // Remove existing categories
            $stmt = $this->db->prepare("DELETE FROM blog_post_categories WHERE post_id = ?");
            $stmt->execute([$postId]);
            
            // Add new categories
            if (!empty($categoryIds)) {
                $stmt = $this->db->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
                foreach ($categoryIds as $categoryId) {
                    $stmt->execute([$postId, $categoryId]);
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("BlogPost setCategories error: " . $e->getMessage());
            throw new Exception("Failed to set categories");
        }
    }
}