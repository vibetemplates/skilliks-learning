<?php
/**
 * API Endpoint: Get user's conversations
 * Method: GET
 * Returns: List of conversations with last message preview
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

// Get current user
$user_id = getCurrentUserId();

try {
    // Get conversations from all user's communities
    $conversations = getUserConversationsAllCommunities($user_id);
    
    // Format response
    $response = [
        'conversations' => []
    ];
    
    foreach ($conversations as $conv) {
        $response['conversations'][] = [
            'conversation_id' => $conv['conversation_id'],
            'community_name' => $conv['community_name'],
            'other_user' => [
                'id' => $conv['other_user_id'],
                'name' => $conv['other_user_name'],
                'photo' => $conv['other_user_photo'],
                'online' => getUserOnlineStatus($conv['other_user_id'])
            ],
            'last_message' => $conv['last_message'] ? [
                'text' => htmlspecialchars($conv['last_message']),
                'sender_id' => $conv['last_message_sender'],
                'time' => $conv['last_message_time'],
                'is_mine' => $conv['last_message_sender'] == $user_id
            ] : null,
            'unread_count' => $conv['unread_count'],
            'updated_at' => $conv['updated_at']
        ];
    }
    
    // Add total unread count from all communities
    $response['total_unread'] = getUnreadMessageCountAllCommunities($user_id);
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load conversations']);
}
?>