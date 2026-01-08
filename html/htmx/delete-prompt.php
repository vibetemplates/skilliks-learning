<?php
/**
 * HTMX Delete Prompt Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$promptId = $_POST['prompt_id'] ?? null;
if (!$promptId) {
    echo '<div class="alert alert-danger">Prompt ID is required.</div>';
    exit;
}

$db = getDB();
$sprintObj = new Sprint();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

try {
    // Get prompt details to check permissions
    $stmt = $db->prepare("
        SELECT pdp.*, s.project_id, s.created_by as sprint_created_by
        FROM project_dev_prompts pdp
        LEFT JOIN sprints s ON pdp.sprint_id = s.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        echo '<div class="alert alert-danger">Prompt not found.</div>';
        exit;
    }
    
    // Check permissions
    $project = $projectObj->findById($prompt['project_id']);
    $isProjectManager = $project && $project['project_manager_id'] == $currentUserId;
    $isSprintCreator = $prompt['sprint_created_by'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isSprintCreator && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to delete this prompt.</div>';
        exit;
    }
    
    // Check if prompt is in a deletable state - only pending or failed prompts can be deleted
    if (!in_array($prompt['status'], ['pending', 'failed'])) {
        echo '<div class="alert alert-warning">Only pending or failed prompts can be deleted. This prompt has status: ' . htmlspecialchars($prompt['status']) . '</div>';
        exit;
    }
    
    // Delete the prompt
    $deleteStmt = $db->prepare("DELETE FROM project_dev_prompts WHERE id = ?");
    $deleteStmt->execute([$promptId]);
    
    // Return success message
    echo '<div class="alert alert-success">Prompt deleted successfully.</div>';
    
    // Trigger page refresh after a short delay
    echo '<script>setTimeout(function() { window.location.reload(); }, 1000);</script>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}