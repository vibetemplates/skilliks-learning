<?php
/**
 * API Endpoint: Create or get conversation with another user
 * Method: POST
 * Parameters: user_id (other user to message)
 * Returns: conversation_id
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
$other_user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if (!$other_user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

// Get current user
$user_id = getCurrentUserId();

// Find a shared community between the two users
$db = getDB();
$shared_sql = "SELECT cm1.community_id 
               FROM community_members cm1
               INNER JOIN community_members cm2 ON cm1.community_id = cm2.community_id
               WHERE cm1.user_id = ? AND cm2.user_id = ? 
               AND cm1.is_active = 1 AND cm2.is_active = 1
               LIMIT 1";
$stmt = $db->prepare($shared_sql);
$stmt->execute([$user_id, $other_user_id]);
$community_id = $stmt->fetchColumn();

if (!$community_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No shared community with this user']);
    exit;
}

// Cannot message yourself
if ($user_id == $other_user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot message yourself']);
    exit;
}

try {
    // Create or get conversation
    $result = getOrCreateConversation($user_id, $other_user_id, $community_id);
    
    if (isset($result['error'])) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }
    
    // Get other user info
    $sql = "SELECT CONCAT(first_name, ' ', last_name) as name, profile_photo FROM users WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$other_user_id]);
    $other_user = $stmt->fetch();
    
    $response = [
        'conversation_id' => $result['conversation_id'],
        'other_user' => [
            'id' => $other_user_id,
            'name' => $other_user['name'],
            'photo' => $other_user['profile_photo'],
            'online' => getUserOnlineStatus($other_user_id)
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create conversation']);
}
?>