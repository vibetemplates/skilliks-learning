<?php
/**
 * Task Display Page
 * 
 * Shows detailed information about a specific task with assignment details
 */

$page_title = 'Task Details';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Task.php';
require_once 'classes/User.php';
require_once 'classes/Project.php';

// Require login
requireLogin();

// Get task ID from URL
$taskId = $_GET['id'] ?? null;
if (!$taskId) {
    setFlashMessage('error', 'Task not found.');
    header('Location: /tasks.php');
    exit;
}

$taskObj = new Task();
$userObj = new User();
$projectObj = new Project();
$currentUserId = getCurrentUserId();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

// Get task information
$task = $taskObj->findById($taskId);
if (!$task) {
    setFlashMessage('error', 'Task not found.');
    header('Location: /tasks.php');
    exit;
}

// Check access permission
if (!$taskObj->canAccess($taskId, $currentUserId)) {
    setFlashMessage('error', 'Access denied to this task.');
    header('Location: /tasks.php');
    exit;
}

// Handle assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign') {
        $gitBranch = trim($_POST['git_branch'] ?? '');
        if ($taskObj->assignTo($taskId, $currentUserId, $gitBranch)) {
            setFlashMessage('success', 'Task assigned to you successfully!');
        } else {
            setFlashMessage('error', 'Failed to assign task or you are already assigned.');
        }
    } elseif ($_POST['action'] === 'unassign') {
        if ($taskObj->unassign($taskId, $currentUserId)) {
            setFlashMessage('success', 'Task unassigned successfully!');
        } else {
            setFlashMessage('error', 'Failed to unassign task.');
        }
    } elseif ($_POST['action'] === 'update_branch') {
        $gitBranch = trim($_POST['git_branch'] ?? '');
        if ($taskObj->updateGitBranch($taskId, $currentUserId, $gitBranch)) {
            setFlashMessage('success', 'Git branch updated successfully!');
        } else {
            setFlashMessage('error', 'Failed to update Git branch.');
        }
    }
    
    header("Location: /task-display.php?id={$taskId}");
    exit;
}

// Get task assignments
$assignments = $taskObj->getTaskAssignments($taskId);
$isUserAssigned = $taskObj->isUserAssigned($taskId, $currentUserId);
$userAssignment = null;

foreach ($assignments as $assignment) {
    if ($assignment['user_id'] == $currentUserId) {
        $userAssignment = $assignment;
        break;
    }
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    
        

        
        
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/tasks.php">Tasks</a></li>
                    <?php if (!empty($task['project_name'])): ?>
                        <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $task['project_id']; ?>"><?php echo htmlspecialchars($task['project_name']); ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($task['title']); ?></li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo htmlspecialchars($task['title']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/task-edit.php?id=<?php echo $taskId; ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-pencil"></i> Edit Task
                    </a>
                    <a href="/tasks.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Tasks
                    </a>
                </div>
            </div>

            
                <!-- Task Details -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Task Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Status:</strong>
                                    <div>
                                        <span class="badge bg-<?php echo $task['status'] === 'done' ? 'success' : ($task['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Priority:</strong>
                                    <div>
                                        <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Type:</strong>
                                    <div><span class="badge bg-light text-dark"><?php echo ucfirst($task['type']); ?></span></div>
                                </div>
                                <?php if ($task['due_date']): ?>
                                <div class="col-md-6">
                                    <strong>Due Date:</strong>
                                    <div><?php echo date('M j, Y', strtotime($task['due_date'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($task['project_name'])): ?>
                            <div class="mb-3">
                                <strong>Project:</strong>
                                <div>
                                    <a href="/project-detail?id=<?php echo $task['project_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($task['project_name']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <strong>Created by:</strong>
                                <div><?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?></div>
                            </div>
                            
                            <?php if (!empty($task['description'])): ?>
                            <div class="mb-3">
                                <strong>Description:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            
                                <div class="col-md-6">
                                    <strong>Created:</strong>
                                    <div><?php echo date('M j, Y g:i A', strtotime($task['created_at'])); ?></div>
                                </div>
                                <?php if ($task['updated_at'] && $task['updated_at'] !== $task['created_at']): ?>
                                <div class="col-md-6">
                                    <strong>Last Updated:</strong>
                                    <div><?php echo date('M j, Y g:i A', strtotime($task['updated_at'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignment Panel -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Assignments</h5>
                            <span class="badge bg-secondary"><?php echo count($assignments); ?> member<?php echo count($assignments) != 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assignments)): ?>
                                <div class="text-muted text-center py-3">
                                    <i class="bi bi-person-plus"></i><br>
                                    No assignments yet
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($assignments as $assignment): ?>
                                    <div class="list-group-item px-0 <?php echo $assignment['user_id'] == $currentUserId ? 'bg-light' : ''; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                 style="width: 32px; height: 32px; font-size: 0.8em;">
                                                <?php echo strtoupper(substr($assignment['first_name'], 0, 1) . substr($assignment['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold"><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></div>
                                                <small class="text-muted">Assigned <?php echo date('M j', strtotime($assignment['assigned_at'])); ?></small>
                                                <?php if (!empty($assignment['git_branch'])): ?>
                                                <div class="mt-1">
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-git"></i> <?php echo htmlspecialchars($assignment['git_branch']); ?>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Assignment Actions -->
                            <div class="mt-3">
                                <?php if (!$isUserAssigned): ?>
                                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#assignModal">
                                        <i class="bi bi-person-plus"></i> Assign to Me
                                    </button>
                                <?php else: ?>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateBranchModal">
                                            <i class="bi bi-git"></i> Update Git Branch
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="unassign">
                                            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Are you sure you want to unassign yourself from this task?')">
                                                <i class="bi bi-person-dash"></i> Unassign Me
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Comments Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <?php 
                            require_once 'includes/comments-component.php';
                            renderComments('task', $taskId, $task['project_id']);
                            ?>
                        </div>
                    </div>
                </div>
            </div>

</main>

<!-- Assign Task Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignModalLabel">Assign Task to Me</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="git_branch" class="form-label">Git Branch (Optional)</label>
                        <input type="text" class="form-control" id="git_branch" name="git_branch" placeholder="e.g., feature/task-<?php echo $taskId; ?>">
                        <div class="form-text">Specify which Git branch you'll use for this task.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign to Me</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Git Branch Modal -->
<?php if ($isUserAssigned): ?>
<div class="modal fade" id="updateBranchModal" tabindex="-1" aria-labelledby="updateBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateBranchModalLabel">Update Git Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_branch">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="update_git_branch" class="form-label">Git Branch</label>
                        <input type="text" class="form-control" id="update_git_branch" name="git_branch" 
                               value="<?php echo htmlspecialchars($userAssignment['git_branch'] ?? ''); ?>" 
                               placeholder="e.g., feature/task-<?php echo $taskId; ?>">
                        <div class="form-text">Update the Git branch you're using for this task.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>