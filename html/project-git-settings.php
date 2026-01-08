<?php
/**
 * Project Git Repository Settings Page
 * 
 * Allows project managers to configure Git repository settings for their projects
 */

$page_title = 'Git Repository Settings';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';

// Require login
requireLogin();

// Get project ID from URL
$projectId = $_GET['project_id'] ?? null;
if (!$projectId) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

// Get project details
$project = $projectObj->findById($projectId);
if (!$project) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

// Check if user is project manager or admin
if (!$userObj->isProjectManagerOrAdmin($currentUserId)) {
    setFlashMessage('error', 'Only project managers and admins can access Git settings.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Get current Git repository settings
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT gr.*, p.git_repository_url 
        FROM projects p
        LEFT JOIN git_repositories gr ON p.id = gr.project_id
        WHERE p.id = ?
    ");
    $stmt->execute([$projectId]);
    $gitSettings = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Git settings query error: " . $e->getMessage());
    $gitSettings = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_git_url') {
        $gitUrl = trim($_POST['git_repository_url'] ?? '');
        
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE projects SET git_repository_url = ? WHERE id = ?");
            $stmt->execute([$gitUrl ?: null, $projectId]);
            
            setFlashMessage('success', 'Git repository URL updated successfully!');
            header('Location: /project-git-settings.php?project_id=' . $projectId);
            exit;
        } catch (PDOException $e) {
            setFlashMessage('error', 'Failed to update Git repository URL.');
        }
    } elseif ($action === 'configure_advanced') {
        $provider = $_POST['provider'] ?? 'other';
        $url = trim($_POST['url'] ?? '');
        $accessToken = trim($_POST['access_token'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $branchNamingPattern = trim($_POST['branch_naming_pattern'] ?? 'feature/{task_id}-{task_slug}');
        $defaultBranch = trim($_POST['default_branch'] ?? 'main');
        $enableWebhooks = isset($_POST['enable_webhooks']) ? 1 : 0;
        $enableBranchProtection = isset($_POST['enable_branch_protection']) ? 1 : 0;
        
        try {
            $db = getDB();
            
            // Check if repository settings already exist
            if ($gitSettings && isset($gitSettings['id'])) {
                // Update existing settings
                $stmt = $db->prepare("
                    UPDATE git_repositories 
                    SET url = ?, provider = ?, access_token = ?, webhook_secret = ?,
                        branch_naming_pattern = ?, default_branch = ?, 
                        enable_webhooks = ?, enable_branch_protection = ?,
                        updated_at = NOW()
                    WHERE project_id = ?
                ");
                $stmt->execute([
                    $url, $provider, $accessToken ?: null, $webhookSecret ?: null,
                    $branchNamingPattern, $defaultBranch, 
                    $enableWebhooks, $enableBranchProtection,
                    $projectId
                ]);
            } else {
                // Insert new settings
                $stmt = $db->prepare("
                    INSERT INTO git_repositories 
                    (project_id, url, provider, access_token, webhook_secret,
                     branch_naming_pattern, default_branch, enable_webhooks, 
                     enable_branch_protection, webhook_url, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/webhooks/git/' . $projectId;
                $stmt->execute([
                    $projectId, $url, $provider, $accessToken ?: null, $webhookSecret ?: null,
                    $branchNamingPattern, $defaultBranch, $enableWebhooks, 
                    $enableBranchProtection, $webhookUrl
                ]);
            }
            
            // Also update the simple URL in projects table
            if ($url) {
                $stmt = $db->prepare("UPDATE projects SET git_repository_url = ? WHERE id = ?");
                $stmt->execute([$url, $projectId]);
            }
            
            setFlashMessage('success', 'Git repository settings saved successfully!');
            header('Location: /project-git-settings.php?project_id=' . $projectId);
            exit;
        } catch (PDOException $e) {
            error_log("Git settings update error: " . $e->getMessage());
            setFlashMessage('error', 'Failed to save Git repository settings.');
        }
    }
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    
        

        
        
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
                    <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Git Settings</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Git Repository Settings</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/project-detail?id=<?php echo $projectId; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Project
                    </a>
                </div>
            </div>

            <!-- Basic Git URL Configuration -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Repository URL</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_git_url">
                        <div class="mb-3">
                            <label for="git_repository_url" class="form-label">Git Repository URL</label>
                            <input type="url" class="form-control" id="git_repository_url" name="git_repository_url" 
                                   placeholder="https://github.com/username/repository.git"
                                   value="<?php echo htmlspecialchars($gitSettings['git_repository_url'] ?? ''); ?>">
                            <small class="form-text text-muted">
                                Enter the full URL of your Git repository (GitHub, GitLab, Bitbucket, etc.)
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Repository URL</button>
                    </form>
                </div>
            </div>

            <!-- Advanced Configuration -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Advanced Repository Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="configure_advanced">
                        
                        
                            <div class="col-md-6 mb-3">
                                <label for="provider" class="form-label">Git Provider</label>
                                <select class="form-select" id="provider" name="provider">
                                    <option value="github" <?php echo ($gitSettings['provider'] ?? '') === 'github' ? 'selected' : ''; ?>>GitHub</option>
                                    <option value="gitlab" <?php echo ($gitSettings['provider'] ?? '') === 'gitlab' ? 'selected' : ''; ?>>GitLab</option>
                                    <option value="bitbucket" <?php echo ($gitSettings['provider'] ?? '') === 'bitbucket' ? 'selected' : ''; ?>>Bitbucket</option>
                                    <option value="other" <?php echo ($gitSettings['provider'] ?? 'other') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="url" class="form-label">Repository URL</label>
                                <input type="url" class="form-control" id="url" name="url" 
                                       value="<?php echo htmlspecialchars($gitSettings['url'] ?? $gitSettings['git_repository_url'] ?? ''); ?>"
                                       placeholder="https://github.com/username/repository.git">
                            </div>
                        </div>
                        
                        
                            <div class="col-md-6 mb-3">
                                <label for="default_branch" class="form-label">Default Branch</label>
                                <input type="text" class="form-control" id="default_branch" name="default_branch" 
                                       value="<?php echo htmlspecialchars($gitSettings['default_branch'] ?? 'main'); ?>"
                                       placeholder="main">
                                <small class="form-text text-muted">Usually 'main' or 'master'</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="branch_naming_pattern" class="form-label">Branch Naming Pattern</label>
                                <input type="text" class="form-control" id="branch_naming_pattern" name="branch_naming_pattern" 
                                       value="<?php echo htmlspecialchars($gitSettings['branch_naming_pattern'] ?? 'feature/{task_id}-{task_slug}'); ?>">
                                <small class="form-text text-muted">Available variables: {task_id}, {task_slug}, {user_id}</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="access_token" class="form-label">Access Token <span class="text-muted">(Optional)</span></label>
                            <input type="password" class="form-control" id="access_token" name="access_token" 
                                   value="<?php echo htmlspecialchars($gitSettings['access_token'] ?? ''); ?>"
                                   placeholder="Personal access token for API access">
                            <small class="form-text text-muted">
                                Required for advanced features like automatic branch creation and PR management
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="webhook_secret" class="form-label">Webhook Secret <span class="text-muted">(Optional)</span></label>
                            <input type="text" class="form-control" id="webhook_secret" name="webhook_secret" 
                                   value="<?php echo htmlspecialchars($gitSettings['webhook_secret'] ?? ''); ?>"
                                   placeholder="Secret for validating webhook requests">
                            <small class="form-text text-muted">
                                Used to verify that webhook requests are coming from your Git provider
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enable_webhooks" name="enable_webhooks" 
                                       <?php echo ($gitSettings['enable_webhooks'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_webhooks">
                                    Enable Webhooks
                                </label>
                                <small class="form-text text-muted d-block">
                                    Automatically sync commits, branches, and pull requests
                                </small>
                            </div>
                            
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="enable_branch_protection" name="enable_branch_protection" 
                                       <?php echo ($gitSettings['enable_branch_protection'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_branch_protection">
                                    Enable Branch Protection
                                </label>
                                <small class="form-text text-muted d-block">
                                    Enforce rules for protected branches (requires access token)
                                </small>
                            </div>
                        </div>
                        
                        <?php if ($gitSettings && isset($gitSettings['webhook_url'])): ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Webhook URL</h6>
                            <p class="mb-2">Configure your Git provider to send webhooks to:</p>
                            <code><?php echo htmlspecialchars($gitSettings['webhook_url']); ?></code>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">Save Advanced Settings</button>
                    </form>
                </div>
            </div>

            <?php if ($gitSettings && isset($gitSettings['last_sync']) && $gitSettings['last_sync']): ?>
            <div class="mt-3 text-muted">
                <small>Last synchronized: <?php echo date('M j, Y g:i A', strtotime($gitSettings['last_sync'])); ?></small>
            </div>
            <?php endif; ?>

</main>

<?php require_once 'includes/footer.php'; ?>