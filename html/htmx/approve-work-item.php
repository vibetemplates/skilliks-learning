<?php
/**
 * HTMX Approve Work Item Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/WorkItem.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$workItemObj = new WorkItem();
$userObj = new User();
$currentUserId = getCurrentUserId();
$workItemId = $_POST['work_item_id'] ?? null;
$action = $_POST['action'] ?? 'approve';
$reason = $_POST['reason'] ?? '';

if (!$workItemId) {
    echo '<div class="alert alert-danger">Work item ID is required.</div>';
    exit;
}

// Check permissions - must be project manager or admin
$workItem = $workItemObj->findById($workItemId);
if (!$workItem) {
    echo '<div class="alert alert-danger">Work item not found.</div>';
    exit;
}

$isProjectManager = false;
$isAdmin = $userObj->isAdmin($currentUserId);

// Check if user is project manager
$db = getDB();
$stmt = $db->prepare("SELECT project_manager_id FROM projects WHERE id = ?");
$stmt->execute([$workItem['project_id']]);
$project = $stmt->fetch();

if ($project && $project['project_manager_id'] == $currentUserId) {
    $isProjectManager = true;
}

if (!$isProjectManager && !$isAdmin) {
    echo '<div class="alert alert-danger">You do not have permission to approve work items.</div>';
    exit;
}

// Perform action
if ($action === 'approve') {
    $result = $workItemObj->approve($workItemId, $currentUserId);
    if ($result) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-check-circle-fill me-2"></i>';
        echo 'Work item approved and added to backlog.';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        // Trigger refresh
        header('HX-Trigger: workItemUpdated');
    } else {
        echo '<div class="alert alert-danger">Failed to approve work item.</div>';
    }
} elseif ($action === 'reject') {
    if (empty($reason)) {
        echo '<div class="alert alert-danger">Rejection reason is required.</div>';
        exit;
    }
    
    $result = $workItemObj->reject($workItemId, $currentUserId, $reason);
    if ($result) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-x-circle-fill me-2"></i>';
        echo 'Work item rejected.';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        // Trigger refresh
        header('HX-Trigger: workItemUpdated');
    } else {
        echo '<div class="alert alert-danger">Failed to reject work item.</div>';
    }
}