<?php
/**
 * Comment Model Class
 * 
 * Handles all comment-related operations for the universal comment system
 */

require_once dirname(__DIR__) . '/config/database.php';

class Comment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new comment
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO comments (commentable_type, commentable_id, parent_comment_id, 
                    user_id, content, mentions) 
                    VALUES (:commentable_type, :commentable_id, :parent_comment_id, 
                    :user_id, :content, :mentions)";
            
            $stmt = $this->db->prepare($sql);
            
            // Extract mentions from content
            $mentions = $this->extractMentions($data['content']);
            
            $params = [
                ':commentable_type' => $data['commentable_type'],
                ':commentable_id' => $data['commentable_id'],
                ':parent_comment_id' => $data['parent_comment_id'] ?? null,
                ':user_id' => $data['user_id'],
                ':content' => $data['content'],
                ':mentions' => json_encode($mentions)
            ];
            
            $stmt->execute($params);
            $commentId = $this->db->lastInsertId();
            
            // Create notifications for mentions
            if (!empty($mentions)) {
                $this->createMentionNotifications($commentId, $mentions, $data['user_id']);
            }
            
            // Log activity
            $this->logActivity($data['user_id'], 'comment_created', $commentId, $data);
            
            return $commentId;
        } catch (PDOException $e) {
            error_log("Error creating comment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get comments for a specific entity
     */
    public function getByEntity($commentableType, $commentableId, $parentId = null) {
        try {
            $sql = "SELECT c.*, 
                    u.first_name, u.last_name, u.email, u.profile_photo,
                    (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.id) as reply_count,
                    GROUP_CONCAT(DISTINCT cl.user_id) as liked_by_users
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    LEFT JOIN comment_likes cl ON c.id = cl.comment_id
                    WHERE c.commentable_type = :type 
                    AND c.commentable_id = :id ";
            
            if ($parentId === null) {
                $sql .= "AND c.parent_comment_id IS NULL ";
            } else {
                $sql .= "AND c.parent_comment_id = :parent_id ";
            }
            
            $sql .= "GROUP BY c.id 
                     ORDER BY c.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $params = [
                ':type' => $commentableType,
                ':id' => $commentableId
            ];
            
            if ($parentId !== null) {
                $params[':parent_id'] = $parentId;
            }
            
            $stmt->execute($params);
            $comments = $stmt->fetchAll();
            
            // Process comments to add additional data
            foreach ($comments as &$comment) {
                $comment['author_name'] = $comment['first_name'] . ' ' . $comment['last_name'];
                $comment['author_initials'] = strtoupper(substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1));
                $comment['mentions'] = $comment['mentions'] ? json_decode($comment['mentions'], true) : [];
                $comment['reactions'] = $comment['reactions'] ? json_decode($comment['reactions'], true) : [];
                $comment['liked_by_users'] = $comment['liked_by_users'] ? explode(',', $comment['liked_by_users']) : [];
                $comment['is_liked'] = in_array($_SESSION['user_id'] ?? 0, $comment['liked_by_users']);
                $comment['like_count'] = count($comment['liked_by_users']);
                
                // Get replies if this is a parent comment
                if ($parentId === null && $comment['reply_count'] > 0) {
                    $comment['replies'] = $this->getByEntity($commentableType, $commentableId, $comment['id']);
                }
            }
            
            return $comments;
        } catch (PDOException $e) {
            error_log("Error fetching comments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update a comment
     */
    public function update($commentId, $userId, $content) {
        try {
            // Check if user owns the comment
            if (!$this->isOwner($commentId, $userId)) {
                return false;
            }
            
            $sql = "UPDATE comments 
                    SET content = :content, 
                        mentions = :mentions, 
                        edited = 1,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND user_id = :user_id";
            
            $mentions = $this->extractMentions($content);
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':content' => $content,
                ':mentions' => json_encode($mentions),
                ':id' => $commentId,
                ':user_id' => $userId
            ]);
            
            if ($result && !empty($mentions)) {
                $this->createMentionNotifications($commentId, $mentions, $userId);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error updating comment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a comment (soft delete)
     */
    public function delete($commentId, $userId) {
        try {
            // Check if user owns the comment or is admin
            if (!$this->isOwner($commentId, $userId) && !$this->isAdmin($userId)) {
                return false;
            }
            
            // Soft delete by updating content
            $sql = "UPDATE comments 
                    SET content = '[Comment deleted]', 
                        edited = 1,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $commentId]);
        } catch (PDOException $e) {
            error_log("Error deleting comment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle like on a comment
     */
    public function toggleLike($commentId, $userId) {
        try {
            // Check if already liked
            $sql = "SELECT id FROM comment_likes WHERE comment_id = :comment_id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':comment_id' => $commentId, ':user_id' => $userId]);
            
            if ($stmt->fetch()) {
                // Unlike
                $sql = "DELETE FROM comment_likes WHERE comment_id = :comment_id AND user_id = :user_id";
                $action = 'unliked';
            } else {
                // Like
                $sql = "INSERT INTO comment_likes (comment_id, user_id) VALUES (:comment_id, :user_id)";
                $action = 'liked';
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':comment_id' => $commentId, ':user_id' => $userId]);
            
            // Get updated like count
            $sql = "SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = :comment_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':comment_id' => $commentId]);
            $count = $stmt->fetch()['count'];
            
            return ['action' => $action, 'count' => $count];
        } catch (PDOException $e) {
            error_log("Error toggling like: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a reaction to a comment
     */
    public function addReaction($commentId, $userId, $reaction) {
        try {
            // Get current reactions
            $sql = "SELECT reactions FROM comments WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $commentId]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                return false;
            }
            
            $reactions = $comment['reactions'] ? json_decode($comment['reactions'], true) : [];
            
            // Add or update user's reaction
            $reactions[$userId] = $reaction;
            
            // Update reactions
            $sql = "UPDATE comments SET reactions = :reactions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':reactions' => json_encode($reactions),
                ':id' => $commentId
            ]);
        } catch (PDOException $e) {
            error_log("Error adding reaction: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Extract @mentions from content
     */
    private function extractMentions($content) {
        $mentions = [];
        
        // Match @username patterns
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        
        if (!empty($matches[1])) {
            // Look up user IDs for mentioned usernames
            $placeholders = str_repeat('?,', count($matches[1]) - 1) . '?';
            $sql = "SELECT id, CONCAT(first_name, last_name) as username 
                    FROM users 
                    WHERE REPLACE(LOWER(CONCAT(first_name, last_name)), ' ', '') IN ($placeholders)";
            
            $stmt = $this->db->prepare($sql);
            $usernames = array_map(function($name) {
                return strtolower(str_replace(' ', '', $name));
            }, $matches[1]);
            
            $stmt->execute($usernames);
            $users = $stmt->fetchAll();
            
            foreach ($users as $user) {
                $mentions[] = $user['id'];
            }
        }
        
        return array_unique($mentions);
    }
    
    /**
     * Create notifications for mentioned users
     */
    private function createMentionNotifications($commentId, $mentionedUserIds, $mentioningUserId) {
        try {
            // Get comment details
            $sql = "SELECT c.*, u.first_name, u.last_name 
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $commentId]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                return;
            }
            
            $mentionerName = $comment['first_name'] . ' ' . $comment['last_name'];
            
            foreach ($mentionedUserIds as $userId) {
                // Don't notify the user who created the comment
                if ($userId == $mentioningUserId) {
                    continue;
                }
                
                $sql = "INSERT INTO notifications (user_id, type, title, message, data) 
                        VALUES (:user_id, :type, :title, :message, :data)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':user_id' => $userId,
                    ':type' => 'mention',
                    ':title' => 'You were mentioned',
                    ':message' => "$mentionerName mentioned you in a comment",
                    ':data' => json_encode([
                        'comment_id' => $commentId,
                        'commentable_type' => $comment['commentable_type'],
                        'commentable_id' => $comment['commentable_id']
                    ])
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error creating mention notifications: " . $e->getMessage());
        }
    }
    
    /**
     * Check if user owns a comment
     */
    private function isOwner($commentId, $userId) {
        $sql = "SELECT id FROM comments WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $commentId, ':user_id' => $userId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if user is admin
     */
    private function isAdmin($userId) {
        $sql = "SELECT role FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * Log activity
     */
    private function logActivity($userId, $type, $commentId, $data) {
        try {
            $sql = "INSERT INTO activities (user_id, type, entity_type, entity_id, description, data) 
                    VALUES (:user_id, :type, :entity_type, :entity_id, :description, :data)";
            
            $description = "Added a comment on " . $data['commentable_type'] . " #" . $data['commentable_id'];
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':type' => $type,
                ':entity_type' => 'comment',
                ':entity_id' => $commentId,
                ':description' => $description,
                ':data' => json_encode($data)
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
    
    /**
     * Get comment count for an entity
     */
    public function getCommentCount($commentableType, $commentableId) {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM comments 
                    WHERE commentable_type = :type 
                    AND commentable_id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':type' => $commentableType,
                ':id' => $commentableId
            ]);
            
            return $stmt->fetch()['count'];
        } catch (PDOException $e) {
            error_log("Error getting comment count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get recent comments for activity feed
     */
    public function getRecentComments($projectId = null, $limit = 10) {
        try {
            $sql = "SELECT c.*, 
                    u.first_name, u.last_name, u.email, u.profile_photo,
                    CASE 
                        WHEN c.commentable_type = 'task' THEN t.title
                        WHEN c.commentable_type = 'feature' THEN f.title
                        ELSE 'Unknown'
                    END as item_title,
                    CASE 
                        WHEN c.commentable_type = 'task' THEN t.project_id
                        WHEN c.commentable_type = 'feature' THEN f.project_id
                        ELSE NULL
                    END as project_id
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    LEFT JOIN tasks t ON c.commentable_type = 'task' AND c.commentable_id = t.id
                    LEFT JOIN features f ON c.commentable_type = 'feature' AND c.commentable_id = f.id
                    WHERE 1=1 ";
            
            if ($projectId) {
                $sql .= "AND ((c.commentable_type = 'task' AND t.project_id = :project_id) 
                         OR (c.commentable_type = 'feature' AND f.project_id = :project_id)) ";
            }
            
            $sql .= "ORDER BY c.created_at DESC 
                     LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            if ($projectId) {
                $stmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $comments = $stmt->fetchAll();
            
            foreach ($comments as &$comment) {
                $comment['author_name'] = $comment['first_name'] . ' ' . $comment['last_name'];
                $comment['author_initials'] = strtoupper(substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1));
            }
            
            return $comments;
        } catch (PDOException $e) {
            error_log("Error fetching recent comments: " . $e->getMessage());
            return [];
        }
    }
}
?>