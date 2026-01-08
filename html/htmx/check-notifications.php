<?php
/**
 * HTMX endpoint to check for pending prompt notifications
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Require login
requireLogin();

// Only handle GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$currentUserId = getCurrentUserId();
$db = getDB();

try {
    // Get unnotified prompt completions for user's projects
    $stmt = $db->prepare("
        SELECT pn.*, pdp.prompt_text, pdp.work_item_id, pdp.sprint_id,
               p.name as project_name, wi.title as work_item_title,
               s.name as sprint_name
        FROM prompt_notifications pn
        JOIN project_dev_prompts pdp ON pn.prompt_id = pdp.id
        JOIN projects p ON pdp.project_id = p.id
        LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
        LEFT JOIN sprints s ON pdp.sprint_id = s.id
        WHERE pn.notified = FALSE
        AND (p.project_manager_id = ? OR p.created_by = ?)
        ORDER BY pn.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$currentUserId, $currentUserId]);
    $notifications = $stmt->fetchAll();
    
    // Return JSON for JavaScript to process
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notifications' => array_map(function($n) {
            return [
                'id' => $n['id'],
                'prompt_id' => $n['prompt_id'],
                'status' => $n['status'],
                'project_name' => $n['project_name'],
                'sprint_name' => $n['sprint_name'],
                'work_item_title' => $n['work_item_title'],
                'prompt_text' => substr($n['prompt_text'], 0, 100) . '...',
                'created_at' => $n['created_at']
            ];
        }, $notifications)
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}