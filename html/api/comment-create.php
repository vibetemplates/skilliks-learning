<?php
/**
 * Comment Create API Endpoint
 */

require_once '../includes/session.php';
require_once '../classes/Comment.php';

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate required fields
$requiredFields = ['commentable_type', 'commentable_id', 'content'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate commentable type
$allowedTypes = ['task', 'feature', 'project', 'lesson', 'blog_post'];
if (!in_array($_POST['commentable_type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid commentable type']);
    exit;
}

// Create comment
$comment = new Comment();
$data = [
    'commentable_type' => $_POST['commentable_type'],
    'commentable_id' => (int)$_POST['commentable_id'],
    'parent_comment_id' => !empty($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null,
    'user_id' => getCurrentUserId(),
    'content' => trim($_POST['content'])
];

// Validate content length
if (strlen($data['content']) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment is too long (max 5000 characters)']);
    exit;
}

$commentId = $comment->create($data);

if ($commentId) {
    // Get the created comment details
    $comments = $comment->getByEntity($data['commentable_type'], $data['commentable_id']);
    $createdComment = null;
    
    foreach ($comments as $c) {
        if ($c['id'] == $commentId) {
            $createdComment = $c;
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'comment_id' => $commentId,
        'comment' => $createdComment
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create comment']);
}
?>