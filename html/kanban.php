<?php
/**
 * Kanban Board Page
 * 
 * Visual task management using drag-and-drop columns
 */

$page_title = 'Kanban Board';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/Task.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$projectId = $_GET['project'] ?? null;
$projectObj = new Project();
$taskObj = new Task();
$userObj = new User();
$project = null;
$currentUserId = getCurrentUserId();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

if ($projectId) {
    $project = $projectObj->findById($projectId);
    if (!$project || !$projectObj->isMember($projectId, getCurrentUserId())) {
        setFlashMessage('error', 'Access denied to this project.');
        header('Location: /projects.php');
        exit;
    }
}

// Handle AJAX requests for task status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task_status') {
    header('Content-Type: application/json');
    
    $taskId = $_POST['task_id'] ?? null;
    $newStatus = $_POST['status'] ?? null;
    
    if ($taskId && $newStatus && $taskObj->canAccess($taskId, getCurrentUserId())) {
        $result = $taskObj->updateStatus($taskId, $newStatus);
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Access denied or invalid parameters']);
    }
    exit;
}

// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign') {
        $taskId = $_POST['task_id'] ?? null;
        $gitBranch = trim($_POST['git_branch'] ?? '');
        
        if ($taskId && $taskObj->canAccess($taskId, $currentUserId)) {
            if ($taskObj->assignTo($taskId, $currentUserId, $gitBranch)) {
                setFlashMessage('success', 'Task assigned to you successfully!');
            } else {
                setFlashMessage('error', 'Failed to assign task or you are already assigned.');
            }
        } else {
            setFlashMessage('error', 'Access denied or invalid task.');
        }
    } elseif ($_POST['action'] === 'unassign') {
        $taskId = $_POST['task_id'] ?? null;
        
        if ($taskId && $taskObj->canAccess($taskId, $currentUserId)) {
            if ($taskObj->unassign($taskId, $currentUserId)) {
                setFlashMessage('success', 'Task unassigned successfully!');
            } else {
                setFlashMessage('error', 'Failed to unassign task.');
            }
        } else {
            setFlashMessage('error', 'Access denied or invalid task.');
        }
    }
    
    $redirect_url = $projectId ? "/kanban.php?project={$projectId}" : "/kanban.php";
    header("Location: {$redirect_url}");
    exit;
}

// Get tasks based on context
if ($projectId) {
    $allTasks = $taskObj->findByProject($projectId);
} else {
    $allTasks = $taskObj->findAll();
}

// Group tasks by status with role-based filtering
$tasksByStatus = [
    'todo' => [],
    'in_progress' => [],
    'done' => []
];

foreach ($allTasks as $task) {
    if (isset($tasksByStatus[$task['status']])) {
        // Apply role-based filtering
        $isUserAssignedToTask = $taskObj->isUserAssigned($task['id'], $currentUserId);
        $showTask = $isProjectManagerOrAdmin || 
                   $isUserAssignedToTask || 
                   (isset($task['assignment_count']) && $task['assignment_count'] == 0); // Unassigned tasks visible to everyone
        
        if ($showTask) {
            $tasksByStatus[$task['status']][] = $task;
        }
    }
}

// Get user's projects for dropdown
$userProjects = [];
if (!$projectId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT p.id, p.name 
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = ?
            ORDER BY p.name
        ");
        $stmt->execute([getCurrentUserId()]);
        $userProjects = $stmt->fetchAll();
    } catch (PDOException $e) {
        $userProjects = [];
    }
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
            <!-- Breadcrumb -->
            <?php if ($project): ?>
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
                    <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kanban Board</li>
                </ol>
            </nav>
            <?php endif; ?>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-kanban"></i> Kanban Board
                    <?php if ($project): ?>
                        - <?php echo htmlspecialchars($project['name']); ?>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if (!$projectId): ?>
                    <div class="me-3">
                        <select class="form-select" id="projectFilter" onchange="filterByProject(this.value)">
                            <option value="">All Projects</option>
                            <?php foreach ($userProjects as $proj): ?>
                                <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="bi bi-plus-circle"></i> New Task
                    </button>
                </div>
            </div>


            <!-- Kanban Board -->
            <div class="row g-3">
                <!-- To Do Column -->
                <div class="col-md-4">
                    <div class="kanban-column" data-status="todo" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <div class="kanban-header">
                            <i class="bi bi-clock"></i> To Do
                            <span class="badge bg-light text-dark ms-2"><?php echo count($tasksByStatus['todo']); ?></span>
                        </div>
                        
                        <div class="kanban-body">
                            <?php if (empty($tasksByStatus['todo'])): ?>
                                <div class="empty-column">
                                    <i class="bi bi-inbox"></i>
                                    <p>No tasks in To Do</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasksByStatus['todo'] as $task): ?>
                                <?php $isUserAssignedToTask = $taskObj->isUserAssigned($task['id'], $currentUserId); ?>
                                <div class="kanban-task task-priority-<?php echo $task['priority']; ?>" 
                                     draggable="true" 
                                     ondragstart="drag(event)" 
                                     data-task-id="<?php echo $task['id']; ?>">
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 flex-grow-1">
                                            <a href="/task-display.php?id=<?php echo $task['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </a>
                                        </h6>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="/task-display.php?id=<?php echo $task['id']; ?>"><i class="bi bi-eye"></i> View</a></li>
                                                <li><a class="dropdown-item" href="/task-edit.php?id=<?php echo $task['id']; ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><button class="dropdown-item" onclick="moveTaskToProgress(<?php echo $task['id']; ?>)"><i class="bi bi-play"></i> Start</button></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="small text-muted mb-2"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : ($task['priority'] === 'critical' ? 'dark' : 'info')); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <span class="badge bg-secondary"><?php echo ucfirst($task['type']); ?></span>
                                        <?php if (isset($task['assignment_count']) && $task['assignment_count'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $task['assignment_count']; ?> assigned</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($task['project_name'])): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-folder"></i> <?php echo htmlspecialchars($task['project_name']); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['due_date']): ?>
                                        <small class="text-muted d-block mb-2">
                                            <i class="bi bi-calendar"></i> Due: <?php echo date('M j', strtotime($task['due_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <!-- Assignment Actions -->
                                    <div class="mt-2">
                                        <?php if (!$isUserAssignedToTask): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100" 
                                                    data-bs-toggle="modal" data-bs-target="#assignModal<?php echo $task['id']; ?>">
                                                <i class="bi bi-person-plus"></i> Assign to me
                                            </button>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-primary"><i class="bi bi-person-check"></i> You are assigned</small>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="unassign">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Unassign">
                                                        <i class="bi bi-person-dash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- In Progress Column -->
                <div class="col-md-4">
                    <div class="kanban-column" data-status="in_progress" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <div class="kanban-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="bi bi-gear"></i> In Progress
                            <span class="badge bg-light text-dark ms-2"><?php echo count($tasksByStatus['in_progress']); ?></span>
                        </div>
                        
                        <div class="kanban-body">
                            <?php if (empty($tasksByStatus['in_progress'])): ?>
                                <div class="empty-column">
                                    <i class="bi bi-gear"></i>
                                    <p>No tasks in progress</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasksByStatus['in_progress'] as $task): ?>
                                <?php $isUserAssignedToTask = $taskObj->isUserAssigned($task['id'], $currentUserId); ?>
                                <div class="kanban-task task-priority-<?php echo $task['priority']; ?> border-warning" 
                                     draggable="true" 
                                     ondragstart="drag(event)" 
                                     data-task-id="<?php echo $task['id']; ?>">
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 flex-grow-1">
                                            <a href="/task-display.php?id=<?php echo $task['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </a>
                                        </h6>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="/task-display.php?id=<?php echo $task['id']; ?>"><i class="bi bi-eye"></i> View</a></li>
                                                <li><a class="dropdown-item" href="/task-edit.php?id=<?php echo $task['id']; ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><button class="dropdown-item" onclick="moveTaskToDone(<?php echo $task['id']; ?>)"><i class="bi bi-check"></i> Complete</button></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="small text-muted mb-2"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <span class="badge bg-warning">In Progress</span>
                                        <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : ($task['priority'] === 'critical' ? 'dark' : 'info')); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <?php if (isset($task['assignment_count']) && $task['assignment_count'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $task['assignment_count']; ?> assigned</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($task['project_name'])): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-folder"></i> <?php echo htmlspecialchars($task['project_name']); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['due_date']): ?>
                                        <small class="text-muted d-block mb-2">
                                            <i class="bi bi-calendar"></i> Due: <?php echo date('M j', strtotime($task['due_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <!-- Assignment Actions -->
                                    <div class="mt-2">
                                        <?php if (!$isUserAssignedToTask): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100" 
                                                    data-bs-toggle="modal" data-bs-target="#assignModal<?php echo $task['id']; ?>">
                                                <i class="bi bi-person-plus"></i> Assign to me
                                            </button>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-primary"><i class="bi bi-person-check"></i> You are assigned</small>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="unassign">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Unassign">
                                                        <i class="bi bi-person-dash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Done Column -->
                <div class="col-md-4">
                    <div class="kanban-column" data-status="done" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <div class="kanban-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="bi bi-check-circle"></i> Done
                            <span class="badge bg-light text-dark ms-2"><?php echo count($tasksByStatus['done']); ?></span>
                        </div>
                        
                        <div class="kanban-body">
                            <?php if (empty($tasksByStatus['done'])): ?>
                                <div class="empty-column">
                                    <i class="bi bi-check-circle"></i>
                                    <p>No completed tasks</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasksByStatus['done'] as $task): ?>
                                <div class="kanban-task task-priority-<?php echo $task['priority']; ?> border-success" 
                                     draggable="true" 
                                     ondragstart="drag(event)" 
                                     data-task-id="<?php echo $task['id']; ?>">
                                    
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 flex-grow-1">
                                            <a href="/task-display.php?id=<?php echo $task['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </a>
                                        </h6>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="/task-display.php?id=<?php echo $task['id']; ?>"><i class="bi bi-eye"></i> View</a></li>
                                                <li><a class="dropdown-item" href="/task-edit.php?id=<?php echo $task['id']; ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($task['description'])): ?>
                                        <p class="small text-muted mb-2"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <span class="badge bg-success">Completed</span>
                                        <span class="badge bg-secondary"><?php echo ucfirst($task['type']); ?></span>
                                        <?php if (isset($task['assignment_count']) && $task['assignment_count'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $task['assignment_count']; ?> assigned</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($task['project_name'])): ?>
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-folder"></i> <?php echo htmlspecialchars($task['project_name']); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <small class="text-muted d-block">
                                        <i class="bi bi-check"></i> Completed: <?php echo date('M j', strtotime($task['updated_at'])); ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

</main>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createTaskModalLabel">Create New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/tasks.php">
                <input type="hidden" name="action" value="create">
                <?php if ($projectId): ?>
                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                <?php endif; ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Task Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="task">Task</option>
                                    <option value="feature">Feature</option>
                                    <option value="bug">Bug Fix</option>
                                    <option value="improvement">Improvement</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php if (!$projectId && !empty($userProjects)): ?>
                    <div class="mb-3">
                        <label for="project_id" class="form-label">Project</label>
                        <select class="form-select" id="project_id" name="project_id">
                            <option value="">Personal Task</option>
                            <?php foreach ($userProjects as $proj): ?>
                                <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="due_date" class="form-label">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assignment Modals -->
<?php foreach ($allTasks as $task): ?>
    <?php if (!$taskObj->isUserAssigned($task['id'], $currentUserId)): ?>
    <div class="modal fade" id="assignModal<?php echo $task['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Task to Me</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                    <div class="modal-body">
                        <p><strong>Task:</strong> <?php echo htmlspecialchars($task['title']); ?></p>
                        <div class="mb-3">
                            <label for="git_branch<?php echo $task['id']; ?>" class="form-label">Git Branch (Optional)</label>
                            <input type="text" class="form-control" id="git_branch<?php echo $task['id']; ?>" name="git_branch" placeholder="e.g., feature/task-<?php echo $task['id']; ?>">
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
    <?php endif; ?>
<?php endforeach; ?>

<style>
/* Enhanced Kanban Styling */
.kanban-column {
    min-height: 500px;
    padding: 0;
    transition: all 0.3s ease;
}

.kanban-column.drag-over {
    background: var(--primary-light) !important;
    transform: scale(1.02);
}

.kanban-task {
    cursor: move;
    margin: 10px;
    padding: 15px;
    transition: all 0.3s ease;
}

.kanban-task.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}

.kanban-task.drag-preview {
    position: fixed;
    pointer-events: none;
    z-index: 1000;
    transform: rotate(5deg);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.kanban-header {
    padding: 15px;
    color: white;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.kanban-body {
    padding: 10px 5px;
    min-height: 450px;
}

.task-priority-critical {
    border-left: 4px solid var(--danger-color) !important;
}

.task-priority-high {
    border-left: 4px solid var(--warning-color) !important;
}

.task-priority-medium {
    border-left: 4px solid var(--info-color) !important;
}

.task-priority-low {
    border-left: 4px solid var(--success-color) !important;
}

/* Drop zone indicator */
.drop-indicator {
    height: 3px;
    background: var(--primary-color);
    margin: 5px 10px;
    border-radius: 2px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.drop-indicator.active {
    opacity: 1;
}

/* Loading state */
.kanban-column.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Empty state styling */
.empty-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    padding: 40px;
}

.empty-column i {
    font-size: 3rem;
    color: var(--font-color-light);
    margin-bottom: 15px;
}

.empty-column p {
    color: var(--font-color-light);
    font-size: 16px;
}
</style>

<script>
// Enhanced Drag and Drop functionality
let draggedTask = null;
let dragPreview = null;
let dropIndicator = null;
let currentDropTarget = null;

// Initialize drag and drop when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create drop indicator element
    dropIndicator = document.createElement('div');
    dropIndicator.className = 'drop-indicator';
    
    // Add touch support for mobile devices
    addTouchSupport();
    
    // Initialize sortable behavior
    initializeSortable();
});

function allowDrop(ev) {
    ev.preventDefault();
    const column = ev.currentTarget;
    
    // Only add drag-over class to the column, not individual tasks
    if (column.classList.contains('kanban-column')) {
        column.classList.add('drag-over');
        
        // Show drop indicator
        if (currentDropTarget !== column) {
            hideDropIndicator();
            currentDropTarget = column;
        }
        
        const afterElement = getDragAfterElement(column, ev.clientY);
        const body = column.querySelector('.kanban-body') || column;
        
        if (afterElement == null) {
            body.appendChild(dropIndicator);
        } else {
            body.insertBefore(dropIndicator, afterElement);
        }
        dropIndicator.classList.add('active');
    }
}

function drag(ev) {
    draggedTask = ev.target.closest('.kanban-task');
    if (!draggedTask) return;
    
    draggedTask.classList.add('dragging');
    ev.dataTransfer.effectAllowed = 'move';
    ev.dataTransfer.setData('text/html', draggedTask.innerHTML);
    ev.dataTransfer.setData('taskId', draggedTask.getAttribute('data-task-id'));
    
    // Create custom drag preview
    createDragPreview(draggedTask);
}

function drop(ev) {
    ev.preventDefault();
    ev.stopPropagation();
    
    const column = ev.currentTarget;
    column.classList.remove('drag-over');
    hideDropIndicator();
    
    if (!draggedTask) return;
    
    draggedTask.classList.remove('dragging');
    removeDragPreview();
    
    const taskId = draggedTask.getAttribute('data-task-id');
    const oldStatus = findTaskStatus(draggedTask);
    const newStatus = column.getAttribute('data-status');
    
    // Only update if status actually changed
    if (taskId && newStatus && oldStatus !== newStatus) {
        // Show loading state
        column.classList.add('loading');
        
        // Optimistically move the task in the UI
        const afterElement = getDragAfterElement(column, ev.clientY);
        const body = column.querySelector('.kanban-body') || column;
        
        if (afterElement == null) {
            body.appendChild(draggedTask);
        } else {
            body.insertBefore(draggedTask, afterElement);
        }
        
        // Update the server
        updateTaskStatus(taskId, newStatus, column);
    }
    
    draggedTask = null;
    currentDropTarget = null;
}

// Get the element after which the dragged element should be inserted
function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.kanban-task:not(.dragging)')];
    
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

// Find the current status of a task based on its parent column
function findTaskStatus(taskElement) {
    const column = taskElement.closest('.kanban-column');
    return column ? column.getAttribute('data-status') : null;
}

// Create custom drag preview
function createDragPreview(element) {
    dragPreview = element.cloneNode(true);
    dragPreview.classList.add('drag-preview');
    dragPreview.style.width = element.offsetWidth + 'px';
    document.body.appendChild(dragPreview);
    
    // Update preview position on mouse move
    document.addEventListener('mousemove', updateDragPreview);
}

function updateDragPreview(ev) {
    if (dragPreview) {
        dragPreview.style.left = (ev.clientX + 10) + 'px';
        dragPreview.style.top = (ev.clientY + 10) + 'px';
    }
}

function removeDragPreview() {
    if (dragPreview) {
        document.removeEventListener('mousemove', updateDragPreview);
        dragPreview.remove();
        dragPreview = null;
    }
}

function hideDropIndicator() {
    if (dropIndicator) {
        dropIndicator.classList.remove('active');
        if (dropIndicator.parentNode) {
            dropIndicator.parentNode.removeChild(dropIndicator);
        }
    }
}

// Handle drag end event
document.addEventListener('dragend', function(ev) {
    if (ev.target.classList.contains('kanban-task')) {
        ev.target.classList.remove('dragging');
        removeDragPreview();
        hideDropIndicator();
        
        // Remove drag-over class from all columns
        document.querySelectorAll('.kanban-column').forEach(col => {
            col.classList.remove('drag-over');
        });
    }
});

// Handle drag leave to remove visual feedback
document.addEventListener('dragleave', function(ev) {
    if (ev.target.classList.contains('kanban-column')) {
        // Only remove class if we're actually leaving the column
        const rect = ev.target.getBoundingClientRect();
        if (ev.clientX < rect.left || ev.clientX > rect.right || 
            ev.clientY < rect.top || ev.clientY > rect.bottom) {
            ev.target.classList.remove('drag-over');
        }
    }
});

// Enhanced AJAX function with optimistic UI updates
function updateTaskStatus(taskId, newStatus, columnElement) {
    const formData = new FormData();
    formData.append('action', 'update_task_status');
    formData.append('task_id', taskId);
    formData.append('status', newStatus);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update task count badges
            updateColumnCounts();
            
            // Remove loading state
            if (columnElement) {
                columnElement.classList.remove('loading');
            }
            
            // Show success feedback
            showNotification('Task moved successfully', 'success');
        } else {
            // Revert the UI change on error
            showNotification('Failed to update task: ' + (data.error || 'Unknown error'), 'error');
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Failed to update task status', 'error');
        window.location.reload();
    });
}

// Update column task counts
function updateColumnCounts() {
    ['todo', 'in_progress', 'done'].forEach(status => {
        const column = document.querySelector(`[data-status="${status}"]`);
        if (column) {
            const count = column.querySelectorAll('.kanban-task').length;
            const badge = column.querySelector('.kanban-header .badge');
            if (badge) {
                badge.textContent = count;
            }
        }
    });
}

// Show notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Touch support for mobile devices
function addTouchSupport() {
    let touchItem = null;
    let touchOffset = { x: 0, y: 0 };
    
    document.addEventListener('touchstart', function(e) {
        const task = e.target.closest('.kanban-task');
        if (task) {
            touchItem = task;
            const touch = e.touches[0];
            const rect = task.getBoundingClientRect();
            touchOffset.x = touch.clientX - rect.left;
            touchOffset.y = touch.clientY - rect.top;
            task.style.opacity = '0.8';
        }
    });
    
    document.addEventListener('touchmove', function(e) {
        if (!touchItem) return;
        
        e.preventDefault();
        const touch = e.touches[0];
        
        // Move the element
        touchItem.style.position = 'fixed';
        touchItem.style.zIndex = '1000';
        touchItem.style.left = (touch.clientX - touchOffset.x) + 'px';
        touchItem.style.top = (touch.clientY - touchOffset.y) + 'px';
        
        // Find drop target
        const dropTarget = document.elementFromPoint(touch.clientX, touch.clientY);
        const column = dropTarget?.closest('.kanban-column');
        
        if (column && column !== touchItem.parentElement) {
            column.classList.add('drag-over');
        }
    });
    
    document.addEventListener('touchend', function(e) {
        if (!touchItem) return;
        
        const touch = e.changedTouches[0];
        const dropTarget = document.elementFromPoint(touch.clientX, touch.clientY);
        const column = dropTarget?.closest('.kanban-column');
        
        // Reset styles
        touchItem.style.position = '';
        touchItem.style.zIndex = '';
        touchItem.style.left = '';
        touchItem.style.top = '';
        touchItem.style.opacity = '';
        
        if (column && column !== touchItem.parentElement) {
            const taskId = touchItem.getAttribute('data-task-id');
            const newStatus = column.getAttribute('data-status');
            
            if (taskId && newStatus) {
                column.appendChild(touchItem);
                updateTaskStatus(taskId, newStatus, column);
            }
        }
        
        // Clean up
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        touchItem = null;
    });
}

// Initialize sortable within columns
function initializeSortable() {
    // This allows reordering tasks within the same column
    const columns = document.querySelectorAll('.kanban-column');
    
    columns.forEach(column => {
        const body = column.querySelector('.kanban-body');
        if (!body) return;
        
        // Make tasks sortable within the same column
        let draggedElement = null;
        
        body.addEventListener('dragstart', function(e) {
            if (e.target.classList.contains('kanban-task')) {
                draggedElement = e.target;
            }
        });
        
        body.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(body, e.clientY);
            if (afterElement == null) {
                body.appendChild(draggedElement);
            } else {
                body.insertBefore(draggedElement, afterElement);
            }
        });
    });
}

// Quick action functions with animation
function moveTaskToProgress(taskId) {
    animateTaskMove(taskId, 'in_progress');
}

function moveTaskToDone(taskId) {
    animateTaskMove(taskId, 'done');
}

function animateTaskMove(taskId, newStatus) {
    const task = document.querySelector(`[data-task-id="${taskId}"]`);
    const targetColumn = document.querySelector(`[data-status="${newStatus}"]`);
    
    if (task && targetColumn) {
        // Add animation class
        task.style.transition = 'all 0.5s ease';
        task.style.transform = 'scale(0.95)';
        task.style.opacity = '0.7';
        
        setTimeout(() => {
            updateTaskStatus(taskId, newStatus, targetColumn);
        }, 200);
    } else {
        updateTaskStatus(taskId, newStatus);
    }
}

// Project filter function
function filterByProject(projectId) {
    if (projectId) {
        window.location.href = '/kanban.php?project=' + projectId;
    } else {
        window.location.href = '/kanban.php';
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Press 'n' to open new task modal
    if (e.key === 'n' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        const activeElement = document.activeElement;
        if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
            e.preventDefault();
            const modal = document.getElementById('createTaskModal');
            if (modal) {
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>