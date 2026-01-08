<?php
/**
 * HTMX Join Project Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';
require_once '../classes/ProjectMember.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if (!$projectId) {
    echo '<div class="alert alert-danger">Invalid project ID.</div>';
    exit;
}

$projectObj = new Project();
$memberObj = new ProjectMember();
$currentUserId = getCurrentUserId();

// Check if project exists and is active
$project = $projectObj->findById($projectId);
if (!$project || $project['status'] !== 'active') {
    echo '<div class="alert alert-danger">This project is not available for joining.</div>';
    exit;
}

// Check if user is already a member
if ($projectObj->isMember($projectId, $currentUserId)) {
    echo '<div class="alert alert-info">You are already a member of this project.</div>';
    exit;
}

// Add user to project
try {
    $memberData = [
        'project_id' => $projectId,
        'user_id' => $currentUserId,
        'role' => 'member',
        'status' => 'working'
    ];
    
    if ($memberObj->create($memberData)) {
        // Return success message and updated button
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-check-circle-fill me-2"></i>';
        echo 'Successfully joined the project!';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        echo '<a href="/project-detail?id=' . $projectId . '" class="btn btn-success w-100">';
        echo '<i class="bi bi-check-circle"></i> You are now a member';
        echo '</a>';
    } else {
        echo '<div class="alert alert-danger">Failed to join the project. Please try again.</div>';
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">An error occurred while joining the project.</div>';
    error_log("Join project error: " . $e->getMessage());
}