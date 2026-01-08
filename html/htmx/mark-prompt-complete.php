<?php
/**
 * HTMX Mark Prompt Complete Endpoint
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
    // Get prompt details with project info
    $stmt = $db->prepare("
        SELECT pdp.*, s.project_id, wi.title as work_item_title, p.project_manager_id
        FROM project_dev_prompts pdp
        LEFT JOIN sprints s ON pdp.sprint_id = s.id
        LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
        LEFT JOIN projects p ON pdp.project_id = p.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        echo '<div class="alert alert-danger">Prompt not found.</div>';
        exit;
    }
    
    // Check permissions
    $isProjectManager = $prompt['project_manager_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to mark this prompt as complete.</div>';
        exit;
    }
    
    // Update prompt status to completed
    $updateStmt = $db->prepare("
        UPDATE project_dev_prompts 
        SET status = 'completed',
            response_text = CONCAT(COALESCE(response_text, ''), '\n\n[Manually marked as completed by user]'),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $updateStmt->execute([$promptId]);
    
    
    echo '<div class="alert alert-success">';
    echo '<i class="bi bi-check-circle"></i> Prompt marked as completed successfully.';
    echo ' <a href="#" onclick="window.location.reload();" class="alert-link">Refresh page</a> to see updated status.';
    echo '</div>';
    
} catch (Exception $e) {
    error_log("Error in mark-prompt-complete.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error marking prompt as complete: ' . htmlspecialchars($e->getMessage()) . '</div>';
}