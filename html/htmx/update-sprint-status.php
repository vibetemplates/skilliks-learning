<?php
/**
 * HTMX Update Sprint Status Endpoint
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
$projectId = $_POST['project_id'] ?? null;
$newStatus = $_POST['status'] ?? null;

if (!$sprintId || !$newStatus) {
    setFlashMessage('error', 'Sprint ID and status are required.');
    header('Location: /sprint-dashboard.php?id=' . $sprintId);
    exit;
}

// Validate status
$validStatuses = ['planning', 'active', 'completed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    setFlashMessage('error', 'Invalid status.');
    header('Location: /sprint-dashboard.php?id=' . $sprintId);
    exit;
}

$db = getDB();
$sprintObj = new Sprint();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

try {
    // Get sprint details
    $sprint = $sprintObj->findById($sprintId);
    if (!$sprint) {
        setFlashMessage('error', 'Sprint not found.');
        header('Location: /project-sprints.php?project=' . $projectId);
        exit;
    }
    
    // Check permissions
    $project = $projectObj->findById($sprint['project_id']);
    $isProjectManager = $project && $project['project_manager_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isAdmin) {
        setFlashMessage('error', 'You do not have permission to update this sprint.');
        header('Location: /sprint-dashboard.php?id=' . $sprintId);
        exit;
    }
    
    // Check for business rules
    if ($newStatus === 'active') {
        // Check if there's already an active sprint for this project
        $activeCheck = $db->prepare("
            SELECT COUNT(*) as count 
            FROM sprints 
            WHERE project_id = ? 
            AND status = 'active' 
            AND id != ?
        ");
        $activeCheck->execute([$sprint['project_id'], $sprintId]);
        $activeCount = $activeCheck->fetch()['count'];
        
        if ($activeCount > 0) {
            setFlashMessage('error', 'This project already has an active sprint. Complete or cancel it before activating another.');
            header('Location: /sprint-dashboard.php?id=' . $sprintId);
            exit;
        }
    }
    
    // Check for incomplete prompts when marking as complete
    if ($newStatus === 'completed') {
        $incompleteCheck = $db->prepare("
            SELECT COUNT(*) as count,
                   GROUP_CONCAT(CASE 
                       WHEN wi.title IS NOT NULL THEN CONCAT('Work Item: ', wi.title)
                       ELSE 'Manual Prompt'
                   END SEPARATOR ', ') as incomplete_items
            FROM project_dev_prompts pdp
            LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
            WHERE pdp.sprint_id = ? 
            AND pdp.status NOT IN ('completed', 'cancelled')
        ");
        $incompleteCheck->execute([$sprintId]);
        $incompleteResult = $incompleteCheck->fetch();
        
        if ($incompleteResult['count'] > 0) {
            setFlashMessage('error', 
                'Cannot mark sprint as complete. There are ' . $incompleteResult['count'] . 
                ' incomplete prompts. Please complete or delete these prompts first: ' . 
                htmlspecialchars($incompleteResult['incomplete_items']));
            header('Location: /sprint-dashboard.php?id=' . $sprintId);
            exit;
        }
    }
    
    // Update sprint status
    $updateStmt = $db->prepare("
        UPDATE sprints 
        SET status = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$newStatus, $sprintId]);
    
    // Log the status change
    error_log("Sprint {$sprintId} status changed from {$sprint['status']} to {$newStatus} by user {$currentUserId}");
    
    setFlashMessage('success', 'Sprint status updated successfully.');
    
} catch (Exception $e) {
    setFlashMessage('error', 'Error updating sprint status: ' . $e->getMessage());
}

header('Location: /sprint-dashboard.php?id=' . $sprintId);
exit;