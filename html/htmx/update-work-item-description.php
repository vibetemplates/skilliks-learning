<?php
/**
 * HTMX endpoint to update work item description
 */
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/WorkItem.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

// Require login
requireLogin();

$workItemId = $_POST['work_item_id'] ?? null;
$description = $_POST['description'] ?? '';

if (!$workItemId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid request parameters</div>';
    exit;
}

$workItemObj = new WorkItem();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

// Get work item details
$workItem = $workItemObj->findById($workItemId);
if (!$workItem) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Work item not found</div>';
    exit;
}

// Check if user has permission to edit
$project = $projectObj->findById($workItem['project_id']);
$isProjectManager = $project['project_manager_id'] == $currentUserId;
$isAdmin = $userObj->isAdmin($currentUserId);
$isAssignee = $workItem['assignee_id'] == $currentUserId;
$isReporter = $workItem['reporter_id'] == $currentUserId;

if (!$isProjectManager && !$isAdmin && !$isAssignee && !$isReporter) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to edit this work item</div>';
    exit;
}

try {
    // Update the description
    $result = $workItemObj->update($workItemId, ['description' => $description]);
    
    if ($result) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> Description updated successfully
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    } else {
        echo '<div class="alert alert-danger">Failed to update description</div>';
    }
} catch (Exception $e) {
    error_log("Error updating work item description: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">An error occurred while updating the description</div>';
}
?>