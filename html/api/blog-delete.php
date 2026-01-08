<?php
/**
 * Blog Post Delete API Endpoint
 * 
 * Handles deleting blog posts
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
    
    // Check if user can delete this post
    if (!$blogPost->canEdit($postId, $currentUserId)) {
        throw new Exception('You do not have permission to delete this post');
    }
    
    // Delete the post
    $success = $blogPost->delete($postId);
    
    if (!$success) {
        throw new Exception('Failed to delete post');
    }
    
    $response['success'] = true;
    $response['message'] = 'Post deleted successfully';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Blog delete error: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);