<?php
/**
 * HTMX Work Items List Endpoint
 * Returns filtered work items list
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';
require_once '../classes/WorkItem.php';
require_once '../classes/User.php';

// Require login
requireLogin();

$projectId = $_GET['project'] ?? null;
$activeTab = $_GET['tab'] ?? 'story';
$projectObj = new Project();
$workItemObj = new WorkItem();
$userObj = new User();
$currentUserId = getCurrentUserId();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

// Get work items
try {
    $db = getDB();
    
    if ($projectId) {
        // Verify access to project
        $project = $projectObj->findById($projectId);
        if (!$project || !$projectObj->isMember($projectId, $currentUserId)) {
            echo '<div class="alert alert-danger">Access denied to this project.</div>';
            exit;
        }
        
        // Check if user is project manager for this specific project
        if ($project['project_manager_id'] == $currentUserId) {
            $isProjectManagerOrAdmin = true;
        }
        
        // Get work items for specific project
        $filters = [];
        if (isset($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        
        $workItems = $workItemObj->findByProject($projectId, $filters);
    } else {
        // Get work items from user's projects - exclude items already in sprints
        $stmt = $db->prepare("
            SELECT wi.*, p.name as project_name, 
                   reporter.first_name as creator_first_name, reporter.last_name as creator_last_name,
                   assignee.first_name as assignee_first_name, assignee.last_name as assignee_last_name,
                   NULL as sprint_name,
                   NULL as sprint_status,
                   NULL as sprint_end_date,
                   wi.status as display_status
            FROM work_items wi
            INNER JOIN projects p ON wi.project_id = p.id
            INNER JOIN project_members pm ON p.id = pm.project_id
            LEFT JOIN users reporter ON wi.reporter_id = reporter.id
            LEFT JOIN users assignee ON wi.assignee_id = assignee.id
            WHERE pm.user_id = ? AND pm.status = 'approved' AND wi.sprint_id IS NULL
            ORDER BY wi.type, wi.priority DESC, wi.created_at DESC
        ");
        $stmt->execute([$currentUserId]);
        $workItems = $stmt->fetchAll();
    }
    
    // Group by type
    $groupedItems = [
        'epic' => [],
        'story' => [],
        'task' => [],
        'bug' => [],
        'spike' => []
    ];
    
    foreach ($workItems as $item) {
        $groupedItems[$item['type']][] = $item;
    }
    
} catch (PDOException $e) {
    error_log("Error fetching work items: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading work items.</div>';
    exit;
}

$typeInfo = [
    'story' => ['icon' => 'bi-card-text', 'color' => 'success', 'label' => 'Stories'],
    'epic' => ['icon' => 'bi-diagram-3', 'color' => 'primary', 'label' => 'Epics'],
    'task' => ['icon' => 'bi-check2-square', 'color' => 'info', 'label' => 'Tasks'],
    'bug' => ['icon' => 'bi-bug', 'color' => 'danger', 'label' => 'Bugs'],
    'spike' => ['icon' => 'bi-lightning', 'color' => 'warning', 'label' => 'Spikes']
];

// Output only the requested tab content
$type = $activeTab;
$info = $typeInfo[$type];
$items = $groupedItems[$type];
?>

<?php if (empty($items)): ?>
    <div class="text-center text-muted py-5">
        <i class="<?php echo $info['icon']; ?> fs-1 mb-3 d-block"></i>
        <p>No <?php echo strtolower($info['label']); ?> found.</p>
        <?php if ($projectId): ?>
            <button class="btn btn-sm btn-outline-primary" 
                    @click="showAddWorkItem('<?php echo $type; ?>')">
                <i class="bi bi-plus-circle"></i> Create <?php echo strtolower(rtrim($info['label'], 's')); ?>
            </button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th style="width: 40%">Title</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <?php if (in_array($type, ['story', 'epic', 'spike'])): ?>
                    <th>Points</th>
                    <?php endif; ?>
                    <?php if (in_array($type, ['task', 'bug'])): ?>
                    <th>Hours</th>
                    <?php endif; ?>
                    <th>Assignee</th>
                    <?php if (!$projectId): ?>
                    <th>Project</th>
                    <?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <a href="/work-item-detail.php?id=<?php echo $item['id']; ?>" class="text-decoration-none">
                            <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                        </a>
                        <?php if ($item['child_count'] > 0): ?>
                            <small class="text-muted d-block">
                                <i class="bi bi-diagram-2"></i> <?php echo $item['child_count']; ?> child items
                            </small>
                        <?php endif; ?>
                        <?php if ($item['criteria_count'] > 0): ?>
                            <small class="text-muted d-block">
                                <i class="bi bi-check2-circle"></i> 
                                <?php echo $item['criteria_completed']; ?>/<?php echo $item['criteria_count']; ?> criteria
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $displayStatus = isset($item['display_status']) ? $item['display_status'] : $item['status'];
                        $isCompletedSprint = isset($item['sprint_status']) && ($item['sprint_status'] === 'completed' || 
                                           (isset($item['sprint_end_date']) && strtotime($item['sprint_end_date']) < time()));
                        ?>
                        <span class="badge bg-<?php 
                            echo $displayStatus === 'done' ? 'success' : 
                                ($displayStatus === 'in_progress' ? 'warning' : 
                                ($displayStatus === 'in_review' ? 'info' : 'secondary')); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $displayStatus)); ?>
                            <?php if ($isCompletedSprint && $item['status'] !== 'done'): ?>
                            <i class="bi bi-check-circle ms-1" title="Sprint completed"></i>
                            <?php endif; ?>
                        </span>
                        <span class="badge bg-<?php 
                            echo $item['approval_status'] === 'approved' ? 'success' : 
                                ($item['approval_status'] === 'rejected' ? 'danger' : 'warning'); 
                        ?> ms-1">
                            <?php echo ucfirst($item['approval_status']); ?>
                        </span>
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
                    <?php if (in_array($type, ['story', 'epic', 'spike'])): ?>
                    <td class="text-center">
                        <?php if ($item['story_points']): ?>
                            <span class="badge bg-light text-dark"><?php echo $item['story_points']; ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array($type, ['task', 'bug'])): ?>
                    <td class="text-center">
                        <?php if ($item['estimate_hours']): ?>
                            <?php echo $item['estimate_hours']; ?>h
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php if ($item['assignee_id']): ?>
                            <?php echo htmlspecialchars($item['assignee_first_name'] . ' ' . $item['assignee_last_name']); ?>
                        <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <?php if (!$projectId): ?>
                    <td><?php echo htmlspecialchars($item['project_name']); ?></td>
                    <?php endif; ?>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/work-item-detail.php?id=<?php echo $item['id']; ?>">
                                    <i class="bi bi-eye"></i> View
                                </a></li>
                                <li><a class="dropdown-item" href="/work-item-edit.php?id=<?php echo $item['id']; ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <h6 class="dropdown-header">Update Status</h6>
                                </li>
                                <?php 
                                $statuses = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'in_review' => 'In Review'];
                                foreach ($statuses as $statusKey => $statusLabel): 
                                    if ($statusKey !== $item['status']):
                                ?>
                                <li>
                                    <button class="dropdown-item"
                                            hx-post="/htmx/update-work-item-status.php"
                                            hx-target="#<?php echo $type; ?>-content"
                                            hx-swap="innerHTML"
                                            hx-vals='{"work_item_id": "<?php echo $item['id']; ?>", "status": "<?php echo $statusKey; ?>", "tab": "<?php echo $type; ?>", "project": "<?php echo $projectId; ?>"}'>
                                        <?php echo $statusLabel; ?>
                                    </button>
                                </li>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                                <?php if ($isProjectManagerOrAdmin): ?>
                                <li><hr class="dropdown-divider"></li>
                                    <?php if ($item['approval_status'] !== 'approved'): ?>
                                    <li>
                                        <h6 class="dropdown-header">Approval</h6>
                                    </li>
                                    <li>
                                        <button class="dropdown-item text-success"
                                                hx-post="/htmx/approve-work-item.php"
                                                hx-target="#work-item-messages-<?php echo $item['id']; ?>"
                                                hx-swap="innerHTML"
                                                hx-vals='{"work_item_id": "<?php echo $item['id']; ?>", "action": "approve"}'
                                                hx-confirm="Approve this work item for backlog?">
                                            <i class="bi bi-check-circle"></i> Approve for Backlog
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item text-danger"
                                                onclick="showRejectModal(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                    </li>
                                    <?php else: ?>
                                    <li>
                                        <a class="dropdown-item" href="/product-backlog.php?project=<?php echo $item['project_id']; ?>">
                                            <i class="bi bi-kanban"></i> View in Product Backlog
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div id="work-item-messages-<?php echo $item['id']; ?>"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif;