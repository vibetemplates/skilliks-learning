<?php
/**
 * HTMX Add to Sprint Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Sprint.php';
require_once '../classes/WorkItem.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$sprintObj = new Sprint();
$workItemObj = new WorkItem();
$userObj = new User();
$currentUserId = getCurrentUserId();

$workItemId = $_POST['work_item_id'] ?? null;
$sprintId = $_POST['sprint_id'] ?? null;

if (!$workItemId || !$sprintId) {
    echo '<div class="alert alert-danger">Work item ID and Sprint ID are required.</div>';
    exit;
}

// Get work item details
$workItem = $workItemObj->findById($workItemId);
if (!$workItem) {
    echo '<div class="alert alert-danger">Work item not found.</div>';
    exit;
}

// Get sprint details
$sprint = $sprintObj->findById($sprintId);
if (!$sprint) {
    echo '<div class="alert alert-danger">Sprint not found.</div>';
    exit;
}

// Check permissions
$canManage = $sprintObj->canManageSprints($sprint['project_id'], $currentUserId) || 
             $userObj->isAdmin($currentUserId);

if (!$canManage) {
    echo '<div class="alert alert-danger">You do not have permission to manage this sprint.</div>';
    exit;
}

// Check if work item is approved
if ($workItem['approval_status'] !== 'approved') {
    echo '<div class="alert alert-warning">Only approved work items can be added to sprints.</div>';
    exit;
}

// Add work item to sprint
$result = $sprintObj->addWorkItem($sprintId, $workItemId, $currentUserId);

if ($result) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-check-circle-fill me-2"></i>';
    echo 'Work item added to sprint "' . htmlspecialchars($sprint['name']) . '"';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    
    // Trigger page refresh to update the backlog
    header('HX-Refresh: true');
} else {
    echo '<div class="alert alert-danger">Failed to add work item to sprint.</div>';
}