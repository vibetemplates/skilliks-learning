<?php
/**
 * HTMX Process Sprint Prompts Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/DevSystemAPI.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$sprintObj = new Sprint();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

$sprintId = $_POST['sprint_id'] ?? null;

if (!$sprintId) {
    echo '<div class="alert alert-danger">Sprint ID is required.</div>';
    exit;
}

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
    echo '<div class="alert alert-danger">You do not have permission to process prompts for this sprint.</div>';
    exit;
}

// Check if project has an API key configured
if (empty($project['skilliks_api_key'])) {
    echo '<div class="alert alert-warning">No API key configured for this project. Please configure it in the project settings.</div>';
    exit;
}

// Initialize API client with project-specific key
$apiKey = $project['skilliks_api_key'];
$devSystemAPI = new DevSystemAPI($project['dev_system_url'], $apiKey);

try {
    // Process all prompts for the sprint
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0">Processing Sprint Prompts</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    
    $results = $devSystemAPI->processSprintPrompts($sprintId);
    
    if (empty($results)) {
        echo '<div class="alert alert-info">No pending prompts to process.</div>';
    } else {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Work Item</th>';
        echo '<th>Status</th>';
        echo '<th>Details</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($results as $result) {
            $success = $result['result']['success'] ?? false;
            if ($success) {
                $successCount++;
            } else {
                $failureCount++;
            }
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($result['title'] ?? 'Work Item #' . $result['work_item_id']) . '</td>';
            echo '<td>';
            if ($success) {
                echo '<span class="badge bg-success">Sent</span>';
            } else {
                echo '<span class="badge bg-danger">Failed</span>';
            }
            echo '</td>';
            echo '<td>';
            if ($success) {
                if (isset($result['result']['log_file'])) {
                    echo '<small>Log: ' . htmlspecialchars($result['result']['log_file']) . '</small>';
                } else {
                    echo '<small class="text-muted">Processing...</small>';
                }
            } else {
                echo '<small class="text-danger">' . htmlspecialchars($result['result']['error'] ?? 'Unknown error') . '</small>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        // Summary
        echo '<div class="alert alert-info mt-3">';
        echo '<strong>Summary:</strong> ';
        echo $successCount . ' prompts sent successfully';
        if ($failureCount > 0) {
            echo ', ' . $failureCount . ' failed';
        }
        echo '</div>';
        
        // Add monitoring button
        if ($successCount > 0) {
            echo '<div class="mt-3">';
            echo '<a href="/sprint-prompts-monitor.php?sprint=' . $sprintId . '" class="btn btn-primary">';
            echo '<i class="bi bi-activity me-2"></i>Monitor Prompt Execution';
            echo '</a>';
            echo '</div>';
        }
    }
    
    echo '</div>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}