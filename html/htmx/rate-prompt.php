<?php
/**
 * Rate Prompt Endpoint
 * 
 * Updates the rating for a prompt (1-5 stars)
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
$rating = $_POST['rating'] ?? null;

if (!$promptId || !$rating || !is_numeric($rating) || $rating < 1 || $rating > 5) {
    echo '<div class="alert alert-danger">Invalid rating. Please select between 1 and 5 stars.</div>';
    exit;
}

$db = getDB();
$currentUserId = getCurrentUserId();

try {
    // Get prompt details to check permissions
    $stmt = $db->prepare("
        SELECT pdp.*, s.project_id, s.created_by as sprint_created_by, p.project_manager_id
        FROM project_dev_prompts pdp
        LEFT JOIN sprints s ON pdp.sprint_id = s.id
        LEFT JOIN projects p ON s.project_id = p.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        echo '<div class="alert alert-danger">Prompt not found.</div>';
        exit;
    }
    
    // Check permissions - any project member can rate
    $projectObj = new Project();
    $userObj = new User();
    $isProjectMember = $projectObj->isMember($prompt['project_id'], $currentUserId);
    $isProjectManager = $prompt['project_manager_id'] == $currentUserId;
    $isSprintCreator = $prompt['sprint_created_by'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectMember && !$isProjectManager && !$isSprintCreator && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to rate this prompt.</div>';
        exit;
    }
    
    // Update the rating
    $updateStmt = $db->prepare("
        UPDATE project_dev_prompts 
        SET rating = ?, 
            rating_updated_at = NOW(), 
            rating_updated_by = ?
        WHERE id = ?
    ");
    
    $updateStmt->execute([
        intval($rating),
        $currentUserId,
        $promptId
    ]);
    
    // Return success message
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> Rating saved successfully (' . intval($rating) . ' star' . ($rating > 1 ? 's' : '') . ').
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    
} catch (Exception $e) {
    error_log("Error updating prompt rating: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>