<?php
/**
 * HTMX endpoint to mark notifications as read
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$notificationId = $_POST['notification_id'] ?? null;
if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notification ID required']);
    exit;
}

$currentUserId = getCurrentUserId();
$db = getDB();

try {
    // Verify user has access to this notification
    $stmt = $db->prepare("
        SELECT pn.id
        FROM prompt_notifications pn
        JOIN project_dev_prompts pdp ON pn.prompt_id = pdp.id
        JOIN projects p ON pdp.project_id = p.id
        WHERE pn.id = ?
        AND (p.project_manager_id = ? OR p.created_by = ?)
    ");
    $stmt->execute([$notificationId, $currentUserId, $currentUserId]);
    
    if ($stmt->fetch()) {
        // Mark as notified
        $updateStmt = $db->prepare("
            UPDATE prompt_notifications 
            SET notified = TRUE, notified_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$notificationId]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}