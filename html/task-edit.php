<?php
/**
 * Task Edit Page
 * 
 * Allows editing of task details
 */

$page_title = 'Edit Task';
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

// Check edit permission (task creator, assignees, or project managers/admins)
$canEdit = $task['reporter_id'] == $currentUserId || 
           $taskObj->isUserAssigned($taskId, $currentUserId) ||
           $isProjectManagerOrAdmin;

if (!$canEdit) {
    setFlashMessage('error', 'You do not have permission to edit this task.');
    header("Location: /task-display.php?id={$taskId}");
    exit;
}

// Handle task update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $type = $_POST['type'] ?? 'task';
    $status = $_POST['status'] ?? $task['status'];
    $due_date = $_POST['due_date'] ?? null;
    
    if (empty($title)) {
        setFlashMessage('error', 'Task title is required.');
    } else {
        $data = [
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'type' => $type,
            'due_date' => $due_date
        ];
        
        $updateResult = $taskObj->update($taskId, $data);
        
        // Update status separately if changed
        if ($status !== $task['status']) {
            $taskObj->updateStatus($taskId, $status);
        }
        
        if ($updateResult !== false) {
            setFlashMessage('success', 'Task updated successfully!');
            header("Location: /task-display.php?id={$taskId}");
            exit;
        } else {
            setFlashMessage('error', 'Failed to update task.');
        }
    }
}

// Get user's projects for dropdown (if applicable)
$userProjects = [];
if (!$task['project_id']) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT p.id, p.name 
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = ?
            ORDER BY p.name
        ");
        $stmt->execute([$currentUserId]);
        $userProjects = $stmt->fetchAll();
    } catch (PDOException $e) {
        $userProjects = [];
    }
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    
        

        
        
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/tasks.php">Tasks</a></li>
                    <li class="breadcrumb-item"><a href="/task-display.php?id=<?php echo $taskId; ?>"><?php echo htmlspecialchars($task['title']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Task</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/task-display.php?id=<?php echo $taskId; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Task
                    </a>
                </div>
            </div>

            
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Task Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update">
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Task Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($task['title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                                </div>
                                
                                
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="todo" <?php echo $task['status'] === 'todo' ? 'selected' : ''; ?>>To Do</option>
                                                <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="done" <?php echo $task['status'] === 'done' ? 'selected' : ''; ?>>Done</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="priority" class="form-label">Priority</label>
                                            <select class="form-select" id="priority" name="priority">
                                                <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                                <option value="medium" <?php echo $task['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                                <option value="critical" <?php echo $task['priority'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="type" class="form-label">Type</label>
                                            <select class="form-select" id="type" name="type">
                                                <option value="task" <?php echo $task['type'] === 'task' ? 'selected' : ''; ?>>Task</option>
                                                <option value="feature" <?php echo $task['type'] === 'feature' ? 'selected' : ''; ?>>Feature</option>
                                                <option value="bug" <?php echo $task['type'] === 'bug' ? 'selected' : ''; ?>>Bug Fix</option>
                                                <option value="improvement" <?php echo $task['type'] === 'improvement' ? 'selected' : ''; ?>>Improvement</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="due_date" class="form-label">Due Date</label>
                                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                                   value="<?php echo $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="/task-display.php?id=<?php echo $taskId; ?>" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check2"></i> Update Task
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Task Information</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($task['project_name'])): ?>
                            <div class="mb-2">
                                <strong>Project:</strong>
                                <div><?php echo htmlspecialchars($task['project_name']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <strong>Created by:</strong>
                                <div><?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?></div>
                            </div>
                            
                            <div class="mb-2">
                                <strong>Created:</strong>
                                <div><?php echo date('M j, Y g:i A', strtotime($task['created_at'])); ?></div>
                            </div>
                            
                            <?php if ($task['updated_at'] && $task['updated_at'] !== $task['created_at']): ?>
                            <div class="mb-2">
                                <strong>Last Updated:</strong>
                                <div><?php echo date('M j, Y g:i A', strtotime($task['updated_at'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <strong>Assignments:</strong>
                                <div>
                                    <span class="badge bg-secondary"><?php echo $taskObj->getAssignmentCount($taskId); ?> member<?php echo $taskObj->getAssignmentCount($taskId) != 1 ? 's' : ''; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

</main>

<?php require_once 'includes/footer.php'; ?>