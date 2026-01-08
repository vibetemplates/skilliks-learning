<?php
/**
 * Work Item Edit Page
 * 
 * Allows editing of work item details
 */

$page_title = 'Edit Work Item';
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

if (!$canEdit) {
    setFlashMessage('error', 'You do not have permission to edit this work item.');
    header('Location: /work-item-detail.php?id=' . $workItemId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'type' => $_POST['type'] ?? 'task',
        'status' => $_POST['status'] ?? 'todo',
        'priority' => $_POST['priority'] ?? 'medium',
        'assignee_id' => !empty($_POST['assignee_id']) ? intval($_POST['assignee_id']) : null,
        'story_points' => !empty($_POST['story_points']) ? intval($_POST['story_points']) : null,
        'estimate_hours' => !empty($_POST['estimate_hours']) ? floatval($_POST['estimate_hours']) : null,
        'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        'labels' => trim($_POST['labels'] ?? '')
    ];
    
    if (empty($data['title'])) {
        setFlashMessage('error', 'Title is required.');
    } else {
        if ($workItemObj->update($workItemId, $data)) {
            setFlashMessage('success', 'Work item updated successfully!');
            header('Location: /work-item-detail.php?id=' . $workItemId);
            exit;
        } else {
            setFlashMessage('error', 'Failed to update work item.');
        }
    }
}

// Get project members for assignee dropdown
$members = $projectObj->getMembers($workItem['project_id']);

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $workItem['project_id']; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item"><a href="/work-items.php?project=<?php echo $workItem['project_id']; ?>">Work Items</a></li>
            <li class="breadcrumb-item"><a href="/work-item-detail.php?id=<?php echo $workItemId; ?>"><?php echo htmlspecialchars($workItem['title']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Edit Work Item</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($workItem['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($workItem['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="epic" <?php echo $workItem['type'] == 'epic' ? 'selected' : ''; ?>>Epic</option>
                                    <option value="story" <?php echo $workItem['type'] == 'story' ? 'selected' : ''; ?>>Story</option>
                                    <option value="task" <?php echo $workItem['type'] == 'task' ? 'selected' : ''; ?>>Task</option>
                                    <option value="bug" <?php echo $workItem['type'] == 'bug' ? 'selected' : ''; ?>>Bug</option>
                                    <option value="spike" <?php echo $workItem['type'] == 'spike' ? 'selected' : ''; ?>>Spike</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="todo" <?php echo $workItem['status'] == 'todo' ? 'selected' : ''; ?>>To Do</option>
                                    <option value="in_progress" <?php echo $workItem['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="done" <?php echo $workItem['status'] == 'done' ? 'selected' : ''; ?>>Done</option>
                                    <option value="blocked" <?php echo $workItem['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                    <option value="cancelled" <?php echo $workItem['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="critical" <?php echo $workItem['priority'] == 'critical' ? 'selected' : ''; ?>>Critical</option>
                                    <option value="high" <?php echo $workItem['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="medium" <?php echo $workItem['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="low" <?php echo $workItem['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="assignee_id" class="form-label">Assignee</label>
                                <select class="form-select" id="assignee_id" name="assignee_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" 
                                                <?php echo $workItem['assignee_id'] == $member['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="story_points" class="form-label">Story Points</label>
                                <input type="number" class="form-control" id="story_points" name="story_points" 
                                       value="<?php echo htmlspecialchars($workItem['story_points'] ?? ''); ?>" 
                                       min="0" max="100">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="estimate_hours" class="form-label">Estimate (hours)</label>
                                <input type="number" class="form-control" id="estimate_hours" name="estimate_hours" 
                                       value="<?php echo htmlspecialchars($workItem['estimate_hours'] ?? ''); ?>" 
                                       min="0" max="999" step="0.5">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?php echo htmlspecialchars($workItem['due_date'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="labels" class="form-label">Labels</label>
                            <input type="text" class="form-control" id="labels" name="labels" 
                                   value="<?php echo htmlspecialchars($workItem['labels'] ?? ''); ?>"
                                   placeholder="Comma-separated labels (e.g., frontend, urgent, refactor)">
                            <small class="form-text text-muted">Enter labels separated by commas</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/work-item-detail.php?id=<?php echo $workItemId; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Work Item Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Type:</dt>
                        <dd class="col-sm-7">
                            <span class="badge bg-<?php echo $workItem['type'] == 'bug' ? 'danger' : ($workItem['type'] == 'story' ? 'primary' : 'info'); ?>">
                                <?php echo ucfirst($workItem['type']); ?>
                            </span>
                        </dd>
                        <dt class="col-sm-5">Created:</dt>
                        <dd class="col-sm-7"><?php echo date('M j, Y', strtotime($workItem['created_at'])); ?></dd>
                        
                        <dt class="col-sm-5">Updated:</dt>
                        <dd class="col-sm-7"><?php echo date('M j, Y g:i A', strtotime($workItem['updated_at'])); ?></dd>
                        
                        <?php if ($workItem['actual_hours']): ?>
                        <dt class="col-sm-5">Actual Hours:</dt>
                        <dd class="col-sm-7"><?php echo $workItem['actual_hours']; ?> hours</dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Use clear, descriptive titles</li>
                        <li>Add story points for better estimation</li>
                        <li>Set appropriate priority levels</li>
                        <li>Assign to team members when ready</li>
                        <li>Use labels to categorize work</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>