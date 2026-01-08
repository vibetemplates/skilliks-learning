<?php
/**
 * HTMX Create Work Item Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';
require_once '../classes/WorkItem.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$projectObj = new Project();
$workItemObj = new WorkItem();
$userObj = new User();
$currentUserId = getCurrentUserId();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

// Get form data
$type = $_POST['type'] ?? WorkItem::TYPE_TASK;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$projectId = $_POST['project_id'] ?? null;
$priority = $_POST['priority'] ?? WorkItem::PRIORITY_MEDIUM;
$status = $_POST['status'] ?? WorkItem::STATUS_TODO;
$storyPoints = $_POST['story_points'] ?? null;
$estimateHours = $_POST['estimate_hours'] ?? null;
$dueDate = $_POST['due_date'] ?? null;
$assigneeId = $_POST['assignee_id'] ?? null;
$parentId = $_POST['parent_id'] ?? null;
$sprintId = $_POST['sprint_id'] ?? null;

// Validation
if (!$title || !$projectId) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'Title and project are required.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    exit;
}

// Verify user has access to the project
if (!$projectObj->isMember($projectId, $currentUserId) && !$isProjectManagerOrAdmin) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'You do not have permission to create work items in this project.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    exit;
}

$data = [
    'type' => $type,
    'title' => $title,
    'description' => $description,
    'project_id' => $projectId,
    'priority' => $priority,
    'status' => $status,
    'story_points' => $storyPoints,
    'estimate_hours' => $estimateHours,
    'due_date' => $dueDate,
    'assignee_id' => $assigneeId,
    'parent_id' => $parentId,
    'sprint_id' => $sprintId,
    'reporter_id' => $currentUserId
];

$workItemId = $workItemObj->create($data);
if ($workItemId) {
    // Success - return success message and trigger refresh
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-check-circle-fill me-2"></i>';
    echo 'Work item created successfully!';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    
    // Add HX-Trigger header to refresh the active tab
    header('HX-Trigger: workItemCreated');
} else {
    $error = $workItemObj->lastError ?? 'Unknown error';
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'Failed to create work item: ' . htmlspecialchars($error);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}