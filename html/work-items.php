<?php
/**
 * Work Items Page
 * 
 * Lists and manages agile work items (epics, stories, tasks, bugs, spikes)
 */

$page_title = 'Work Items';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/WorkItem.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$projectId = $_GET['project'] ?? null;
$projectObj = new Project();
$workItemObj = new WorkItem();
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

// Remove server-side form handling - now handled by HTMX endpoints

// Get work items
try {
    $db = getDB();
    
    if ($projectId) {
        // Get work items for specific project grouped by type
        $filters = [];
        if (isset($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        
        $workItems = $workItemObj->findByProject($projectId, $filters);
        
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
    }
    
} catch (PDOException $e) {
    error_log("Error fetching work items: " . $e->getMessage());
    $groupedItems = [
        'epic' => [],
        'story' => [],
        'task' => [],
        'bug' => [],
        'spike' => []
    ];
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
        $stmt->execute([$currentUserId]);
        $userProjects = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching user projects: " . $e->getMessage());
    }
} else {
    // Get project members for assignment dropdown
    try {
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name
            FROM users u
            JOIN project_members pm ON u.id = pm.user_id
            WHERE pm.project_id = ? AND pm.status = 'approved'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$projectId]);
        $projectMembers = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching project members: " . $e->getMessage());
    }
}

// Include header
$pageTitle = $project ? $project['name'] . ' - Work Items' : 'Work Items';
include 'includes/header.php';
?>

<div class="container-fluid mt-4" x-data="workItemsApp()" x-init="init()">
    <div class="row">
        <div class="col-12">
            <!-- Breadcrumb Navigation -->
            <?php if ($project): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/projects">Projects</a></li>
                    <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                    <li class="breadcrumb-item active">Work Items</li>
                </ol>
            </nav>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <?php if ($project): ?>
                        <?php echo htmlspecialchars($project['name']); ?> - Work Items
                    <?php else: ?>
                        My Work Items
                    <?php endif; ?>
                </h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkItemModal">
                    <i class="bi bi-plus-circle"></i> Add Work Item
                </button>
            </div>

            <?php 
            $typeInfo = [
                'story' => ['icon' => 'bi-card-text', 'color' => 'success', 'label' => 'Stories'],
                'epic' => ['icon' => 'bi-diagram-3', 'color' => 'primary', 'label' => 'Epics'],
                'task' => ['icon' => 'bi-check2-square', 'color' => 'info', 'label' => 'Tasks'],
                'bug' => ['icon' => 'bi-bug', 'color' => 'danger', 'label' => 'Bugs'],
                'spike' => ['icon' => 'bi-lightning', 'color' => 'warning', 'label' => 'Spikes']
            ];
            ?>

            <!-- Work Items Tabs with HTMX -->
            <ul class="nav nav-tabs mb-4" id="workItemTabs" role="tablist">
                <?php foreach ($typeInfo as $type => $info): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $type === 'story' ? 'active' : ''; ?>" 
                            id="<?php echo $type; ?>-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#<?php echo $type; ?>-content" 
                            type="button" 
                            role="tab" 
                            aria-controls="<?php echo $type; ?>-content" 
                            aria-selected="<?php echo $type === 'story' ? 'true' : 'false'; ?>"
                            @click="activeTab = '<?php echo $type; ?>'"
                            hx-get="/htmx/work-items-list.php?project=<?php echo $projectId ?? ''; ?>&tab=<?php echo $type; ?>"
                            hx-target="#<?php echo $type; ?>-content"
                            hx-trigger="shown.bs.tab">
                        <i class="<?php echo $info['icon']; ?>"></i> 
                        <?php echo $info['label']; ?>
                        <span class="badge bg-<?php echo $info['color']; ?> ms-2" id="<?php echo $type; ?>-count"><?php echo count($groupedItems[$type]); ?></span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- Tab Content with HTMX -->
            <div class="tab-content" id="workItemTabContent" hx-trigger="workItemCreated from:body" hx-get="/htmx/work-items-list.php" hx-target="#story-content" hx-vals='{"project": "<?php echo $projectId ?? ''; ?>", "tab": "story"}'>
                <?php foreach ($typeInfo as $type => $info): ?>
                <div class="tab-pane fade <?php echo $type === 'story' ? 'show active' : ''; ?>" 
                     id="<?php echo $type; ?>-content" 
                     role="tabpanel" 
                     aria-labelledby="<?php echo $type; ?>-tab"
                     hx-get="/htmx/work-items-list.php?project=<?php echo $projectId ?? ''; ?>&tab=<?php echo $type; ?>"
                     hx-trigger="load<?php echo $type !== 'story' ? ' delay:100ms' : ''; ?>"
                     hx-swap="innerHTML">
                    <!-- Content will be loaded via HTMX -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Work Item Modal -->
<div class="modal fade" id="addWorkItemModal" tabindex="-1" aria-labelledby="addWorkItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWorkItemModalLabel">Add New Work Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/htmx/create-work-item.php" 
                  hx-target="#work-item-messages" 
                  hx-swap="innerHTML"
                  @submit="handleSubmit">
                <div class="modal-body">
                    <!-- Message area for HTMX responses -->
                    <div id="work-item-messages"></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="work_item_type" class="form-label">Type *</label>
                            <select class="form-select" id="work_item_type" name="type" required x-model="workItemType" @change="updateWorkItemForm()">
                                <option value="epic">Epic</option>
                                <option value="story">Story</option>
                                <option value="task" selected>Task</option>
                                <option value="bug">Bug</option>
                                <option value="spike">Spike</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="work_item_parent" class="form-label">Parent Item</label>
                            <select class="form-select" id="work_item_parent" name="parent_id">
                                <option value="">None</option>
                                <?php if ($projectId && isset($groupedItems['epic'])): ?>
                                    <optgroup label="Epics">
                                        <?php foreach ($groupedItems['epic'] as $epic): ?>
                                            <option value="<?php echo $epic['id']; ?>"><?php echo htmlspecialchars($epic['title']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ($projectId && isset($groupedItems['story'])): ?>
                                    <optgroup label="Stories">
                                        <?php foreach ($groupedItems['story'] as $story): ?>
                                            <option value="<?php echo $story['id']; ?>"><?php echo htmlspecialchars($story['title']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="work_item_title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="work_item_title" name="title" x-model="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="work_item_description" class="form-label">Description</label>
                        <textarea class="form-control" id="work_item_description" name="description" rows="3" x-model="description"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="work_item_project_id" class="form-label">Project *</label>
                            <?php if ($projectId): ?>
                                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                <input type="text" class="form-control" id="work_item_project_id" value="<?php echo htmlspecialchars($project['name']); ?>" disabled>
                            <?php else: ?>
                                <select class="form-select" id="work_item_project_id" name="project_id" required>
                                    <option value="">Select Project</option>
                                    <?php foreach ($userProjects as $proj): ?>
                                        <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="work_item_assignee" class="form-label">Assign To</label>
                            <select class="form-select" id="work_item_assignee" name="assignee_id">
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
                            <label for="work_item_priority" class="form-label">Priority</label>
                            <select class="form-select" id="work_item_priority" name="priority">
                                <option value="highest">Highest</option>
                                <option value="high">High</option>
                                <option value="medium" selected>Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3" id="story_points_field" style="display: none;">
                            <label for="work_item_story_points" class="form-label">Story Points</label>
                            <select class="form-select" id="work_item_story_points" name="story_points">
                                <option value="">-</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="5">5</option>
                                <option value="8">8</option>
                                <option value="13">13</option>
                                <option value="21">21</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3" id="estimate_hours_field">
                            <label for="work_item_estimate_hours" class="form-label">Estimate (hours)</label>
                            <input type="number" class="form-control" id="work_item_estimate_hours" name="estimate_hours" step="0.5" min="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="work_item_due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="work_item_due_date" name="due_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting" x-text="isSubmitting ? 'Creating...' : 'Create Work Item'">Create Work Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function workItemsApp() {
    return {
        activeTab: 'story',
        workItemType: 'task',
        title: '',
        description: '',
        isSubmitting: false,
        projectId: '<?php echo $projectId ?? ''; ?>',
        
        init() {
            // Restore last active tab
            const lastActiveTab = localStorage.getItem('activeWorkItemTab');
            if (lastActiveTab) {
                const tabButton = document.getElementById(lastActiveTab);
                if (tabButton) {
                    // Use setTimeout to ensure Alpine has finished initializing
                    setTimeout(() => {
                        const tab = new bootstrap.Tab(tabButton);
                        tab.show();
                        this.activeTab = lastActiveTab.replace('-tab', '');
                    }, 100);
                }
            }
            
            // Listen for tab changes
            const workItemTabs = document.getElementById('workItemTabs');
            if (workItemTabs) {
                const tabButtons = workItemTabs.querySelectorAll('button[data-bs-toggle="tab"]');
                tabButtons.forEach(button => {
                    button.addEventListener('shown.bs.tab', (e) => {
                        localStorage.setItem('activeWorkItemTab', e.target.id);
                    });
                });
            }
            
            // Listen for work item creation events
            document.body.addEventListener('workItemCreated', () => {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addWorkItemModal'));
                if (modal) {
                    modal.hide();
                }
                
                // Refresh the page to show the new work item
                setTimeout(() => {
                    window.location.reload();
                }, 500); // Small delay to ensure the modal closes first
            });
        },
        
        updateWorkItemForm() {
            const storyPointsField = document.getElementById('story_points_field');
            const estimateHoursField = document.getElementById('estimate_hours_field');
            
            // Show/hide fields based on type
            if (this.workItemType === 'epic' || this.workItemType === 'story' || this.workItemType === 'spike') {
                storyPointsField.style.display = 'block';
                estimateHoursField.style.display = 'none';
            } else {
                storyPointsField.style.display = 'none';
                estimateHoursField.style.display = 'block';
            }
        },
        
        showAddWorkItem(type) {
            this.workItemType = type;
            this.updateWorkItemForm();
            const modal = new bootstrap.Modal(document.getElementById('addWorkItemModal'));
            modal.show();
        },
        
        handleSubmit(event) {
            if (!this.title.trim()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                this.isSubmitting = true;
            }
        },
        
        resetForm() {
            this.workItemType = 'task';
            this.title = '';
            this.description = '';
            this.isSubmitting = false;
            
            // Clear any messages
            const messages = document.getElementById('work-item-messages');
            if (messages) {
                messages.innerHTML = '';
            }
            
            // Reset form element
            const form = document.querySelector('#addWorkItemModal form');
            if (form) {
                form.reset();
            }
        }
    }
}

// Initialize work item form on modal show
document.addEventListener('DOMContentLoaded', function() {
    const addModal = document.getElementById('addWorkItemModal');
    if (addModal) {
        addModal.addEventListener('shown.bs.modal', function() {
            const alpineData = Alpine.$data(document.querySelector('[x-data="workItemsApp()"]'));
            if (alpineData) {
                alpineData.updateWorkItemForm();
            }
        });
        
        addModal.addEventListener('hidden.bs.modal', function() {
            const alpineData = Alpine.$data(document.querySelector('[x-data="workItemsApp()"]'));
            if (alpineData) {
                alpineData.resetForm();
            }
        });
    }
});

// Function to show reject modal
function showRejectModal(workItemId) {
    document.getElementById('reject_work_item_id').value = workItemId;
    const modal = new bootstrap.Modal(document.getElementById('rejectWorkItemModal'));
    modal.show();
}
</script>

<!-- Reject Work Item Modal -->
<div class="modal fade" id="rejectWorkItemModal" tabindex="-1" aria-labelledby="rejectWorkItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectWorkItemModalLabel">Reject Work Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/htmx/approve-work-item.php" 
                  hx-target="#reject-messages" 
                  hx-swap="innerHTML">
                <div class="modal-body">
                    <div id="reject-messages"></div>
                    <input type="hidden" id="reject_work_item_id" name="work_item_id">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label for="reject_reason" class="form-label">Rejection Reason *</label>
                        <textarea class="form-control" id="reject_reason" name="reason" rows="3" required
                                  placeholder="Please provide a reason for rejecting this work item..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Reject Work Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link {
    color: #495057;
    border: 1px solid transparent;
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
}

.nav-tabs .nav-link.active {
    font-weight: 500;
}

.table td {
    vertical-align: middle;
}

.dropdown-toggle::after {
    display: none;
}

/* Fix dropdown menu being cut off */
.table-responsive {
    overflow: visible !important;
}

.dropdown {
    position: static;
}

.dropdown-menu {
    z-index: 1050;
}

/* Ensure tab content doesn't hide dropdowns */
.tab-content {
    overflow: visible !important;
}

.tab-pane {
    overflow: visible !important;
}

/* Alternative fix using position strategy */
@media (max-width: 768px) {
    .dropdown-menu {
        position: fixed !important;
        inset: auto !important;
        transform: none !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>