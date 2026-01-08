<?php
/**
 * API Endpoint: Mark messages as read
 * Method: POST
 * Parameters: conversation_id
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
$conversation_id = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;

if (!$conversation_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Conversation ID required']);
    exit;
}

// Get current user
$user_id = getCurrentUserId();

try {
    // Verify user is participant
    if (!isConversationParticipant($user_id, $conversation_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Mark as read
    $success = markMessagesAsRead($conversation_id, $user_id);
    
    $response = [
        'success' => $success,
        'conversation_id' => $conversation_id
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark messages as read']);
}
?>