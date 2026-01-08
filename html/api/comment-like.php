<?php
/**
 * Comment Like API Endpoint
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

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['comment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing comment ID']);
    exit;
}

$commentId = (int)$data['comment_id'];
$userId = getCurrentUserId();

// Toggle like
$comment = new Comment();
$result = $comment->toggleLike($commentId, $userId);

if ($result) {
    echo json_encode([
        'success' => true,
        'action' => $result['action'],
        'count' => $result['count']
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to toggle like']);
}
?>