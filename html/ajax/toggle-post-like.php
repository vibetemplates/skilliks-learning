<?php
/**
 * Toggle Post Like AJAX Handler
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/BlogPost.php';

// Require login
requireLogin();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$postId = $input['post_id'] ?? null;

if (!$postId) {
    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
    exit;
}

try {
    $blogPost = new BlogPost();
    $userId = getCurrentUserId();
    
    // Toggle the like
    $liked = $blogPost->toggleLike($postId, $userId);
    
    // Get updated like count
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as like_count FROM blog_post_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $result = $stmt->fetch();
    $likeCount = $result['like_count'];
    
    // Update the cached like count in blog_posts table
    $stmt = $db->prepare("UPDATE blog_posts SET like_count = ? WHERE id = ?");
    $stmt->execute([$likeCount, $postId]);
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => $likeCount
    ]);
    
} catch (Exception $e) {
    error_log("Toggle like error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to toggle like']);
}