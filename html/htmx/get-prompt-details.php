<?php
/**
 * Get Prompt Details Endpoint
 * 
 * Returns prompt details including response and error message
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';

// Require login
requireLogin();

header('Content-Type: application/json');

$promptId = $_GET['id'] ?? null;

if (!$promptId) {
    echo json_encode(['success' => false, 'message' => 'Prompt ID is required']);
    exit;
}

try {
    $db = getDB();
    $currentUserId = getCurrentUserId();
    $userObj = new User();
    
    // Get prompt details with project info to check access
    $stmt = $db->prepare("
        SELECT pdp.*, s.project_id, s.created_by as sprint_created_by,
               p.project_manager_id
        FROM project_dev_prompts pdp
        JOIN sprints s ON pdp.sprint_id = s.id
        JOIN projects p ON s.project_id = p.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        echo json_encode(['success' => false, 'message' => 'Prompt not found']);
        exit;
    }
    
    // Check access - user must be project member, sprint creator, project manager, or admin
    $isAdmin = $userObj->isAdmin($currentUserId);
    $isProjectManager = $prompt['project_manager_id'] == $currentUserId;
    $isSprintCreator = $prompt['sprint_created_by'] == $currentUserId;
    
    // Check if user is a project member
    $memberStmt = $db->prepare("
        SELECT 1 FROM project_members 
        WHERE project_id = ? AND user_id = ? AND status = 'approved'
    ");
    $memberStmt->execute([$prompt['project_id'], $currentUserId]);
    $isMember = $memberStmt->fetch() !== false;
    
    if (!$isAdmin && !$isProjectManager && !$isSprintCreator && !$isMember) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Return prompt details
    echo json_encode([
        'success' => true,
        'prompt' => [
            'id' => $prompt['id'],
            'prompt_text' => $prompt['prompt_text'],
            'response' => $prompt['response_text'],
            'response_text' => $prompt['response_text'],
            'error_message' => $prompt['error_message'],
            'status' => $prompt['status'],
            'executed_at' => $prompt['executed_at']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching prompt details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>