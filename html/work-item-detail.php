<?php
/**
 * Work Item Detail Page
 * 
 * Displays detailed information about a specific work item
 */

$page_title = 'Work Item Details';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/WorkItem.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$workItemId = $_GET['id'] ?? null;
if (!$workItemId) {
    setFlashMessage('error', 'Work item not found.');
    header('Location: /work-items.php');
    exit;
}

$workItemObj = new WorkItem();
$projectObj = new Project();
$userObj = new User();
$currentUserId = getCurrentUserId();

// Get work item details
$workItem = $workItemObj->findById($workItemId);
if (!$workItem) {
    setFlashMessage('error', 'Work item not found.');
    header('Location: /work-items.php');
    exit;
}

// Check access
$project = $projectObj->findById($workItem['project_id']);
if (!$project || !$projectObj->isMember($workItem['project_id'], $currentUserId)) {
    setFlashMessage('error', 'Access denied to this work item.');
    header('Location: /work-items.php');
    exit;
}

$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);
$canEdit = $isProjectManagerOrAdmin || $workItem['reporter_id'] == $currentUserId || $workItem['assignee_id'] == $currentUserId;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_criteria' && $canEdit) {
        $criteriaText = trim($_POST['criteria_text'] ?? '');
        $mustPass = isset($_POST['must_pass']);
        
        if ($criteriaText) {
            if ($workItemObj->addAcceptanceCriteria($workItemId, $criteriaText, $mustPass)) {
                setFlashMessage('success', 'Acceptance criteria added successfully!');
            } else {
                setFlashMessage('error', 'Failed to add acceptance criteria.');
            }
        }
        header("Location: /work-item-detail.php?id={$workItemId}");
        exit;
    }
    
    if ($_POST['action'] === 'update_criteria') {
        $criteriaId = $_POST['criteria_id'] ?? null;
        $isCompleted = isset($_POST['is_completed']);
        
        if ($criteriaId) {
            if ($workItemObj->updateAcceptanceCriteria($criteriaId, $isCompleted, $currentUserId)) {
                setFlashMessage('success', 'Criteria updated successfully!');
            } else {
                setFlashMessage('error', 'Failed to update criteria.');
            }
        }
        header("Location: /work-item-detail.php?id={$workItemId}");
        exit;
    }
    
    if ($_POST['action'] === 'add_child' && $canEdit) {
        $childType = $_POST['child_type'] ?? '';
        $childTitle = trim($_POST['child_title'] ?? '');
        
        if ($childTitle && $childType) {
            $childData = [
                'type' => $childType,
                'title' => $childTitle,
                'project_id' => $workItem['project_id'],
                'reporter_id' => $currentUserId,
                'parent_id' => $workItemId
            ];
            
            $childId = $workItemObj->create($childData);
            if ($childId) {
                setFlashMessage('success', 'Child work item created successfully!');
            } else {
                setFlashMessage('error', 'Failed to create child work item.');
            }
        }
        header("Location: /work-item-detail.php?id={$workItemId}");
        exit;
    }
}

// Get acceptance criteria
$acceptanceCriteria = $workItemObj->getAcceptanceCriteria($workItemId);

// Get child work items
$childItems = $workItemObj->getChildren($workItemId);

// Calculate completion
$completionPercentage = $workItemObj->calculateCompletion($workItemId);

// Type info for display
$typeInfo = [
    'epic' => ['icon' => 'bi-diagram-3', 'color' => 'primary'],
    'story' => ['icon' => 'bi-card-text', 'color' => 'success'],
    'task' => ['icon' => 'bi-check2-square', 'color' => 'info'],
    'bug' => ['icon' => 'bi-bug', 'color' => 'danger'],
    'spike' => ['icon' => 'bi-lightning', 'color' => 'warning']
];

$pageTitle = $workItem['title'] . ' - Work Item';
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/projects">Projects</a></li>
                    <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                    <li class="breadcrumb-item"><a href="/work-items.php?project=<?php echo $project['id']; ?>">Work Items</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($workItem['title']); ?></li>
                </ol>
            </nav>

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-<?php echo $typeInfo[$workItem['type']]['color']; ?> text-white">
                            <h4 class="mb-0">
                                <i class="<?php echo $typeInfo[$workItem['type']]['icon']; ?>"></i>
                                <?php echo htmlspecialchars($workItem['title']); ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo strtoupper($workItem['type']); ?></span>
                            </h4>
                        </div>
                        <div class="card-body">
                            <!-- Parent Information -->
                            <?php if ($workItem['parent_id']): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-diagram-2"></i> Child of: 
                                <a href="/work-item-detail.php?id=<?php echo $workItem['parent_id']; ?>">
                                    <?php echo htmlspecialchars($workItem['parent_title']); ?>
                                </a>
                                <span class="badge bg-<?php echo $typeInfo[$workItem['parent_type']]['color']; ?>">
                                    <?php echo strtoupper($workItem['parent_type']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Description -->
                            <div class="mb-4">
                                <h5>Description</h5>
                                <p class="text-break">
                                    <?php echo nl2br(htmlspecialchars($workItem['description'] ?? 'No description provided.')); ?>
                                </p>
                            </div>
                            
                            <!-- Progress Bar -->
                            <?php if ($completionPercentage > 0 || count($childItems) > 0 || count($acceptanceCriteria) > 0): ?>
                            <div class="mb-4">
                                <h5>Progress</h5>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar <?php echo $completionPercentage == 100 ? 'bg-success' : 'bg-primary'; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $completionPercentage; ?>%">
                                        <?php echo $completionPercentage; ?>%
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Acceptance Criteria (for stories and bugs) -->
                            <?php if (in_array($workItem['type'], ['story', 'bug', 'spike'])): ?>
                            <div class="mb-4">
                                <h5>Acceptance Criteria</h5>
                                <?php if (empty($acceptanceCriteria)): ?>
                                    <p class="text-muted">No acceptance criteria defined.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($acceptanceCriteria as $criteria): ?>
                                        <div class="list-group-item">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_criteria">
                                                <input type="hidden" name="criteria_id" value="<?php echo $criteria['id']; ?>">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="is_completed" 
                                                           id="criteria_<?php echo $criteria['id']; ?>"
                                                           <?php echo $criteria['is_completed'] ? 'checked' : ''; ?>
                                                           onchange="this.form.submit()">
                                                    <label class="form-check-label <?php echo $criteria['is_completed'] ? 'text-decoration-line-through' : ''; ?>" 
                                                           for="criteria_<?php echo $criteria['id']; ?>">
                                                        <?php echo htmlspecialchars($criteria['text']); ?>
                                                        <?php if ($criteria['must_pass']): ?>
                                                            <span class="badge bg-danger ms-1">Required</span>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </form>
                                            <?php if ($criteria['is_completed'] && $criteria['completed_by']): ?>
                                                <small class="text-muted">
                                                    Completed by <?php echo htmlspecialchars($criteria['first_name'] . ' ' . $criteria['last_name']); ?>
                                                    on <?php echo date('M j, Y', strtotime($criteria['completed_at'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($canEdit): ?>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="action" value="add_criteria">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="criteria_text" 
                                               placeholder="Add new acceptance criteria..." required>
                                        <div class="input-group-text">
                                            <input class="form-check-input mt-0" type="checkbox" name="must_pass" 
                                                   id="must_pass" checked>
                                            <label class="form-check-label ms-1" for="must_pass">Required</label>
                                        </div>
                                        <button class="btn btn-primary" type="submit">Add</button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Child Work Items -->
                            <?php if (in_array($workItem['type'], ['epic', 'story', 'spike'])): ?>
                            <div class="mb-4">
                                <h5>Child Items</h5>
                                <?php if (empty($childItems)): ?>
                                    <p class="text-muted">No child items.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Title</th>
                                                    <th>Status</th>
                                                    <th>Assignee</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($childItems as $child): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?php echo $typeInfo[$child['type']]['color']; ?>">
                                                            <?php echo strtoupper($child['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="/work-item-detail.php?id=<?php echo $child['id']; ?>">
                                                            <?php echo htmlspecialchars($child['title']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $child['status'] === 'done' ? 'success' : 
                                                                ($child['status'] === 'in_progress' ? 'warning' : 'secondary'); 
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $child['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($child['assignee_id']): ?>
                                                            <?php echo htmlspecialchars($child['assignee_first_name'] . ' ' . $child['assignee_last_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Unassigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($canEdit): ?>
                                    <?php
                                    // Determine valid child types based on parent type
                                    $validChildTypes = [];
                                    if ($workItem['type'] === 'epic') {
                                        $validChildTypes = ['story', 'bug', 'spike'];
                                    } elseif ($workItem['type'] === 'story') {
                                        $validChildTypes = ['task', 'bug'];
                                    } elseif ($workItem['type'] === 'spike') {
                                        $validChildTypes = ['task'];
                                    }
                                    ?>
                                    <?php if (!empty($validChildTypes)): ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="add_child">
                                        <div class="input-group">
                                            <select class="form-select" name="child_type" required style="max-width: 150px;">
                                                <?php foreach ($validChildTypes as $childType): ?>
                                                    <option value="<?php echo $childType; ?>"><?php echo ucfirst($childType); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="form-control" name="child_title" 
                                                   placeholder="Quick add child item..." required>
                                            <button class="btn btn-primary" type="submit">Add</button>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Details Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Details</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Status:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-<?php 
                                        echo $workItem['status'] === 'done' ? 'success' : 
                                            ($workItem['status'] === 'in_progress' ? 'warning' : 
                                            ($workItem['status'] === 'in_review' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $workItem['status'])); ?>
                                    </span>
                                </dd>
                                
                                <dt class="col-sm-5">Priority:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-<?php 
                                        echo $workItem['priority'] === 'highest' ? 'danger' :
                                            ($workItem['priority'] === 'high' ? 'warning' : 
                                            ($workItem['priority'] === 'medium' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($workItem['priority']); ?>
                                    </span>
                                </dd>
                                
                                <dt class="col-sm-5">Reporter:</dt>
                                <dd class="col-sm-7">
                                    <?php echo htmlspecialchars($workItem['creator_first_name'] . ' ' . $workItem['creator_last_name']); ?>
                                </dd>
                                
                                <dt class="col-sm-5">Assignee:</dt>
                                <dd class="col-sm-7">
                                    <?php if ($workItem['assignee_id']): ?>
                                        <?php echo htmlspecialchars($workItem['assignee_first_name'] . ' ' . $workItem['assignee_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </dd>
                                
                                <?php if ($workItem['sprint_name']): ?>
                                <dt class="col-sm-5">Sprint:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($workItem['sprint_name']); ?></dd>
                                <?php endif; ?>
                                
                                <?php if ($workItem['story_points']): ?>
                                <dt class="col-sm-5">Story Points:</dt>
                                <dd class="col-sm-7"><?php echo $workItem['story_points']; ?></dd>
                                <?php endif; ?>
                                
                                <?php if ($workItem['estimate_hours']): ?>
                                <dt class="col-sm-5">Estimate:</dt>
                                <dd class="col-sm-7"><?php echo $workItem['estimate_hours']; ?> hours</dd>
                                <?php endif; ?>
                                
                                <?php if ($workItem['actual_hours']): ?>
                                <dt class="col-sm-5">Actual:</dt>
                                <dd class="col-sm-7"><?php echo $workItem['actual_hours']; ?> hours</dd>
                                <?php endif; ?>
                                
                                <?php if ($workItem['due_date']): ?>
                                <dt class="col-sm-5">Due Date:</dt>
                                <dd class="col-sm-7"><?php echo date('M j, Y', strtotime($workItem['due_date'])); ?></dd>
                                <?php endif; ?>
                                
                                <dt class="col-sm-5">Created:</dt>
                                <dd class="col-sm-7"><?php echo date('M j, Y g:i A', strtotime($workItem['created_at'])); ?></dd>
                                
                                <dt class="col-sm-5">Updated:</dt>
                                <dd class="col-sm-7"><?php echo date('M j, Y g:i A', strtotime($workItem['updated_at'])); ?></dd>
                                
                                <?php if ($workItem['completed_at']): ?>
                                <dt class="col-sm-5">Completed:</dt>
                                <dd class="col-sm-7"><?php echo date('M j, Y g:i A', strtotime($workItem['completed_at'])); ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                    
                    <!-- Actions Card -->
                    <?php if ($canEdit): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <a href="/work-item-edit.php?id=<?php echo $workItemId; ?>" class="btn btn-primary w-100 mb-2">
                                <i class="bi bi-pencil"></i> Edit Work Item
                            </a>
                            
                            <div class="dropdown">
                                <button class="btn btn-secondary w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-arrow-repeat"></i> Change Status
                                </button>
                                <ul class="dropdown-menu">
                                    <?php 
                                    $statuses = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'in_review' => 'In Review', 'done' => 'Done'];
                                    foreach ($statuses as $statusKey => $statusLabel): 
                                        if ($statusKey !== $workItem['status']):
                                    ?>
                                    <li>
                                        <form method="POST" action="/work-items.php?project=<?php echo $workItem['project_id']; ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="work_item_id" value="<?php echo $workItemId; ?>">
                                            <input type="hidden" name="status" value="<?php echo $statusKey; ?>">
                                            <button type="submit" class="dropdown-item">
                                                <?php echo $statusLabel; ?>
                                            </button>
                                        </form>
                                    </li>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>