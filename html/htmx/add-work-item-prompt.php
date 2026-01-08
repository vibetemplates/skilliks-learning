<?php
/**
 * HTMX Add Work Item Prompt Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';
require_once '../classes/Sprint.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$workItemId = $_POST['work_item_id'] ?? null;
$sprintId = $_POST['sprint_id'] ?? null;
$mode = $_POST['mode'] ?? 'form'; // 'form' or 'save'

if (!$workItemId || !$sprintId) {
    echo '<div class="alert alert-danger">Work item ID and Sprint ID are required.</div>';
    exit;
}

$db = getDB();
$projectObj = new Project();
$userObj = new User();
$sprintObj = new Sprint();
$currentUserId = getCurrentUserId();

try {
    // Get work item and sprint details
    $stmt = $db->prepare("
        SELECT wi.*, s.project_id, s.name as sprint_name, p.project_manager_id
        FROM work_items wi
        JOIN sprints s ON wi.sprint_id = s.id
        JOIN projects p ON s.project_id = p.id
        WHERE wi.id = ? AND s.id = ?
    ");
    $stmt->execute([$workItemId, $sprintId]);
    $workItem = $stmt->fetch();
    
    if (!$workItem) {
        echo '<div class="alert alert-danger">Work item not found or does not belong to this sprint.</div>';
        exit;
    }
    
    // Check permissions
    $isProjectManager = $workItem['project_manager_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to add prompts to this work item.</div>';
        exit;
    }
    
    if ($mode === 'save') {
        // Save the new prompt
        $promptText = $_POST['prompt_text'] ?? '';
        $promptTitle = 'Manual prompt for: ' . $workItem['title'];
        
        if (empty(trim($promptText))) {
            echo '<div class="alert alert-danger">Prompt text cannot be empty.</div>';
            exit;
        }
        
        // Get the next prompt order
        $orderStmt = $db->prepare("
            SELECT COALESCE(MAX(prompt_order), 0) + 1 as next_order 
            FROM project_dev_prompts 
            WHERE sprint_id = ?
        ");
        $orderStmt->execute([$sprintId]);
        $nextOrder = $orderStmt->fetch()['next_order'];
        
        // Sanitize the prompt text before saving
        $sanitizedPromptText = sanitizePromptText($promptText);

        // Insert the prompt
        $insertStmt = $db->prepare("
            INSERT INTO project_dev_prompts
            (project_id, sprint_id, work_item_id, prompt_text, prompt_order, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $insertStmt->execute([
            $workItem['project_id'],
            $sprintId,
            $workItemId,
            $sanitizedPromptText,
            $nextOrder
        ]);
        
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-check-circle"></i> Prompt added successfully!';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        echo '<script>setTimeout(function() { window.location.reload(); }, 1500);</script>';
        
    } else {
        // Show the add prompt form
        ?>
        <div id="add-prompt-form-<?php echo $workItemId; ?>">
            <form hx-post="/htmx/add-work-item-prompt.php"
                  hx-target="#add-prompt-form-<?php echo $workItemId; ?>"
                  hx-swap="innerHTML">
                <input type="hidden" name="work_item_id" value="<?php echo $workItemId; ?>">
                <input type="hidden" name="sprint_id" value="<?php echo $sprintId; ?>">
                <input type="hidden" name="mode" value="save">
                
                <div class="card mt-2">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">Add Prompt for: <?php echo htmlspecialchars($workItem['title']); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="prompt_text_<?php echo $workItemId; ?>" class="form-label">Prompt Text <span class="text-danger">*</span></label>
                            <textarea class="form-control" 
                                      id="prompt_text_<?php echo $workItemId; ?>" 
                                      name="prompt_text" 
                                      rows="6" 
                                      required></textarea>
                            <small class="form-text text-muted">
                                This prompt will be sent to the development system for this work item.
                            </small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add Prompt
                            </button>
                            <button type="button" 
                                    class="btn btn-secondary" 
                                    onclick="document.getElementById('work-item-prompt-<?php echo $workItemId; ?>').innerHTML = '';">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
} catch (Exception $e) {
    error_log("Error in add-work-item-prompt.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error adding prompt: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>