<?php
/**
 * Comment List API Endpoint
 */

require_once '../includes/session.php';
require_once '../classes/Comment.php';

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate required parameters
if (empty($_GET['type']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$commentableType = $_GET['type'];
$commentableId = (int)$_GET['id'];

// Validate commentable type
$allowedTypes = ['task', 'feature', 'project', 'lesson'];
if (!in_array($commentableType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid commentable type']);
    exit;
}

// Get comments
$comment = new Comment();
$comments = $comment->getByEntity($commentableType, $commentableId);
$count = $comment->getCommentCount($commentableType, $commentableId);

echo json_encode([
    'success' => true,
    'comments' => $comments,
    'count' => $count
]);
?>