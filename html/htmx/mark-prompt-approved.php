<?php
/**
 * HTMX Mark Prompt as Approved Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
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
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

try {
    // Get prompt details with project info
    $stmt = $db->prepare("
        SELECT pdp.*, p.id as project_id, p.project_manager_id
        FROM project_dev_prompts pdp
        JOIN projects p ON pdp.project_id = p.id
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
        echo '<div class="alert alert-danger">You do not have permission to approve this prompt.</div>';
        exit;
    }
    
    // Check if prompt has been tested
    if (!$prompt['is_tested']) {
        echo '<div class="alert alert-warning">This prompt must be marked as tested before it can be approved.</div>';
        exit;
    }
    
    // Update the prompt to approved and set status to completed
    $updateStmt = $db->prepare("
        UPDATE project_dev_prompts 
        SET is_approved = TRUE,
            approved_by = ?,
            approved_at = NOW(),
            status = 'completed',
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$currentUserId, $promptId]);
    
    // Return success message with reload trigger
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-check-circle"></i> Prompt approved and marked as completed.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    echo '<script>setTimeout(function() { window.location.reload(); }, 1500);</script>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}