<?php
/**
 * HTMX endpoint to generate prompt for a single work item
 */
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/WorkItem.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';
require_once '../classes/PromptGenerator.php';

// Require login
requireLogin();

$workItemId = $_POST['work_item_id'] ?? null;
$sprintId = $_POST['sprint_id'] ?? null;

if (!$workItemId || !$sprintId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid request parameters</div>';
    exit;
}

$workItemObj = new WorkItem();
$sprintObj = new Sprint();
$projectObj = new Project();
$userObj = new User();
$promptGenerator = new PromptGenerator();
$db = getDB();
$currentUserId = getCurrentUserId();

// Get work item details
$workItem = $workItemObj->findById($workItemId);
if (!$workItem) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Work item not found</div>';
    exit;
}

// Get sprint details
$sprint = $sprintObj->findById($sprintId);
if (!$sprint) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Sprint not found</div>';
    exit;
}

// Check if user can manage sprint
$project = $projectObj->findById($sprint['project_id']);
$isProjectManager = $project['project_manager_id'] == $currentUserId;
$isAdmin = $userObj->isAdmin($currentUserId);
$canManageSprint = $isProjectManager || $isAdmin;

if (!$canManageSprint) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to generate prompts</div>';
    exit;
}

try {
    // Check if a prompt already exists for this work item in this sprint
    $stmt = $db->prepare("
        SELECT id, prompt_text, status, created_at 
        FROM project_dev_prompts 
        WHERE work_item_id = ? AND sprint_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$workItemId, $sprintId]);
    $existingPrompt = $stmt->fetch();
    
    if ($existingPrompt) {
        // Show existing prompt
        ?>
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-robot"></i> Generated Prompt
                        <span class="badge bg-<?php 
                            echo $existingPrompt['status'] === 'completed' ? 'success' : 
                                ($existingPrompt['status'] === 'executing' ? 'warning' : 
                                ($existingPrompt['status'] === 'failed' ? 'danger' : 'secondary')); 
                        ?> ms-2">
                            <?php echo ucfirst($existingPrompt['status']); ?>
                        </span>
                    </h6>
                    <button class="btn btn-sm btn-outline-primary"
                            hx-post="/htmx/generate-work-item-prompt.php"
                            hx-target="#work-item-prompt-<?php echo $workItemId; ?>"
                            hx-swap="innerHTML"
                            hx-vals='{"work_item_id": "<?php echo $workItemId; ?>", "sprint_id": "<?php echo $sprintId; ?>", "regenerate": "true"}'
                            hx-confirm="Regenerate the prompt for this work item?">
                        <i class="bi bi-arrow-clockwise"></i> Regenerate
                    </button>
                </div>
                <pre class="bg-light p-2 rounded mb-0" style="white-space: pre-wrap; font-size: 0.875rem;"><?php echo htmlspecialchars($existingPrompt['prompt_text']); ?></pre>
                <small class="text-muted d-block mt-2">
                    Generated <?php echo date('M j, Y g:i A', strtotime($existingPrompt['created_at'])); ?>
                </small>
            </div>
        </div>
        <?php
    } else {
        // Generate new prompt
        $promptText = $promptGenerator->generateWorkItemPrompt($workItem);
        
        if (!$promptText) {
            echo '<div class="alert alert-warning">Could not generate prompt for this work item type</div>';
            exit;
        }
        
        // Get the next prompt order for this sprint
        $stmt = $db->prepare("
            SELECT COALESCE(MAX(prompt_order), 0) + 1 as next_order 
            FROM project_dev_prompts 
            WHERE sprint_id = ?
        ");
        $stmt->execute([$sprintId]);
        $nextOrder = $stmt->fetchColumn();
        
        // Sanitize the prompt text before saving
        $sanitizedPromptText = sanitizePromptText($promptText);

        // Save the prompt
        $stmt = $db->prepare("
            INSERT INTO project_dev_prompts
            (project_id, work_item_id, sprint_id, prompt_text, prompt_order, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $workItem['project_id'],
            $workItemId,
            $sprintId,
            $sanitizedPromptText,
            $nextOrder
        ]);
        
        ?>
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-robot"></i> Generated Prompt
                        <span class="badge bg-secondary ms-2">Pending</span>
                    </h6>
                    <button class="btn btn-sm btn-outline-primary"
                            hx-post="/htmx/generate-work-item-prompt.php"
                            hx-target="#work-item-prompt-<?php echo $workItemId; ?>"
                            hx-swap="innerHTML"
                            hx-vals='{"work_item_id": "<?php echo $workItemId; ?>", "sprint_id": "<?php echo $sprintId; ?>", "regenerate": "true"}'
                            hx-confirm="Regenerate the prompt for this work item?">
                        <i class="bi bi-arrow-clockwise"></i> Regenerate
                    </button>
                </div>
                <pre class="bg-light p-2 rounded mb-0" style="white-space: pre-wrap; font-size: 0.875rem;"><?php echo htmlspecialchars($promptText); ?></pre>
                <small class="text-muted d-block mt-2">
                    Generated just now
                </small>
            </div>
        </div>
        <?php
    }
    
    // Handle regeneration
    if (isset($_POST['regenerate']) && $_POST['regenerate'] === 'true' && $existingPrompt) {
        // Generate new prompt
        $promptText = $promptGenerator->generateWorkItemPrompt($workItem);
        
        if (!$promptText) {
            echo '<div class="alert alert-warning">Could not regenerate prompt for this work item type</div>';
            exit;
        }
        
        // Sanitize the prompt text before updating
        $sanitizedPromptText = sanitizePromptText($promptText);

        // Update existing prompt
        $stmt = $db->prepare("
            UPDATE project_dev_prompts
            SET prompt_text = ?,
                status = 'pending',
                executed_at = NULL,
                completed_at = NULL,
                response_text = NULL,
                error_message = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$sanitizedPromptText, $existingPrompt['id']]);
        
        ?>
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-robot"></i> Regenerated Prompt
                        <span class="badge bg-secondary ms-2">Pending</span>
                    </h6>
                    <button class="btn btn-sm btn-outline-primary"
                            hx-post="/htmx/generate-work-item-prompt.php"
                            hx-target="#work-item-prompt-<?php echo $workItemId; ?>"
                            hx-swap="innerHTML"
                            hx-vals='{"work_item_id": "<?php echo $workItemId; ?>", "sprint_id": "<?php echo $sprintId; ?>", "regenerate": "true"}'
                            hx-confirm="Regenerate the prompt for this work item?">
                        <i class="bi bi-arrow-clockwise"></i> Regenerate
                    </button>
                </div>
                <pre class="bg-light p-2 rounded mb-0" style="white-space: pre-wrap; font-size: 0.875rem;"><?php echo htmlspecialchars($promptText); ?></pre>
                <small class="text-muted d-block mt-2">
                    Regenerated just now
                </small>
            </div>
        </div>
        <?php
    }
    
} catch (Exception $e) {
    error_log("Error generating prompt for work item: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Failed to generate prompt. Please try again.</div>';
}
?>