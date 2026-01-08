<?php
/**
 * HTMX Edit Prompt Endpoint
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
    http_response_code(405);
    exit;
}

$promptId = $_POST['prompt_id'] ?? null;
$mode = $_POST['mode'] ?? 'form'; // 'form' or 'save'

if (!$promptId) {
    echo '<div class="alert alert-danger">Prompt ID is required.</div>';
    exit;
}

$db = getDB();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

try {
    // Get prompt details with project info
    $stmt = $db->prepare("
        SELECT pdp.*, p.id as project_id, p.project_manager_id
        FROM project_dev_prompts pdp
        JOIN projects p ON pdp.project_id = p.id
        WHERE pdp.id = ?
    ");
    $stmt->execute([$promptId]);
    $prompt = $stmt->fetch();
    
    if (!$prompt) {
        echo '<div class="alert alert-danger">Prompt not found.</div>';
        exit;
    }
    
    // Check permissions
    $isProjectManager = $prompt['project_manager_id'] == $currentUserId;
    $isAdmin = $userObj->isAdmin($currentUserId);
    
    if (!$isProjectManager && !$isAdmin) {
        echo '<div class="alert alert-danger">You do not have permission to edit this prompt.</div>';
        exit;
    }
    
    // Check if prompt can be edited (not currently executing)
    if ($prompt['status'] === 'executing') {
        echo '<div class="alert alert-warning">Cannot edit a prompt that is currently executing.</div>';
        exit;
    }
    
    if ($mode === 'save') {
        // Save the edited prompt
        $promptText = $_POST['prompt_text'] ?? '';
        
        if (empty(trim($promptText))) {
            echo '<div class="alert alert-danger">Prompt text cannot be empty.</div>';
            exit;
        }

        // Sanitize the prompt text before saving
        $sanitizedPromptText = sanitizePromptText($promptText);

        // Update the prompt
        $updateStmt = $db->prepare("
            UPDATE project_dev_prompts
            SET prompt_text = ?,
                status = CASE
                    WHEN status IN ('completed', 'failed') THEN 'pending'
                    ELSE status
                END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$sanitizedPromptText, $promptId]);
        
        // Return the updated prompt display
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-check-circle"></i> Prompt updated successfully.';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        
        // Return the updated prompt content
        echo '<div class="prompt-content">';
        echo '<strong>Prompt:</strong><br>';
        echo '<pre class="mb-0 p-2 bg-light rounded">' . htmlspecialchars($promptText) . '</pre>';
        echo '</div>';
        
    } else {
        // Show the edit form
        ?>
        <div class="prompt-edit-form">
            <form hx-post="/htmx/edit-prompt.php"
                  hx-target="#prompt-content-<?php echo $promptId; ?>"
                  hx-swap="innerHTML">
                <input type="hidden" name="prompt_id" value="<?php echo $promptId; ?>">
                <input type="hidden" name="mode" value="save">
                
                <div class="mb-3">
                    <label class="form-label"><strong>Edit Prompt:</strong></label>
                    <textarea name="prompt_text" 
                              class="form-control" 
                              rows="8"
                              required><?php echo htmlspecialchars($prompt['prompt_text']); ?></textarea>
                    <small class="text-muted">
                        <?php if ($prompt['status'] === 'completed' || $prompt['status'] === 'failed'): ?>
                        Note: Saving will reset the status to 'pending' so you can re-run the prompt.
                        <?php endif; ?>
                    </small>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save"></i> Save
                    </button>
                    <button type="button" 
                            class="btn btn-sm btn-secondary"
                            onclick="document.getElementById('prompt-content-<?php echo $promptId; ?>').innerHTML = this.dataset.original"
                            data-original='<div class="prompt-content"><strong>Prompt:</strong><br><pre class="mb-0 p-2 bg-light rounded"><?php echo htmlspecialchars(str_replace("'", "&#39;", $prompt['prompt_text'])); ?></pre></div>'>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}