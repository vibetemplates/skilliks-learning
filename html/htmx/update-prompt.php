<?php
/**
 * Update Prompt Text
 * 
 * HTMX endpoint to update prompt text
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';

// Require login
requireLogin();

// Get POST data
$promptId = $_POST['prompt_id'] ?? null;
$promptText = $_POST['prompt_text'] ?? '';

if (!$promptId) {
    echo '<div class="alert alert-danger">Prompt ID is required.</div>';
    exit;
}

if (empty(trim($promptText))) {
    echo '<div class="alert alert-danger">Prompt text cannot be empty.</div>';
    exit;
}

$db = getDB();
$currentUserId = getCurrentUserId();
$userObj = new User();
$sprintObj = new Sprint();
$projectObj = new Project();

try {
    // Get prompt details to check permissions
    $stmt = $db->prepare("
        SELECT pdp.*, s.created_by as sprint_created_by, p.project_manager_id
        FROM project_dev_prompts pdp
        JOIN sprints s ON pdp.sprint_id = s.id
        JOIN projects p ON s.project_id = p.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        echo '<div class="alert alert-danger">Prompt not found.</div>';
        exit;
    }
    
    // Check if prompt is pending (only pending prompts can be edited)
    if ($prompt['status'] !== 'pending') {
        echo '<div class="alert alert-danger">Only pending prompts can be edited. Current status: ' . $prompt['status'] . '</div>';
        exit;
    }
    
    // Check permissions: user must be project manager, sprint creator, or admin
    $isProjectManager = $prompt['project_manager_id'] == $currentUserId;
    $isSprintCreator = $prompt['sprint_created_by'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isSprintCreator && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to edit this prompt.</div>';
        exit;
    }

    // Sanitize the prompt text before saving
    $sanitizedPromptText = sanitizePromptText($promptText);

    // Update the prompt text
    $updateStmt = $db->prepare("
        UPDATE project_dev_prompts
        SET prompt_text = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$sanitizedPromptText, $promptId]);
    
    echo '<div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> Prompt updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    
} catch (PDOException $e) {
    error_log("Error updating prompt: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error updating prompt. Please try again.</div>';
}