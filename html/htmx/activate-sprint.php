<?php
/**
 * HTMX Activate Sprint Endpoint
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

$sprintId = $_POST['sprint_id'] ?? null;

if (!$sprintId) {
    echo '<div class="alert alert-danger">Sprint ID is required.</div>';
    exit;
}

$db = getDB();
$sprintObj = new Sprint();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

try {
    // Get sprint details
    $stmt = $db->prepare("
        SELECT s.*, p.project_manager_id, p.name as project_name
        FROM sprints s
        JOIN projects p ON s.project_id = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sprintId]);
    $sprint = $stmt->fetch();
    
    if (!$sprint) {
        echo '<div class="alert alert-danger">Sprint not found.</div>';
        exit;
    }
    
    // Check permissions
    $isProjectManager = $sprint['project_manager_id'] == $currentUserId;
    $isSprintCreator = $sprint['created_by'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isSprintCreator && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to activate this sprint.</div>';
        exit;
    }
    
    // Check if sprint dates are valid for activation
    $today = date('Y-m-d');
    if ($sprint['start_date'] > $today) {
        echo '<div class="alert alert-warning">Cannot activate a sprint with a future start date. Please update the sprint dates first.</div>';
        exit;
    }
    
    if ($sprint['end_date'] < $today) {
        echo '<div class="alert alert-warning">Cannot activate a sprint that has already ended. Please update the sprint dates first.</div>';
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Deactivate any currently active sprints for this project
    $deactivateStmt = $db->prepare("
        UPDATE sprints 
        SET status = 'completed' 
        WHERE project_id = ? AND status = 'active' AND id != ?
    ");
    $deactivateStmt->execute([$sprint['project_id'], $sprintId]);
    
    // Activate the selected sprint
    $activateStmt = $db->prepare("
        UPDATE sprints 
        SET status = 'active' 
        WHERE id = ?
    ");
    $activateStmt->execute([$sprintId]);
    
    $db->commit();
    
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-check-circle-fill me-2"></i>';
    echo 'Sprint "' . htmlspecialchars($sprint['name']) . '" has been activated successfully!';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    echo '<script>setTimeout(function() { window.location.reload(); }, 1500);</script>';
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error activating sprint: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error activating sprint: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>