<?php
/**
 * Comments API
 * 
 * Handles comments for projects and other entities
 */

require_once dirname(dirname(__FILE__)) . '/includes/session.php';
require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(dirname(__FILE__)) . '/config/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$currentUserId = getCurrentUserId();

// Handle POST requests (Create comment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $commentableType = $input['commentable_type'] ?? ''; // 'project', 'feature', 'task'
    $commentableId = (int)($input['commentable_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    $parentCommentId = isset($input['parent_comment_id']) ? (int)$input['parent_comment_id'] : null;
    
    if (!in_array($commentableType, ['project', 'feature', 'task'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid commentable type.']);
        exit;
    }
    
    if ($commentableId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
        exit;
    }
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Comment content is required.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Verify the entity exists
        $tableName = $commentableType === 'project' ? 'projects' : ($commentableType === 'feature' ? 'features' : 'tasks');
        $stmt = $db->prepare("SELECT id FROM $tableName WHERE id = ?");
        $stmt->execute([$commentableId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => ucfirst($commentableType) . ' not found.']);
            exit;
        }
        
        // Create comment
        $stmt = $db->prepare("
            INSERT INTO comments (commentable_type, commentable_id, parent_comment_id, user_id, content) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$commentableType, $commentableId, $parentCommentId, $currentUserId, $content]);
        
        $commentId = $db->lastInsertId();
        
        // Fetch the created comment with user info
        $stmt = $db->prepare("
            SELECT c.*, u.first_name, u.last_name, u.email,
                   (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.id) as reply_count,
                   (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
                   (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$currentUserId, $commentId]);
        $comment = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'comment' => $comment,
            'message' => 'Comment added successfully.'
        ]);
        
    } catch (PDOException $e) {
        error_log("Comment creation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while creating comment.']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get comments for an entity
    $commentableType = $_GET['commentable_type'] ?? '';
    $commentableId = (int)($_GET['commentable_id'] ?? 0);
    $parentCommentId = isset($_GET['parent_comment_id']) ? (int)$_GET['parent_comment_id'] : null;
    
    if (!in_array($commentableType, ['project', 'feature', 'task']) || $commentableId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Get comments with user info
        if ($parentCommentId !== null) {
            // Get replies to a specific comment
            $stmt = $db->prepare("
                SELECT c.*, u.first_name, u.last_name, u.email,
                       (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.id) as reply_count,
                       (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
                       (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.commentable_type = ? AND c.commentable_id = ? AND c.parent_comment_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$currentUserId, $commentableType, $commentableId, $parentCommentId]);
        } else {
            // Get top-level comments
            $stmt = $db->prepare("
                SELECT c.*, u.first_name, u.last_name, u.email,
                       (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.id) as reply_count,
                       (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
                       (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.commentable_type = ? AND c.commentable_id = ? AND c.parent_comment_id IS NULL
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$currentUserId, $commentableType, $commentableId]);
        }
        
        $comments = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'comments' => $comments
        ]);
        
    } catch (PDOException $e) {
        error_log("Comment fetch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while fetching comments.']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update comment (only by owner)
    $input = json_decode(file_get_contents('php://input'), true);
    $commentId = (int)($input['id'] ?? 0);
    $content = trim($input['content'] ?? '');
    
    if ($commentId <= 0 || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Verify ownership
        $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if (!$comment) {
            echo json_encode(['success' => false, 'error' => 'Comment not found.']);
            exit;
        }
        
        if ($comment['user_id'] != $currentUserId) {
            echo json_encode(['success' => false, 'error' => 'You can only edit your own comments.']);
            exit;
        }
        
        // Update comment
        $stmt = $db->prepare("UPDATE comments SET content = ?, edited = 1 WHERE id = ?");
        $stmt->execute([$content, $commentId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment updated successfully.'
        ]);
        
    } catch (PDOException $e) {
        error_log("Comment update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while updating comment.']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Delete comment (only by owner)
    $input = json_decode(file_get_contents('php://input'), true);
    $commentId = (int)($input['id'] ?? 0);
    
    if ($commentId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid comment ID.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Verify ownership
        $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if (!$comment) {
            echo json_encode(['success' => false, 'error' => 'Comment not found.']);
            exit;
        }
        
        if ($comment['user_id'] != $currentUserId) {
            echo json_encode(['success' => false, 'error' => 'You can only delete your own comments.']);
            exit;
        }
        
        // Delete comment (cascades to child comments)
        $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ]);
        
    } catch (PDOException $e) {
        error_log("Comment delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while deleting comment.']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>