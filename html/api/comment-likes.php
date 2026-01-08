<?php
/**
 * Comment Likes API
 * 
 * Handles likes for comments
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

// Handle POST requests (Toggle like)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $commentId = (int)($input['comment_id'] ?? 0);
    $action = $input['action'] ?? 'toggle'; // 'like', 'unlike', or 'toggle'
    
    if ($commentId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid comment ID.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Verify comment exists
        $stmt = $db->prepare("SELECT id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Comment not found.']);
            exit;
        }
        
        // Check if user already liked this comment
        $stmt = $db->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$commentId, $currentUserId]);
        $existingLike = $stmt->fetch();
        
        if ($action === 'toggle') {
            $action = $existingLike ? 'unlike' : 'like';
        }
        
        if ($action === 'like' && !$existingLike) {
            // Add like
            $stmt = $db->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
            $stmt->execute([$commentId, $currentUserId]);
            
            // Update like count
            $stmt = $db->prepare("UPDATE comments SET like_count = like_count + 1 WHERE id = ?");
            $stmt->execute([$commentId]);
            
            $liked = true;
            
        } else if ($action === 'unlike' && $existingLike) {
            // Remove like
            $stmt = $db->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->execute([$commentId, $currentUserId]);
            
            // Update like count
            $stmt = $db->prepare("UPDATE comments SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?");
            $stmt->execute([$commentId]);
            
            $liked = false;
        } else {
            // No change needed
            $liked = (bool)$existingLike;
        }
        
        // Get updated like count
        $stmt = $db->prepare("SELECT like_count FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $likeCount = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'like_count' => (int)$likeCount,
            'message' => $liked ? 'Comment liked.' : 'Comment unliked.'
        ]);
        
    } catch (PDOException $e) {
        error_log("Comment like error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while processing like.']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get like status for comments
    $commentIds = $_GET['comment_ids'] ?? '';
    
    if (empty($commentIds)) {
        echo json_encode(['success' => false, 'error' => 'No comment IDs provided.']);
        exit;
    }
    
    $commentIdArray = array_map('intval', explode(',', $commentIds));
    $commentIdArray = array_filter($commentIdArray, function($id) { return $id > 0; });
    
    if (empty($commentIdArray)) {
        echo json_encode(['success' => false, 'error' => 'Invalid comment IDs.']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Get user's likes for these comments
        $placeholders = str_repeat('?,', count($commentIdArray) - 1) . '?';
        $stmt = $db->prepare("
            SELECT comment_id 
            FROM comment_likes 
            WHERE comment_id IN ($placeholders) AND user_id = ?
        ");
        $params = array_merge($commentIdArray, [$currentUserId]);
        $stmt->execute($params);
        
        $likedComments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build response with like status for each comment
        $likeStatus = [];
        foreach ($commentIdArray as $commentId) {
            $likeStatus[$commentId] = in_array($commentId, $likedComments);
        }
        
        echo json_encode([
            'success' => true,
            'like_status' => $likeStatus
        ]);
        
    } catch (PDOException $e) {
        error_log("Comment like status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error while fetching like status.']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>