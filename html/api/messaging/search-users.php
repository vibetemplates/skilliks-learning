<?php
/**
 * API Endpoint: Search users for messaging
 * Method: GET
 * Parameters: q (search query)
 * Returns: List of users that can be messaged
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
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($searchTerm) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Search term must be at least 2 characters']);
    exit;
}

// Get current user
$user_id = getCurrentUserId();

try {
    // Search users from all user's communities
    $users = searchUsersForMessagingAllCommunities($user_id, $searchTerm);
    
    // Format response
    $response = [
        'users' => []
    ];
    
    foreach ($users as $user) {
        $response['users'][] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'photo' => $user['profile_photo'],
            'is_blocked' => (bool)$user['is_blocked'],
            'communities' => $user['communities']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to search users']);
}
?>