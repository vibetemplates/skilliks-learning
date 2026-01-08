<?php
/**
 * Get Sprint Details AJAX Endpoint
 * 
 * Returns HTML for sprint details modal content
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Sprint.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

// Require login
if (!isLoggedIn()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$sprintId = $_GET['sprint_id'] ?? null;
$projectId = $_GET['project_id'] ?? null;

if (!$sprintId || !$projectId) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit;
}

$currentUserId = getCurrentUserId();
$projectObj = new Project();
$userObj = new User();
$sprintObj = new Sprint();

// Check access
$isMember = $projectObj->isMember($projectId, $currentUserId);
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

if (!$isMember && !$isProjectManagerOrAdmin) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$project = $projectObj->findById($projectId);
$canManageSprints = $sprintObj->canManageSprints($projectId, $currentUserId);

// Get sprint details
$sprint = $sprintObj->getById($sprintId);
if (!$sprint || $sprint['project_id'] != $projectId) {
    echo '<div class="alert alert-danger">Sprint not found</div>';
    exit;
}

// Get work items in sprint
$workItems = $sprintObj->getWorkItems($sprintId);
$availableWorkItems = $sprintObj->getAvailableWorkItems($projectId);

// Group work items by status
$workItemsByStatus = [
    'todo' => [],
    'in_progress' => [],
    'done' => []
];

foreach ($workItems as $item) {
    $status = $item['status'] ?? 'todo';
    if (!isset($workItemsByStatus[$status])) {
        $workItemsByStatus[$status] = [];
    }
    $workItemsByStatus[$status][] = $item;
}
?>

<div class="container-fluid p-0">
    <div class="row">
        <div class="col-12">
            <h4><?php echo htmlspecialchars($sprint['name']); ?></h4>
            <?php if ($sprint['goal']): ?>
                <p class="text-muted"><?php echo htmlspecialchars($sprint['goal']); ?></p>
            <?php endif; ?>
            
            <div class="mb-3">
                <i class="bi bi-calendar-range"></i> 
                <?php echo date('M j, Y', strtotime($sprint['start_date'])); ?> - 
                <?php echo date('M j, Y', strtotime($sprint['end_date'])); ?>
                <span class="badge bg-<?php echo $sprint['status'] == 'active' ? 'primary' : 'secondary'; ?> ms-2">
                    <?php echo ucfirst($sprint['status']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <?php if ($canManageSprints && !empty($availableWorkItems)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <form method="POST" action="/project-sprints.php?project=<?php echo $projectId; ?>" class="d-flex gap-2">
                <input type="hidden" name="action" value="add_work_item">
                <input type="hidden" name="sprint_id" value="<?php echo $sprintId; ?>">
                <select name="work_item_id" class="form-select" required>
                    <option value="">Select work item to add...</option>
                    <?php foreach ($availableWorkItems as $item): ?>
                        <option value="<?php echo $item['id']; ?>">
                            [<?php echo ucfirst($item['type']); ?>] <?php echo htmlspecialchars($item['title']); ?>
                            <?php if ($item['story_points']): ?>(<?php echo $item['story_points']; ?> pts)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add to Sprint
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- To Do Column -->
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">To Do (<?php echo count($workItemsByStatus['todo']); ?>)</h6>
                </div>
                <div class="card-body p-2" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($workItemsByStatus['todo'])): ?>
                        <p class="text-muted text-center my-3">No items</p>
                    <?php else: ?>
                        <?php foreach ($workItemsByStatus['todo'] as $item): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php echo $item['type'] == 'bug' ? 'danger' : 'info'; ?> me-1">
                                                    <?php echo ucfirst($item['type']); ?>
                                                </span>
                                                <a href="/work-item-detail.php?id=<?php echo $item['id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                            </h6>
                                            <?php if ($item['assignee_first_name']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> 
                                                    <?php echo htmlspecialchars($item['assignee_first_name'] . ' ' . $item['assignee_last_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($item['story_points']): ?>
                                                <small class="text-muted ms-2">
                                                    <i class="bi bi-lightning"></i> <?php echo $item['story_points']; ?> pts
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($canManageSprints): ?>
                                            <form method="POST" action="/project-sprints.php?project=<?php echo $projectId; ?>" class="ms-2">
                                                <input type="hidden" name="action" value="remove_work_item">
                                                <input type="hidden" name="sprint_id" value="<?php echo $sprintId; ?>">
                                                <input type="hidden" name="work_item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from sprint">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- In Progress Column -->
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">In Progress (<?php echo count($workItemsByStatus['in_progress']); ?>)</h6>
                </div>
                <div class="card-body p-2" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($workItemsByStatus['in_progress'])): ?>
                        <p class="text-muted text-center my-3">No items</p>
                    <?php else: ?>
                        <?php foreach ($workItemsByStatus['in_progress'] as $item): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php echo $item['type'] == 'bug' ? 'danger' : 'info'; ?> me-1">
                                                    <?php echo ucfirst($item['type']); ?>
                                                </span>
                                                <a href="/work-item-detail.php?id=<?php echo $item['id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                            </h6>
                                            <?php if ($item['assignee_first_name']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> 
                                                    <?php echo htmlspecialchars($item['assignee_first_name'] . ' ' . $item['assignee_last_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($item['story_points']): ?>
                                                <small class="text-muted ms-2">
                                                    <i class="bi bi-lightning"></i> <?php echo $item['story_points']; ?> pts
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($canManageSprints): ?>
                                            <form method="POST" action="/project-sprints.php?project=<?php echo $projectId; ?>" class="ms-2">
                                                <input type="hidden" name="action" value="remove_work_item">
                                                <input type="hidden" name="sprint_id" value="<?php echo $sprintId; ?>">
                                                <input type="hidden" name="work_item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from sprint">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Done Column -->
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">Done (<?php echo count($workItemsByStatus['done']); ?>)</h6>
                </div>
                <div class="card-body p-2" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($workItemsByStatus['done'])): ?>
                        <p class="text-muted text-center my-3">No items</p>
                    <?php else: ?>
                        <?php foreach ($workItemsByStatus['done'] as $item): ?>
                            <div class="card mb-2">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php echo $item['type'] == 'bug' ? 'danger' : 'info'; ?> me-1">
                                                    <?php echo ucfirst($item['type']); ?>
                                                </span>
                                                <a href="/work-item-detail.php?id=<?php echo $item['id']; ?>" target="_blank" class="text-decoration-line-through">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                            </h6>
                                            <?php if ($item['assignee_first_name']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> 
                                                    <?php echo htmlspecialchars($item['assignee_first_name'] . ' ' . $item['assignee_last_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($item['story_points']): ?>
                                                <small class="text-muted ms-2">
                                                    <i class="bi bi-lightning"></i> <?php echo $item['story_points']; ?> pts
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($canManageSprints): ?>
                                            <form method="POST" action="/project-sprints.php?project=<?php echo $projectId; ?>" class="ms-2">
                                                <input type="hidden" name="action" value="remove_work_item">
                                                <input type="hidden" name="sprint_id" value="<?php echo $sprintId; ?>">
                                                <input type="hidden" name="work_item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from sprint">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <div class="text-muted">
                <small>
                    Total Items: <?php echo count($workItems); ?> | 
                    Total Story Points: <?php echo array_sum(array_column($workItems, 'story_points')); ?>
                </small>
            </div>
        </div>
    </div>
</div>