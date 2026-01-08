<?php
/**
 * Product Backlog Page
 * 
 * Displays and manages the product backlog for a project
 */

$page_title = 'Product Backlog';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/WorkItem.php';
require_once 'classes/Sprint.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$projectId = $_GET['project'] ?? null;
if (!$projectId) {
    setFlashMessage('error', 'Project ID is required.');
    header('Location: /projects.php');
    exit;
}

$projectObj = new Project();
$workItemObj = new WorkItem();
$sprintObj = new Sprint();
$userObj = new User();
$currentUserId = getCurrentUserId();

// Get project details
$project = $projectObj->findById($projectId);
if (!$project || !$projectObj->isMember($projectId, $currentUserId)) {
    setFlashMessage('error', 'Access denied to this project.');
    header('Location: /projects.php');
    exit;
}

$isProjectManager = $project['project_manager_id'] == $currentUserId;
$isAdmin = $userObj->isAdmin($currentUserId);
$canManageBacklog = $isProjectManager || $isAdmin;

// Get product backlog
$backlogItems = $workItemObj->getProductBacklog($projectId);

// Get active sprints for assignment
$activeSprints = $sprintObj->getActiveSprintsByProject($projectId);

// Handle backlog priority updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_priority') {
    if ($canManageBacklog) {
        $workItemId = $_POST['work_item_id'] ?? null;
        $priority = $_POST['priority'] ?? 0;
        
        if ($workItemId) {
            $workItemObj->updateBacklogPriority($workItemId, $priority);
            setFlashMessage('success', 'Backlog priority updated.');
            header("Location: /product-backlog.php?project={$projectId}");
            exit;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail.php?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active">Product Backlog</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <h1 class="h2"><?php echo htmlspecialchars($project['name']); ?> - Product Backlog</h1>
            <p class="text-muted">Approved work items ready for sprint planning</p>
        </div>
        <div class="col-auto">
            <a href="/work-items.php?project=<?php echo $projectId; ?>" class="btn btn-secondary">
                <i class="bi bi-list-task"></i> All Work Items
            </a>
            <a href="/project-sprints.php?project=<?php echo $projectId; ?>" class="btn btn-primary">
                <i class="bi bi-calendar3"></i> Sprints
            </a>
        </div>
    </div>


    <!-- Backlog Items -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Product Backlog (<?php echo count($backlogItems); ?> items)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($backlogItems)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No approved items in the backlog. 
                    <a href="/work-items.php?project=<?php echo $projectId; ?>">Create and approve work items</a> to populate the backlog.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="backlog-table">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Priority</th>
                                <th>Story Points</th>
                                <th>Reporter</th>
                                <th>Approved By</th>
                                <th width="150">Backlog Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backlogItems as $index => $item): ?>
                            <tr data-work-item-id="<?php echo $item['id']; ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo ucfirst($item['type']); ?></span>
                                </td>
                                <td>
                                    <a href="/work-item-detail.php?id=<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </a>
                                    <?php if ($item['description']): ?>
                                        <small class="text-muted d-block">
                                            <?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?>...
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $item['priority'] === 'highest' ? 'danger' :
                                            ($item['priority'] === 'high' ? 'warning' : 
                                            ($item['priority'] === 'medium' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($item['priority']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($item['story_points']): ?>
                                        <span class="badge bg-light text-dark"><?php echo $item['story_points']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($item['reporter_first_name'] . ' ' . $item['reporter_last_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($item['approver_first_name'] . ' ' . $item['approver_last_name']); ?>
                                    <small class="text-muted d-block">
                                        <?php echo date('M j', strtotime($item['approved_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($canManageBacklog): ?>
                                        <form method="POST" class="d-inline-block">
                                            <input type="hidden" name="action" value="update_priority">
                                            <input type="hidden" name="work_item_id" value="<?php echo $item['id']; ?>">
                                            <div class="input-group input-group-sm">
                                                <input type="number" 
                                                       name="priority" 
                                                       value="<?php echo $item['backlog_priority']; ?>" 
                                                       min="0" 
                                                       max="999"
                                                       class="form-control" 
                                                       style="width: 60px;">
                                                <button type="submit" class="btn btn-outline-secondary" title="Update priority">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <?php echo $item['backlog_priority']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="/work-item-detail.php?id=<?php echo $item['id']; ?>">
                                                    <i class="bi bi-eye"></i> View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="/work-item-edit.php?id=<?php echo $item['id']; ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <?php if (!empty($activeSprints)): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <h6 class="dropdown-header">Add to Sprint</h6>
                                                </li>
                                                <?php foreach ($activeSprints as $sprint): ?>
                                                <li>
                                                    <button class="dropdown-item"
                                                            hx-post="/htmx/add-to-sprint.php"
                                                            hx-target="#backlog-messages"
                                                            hx-swap="innerHTML"
                                                            hx-vals='{"work_item_id": "<?php echo $item['id']; ?>", "sprint_id": "<?php echo $sprint['id']; ?>"}'>
                                                        <?php echo htmlspecialchars($sprint['name']); ?>
                                                    </button>
                                                </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="backlog-messages" class="mt-3"></div>
                
                <?php if ($canManageBacklog): ?>
                <div class="mt-3">
                    <p class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Drag and drop rows to reorder backlog items, or update the priority values. 
                        Higher priority values appear first.
                    </p>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManageBacklog): ?>
<script>
// Enable drag and drop reordering
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('#backlog-table tbody');
    if (!tbody) return;
    
    let draggedRow = null;
    
    tbody.addEventListener('dragstart', function(e) {
        if (e.target.tagName === 'TR') {
            draggedRow = e.target;
            e.target.style.opacity = '0.5';
        }
    });
    
    tbody.addEventListener('dragend', function(e) {
        if (e.target.tagName === 'TR') {
            e.target.style.opacity = '';
        }
    });
    
    tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        const afterElement = getDragAfterElement(tbody, e.clientY);
        if (afterElement == null) {
            tbody.appendChild(draggedRow);
        } else {
            tbody.insertBefore(draggedRow, afterElement);
        }
    });
    
    tbody.addEventListener('drop', function(e) {
        e.preventDefault();
        // Update priorities based on new order
        const rows = tbody.querySelectorAll('tr');
        const updates = [];
        rows.forEach((row, index) => {
            const workItemId = row.dataset.workItemId;
            const newPriority = rows.length - index; // Higher position = higher priority
            updates.push({id: workItemId, priority: newPriority});
        });
        
        // Send updates to server
        // This could be implemented as a batch update endpoint
    });
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('tr:not(.dragging)')];
        
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
    
    // Make rows draggable
    tbody.querySelectorAll('tr').forEach(row => {
        row.draggable = true;
        row.style.cursor = 'move';
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>