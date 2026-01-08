<?php
/**
 * API Endpoint: Send a message
 * Method: POST
 * Parameters: conversation_id, message_text
 * Returns: message details
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
$message_text = isset($input['message_text']) ? trim($input['message_text']) : '';

if (!$conversation_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Conversation ID required']);
    exit;
}

if (empty($message_text)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message text required']);
    exit;
}

// Get current user
$user_id = getCurrentUserId();

try {
    // Send message
    $result = sendMessage($conversation_id, $user_id, $message_text);
    
    if (isset($result['error'])) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }
    
    // Get message details
    $db = getDB();
    $sql = "SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) AS name, u.profile_photo 
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$result['message_id']]);
    $message = $stmt->fetch();
    
    $response = [
        'message' => [
            'id' => $message['id'],
            'conversation_id' => $message['conversation_id'],
            'sender' => [
                'id' => $message['sender_id'],
                'name' => $message['name'],
                'photo' => $message['profile_photo']
            ],
            'text' => htmlspecialchars($message['message_text']),
            'created_at' => $message['created_at'],
            'is_mine' => true
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
}
?>