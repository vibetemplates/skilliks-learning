<?php
/**
 * Project Sprints Page
 * 
 * Manage project sprints and assign work items
 */

$page_title = 'Project Sprints';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Sprint.php';
require_once 'classes/WorkItem.php';

// Require login
requireLogin();

// Get project ID
$projectId = $_GET['project'] ?? null;
if (!$projectId) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$projectObj = new Project();
$project = $projectObj->findById($projectId);

if (!$project) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$currentUserId = getCurrentUserId();
$isMember = $projectObj->isMember($projectId, $currentUserId);
$isCreator = $project['created_by'] == $currentUserId;

$userObj = new User();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);
$isProjectManager = $project['project_manager_id'] == $currentUserId;

// Require member access
if (!$isMember && !$isProjectManagerOrAdmin) {
    setFlashMessage('error', 'You must be a project member to view sprints.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

$canManageSprints = $isCreator || $isProjectManager || $isProjectManagerOrAdmin;

$sprintObj = new Sprint();
$workItemObj = new WorkItem();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $canManageSprints) {
    $db = getDB();
    
    if ($_POST['action'] === 'create_sprint') {
        $sprintData = [
            'project_id' => $projectId,
            'name' => trim($_POST['name'] ?? ''),
            'goal' => trim($_POST['goal'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'status' => $_POST['status'] ?? 'planning',
            'created_by' => $currentUserId
        ];
        
        if (empty($sprintData['name']) || empty($sprintData['start_date']) || empty($sprintData['end_date'])) {
            setFlashMessage('error', 'Sprint name, start date, and end date are required.');
        } else {
            $sprintId = $sprintObj->create($sprintData);
            if ($sprintId) {
                setFlashMessage('success', 'Sprint created successfully!');
            } else {
                setFlashMessage('error', 'Failed to create sprint.');
            }
        }
        header('Location: /project-sprints.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'update_sprint') {
        $sprintId = $_POST['sprint_id'] ?? null;
        $sprintData = [
            'name' => trim($_POST['name'] ?? ''),
            'goal' => trim($_POST['goal'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'status' => $_POST['status'] ?? ''
        ];
        
        if ($sprintId && $sprintObj->update($sprintId, $sprintData)) {
            setFlashMessage('success', 'Sprint updated successfully!');
        } else {
            setFlashMessage('error', 'Failed to update sprint.');
        }
        header('Location: /project-sprints.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'delete_sprint') {
        $sprintId = $_POST['sprint_id'] ?? null;
        
        if ($sprintId && $sprintObj->delete($sprintId)) {
            setFlashMessage('success', 'Sprint deleted successfully!');
        } else {
            setFlashMessage('error', 'Failed to delete sprint.');
        }
        header('Location: /project-sprints.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'add_work_item') {
        $sprintId = $_POST['sprint_id'] ?? null;
        $workItemId = $_POST['work_item_id'] ?? null;
        
        if ($sprintId && $workItemId && $sprintObj->addWorkItem($sprintId, $workItemId, $currentUserId)) {
            setFlashMessage('success', 'Work item added to sprint!');
        } else {
            setFlashMessage('error', 'Failed to add work item to sprint.');
        }
        header('Location: /project-sprints.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'remove_work_item') {
        $sprintId = $_POST['sprint_id'] ?? null;
        $workItemId = $_POST['work_item_id'] ?? null;
        
        if ($sprintId && $workItemId && $sprintObj->removeWorkItem($sprintId, $workItemId)) {
            setFlashMessage('success', 'Work item removed from sprint!');
        } else {
            setFlashMessage('error', 'Failed to remove work item from sprint.');
        }
        header('Location: /project-sprints.php?project=' . $projectId);
        exit;
    }
}

// Get sprints (excluding completed ones)
$db = getDB();
$stmt = $db->prepare("
    SELECT s.*, 
           u.first_name as creator_first_name, 
           u.last_name as creator_last_name,
           (SELECT COUNT(*) FROM sprint_work_items WHERE sprint_id = s.id) as work_item_count,
           (SELECT COUNT(*) FROM work_items wi 
            JOIN sprint_work_items swi ON wi.id = swi.work_item_id 
            WHERE swi.sprint_id = s.id AND wi.status = 'done') as completed_items,
           (SELECT SUM(wi.story_points) FROM work_items wi 
            JOIN sprint_work_items swi ON wi.id = swi.work_item_id 
            WHERE swi.sprint_id = s.id) as total_story_points,
           (SELECT SUM(wi.story_points) FROM work_items wi 
            JOIN sprint_work_items swi ON wi.id = swi.work_item_id 
            WHERE swi.sprint_id = s.id AND wi.status = 'done') as completed_story_points
    FROM sprints s
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.project_id = ? AND s.status != 'completed'
    ORDER BY s.start_date DESC
");
$stmt->execute([$projectId]);
$sprints = $stmt->fetchAll(PDO::FETCH_ASSOC);
$activeSprint = $sprintObj->getActiveSprint($projectId);

// Get available work items
$availableWorkItems = $sprintObj->getAvailableWorkItems($projectId);

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Sprints</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Project Sprints</h1>
        <div>
            <?php if (!empty($project['test_url'])): ?>
                <a href="<?php echo htmlspecialchars($project['test_url']); ?>" target="_blank" class="btn btn-primary me-2">
                    <i class="bi bi-play-circle"></i> Run Project
                </a>
            <?php endif; ?>
            <a href="/completed-sprints.php?project=<?php echo $projectId; ?>" class="btn btn-outline-primary me-2">
                <i class="bi bi-check-circle"></i> Completed Sprints
            </a>
            <?php if ($canManageSprints): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSprintModal">
                    <i class="bi bi-plus-circle"></i> Create Sprint
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="sprint-messages"></div>
    
    <?php if (!empty($activeSprint)): ?>
    <div class="alert alert-info d-flex align-items-center mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Active Sprint:</strong>&nbsp;<?php echo htmlspecialchars($activeSprint['name']); ?> 
        (<?php echo date('M j', strtotime($activeSprint['start_date'])); ?> - <?php echo date('M j, Y', strtotime($activeSprint['end_date'])); ?>)
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="row">
        <!-- Sprints List -->
        <div class="col-lg-6">
            <div class="row">
                <?php if (empty($sprints)): ?>
                    <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-calendar3 text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No sprints have been created for this project yet.</p>
                        <?php if ($canManageSprints): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSprintModal">
                                <i class="bi bi-plus-circle"></i> Create First Sprint
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($sprints as $sprint): ?>
                <div class="col-lg-12 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($sprint['name']); ?>
                                <?php
                                // Use the actual status from the database
                                $displayStatus = $sprint['status'];
                                
                                $statusClasses = [
                                    'planning' => 'warning',
                                    'active' => 'success',
                                    'completed' => 'primary',
                                    'cancelled' => 'dark'
                                ];
                                $badgeClass = $statusClasses[$displayStatus] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?> ms-2"><?php echo ucfirst($displayStatus); ?></span>
                            </h5>
                            <?php if ($canManageSprints): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="editSprint(<?php echo htmlspecialchars(json_encode($sprint)); ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="viewSprintDetails(<?php echo $sprint['id']; ?>)">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
                                        </li>
                                        <?php if ($sprint['status'] === 'planning' || $sprint['status'] === 'active'): ?>
                                        <li>
                                            <button class="dropdown-item" 
                                                    hx-post="/htmx/generate-sprint-prompts.php"
                                                    hx-target="#sprint-prompts-<?php echo $sprint['id']; ?>"
                                                    hx-swap="innerHTML"
                                                    hx-vals='{"sprint_id": "<?php echo $sprint['id']; ?>"}'>
                                                <i class="bi bi-code-slash"></i> Generate Prompts
                                            </button>
                                        </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this sprint?');">
                                                <input type="hidden" name="action" value="delete_sprint">
                                                <input type="hidden" name="sprint_id" value="<?php echo $sprint['id']; ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($sprint['goal']): ?>
                                <p class="text-muted mb-3">
                                    <strong>Goal:</strong> <?php echo htmlspecialchars($sprint['goal']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <i class="bi bi-calendar-range"></i> 
                                <?php echo date('M j, Y', strtotime($sprint['start_date'])); ?> - 
                                <?php echo date('M j, Y', strtotime($sprint['end_date'])); ?>
                            </div>
                            
                            <?php 
                            $progress = $sprintObj->calculateProgress($sprint['id']);
                            $progressPercent = $progress['total_items'] > 0 
                                ? round(($progress['completed_items'] / $progress['total_items']) * 100) 
                                : 0;
                            ?>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $progress['completed_items']; ?>/<?php echo $progress['total_items']; ?> items</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $progressPercent; ?>%">
                                        <?php echo $progressPercent; ?>%
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($progress['total_points'] > 0): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Story Points</span>
                                        <span><?php echo $progress['completed_points'] ?? 0; ?>/<?php echo $progress['total_points']; ?> points</span>
                                    </div>
                                    <?php 
                                    $pointsPercent = round(($progress['completed_points'] / $progress['total_points']) * 100);
                                    ?>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $pointsPercent; ?>%">
                                            <?php echo $pointsPercent; ?>%
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div id="sprint-prompts-<?php echo $sprint['id']; ?>" class="mb-3"></div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Created by <?php echo htmlspecialchars($sprint['creator_first_name'] . ' ' . $sprint['creator_last_name']); ?>
                                </small>
                                <div class="d-flex gap-2">
                                    <?php if ($displayStatus === 'planning' && $canManageSprints): ?>
                                    <button class="btn btn-sm btn-success"
                                            hx-post="/htmx/activate-sprint.php"
                                            hx-vals='{"sprint_id": "<?php echo $sprint['id']; ?>"}'
                                            hx-target="#sprint-messages"
                                            hx-swap="innerHTML"
                                            hx-confirm="Are you sure you want to activate this sprint? This will make it the active sprint for this project.">
                                        <i class="bi bi-play-circle"></i> Activate Sprint
                                    </button>
                                    <?php endif; ?>
                                    <a href="/sprint-dashboard.php?id=<?php echo $sprint['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-kanban"></i> Manage Sprint
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
            </div>
        </div>
        
        <!-- Product Backlog Panel -->
        <div class="col-lg-6">
            <div class="card sticky-top" style="top: 80px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Product Backlog</h5>
                    <span class="badge bg-secondary"><?php echo count($availableWorkItems); ?></span>
                </div>
                <div class="card-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                    <?php if (empty($availableWorkItems)): ?>
                        <p class="text-muted text-center">All work items are assigned to sprints</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($availableWorkItems as $item): ?>
                                <div class="list-group-item" data-work-item-id="<?php echo $item['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php echo $item['type'] == 'bug' ? 'danger' : ($item['type'] == 'story' ? 'primary' : 'info'); ?> me-1">
                                                    <?php echo ucfirst($item['type']); ?>
                                                </span>
                                                <a href="/work-item-detail.php?id=<?php echo $item['id']; ?>" target="_blank" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                            </h6>
                                            <div class="small text-muted">
                                                <?php if ($item['assignee_first_name']): ?>
                                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($item['assignee_first_name'] . ' ' . $item['assignee_last_name']); ?>
                                                <?php else: ?>
                                                    <i class="bi bi-person"></i> Unassigned
                                                <?php endif; ?>
                                                <?php if ($item['story_points']): ?>
                                                    <span class="ms-2"><i class="bi bi-lightning"></i> <?php echo $item['story_points']; ?> pts</span>
                                                <?php endif; ?>
                                                <span class="ms-2">
                                                    <i class="bi bi-flag"></i> 
                                                    <?php 
                                                    $priorityClass = 'secondary';
                                                    if ($item['priority'] == 'critical') $priorityClass = 'danger';
                                                    elseif ($item['priority'] == 'high') $priorityClass = 'warning';
                                                    elseif ($item['priority'] == 'medium') $priorityClass = 'info';
                                                    ?>
                                                    <span class="text-<?php echo $priorityClass; ?>"><?php echo ucfirst($item['priority']); ?></span>
                                                </span>
                                            </div>
                                            <?php if ($canManageSprints && !empty($sprints)): ?>
                                                <div class="mt-2">
                                                    <form method="POST" action="/project-sprints.php?project=<?php echo $projectId; ?>" class="d-flex gap-2">
                                                        <input type="hidden" name="action" value="add_work_item">
                                                        <input type="hidden" name="work_item_id" value="<?php echo $item['id']; ?>">
                                                        <select name="sprint_id" class="form-select form-select-sm" required onchange="this.form.submit()">
                                                            <option value="">Add to sprint...</option>
                                                            <?php foreach ($sprints as $sprint): ?>
                                                                <?php if ($sprint['status'] != 'completed' && $sprint['status'] != 'cancelled'): ?>
                                                                    <option value="<?php echo $sprint['id']; ?>">
                                                                        <?php echo htmlspecialchars($sprint['name']); ?>
                                                                        <?php if ($sprint['status'] == 'active'): ?>(Active)<?php endif; ?>
                                                                    </option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Create Sprint Modal -->
<?php if ($canManageSprints): ?>
<div class="modal fade" id="createSprintModal" tabindex="-1" aria-labelledby="createSprintModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSprintModalLabel">Create New Sprint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_sprint">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sprintName" class="form-label">Sprint Name *</label>
                        <input type="text" class="form-control" id="sprintName" name="name" required 
                               placeholder="e.g., Sprint 1, Q1 Sprint">
                    </div>
                    
                    <div class="mb-3">
                        <label for="sprintGoal" class="form-label">Sprint Goal</label>
                        <textarea class="form-control" id="sprintGoal" name="goal" rows="3" 
                                  placeholder="What do you want to achieve in this sprint?"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="startDate" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="endDate" class="form-label">End Date *</label>
                            <input type="date" class="form-control" id="endDate" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="planning" selected>Planning</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Sprint
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Sprint Modal -->
<div class="modal fade" id="editSprintModal" tabindex="-1" aria-labelledby="editSprintModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSprintModalLabel">Edit Sprint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_sprint">
                <input type="hidden" name="sprint_id" id="editSprintId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editSprintName" class="form-label">Sprint Name *</label>
                        <input type="text" class="form-control" id="editSprintName" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editSprintGoal" class="form-label">Sprint Goal</label>
                        <textarea class="form-control" id="editSprintGoal" name="goal" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editStartDate" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="editStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEndDate" class="form-label">End Date *</label>
                            <input type="date" class="form-control" id="editEndDate" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editStatus" class="form-label">Status</label>
                        <select class="form-select" id="editStatus" name="status">
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Update Sprint
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Sprint Details Modal -->
<div class="modal fade" id="sprintDetailsModal" tabindex="-1" aria-labelledby="sprintDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sprintDetailsModalLabel">Sprint Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sprintDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<style>
.list-group-item {
    transition: all 0.3s ease;
    cursor: move;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.sprint-card {
    min-height: 200px;
}

.sticky-top {
    z-index: 100;
}
</style>

<script>
function editSprint(sprint) {
    document.getElementById('editSprintId').value = sprint.id;
    document.getElementById('editSprintName').value = sprint.name;
    document.getElementById('editSprintGoal').value = sprint.goal || '';
    document.getElementById('editStartDate').value = sprint.start_date;
    document.getElementById('editEndDate').value = sprint.end_date;
    document.getElementById('editStatus').value = sprint.status;
    
    const modal = new bootstrap.Modal(document.getElementById('editSprintModal'));
    modal.show();
}

function viewSprintDetails(sprintId) {
    // Load sprint details via AJAX
    fetch(`/ajax/get-sprint-details.php?sprint_id=${sprintId}&project_id=<?php echo $projectId; ?>`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('sprintDetailsContent').innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById('sprintDetailsModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error loading sprint details:', error);
            alert('Failed to load sprint details');
        });
}

// Set default dates for new sprint
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const twoWeeksLater = new Date(today.getTime() + (14 * 24 * 60 * 60 * 1000));
    
    document.getElementById('startDate').valueAsDate = today;
    document.getElementById('endDate').valueAsDate = twoWeeksLater;
});
</script>

<?php require_once 'includes/footer.php'; ?>