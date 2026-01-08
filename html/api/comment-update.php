<?php
/**
 * Comment Update API Endpoint
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
if (empty($_POST['comment_id']) || empty($_POST['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$commentId = (int)$_POST['comment_id'];
$content = trim($_POST['content']);
$userId = getCurrentUserId();

// Validate content length
if (strlen($content) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment is too long (max 5000 characters)']);
    exit;
}

// Update comment
$comment = new Comment();
$result = $comment->update($commentId, $userId, $content);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Failed to update comment or access denied']);
}
?>