<?php
/**
 * API endpoint to switch the user's current community
 */

require_once '../includes/session.php';
require_once '../classes/Community.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['community_id']) || !is_numeric($input['community_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid community ID']);
    exit;
}

$communityId = (int)$input['community_id'];
$userId = $_SESSION['user_id'];

// Initialize Community class
$community = new Community();

// Check if user is a member of this community
$userCommunities = $community->getUserCommunities($userId);
$isMember = false;

foreach ($userCommunities as $userCommunity) {
    if ($userCommunity['id'] == $communityId) {
        $isMember = true;
        break;
    }
}

if (!$isMember) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not a member of this community']);
    exit;
}

// Set the current community in session
setCurrentCommunity($communityId);

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Community switched successfully',
    'community_id' => $communityId
]);