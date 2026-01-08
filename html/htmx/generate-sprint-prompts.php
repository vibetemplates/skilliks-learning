<?php
/**
 * HTMX Generate Sprint Prompts Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/PromptGenerator.php';
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
$promptGenerator = new PromptGenerator();
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
    echo '<div class="alert alert-danger">You do not have permission to generate prompts for this sprint.</div>';
    exit;
}

// Check if project has dev_system_url
if (empty($project['dev_system_url'])) {
    echo '<div class="alert alert-warning">Please configure the Development System API URL for this project first.</div>';
    exit;
}

try {
    // Generate prompts for all work items in the sprint
    $prompts = $promptGenerator->generateSprintPrompts($sprintId, $sprint['project_id']);
    
    if (empty($prompts)) {
        echo '<div class="alert alert-warning">No approved work items found in this sprint to generate prompts.</div>';
        exit;
    }
    
    // Save prompts to database
    $savedPrompts = $promptGenerator->savePrompts($prompts);
    
    if ($savedPrompts) {
        $count = count($savedPrompts);
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-check-circle-fill me-2"></i>';
        echo "Successfully generated {$count} prompts for this sprint.";
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        // Show preview of generated prompts
        echo '<div class="mt-3">';
        echo '<h6>Generated Prompts:</h6>';
        echo '<div class="list-group">';
        foreach ($savedPrompts as $index => $prompt) {
            echo '<div class="list-group-item">';
            echo '<div class="d-flex w-100 justify-content-between">';
            echo '<h6 class="mb-1">Prompt #' . ($index + 1) . '</h6>';
            echo '<small>Work Item ID: ' . $prompt['work_item_id'] . '</small>';
            echo '</div>';
            echo '<p class="mb-1 text-truncate" style="max-width: 600px;">';
            echo htmlspecialchars(substr($prompt['prompt_text'], 0, 150)) . '...';
            echo '</p>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        
        // Add button to process prompts
        echo '<div class="mt-3">';
        echo '<button class="btn btn-primary" ';
        echo 'hx-post="/htmx/process-sprint-prompts.php" ';
        echo 'hx-target="#sprint-prompt-results" ';
        echo 'hx-swap="innerHTML" ';
        echo 'hx-vals=\'{"sprint_id": "' . $sprintId . '"}\' ';
        echo 'hx-indicator="#prompt-processing-spinner">';
        echo '<i class="bi bi-play-circle me-2"></i>Send Prompts to Development System';
        echo '</button>';
        echo '<div id="prompt-processing-spinner" class="spinner-border spinner-border-sm ms-2 htmx-indicator" role="status">';
        echo '<span class="visually-hidden">Processing...</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div id="sprint-prompt-results" class="mt-3"></div>';
        
        // Trigger refresh
        header('HX-Trigger: sprintPromptsGenerated');
    } else {
        echo '<div class="alert alert-danger">Failed to save generated prompts.</div>';
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}