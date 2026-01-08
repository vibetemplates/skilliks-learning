<?php
/**
 * HTMX Mark Prompt Complete with Details Endpoint
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
$rating = $_POST['rating'] ?? 0;
$testNotes = $_POST['test_notes'] ?? '';
$developerNotes = $_POST['developer_notes'] ?? '';

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
        SELECT pdp.*, s.project_id, p.project_manager_id, s.created_by as sprint_creator_id
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
    
    // Check if already completed
    if ($prompt['status'] === 'completed') {
        echo '<div class="alert alert-warning">This prompt is already marked as completed.</div>';
        exit;
    }
    
    // Check permissions
    $isProjectManager = $prompt['project_manager_id'] == $currentUserId;
    $isSprintCreator = $prompt['sprint_creator_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    $canManageSprint = $isProjectManager || $isAdmin || $isSprintCreator;
    
    if (!$canManageSprint) {
        echo '<div class="alert alert-danger">You do not have permission to manage this prompt.</div>';
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Update the prompt status and completion details
    $updateStmt = $db->prepare("
        UPDATE project_dev_prompts 
        SET status = 'completed',
            completed_at = NOW(),
            updated_at = NOW(),
            rating = ?,
            rating_updated_by = ?,
            rating_updated_at = NOW(),
            test_notes = ?,
            test_notes_updated_by = ?,
            test_notes_updated_at = NOW(),
            developer_notes = ?,
            developer_notes_updated_by = ?,
            developer_notes_updated_at = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->execute([
        $rating > 0 ? $rating : null,
        $rating > 0 ? $currentUserId : null,
        !empty($testNotes) ? $testNotes : null,
        !empty($testNotes) ? $currentUserId : null,
        !empty($developerNotes) ? $developerNotes : null,
        !empty($developerNotes) ? $currentUserId : null,
        $promptId
    ]);
    
    // Update work item status if all prompts are completed
    if ($prompt['work_item_id']) {
        $checkStmt = $db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM project_dev_prompts
            WHERE work_item_id = ? AND sprint_id = ?
        ");
        $checkStmt->execute([$prompt['work_item_id'], $prompt['sprint_id']]);
        $counts = $checkStmt->fetch();
        
        // If all prompts for this work item are completed, update the work item
        if ($counts['total'] == $counts['completed']) {
            $updateWorkItemStmt = $db->prepare("
                UPDATE work_items 
                SET status = 'done',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateWorkItemStmt->execute([$prompt['work_item_id']]);
        }
    }
    
    $db->commit();
    
    echo '<div class="alert alert-success">';
    echo '<i class="bi bi-check-circle"></i> Prompt marked as completed successfully!';
    if ($rating > 0) {
        echo ' Rating: ' . str_repeat('⭐', $rating);
    }
    echo '</div>';
    
} catch (Exception $e) {
    $db->rollBack();
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}