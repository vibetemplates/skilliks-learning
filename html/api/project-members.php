<?php
/**
 * API endpoint to get project members
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';

// Require login
requireLogin();

header('Content-Type: application/json');

$projectId = $_GET['project_id'] ?? null;

if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'Project ID required']);
    exit;
}

$projectObj = new Project();
$currentUserId = getCurrentUserId();

// Check if user has access to the project
if (!$projectObj->isMember($projectId, $currentUserId)) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get project members
$members = $projectObj->getMembers($projectId);

// Filter to only approved members
$approvedMembers = array_filter($members, function($member) {
    return $member['status'] === 'approved';
});

// Return only necessary fields
$memberData = array_map(function($member) {
    return [
        'id' => $member['user_id'],
        'first_name' => $member['first_name'],
        'last_name' => $member['last_name'],
        'email' => $member['email']
    ];
}, $approvedMembers);

echo json_encode([
    'success' => true,
    'members' => array_values($memberData)
]);