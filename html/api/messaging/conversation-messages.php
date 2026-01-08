<?php
/**
 * API Endpoint: Get messages for a conversation
 * Method: GET
 * Parameters: conversation_id, limit (optional), offset (optional)
 * Returns: Messages for the conversation with pagination
 */

require_once '../../includes/session.php';
require_once '../../includes/messaging_functions.php';

// Check authentication
requireLogin();

// Only GET method allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get parameters
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if (!$conversation_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Conversation ID required']);
    exit;
}

// Get current user
$user_id = getCurrentUserId();

try {
    // Get messages
    $result = getConversationMessages($conversation_id, $user_id, $limit, $offset);
    
    if (isset($result['error'])) {
        http_response_code(403);
        echo json_encode($result);
        exit;
    }
    
    // Mark messages as read
    markMessagesAsRead($conversation_id, $user_id);
    
    // Format response
    $response = [
        'messages' => [],
        'has_more' => count($result) == $limit
    ];
    
    foreach ($result as $message) {
        $response['messages'][] = [
            'id' => $message['id'],
            'sender' => [
                'id' => $message['sender_id'],
                'name' => $message['sender_name'],
                'photo' => $message['sender_photo']
            ],
            'text' => htmlspecialchars($message['message_text']),
            'created_at' => $message['created_at'],
            'is_mine' => $message['sender_id'] == $user_id
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load messages']);
}
?>