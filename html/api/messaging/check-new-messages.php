<?php
/**
 * API Endpoint: Check for new messages (polling)
 * Method: GET
 * Parameters: since (timestamp)
 * Returns: new messages since timestamp
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
$since = isset($_GET['since']) ? $_GET['since'] : date('Y-m-d H:i:s', time() - 60);

// Get current user and community
$user_id = getCurrentUserId();
$community_id = getCurrentCommunityId();

if (!$community_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No community selected']);
    exit;
}

try {
    // Get new messages
    $messages = getNewMessagesSince($user_id, $community_id, $since);
    
    // Format response
    $response = [
        'messages' => [],
        'timestamp' => date('Y-m-d H:i:s'),
        'unread_count' => getUnreadMessageCount($user_id, $community_id)
    ];
    
    foreach ($messages as $message) {
        $response['messages'][] = [
            'id' => $message['id'],
            'conversation_id' => $message['conversation_id'],
            'sender' => [
                'id' => $message['sender_id'],
                'name' => $message['sender_name'],
                'photo' => $message['sender_photo']
            ],
            'text' => htmlspecialchars($message['message_text']),
            'created_at' => $message['created_at']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to check for new messages']);
}
?>