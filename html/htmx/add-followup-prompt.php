<?php
/**
 * HTMX Add Follow-up Prompt Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Non-POST request to add-followup-prompt.php: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    exit;
}

$parentPromptId = $_POST['parent_prompt_id'] ?? null;
$mode = $_POST['mode'] ?? 'form'; // 'form' or 'save'

// Debug logging
error_log("add-followup-prompt.php - Mode: $mode, Parent ID: $parentPromptId");

if (!$parentPromptId) {
    error_log("ERROR: No parent prompt ID provided");
    echo '<div class="alert alert-danger">Parent prompt ID is required.</div>';
    exit;
}

$db = getDB();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

try {
    // Get parent prompt details with project info including session_id
    // Make sure we get sprint_id from the parent prompt
    $stmt = $db->prepare("
        SELECT pdp.*, p.id as project_id, p.project_manager_id, wi.title as work_item_title,
               pdp.sprint_id, pdp.session_id
        FROM project_dev_prompts pdp
        JOIN projects p ON pdp.project_id = p.id
        LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$parentPromptId]);
    $parentPrompt = $stmt->fetch();
    
    if (!$parentPrompt) {
        echo '<div class="alert alert-danger">Parent prompt not found.</div>';
        exit;
    }
    
    // Debug: Check if sprint_id exists
    error_log("Parent prompt data: " . json_encode($parentPrompt));
    if (empty($parentPrompt['sprint_id'])) {
        error_log("Sprint ID is empty! Parent prompt ID: $parentPromptId, sprint_id value: " . var_export($parentPrompt['sprint_id'], true));
        echo '<div class="alert alert-danger">Sprint ID Required: Parent prompt (ID: ' . $parentPromptId . ') does not have a sprint_id.</div>';
        exit;
    }
    error_log("Sprint ID found: " . $parentPrompt['sprint_id']);
    
    // Check permissions
    $isProjectManager = $parentPrompt['project_manager_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to add follow-up prompts.</div>';
        exit;
    }
    
    if ($mode === 'save') {
        // Save the new follow-up prompt
        $promptText = $_POST['prompt_text'] ?? '';
        $promptTitle = $_POST['prompt_title'] ?? 'Follow-up to: ' . ($parentPrompt['work_item_title'] ?? 'Prompt #' . $parentPromptId);
        
        if (empty(trim($promptText))) {
            echo '<div class="alert alert-danger">Prompt text cannot be empty.</div>';
            exit;
        }
        
        // Get the next prompt order
        $orderStmt = $db->prepare("
            SELECT MAX(prompt_order) as max_order 
            FROM project_dev_prompts 
            WHERE sprint_id = ?
        ");
        $orderStmt->execute([$parentPrompt['sprint_id']]);
        $orderResult = $orderStmt->fetch();
        $maxOrder = $orderResult['max_order'] ?? 0;
        $nextOrder = $maxOrder + 1;
        
        // Sanitize the prompt text before saving
        $sanitizedPromptText = sanitizePromptText($promptText);

        // Insert the follow-up prompt with session_id from parent
        $insertStmt = $db->prepare("
            INSERT INTO project_dev_prompts (
                project_id, sprint_id, work_item_id, parent_prompt_id,
                prompt_order, prompt_text, session_id, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");


        try {
            $insertStmt->execute([
                $parentPrompt['project_id'],
                $parentPrompt['sprint_id'],
                $parentPrompt['work_item_id'],
                $parentPromptId,
                $nextOrder,
                $sanitizedPromptText,
                $parentPrompt['session_id']
            ]);
            
            $newPromptId = $db->lastInsertId();
        } catch (PDOException $e) {
            // Log the full error for debugging
            error_log("Database error in add-followup-prompt.php: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            
            // Check if it's a sprint_id related error
            if (strpos($e->getMessage(), 'sprint') !== false || strpos($e->getMessage(), 'Sprint') !== false) {
                echo '<div class="alert alert-danger">Sprint ID Required: ' . htmlspecialchars($e->getMessage()) . '</div>';
            } else {
                echo '<div class="alert alert-danger">Error saving follow-up prompt: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            exit;
        }
        
        // Success! Return JavaScript to reload the page
        $redirectUrl = '/sprint-dashboard.php?id=' . $parentPrompt['sprint_id'];
        
        echo '<script>window.location.href = "' . $redirectUrl . '";</script>';
        echo '<div class="alert alert-success">Follow-up prompt added successfully. Redirecting...</div>';
        exit;
        
    } else {
        // Show the add follow-up form
        ?>
        <div id="followup-form-<?php echo $parentPromptId; ?>">
            <form method="POST" 
                  action="/htmx/add-followup-prompt.php"
                  onsubmit="return submitFollowUpForm(event, <?php echo $parentPromptId; ?>)">
                <input type="hidden" name="parent_prompt_id" value="<?php echo $parentPromptId; ?>">
                <input type="hidden" name="mode" value="save">
                
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Add Follow-up Prompt</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">
                                Follow-up to: <?php echo htmlspecialchars($parentPrompt['work_item_title'] ?? 'Prompt #' . $parentPromptId); ?>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Follow-up Instructions:</label>
                            <textarea name="prompt_text" 
                                      class="form-control" 
                                      rows="6"
                                      placeholder="Enter follow-up instructions based on the parent prompt's results..."
                                      required></textarea>
                            <small class="text-muted">
                                This prompt will only execute after the parent prompt completes successfully.
                            </small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Add Follow-up
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-secondary"
                                    onclick="document.getElementById('followup-container-<?php echo $parentPromptId; ?>').innerHTML = ''">
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
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}