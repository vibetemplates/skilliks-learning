<?php
/**
 * Community Members API
 * 
 * Returns members of a specific community
 */

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../config/functions.php';
require_once '../includes/session.php';
require_once '../classes/Community.php';

// Check if user is admin
if (!isCurrentUserAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get community ID
$community_id = isset($_GET['community_id']) ? (int)$_GET['community_id'] : 0;

if (!$community_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid community ID']);
    exit;
}

// Get members
$community = new Community();
$members = $community->getMembers($community_id);

echo json_encode([
    'success' => true,
    'members' => $members
]);
?>