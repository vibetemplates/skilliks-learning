<?php
/**
 * Blog Post Like API Endpoint
 * 
 * Handles liking/unliking blog posts
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/BlogPost.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();

// Initialize response
$response = ['success' => false];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = $input['post_id'] ?? null;
    
    if (!$postId) {
        throw new Exception('Post ID is required');
    }
    
    // Initialize BlogPost class
    $blogPost = new BlogPost();
    
    // Check if post exists
    $post = $blogPost->getById($postId);
    if (!$post) {
        throw new Exception('Post not found');
    }
    
    // Toggle like
    $liked = $blogPost->toggleLike($postId, $currentUserId);
    
    // Get updated like count
    $updatedPost = $blogPost->getById($postId);
    
    $response['success'] = true;
    $response['liked'] = $liked;
    $response['like_count'] = $updatedPost['like_count'];
    $response['message'] = $liked ? 'Post liked' : 'Post unliked';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Blog like error: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);