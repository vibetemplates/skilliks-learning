<?php
/**
 * Tasks Page
 * 
 * Lists and manages tasks for projects
 */

$page_title = 'My Tasks';
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

// Handle task operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Handle task creation
    if ($_POST['action'] === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $projectId = $_POST['project_id'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        $type = $_POST['type'] ?? 'task';
        $dueDate = $_POST['due_date'] ?? null;
        $assignedTo = $_POST['assigned_to'] ?? null;
        
        if ($title && $projectId) {
            // Verify user has access to the project
            if ($projectObj->isMember($projectId, $currentUserId) || $isProjectManagerOrAdmin) {
                $taskData = [
                    'title' => $title,
                    'description' => $description,
                    'project_id' => $projectId,
                    'priority' => $priority,
                    'type' => $type,
                    'due_date' => $dueDate,
                    'assigned_to' => $assignedTo,
                    'created_by' => $currentUserId
                ];
                
                $taskId = $taskObj->create($taskData);
                if ($taskId) {
                    // If assigned to someone, create assignment record
                    if ($assignedTo) {
                        $taskObj->assignTo($taskId, $assignedTo);
                    }
                    setFlashMessage('success', 'Task created successfully!');
                } else {
                    setFlashMessage('error', 'Failed to create task.');
                }
            } else {
                setFlashMessage('error', 'Access denied to this project.');
            }
        } else {
            setFlashMessage('error', 'Title and project are required.');
        }
        
        $redirect_url = $projectId ? "/tasks.php?project={$projectId}" : "/tasks.php";
        header("Location: {$redirect_url}");
        exit;
    }
    
    // Handle task update
    if ($_POST['action'] === 'update_task') {
        $taskId = $_POST['task_id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $type = $_POST['type'] ?? 'task';
        $dueDate = $_POST['due_date'] ?? null;
        
        if ($taskId && $title && $taskObj->canAccess($taskId, $currentUserId)) {
            $updateData = [
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'type' => $type,
                'due_date' => $dueDate
            ];
            
            if ($taskObj->update($taskId, $updateData)) {
                setFlashMessage('success', 'Task updated successfully!');
            } else {
                setFlashMessage('error', 'Failed to update task.');
            }
        } else {
            setFlashMessage('error', 'Access denied or invalid task.');
        }
        
        $redirect_url = $projectId ? "/tasks.php?project={$projectId}" : "/tasks.php";
        header("Location: {$redirect_url}");
        exit;
    }
    if ($_POST['action'] === 'update_status') {
        $taskId = $_POST['task_id'] ?? null;
        $newStatus = $_POST['status'] ?? null;
        
        if ($taskId && $newStatus && $taskObj->canAccess($taskId, getCurrentUserId())) {
            if ($taskObj->updateStatus($taskId, $newStatus)) {
                setFlashMessage('success', 'Task status updated successfully!');
            } else {
                setFlashMessage('error', 'Failed to update task status.');
            }
        } else {
            setFlashMessage('error', 'Access denied or invalid task.');
        }
        
        $redirect_url = $projectId ? "/tasks.php?project={$projectId}" : "/tasks.php";
        header("Location: {$redirect_url}");
        exit;
    }
    
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
        
        $redirect_url = $projectId ? "/tasks.php?project={$projectId}" : "/tasks.php";
        header("Location: {$redirect_url}");
        exit;
    }
    
    if ($_POST['action'] === 'unassign') {
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
        
        $redirect_url = $projectId ? "/tasks.php?project={$projectId}" : "/tasks.php";
        header("Location: {$redirect_url}");
        exit;
    }
}

// Get tasks
try {
    $db = getDB();
    
    // Build base query based on whether we're viewing a specific project
    $baseQuery = "
        SELECT t.*, p.name as project_name, 
               reporter.first_name as creator_first_name, reporter.last_name as creator_last_name,
               assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name
        FROM tasks t
        INNER JOIN projects p ON t.project_id = p.id
        LEFT JOIN users reporter ON t.reporter_id = reporter.id
        LEFT JOIN users assignee ON t.assignee_id = assignee.id
    ";
    
    if ($projectId) {
        // Get all current tasks for the specific project
        $stmt = $db->prepare($baseQuery . "
            WHERE t.project_id = ? AND t.status != 'done'
            ORDER BY t.priority DESC, t.created_at DESC
        ");
        $stmt->execute([$projectId]);
        $currentTasks = $stmt->fetchAll();
        
        // Get completed tasks for the specific project
        $stmt = $db->prepare($baseQuery . "
            WHERE t.project_id = ? AND t.status = 'done'
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute([$projectId]);
        $completedTasks = $stmt->fetchAll();
    } else {
        // Get all current tasks from user's projects
        $stmt = $db->prepare($baseQuery . "
            INNER JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = ? AND pm.status = 'approved' AND t.status != 'done'
            ORDER BY t.priority DESC, t.created_at DESC
        ");
        $stmt->execute([$currentUserId]);
        $currentTasks = $stmt->fetchAll();
        
        // Get completed tasks from user's projects
        $stmt = $db->prepare($baseQuery . "
            INNER JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = ? AND pm.status = 'approved' AND t.status = 'done'
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute([$currentUserId]);
        $completedTasks = $stmt->fetchAll();
    }
    
    // Get only tasks assigned to the current user (for My Tasks tab)
    $stmt = $db->prepare($baseQuery . "
        WHERE t.assignee_id = ? AND t.status != 'done'
        ORDER BY t.priority DESC, t.created_at DESC
    ");
    $stmt->execute([$currentUserId]);
    $myTasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $currentTasks = [];
    $completedTasks = [];
    $myTasks = [];
}

// Get user's projects for dropdown
$userProjects = [];
$projectMembers = [];
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
} else {
    // Get project members for assignment dropdown
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email
            FROM users u
            JOIN project_members pm ON u.id = pm.user_id
            WHERE pm.project_id = ? AND pm.status = 'approved'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$projectId]);
        $projectMembers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $projectMembers = [];
    }
}

require_once 'includes/header.php';
?>

    
        
        
        
        <main class="container-fluid px-4 py-3">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
                    <?php if ($project): ?>
                        <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tasks</li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page">My Tasks</li>
                    <?php endif; ?>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $project ? 'Project Tasks' : 'My Tasks'; ?></h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="bi bi-plus-circle"></i> Add Task
                </button>
            </div>


            <!-- Filter Tabs -->
            <ul class="nav nav-tabs mb-3" id="taskTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="current-tasks-tab" data-bs-toggle="tab" data-bs-target="#current-tasks" type="button" role="tab">
                        Current Tasks <span class="badge bg-secondary ms-1"><?php echo count($currentTasks); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="my-tasks-tab" data-bs-toggle="tab" data-bs-target="#my-tasks" type="button" role="tab">
                        My Tasks <span class="badge bg-primary ms-1"><?php echo count($myTasks); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tasks-tab" data-bs-toggle="tab" data-bs-target="#completed-tasks" type="button" role="tab">
                        Completed Tasks <span class="badge bg-success ms-1"><?php echo count($completedTasks); ?></span>
                    </button>
                </li>
            </ul>

                        <!-- Tab Content -->
            <div class="tab-content" id="taskTabContent">
                <!-- Current Tasks Tab -->
                <div class="tab-pane fade show active" id="current-tasks" role="tabpanel">
                    <?php if (empty($currentTasks)): ?>
                        <div class="alert alert-info fade-in">
                            <i class="bi bi-info-circle"></i>
                            No current tasks in <?php echo $project ? 'this project' : 'your projects'; ?>.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($currentTasks as $task): ?>
                                <div class="list-group-item mb-2">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">
                                            <a href="/task-display.php?id=<?php echo $task['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </a>
                                        </h6>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editTask(<?php echo htmlspecialchars(json_encode($task)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Status
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <input type="hidden" name="status" value="todo">
                                                        <button type="submit" class="dropdown-item <?php echo $task['status'] === 'todo' ? 'active' : ''; ?>">To Do</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <input type="hidden" name="status" value="in_progress">
                                                        <button type="submit" class="dropdown-item <?php echo $task['status'] === 'in_progress' ? 'active' : ''; ?>">In Progress</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <input type="hidden" name="status" value="done">
                                                        <button type="submit" class="dropdown-item <?php echo $task['status'] === 'done' ? 'active' : ''; ?>">Done</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                        </div>
                                    </div>
                                    <p class="card-text small"><?php echo htmlspecialchars($task['description'] ?? 'No description'); ?></p>
                                    <div class="mb-2">
                                        <span class="badge bg-<?php echo $task['status'] === 'done' ? 'success' : ($task['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <span class="badge bg-light text-dark"><?php echo ucfirst($task['type']); ?></span>
                                    </div>
                                    <small class="text-muted d-block">Project: <?php echo htmlspecialchars($task['project_name']); ?></small>
                                    <small class="text-muted d-block">
                                        Created by <?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <?php if ($task['assignee_id']): ?>
                                            <i class="bi bi-person-fill"></i> Assigned to: <?php echo htmlspecialchars($task['assignee_first_name'] . ' ' . $task['assignee_last_name']); ?>
                                        <?php else: ?>
                                            <i class="bi bi-person"></i> Unassigned
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($task['due_date']): ?>
                                        <small class="text-muted d-block">Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Completed Tasks Tab -->
                <div class="tab-pane fade" id="completed-tasks" role="tabpanel">
                    <?php if (empty($completedTasks)): ?>
                        <div class="alert alert-success fade-in">
                            <i class="bi bi-check-circle"></i>
                            No completed tasks yet.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($completedTasks as $task): ?>
                                <div class="list-group-item mb-2 border-success">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="card-title mb-0">
                                            <a href="/task-display.php?id=<?php echo $task['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </a>
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editTask(<?php echo htmlspecialchars(json_encode($task)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                    <p class="card-text small"><?php echo htmlspecialchars($task['description'] ?? 'No description'); ?></p>
                                    <div class="mb-2">
                                        <span class="badge bg-success">Completed</span>
                                        <span class="badge bg-light text-dark"><?php echo ucfirst($task['type']); ?></span>
                                    </div>
                                    <small class="text-muted d-block">Project: <?php echo htmlspecialchars($task['project_name']); ?></small>
                                    <small class="text-muted d-block">
                                        Completed: <?php echo date('M j, Y', strtotime($task['updated_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- My Tasks Tab -->
                <div class="tab-pane fade" id="my-tasks" role="tabpanel">
                    <?php if (empty($myTasks)): ?>
                        <div class="alert alert-info fade-in">
                            <i class="bi bi-check-circle"></i>
                            No tasks currently assigned to you.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($myTasks as $task): ?>
                                <div class="list-group-item mb-2">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">
                                            <a href="/task-display.php?id=<?php echo $task['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </a>
                                        </h6>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editTask(<?php echo htmlspecialchars(json_encode($task)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Status
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                            <input type="hidden" name="status" value="todo">
                                                            <button type="submit" class="dropdown-item <?php echo $task['status'] === 'todo' ? 'active' : ''; ?>">To Do</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                            <input type="hidden" name="status" value="in_progress">
                                                            <button type="submit" class="dropdown-item <?php echo $task['status'] === 'in_progress' ? 'active' : ''; ?>">In Progress</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                            <input type="hidden" name="status" value="done">
                                                            <button type="submit" class="dropdown-item <?php echo $task['status'] === 'done' ? 'active' : ''; ?>">Done</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="card-text small"><?php echo htmlspecialchars($task['description'] ?? 'No description'); ?></p>
                                    <div class="mb-2">
                                        <span class="badge bg-<?php echo $task['status'] === 'done' ? 'success' : ($task['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <span class="badge bg-light text-dark"><?php echo ucfirst($task['type']); ?></span>
                                    </div>
                                    <small class="text-muted d-block">Project: <?php echo htmlspecialchars($task['project_name']); ?></small>
                                    <small class="text-muted d-block">
                                        Created by <?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?>
                                    </small>
                                    <?php if ($task['due_date']): ?>
                                        <small class="text-muted d-block">Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>

</main>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_task">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="task_title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="task_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="task_description" class="form-label">Description</label>
                        <textarea class="form-control" id="task_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="task_project_id" class="form-label">Project *</label>
                            <?php if ($projectId): ?>
                                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                <input type="text" class="form-control" id="task_project_id" value="<?php echo htmlspecialchars($project['name']); ?>" disabled>
                            <?php else: ?>
                                <select class="form-select" id="task_project_id" name="project_id" required>
                                    <option value="">Select Project</option>
                                    <?php foreach ($userProjects as $proj): ?>
                                        <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="task_assigned_to" class="form-label">Assign To</label>
                            <select class="form-select" id="task_assigned_to" name="assigned_to">
                                <option value="">Unassigned</option>
                                <option value="<?php echo $currentUserId; ?>">Me</option>
                                <?php if ($projectId && !empty($projectMembers)): ?>
                                    <optgroup label="Team Members">
                                        <?php foreach ($projectMembers as $member): ?>
                                            <?php if ($member['id'] != $currentUserId): ?>
                                                <option value="<?php echo $member['id']; ?>">
                                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="task_type" class="form-label">Type</label>
                            <select class="form-select" id="task_type" name="type">
                                <option value="task">Task</option>
                                <option value="feature">Feature</option>
                                <option value="bug">Bug</option>
                                <option value="improvement">Improvement</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="task_priority" class="form-label">Priority</label>
                            <select class="form-select" id="task_priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="task_due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="task_due_date" name="due_date">
                        </div>
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

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" id="edit_task_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_task_title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="edit_task_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_task_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_task_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit_task_type" class="form-label">Type</label>
                            <select class="form-select" id="edit_task_type" name="type">
                                <option value="task">Task</option>
                                <option value="feature">Feature</option>
                                <option value="bug">Bug</option>
                                <option value="improvement">Improvement</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="edit_task_priority" class="form-label">Priority</label>
                            <select class="form-select" id="edit_task_priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="edit_task_due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="edit_task_due_date" name="due_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTask(task) {
    document.getElementById('edit_task_id').value = task.id;
    document.getElementById('edit_task_title').value = task.title;
    document.getElementById('edit_task_description').value = task.description || '';
    document.getElementById('edit_task_type').value = task.type || 'task';
    document.getElementById('edit_task_priority').value = task.priority || 'medium';
    document.getElementById('edit_task_due_date').value = task.due_date || '';
    
    const editModal = new bootstrap.Modal(document.getElementById('editTaskModal'));
    editModal.show();
}

<?php if (!$projectId): ?>
// Load project members when project is selected (only on general tasks page)
document.getElementById('task_project_id').addEventListener('change', function() {
    const projectId = this.value;
    const assignSelect = document.getElementById('task_assigned_to');
    
    // Reset to default options
    assignSelect.innerHTML = '<option value="">Unassigned</option><option value="<?php echo $currentUserId; ?>">Me</option>';
    
    if (projectId) {
        // Fetch project members via AJAX
        fetch('/api/project-members.php?project_id=' + projectId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.members.length > 0) {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = 'Team Members';
                    
                    data.members.forEach(member => {
                        if (member.id != <?php echo $currentUserId; ?>) {
                            const option = document.createElement('option');
                            option.value = member.id;
                            option.textContent = member.first_name + ' ' + member.last_name;
                            optgroup.appendChild(option);
                        }
                    });
                    
                    assignSelect.appendChild(optgroup);
                }
            })
            .catch(error => console.error('Error loading project members:', error));
    }
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
