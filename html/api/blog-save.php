<?php
/**
 * Blog Post Save API Endpoint
 * 
 * Handles creating and updating blog posts
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/BlogPost.php';
require_once '../classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Initialize response
$response = ['success' => false];

try {
    // Initialize classes
    $blogPost = new BlogPost();
    $community = new Community();
    
    // Check if user is admin
    $userRole = $community->isMember($currentCommunityId, $currentUserId);
    if ($userRole !== 'admin') {
        throw new Exception('You do not have permission to create or edit posts');
    }
    
    // Get post data
    $postId = $_POST['post_id'] ?? null;
    $action = $_POST['action'] ?? 'save_draft';
    
    // Prepare post data
    $data = [
        'community_id' => $currentCommunityId,
        'author_id' => $currentUserId,
        'title' => $_POST['title'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'excerpt' => $_POST['excerpt'] ?? '',
        'content' => $_POST['content'] ?? '',
        'tags' => $_POST['tags'] ?? '',
        'meta_description' => $_POST['meta_description'] ?? '',
        'status' => $_POST['status'] ?? 'draft',
        'visibility' => $_POST['visibility'] ?? 'community',
        'allow_comments' => isset($_POST['allow_comments']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
        'video_url' => $_POST['video_url'] ?? '',
        'video_embed_code' => $_POST['video_embed_code'] ?? ''
    ];
    
    // Validate required fields
    if (empty($data['title']) || empty($data['content'])) {
        throw new Exception('Title and content are required');
    }
    
    // Handle featured image upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/blog/' . date('Y/m/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileInfo = pathinfo($_FILES['featured_image']['name']);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $fileInfo['filename']) . '.' . $fileInfo['extension'];
        $uploadPath = $uploadDir . $fileName;
        
        // Check file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($fileInfo['extension']), $allowedTypes)) {
            throw new Exception('Invalid image type. Allowed types: ' . implode(', ', $allowedTypes));
        }
        
        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $uploadPath)) {
            $data['featured_image'] = str_replace('../', '', $uploadPath);
        } else {
            throw new Exception('Failed to upload image');
        }
    } elseif (isset($_POST['existing_featured_image'])) {
        $data['featured_image'] = $_POST['existing_featured_image'];
    }
    
    // Create or update post
    if ($postId) {
        // Check if user can edit this post
        if (!$blogPost->canEdit($postId, $currentUserId)) {
            throw new Exception('You do not have permission to edit this post');
        }
        
        $success = $blogPost->update($postId, $data);
        if (!$success) {
            throw new Exception('Failed to update post');
        }
    } else {
        $postId = $blogPost->create($data);
        if (!$postId) {
            throw new Exception('Failed to create post');
        }
    }
    
    // Handle categories
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        $blogPost->setCategories($postId, $_POST['categories']);
    }
    
    $response['success'] = true;
    $response['post_id'] = $postId;
    $response['message'] = $postId ? 'Post updated successfully' : 'Post created successfully';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Blog save error: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);