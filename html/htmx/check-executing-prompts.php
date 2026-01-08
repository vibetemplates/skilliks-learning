<?php
/**
 * HTMX endpoint to check status of executing prompts for a sprint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$sprintId = $_GET['sprint_id'] ?? null;
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
    $sprint = $sprintObj->findById($sprintId);
    if (!$sprint) {
        echo '<div class="alert alert-danger">Sprint not found.</div>';
        exit;
    }
    
    // Check permissions
    $project = $projectObj->findById($sprint['project_id']);
    $isProjectManager = $project && $project['project_manager_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isAdmin) {
        exit; // Silent fail for unauthorized users
    }
    
    // Get executing prompts for this sprint
    $stmt = $db->prepare("
        SELECT pdp.id, pdp.prompt_text, pdp.status, pdp.response_text,
               wi.title as work_item_title
        FROM project_dev_prompts pdp
        LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
        WHERE pdp.sprint_id = ?
        AND pdp.status = 'executing'
        ORDER BY pdp.id
    ");
    $stmt->execute([$sprintId]);
    $executingPrompts = $stmt->fetchAll();
    
    if (empty($executingPrompts)) {
        // No executing prompts, stop polling
        echo '<script>stopExecutingPromptsPolling();</script>';
        exit;
    }
    
    // Show status summary
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">';
    echo '<h6 class="alert-heading"><i class="bi bi-arrow-clockwise spin"></i> Executing Prompts</h6>';
    echo '<p class="mb-2">' . count($executingPrompts) . ' prompt(s) currently executing:</p>';
    echo '<ul class="mb-0">';
    
    foreach ($executingPrompts as $prompt) {
        $promptDesc = $prompt['work_item_title'] ?: substr($prompt['prompt_text'], 0, 50) . '...';
        echo '<li>' . htmlspecialchars($promptDesc);
        
        // Show partial response if available
        if (!empty($prompt['response_text'])) {
            $responseLength = strlen($prompt['response_text']);
            echo ' <small class="text-muted">(' . number_format($responseLength) . ' characters processed)</small>';
        }
        
        echo '</li>';
    }
    
    echo '</ul>';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    
    // Add spinning animation CSS if not already present
    echo '<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin {
        display: inline-block;
        animation: spin 2s linear infinite;
    }
    </style>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}