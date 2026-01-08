<?php
/**
 * API Endpoint: Block or unblock a user
 * Method: POST
 * Parameters: user_id, action (block/unblock)
 * Returns: success status
 */

require_once '../../includes/session.php';
require_once '../../includes/messaging_functions.php';

// Check authentication
requireLogin();

// Only POST method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$blocked_user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$action = isset($input['action']) ? $input['action'] : 'block';

if (!$blocked_user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

if (!in_array($action, ['block', 'unblock'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action. Use "block" or "unblock"']);
    exit;
}

// Get current user
$user_id = getCurrentUserId();

// Cannot block yourself
if ($user_id == $blocked_user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot block yourself']);
    exit;
}

try {
    // Block/unblock user
    $success = toggleUserBlock($user_id, $blocked_user_id, $action === 'block');
    
    $response = [
        'success' => $success,
        'action' => $action,
        'user_id' => $blocked_user_id
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to ' . $action . ' user']);
}
?>