<?php
/**
 * HTMX endpoint to remove a work item from a sprint
 */
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/WorkItem.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

// Require login
requireLogin();

$workItemId = $_POST['work_item_id'] ?? null;
$sprintId = $_POST['sprint_id'] ?? null;

if (!$workItemId || !$sprintId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid request parameters</div>';
    exit;
}

$db = getDB();
$workItemObj = new WorkItem();
$sprintObj = new Sprint();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

// Get sprint details
$sprint = $sprintObj->findById($sprintId);
if (!$sprint) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Sprint not found</div>';
    exit;
}

// Check if user can manage sprint
$project = $projectObj->findById($sprint['project_id']);
$isProjectManager = $project['project_manager_id'] == $currentUserId;
$isAdmin = $userObj->isAdmin($currentUserId);
$canManageSprint = $isProjectManager || $isAdmin;

if (!$canManageSprint) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to manage this sprint</div>';
    exit;
}

// Check if sprint is in a state where items can be removed
if ($sprint['status'] === 'completed') {
    http_response_code(400);
    echo '<div class="alert alert-warning">Cannot remove items from a completed sprint</div>';
    exit;
}

try {
    // Remove work item from sprint (set sprint_id to NULL)
    $stmt = $db->prepare("
        UPDATE work_items 
        SET sprint_id = NULL 
        WHERE id = ? AND sprint_id = ?
    ");
    $result = $stmt->execute([$workItemId, $sprintId]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Also remove from sprint_work_items tracking table
        $stmt = $db->prepare("
            DELETE FROM sprint_work_items 
            WHERE work_item_id = ? AND sprint_id = ?
        ");
        $stmt->execute([$workItemId, $sprintId]);
        
        // Get work item details for the success message
        $workItem = $workItemObj->findById($workItemId);
        
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> ' . 
                htmlspecialchars($workItem['title']) . ' has been removed from the sprint.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    } else {
        echo '<div class="alert alert-warning">Work item was not found in this sprint</div>';
    }
} catch (PDOException $e) {
    error_log("Error removing work item from sprint: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Failed to remove work item from sprint</div>';
}
?>