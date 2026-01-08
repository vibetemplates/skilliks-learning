<?php
/**
 * HTMX Update Work Item Status Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/WorkItem.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$workItemObj = new WorkItem();
$currentUserId = getCurrentUserId();

$workItemId = $_POST['work_item_id'] ?? null;
$status = $_POST['status'] ?? null;
$tab = $_POST['tab'] ?? 'story';
$projectId = $_POST['project'] ?? null;

if (!$workItemId || !$status) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

// Check access
if (!$workItemObj->canAccess($workItemId, $currentUserId)) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

// Update status
if ($workItemObj->update($workItemId, ['status' => $status])) {
    // Return the updated tab content
    $_GET['project'] = $projectId;
    $_GET['tab'] = $tab;
    include 'work-items-list.php';
} else {
    echo '<div class="alert alert-danger">Failed to update status.</div>';
}