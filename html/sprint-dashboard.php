<?php
/**
 * Sprint Dashboard Page
 * 
 * Manage sprint items, view product backlog, and handle sprint prompts
 */

$page_title = 'Sprint Dashboard';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Sprint.php';
require_once 'classes/Project.php';
require_once 'classes/WorkItem.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$sprintId = $_GET['id'] ?? null;
if (!$sprintId) {
    setFlashMessage('error', 'Sprint ID is required.');
    header('Location: /projects.php');
    exit;
}

$sprintObj = new Sprint();
$projectObj = new Project();
$workItemObj = new WorkItem();
$userObj = new User();
$currentUserId = getCurrentUserId();

// Get sprint details
$sprint = $sprintObj->findById($sprintId);
if (!$sprint) {
    setFlashMessage('error', 'Sprint not found.');
    header('Location: /projects.php');
    exit;
}

// Get project details
$project = $projectObj->findById($sprint['project_id']);
// Allow access if user is a project member OR the sprint creator
if (!$project || (!$projectObj->isMember($sprint['project_id'], $currentUserId) && $sprint['created_by'] != $currentUserId)) {
    setFlashMessage('error', 'Access denied to this project.');
    header('Location: /projects.php');
    exit;
}

$isProjectManager = $project['project_manager_id'] == $currentUserId;
$isAdmin = $userObj->isAdmin($currentUserId);
$isSprintCreator = $sprint['created_by'] == $currentUserId;
$canManageSprint = $isProjectManager || $isAdmin || $isSprintCreator;

// Get sprint items
$sprintItems = $workItemObj->getSprintBacklog($sprintId);

// Get product backlog for this project
$productBacklog = $workItemObj->getProductBacklog($sprint['project_id']);

// Get sprint prompts
$db = getDB();
// Check if pid column exists
$checkStmt = $db->prepare("SHOW COLUMNS FROM project_dev_prompts LIKE 'pid'");
$checkStmt->execute();
$pidColumnExists = $checkStmt->fetch() !== false;

// Check if session_id column exists
$checkSessionStmt = $db->prepare("SHOW COLUMNS FROM project_dev_prompts LIKE 'session_id'");
$checkSessionStmt->execute();
$sessionIdColumnExists = $checkSessionStmt->fetch() !== false;

if ($pidColumnExists) {
    $promptStmt = $db->prepare("
        SELECT pdp.*, wi.title as work_item_title, wi.type as work_item_type,
               parent.prompt_text as parent_prompt_text
        FROM project_dev_prompts pdp
        LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
        LEFT JOIN project_dev_prompts parent ON pdp.parent_prompt_id = parent.id
        WHERE pdp.sprint_id = ?
        ORDER BY COALESCE(pdp.parent_prompt_id, pdp.id), pdp.parent_prompt_id IS NOT NULL, pdp.prompt_order ASC
    ");
} else {
    // Fallback query without pid for before migration
    $promptStmt = $db->prepare("
        SELECT pdp.*, wi.title as work_item_title, wi.type as work_item_type,
               parent.prompt_text as parent_prompt_text
        FROM project_dev_prompts pdp
        LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
        LEFT JOIN project_dev_prompts parent ON pdp.parent_prompt_id = parent.id
        WHERE pdp.sprint_id = ?
        ORDER BY COALESCE(pdp.parent_prompt_id, pdp.id), pdp.parent_prompt_id IS NOT NULL, pdp.prompt_order ASC
    ");
}
$promptStmt->execute([$sprintId]);
$sprintPrompts = $promptStmt->fetchAll();

// Group prompts by work item ID, excluding completed prompts
$promptsByWorkItem = [];
foreach ($sprintPrompts as $prompt) {
    if ($prompt['work_item_id'] && $prompt['status'] !== 'completed') {
        $promptsByWorkItem[$prompt['work_item_id']][] = $prompt;
    }
}

// Calculate sprint progress
$progress = $sprintObj->calculateProgress($sprintId);

// Count incomplete prompts
$incompletePromptsStmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM project_dev_prompts 
    WHERE sprint_id = ? 
    AND status NOT IN ('completed', 'cancelled')
");
$incompletePromptsStmt->execute([$sprintId]);
$incompletePromptsCount = $incompletePromptsStmt->fetch()['count'];

// Get executed prompts for the sprint ordered by execution time
$executedPromptsStmt = $db->prepare("
    SELECT pdp.*, wi.title as work_item_title, wi.type as work_item_type,
           tn_user.first_name as test_notes_first_name, tn_user.last_name as test_notes_last_name,
           dn_user.first_name as developer_notes_first_name, dn_user.last_name as developer_notes_last_name,
           r_user.first_name as rating_first_name, r_user.last_name as rating_last_name,
           (SELECT COUNT(*) FROM project_dev_prompts child WHERE child.parent_prompt_id = pdp.id) as has_followups,
           parent.prompt_text as parent_prompt_text
    FROM project_dev_prompts pdp
    LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
    LEFT JOIN users tn_user ON pdp.test_notes_updated_by = tn_user.id
    LEFT JOIN users dn_user ON pdp.developer_notes_updated_by = dn_user.id
    LEFT JOIN users r_user ON pdp.rating_updated_by = r_user.id
    LEFT JOIN project_dev_prompts parent ON pdp.parent_prompt_id = parent.id
    WHERE pdp.sprint_id = ? 
    AND pdp.status IN ('completed', 'failed', 'executing', 'test-ready')
    AND pdp.executed_at IS NOT NULL
    ORDER BY pdp.executed_at ASC
");
$executedPromptsStmt->execute([$sprintId]);
$executedPrompts = $executedPromptsStmt->fetchAll();

// Calculate total cost for the sprint
$totalCostStmt = $db->prepare("
    SELECT SUM(total_cost_usd) as total_cost
    FROM project_dev_prompts
    WHERE sprint_id = ?
    AND total_cost_usd IS NOT NULL
");
$totalCostStmt->execute([$sprintId]);
$totalCost = $totalCostStmt->fetch()['total_cost'] ?? 0;

// Get prompts in executing or test-ready status
$activePromptsStmt = $db->prepare("
    SELECT pdp.*, wi.title as work_item_title, wi.type as work_item_type
    FROM project_dev_prompts pdp
    LEFT JOIN work_items wi ON pdp.work_item_id = wi.id
    WHERE pdp.sprint_id = ?
    AND pdp.status IN ('executing', 'test-ready')
    ORDER BY
        CASE pdp.status
            WHEN 'executing' THEN 1
            WHEN 'test-ready' THEN 2
        END,
        pdp.created_at DESC
");
$activePromptsStmt->execute([$sprintId]);
$activePrompts = $activePromptsStmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb with Completed Sprints button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
                <li class="breadcrumb-item"><a href="/project-detail.php?id=<?php echo $sprint['project_id']; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                <li class="breadcrumb-item"><a href="/project-sprints.php?project=<?php echo $sprint['project_id']; ?>">Sprints</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($sprint['name']); ?></li>
            </ol>
        </nav>
        <a href="/completed-sprints.php?project=<?php echo $sprint['project_id']; ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-check-circle"></i> View Completed Sprints
        </a>
    </div>

    <!-- Active Prompts Card (Executing and Test-Ready) -->
    <?php if (!empty($activePrompts)): ?>
    <div class="row mb-4" id="active-prompts-card">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info bg-opacity-10">
                    <h5 class="mb-0">
                        <i class="bi bi-activity"></i> Active Prompts
                        <span class="badge bg-info"><?php echo count($activePrompts); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Prompt</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activePrompts as $prompt): ?>
                                <tr>
                                    <td>#<?php echo $prompt['id']; ?></td>
                                    <td>
                                        <?php
                                        $promptText = $prompt['prompt_text'] ?? '';
                                        if (strlen($promptText) > 100) {
                                            echo '<span title="' . htmlspecialchars($promptText) . '">' .
                                                 htmlspecialchars(substr($promptText, 0, 100)) . '...</span>';
                                        } else {
                                            echo htmlspecialchars($promptText ?: 'No prompt text');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($prompt['status'] === 'executing'): ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-arrow-clockwise"></i> Executing
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-clipboard-check"></i> Test Ready
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, H:i', strtotime($prompt['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        // Check button visibility
                                        $sessionIdColumnExists = true;
                                        $showCheckButton = in_array($prompt['status'], ['executing', 'test-ready']) &&
                                                         ((!empty($prompt['pid']) || !empty($prompt['log_file_name'])) ||
                                                          ($sessionIdColumnExists && !empty($prompt['session_id'])));
                                        ?>

                                        <?php if ($showCheckButton): ?>
                                        <button class="btn btn-sm btn-info"
                                                hx-post="/htmx/check-prompt-status.php"
                                                hx-target="#active-prompt-result-<?php echo $prompt['id']; ?>"
                                                hx-swap="innerHTML"
                                                hx-vals='{"prompt_id": "<?php echo $prompt['id']; ?>"}'>
                                            <i class="bi bi-arrow-clockwise"></i> Check
                                        </button>
                                        <?php endif; ?>

                                        <div id="active-prompt-result-<?php echo $prompt['id']; ?>" class="mt-2"></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sprint Messages -->
    <div id="sprint-messages"></div>

    <?php
    // Define display status early for use in development tool selection
    $displayStatus = $sprint['status'];
    ?>
    
    <!-- Development Tool Selection -->
    <?php if ($canManageSprint && $displayStatus === 'active'): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <label for="dev-tool-select" class="form-label mb-0">Development Tool:</label>
                    <select id="dev-tool-select" class="form-select form-select-sm" onchange="updateDevToolSelection()">
                        <option value="claude" selected>Claude Code</option>
                        <option value="skilliks">Skilliks Coder</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="local-execution" disabled>
                        <label class="form-check-label" for="local-execution">
                            Local Execution <small class="text-muted">(Coming soon)</small>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Sprint Header -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <?php if (!empty($project['test_url'])): ?>
                        <a href="<?php echo htmlspecialchars($project['test_url']); ?>" target="_blank" class="btn btn-primary btn-sm me-3">
                            <i class="bi bi-play-circle"></i> Run Project
                        </a>
                    <?php endif; ?>
                    <h1 class="h4 mb-0">
                        <?php echo htmlspecialchars($sprint['name']); ?>
                    </h1>
                </div>
                <?php
                $statusClasses = [
                    'planning' => 'warning',
                    'active' => 'success',
                    'completed' => 'primary',
                    'cancelled' => 'dark'
                ];
                $statusClass = $statusClasses[$displayStatus] ?? 'secondary';
                ?>
                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($displayStatus); ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-8">
                    <?php if ($sprint['goal']): ?>
                        <p class="mb-2">
                            <strong>Goal:</strong> <?php echo htmlspecialchars($sprint['goal']); ?>
                        </p>
                    <?php endif; ?>
                    <p class="text-muted mb-2">
                        <i class="bi bi-calendar-range"></i> 
                        <?php echo date('M j, Y', strtotime($sprint['start_date'])); ?> - 
                        <?php echo date('M j, Y', strtotime($sprint['end_date'])); ?>
                    </p>
                    <?php if ($canManageSprint): ?>
                    <div class="mt-3">
                        <?php if ($displayStatus === 'planning'): ?>
                        <button class="btn btn-sm btn-success"
                                hx-post="/htmx/activate-sprint.php"
                                hx-vals='{"sprint_id": "<?php echo $sprint['id']; ?>"}'
                                hx-target="#sprint-messages"
                                hx-swap="innerHTML"
                                hx-confirm="Are you sure you want to activate this sprint? This will make it the active sprint for this project.">
                            <i class="bi bi-play-circle"></i> Activate Sprint
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                            <i class="bi bi-arrow-repeat"></i> Change Status
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <!-- Sprint Progress -->
                    <h6 class="mb-3">Sprint Progress</h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Work Items</span>
                            <span><?php echo $progress['completed_items']; ?>/<?php echo $progress['total_items']; ?></span>
                        </div>
                        <?php 
                        $itemsPercent = $progress['total_items'] > 0 
                            ? round(($progress['completed_items'] / $progress['total_items']) * 100) 
                            : 0;
                        ?>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $itemsPercent; ?>%">
                                <?php echo $itemsPercent; ?>%
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($progress['total_points'] > 0): ?>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Story Points</span>
                            <span><?php echo $progress['completed_points'] ?? 0; ?>/<?php echo $progress['total_points']; ?></span>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Sprint Status Messages -->
    <?php
    $statusMessages = [];
    
    // Check for various restrictions
    if (!$canManageSprint) {
        $statusMessages[] = [
            'type' => 'info',
            'icon' => 'info-circle',
            'message' => 'You have view-only access to this sprint. Only the project manager, sprint creator, or admins can manage sprint items and prompts.'
        ];
    }
    
    if ($displayStatus !== 'active') {
        $statusMessage = '';
        $statusType = 'warning';
        
        if ($displayStatus === 'planning') {
            $statusMessage = 'This sprint is in planning status. Activate the sprint to send prompts to the development system.';
        } elseif ($displayStatus === 'completed') {
            $statusMessage = 'This sprint has been completed. Prompts can no longer be sent or modified.';
            $statusType = 'info';
        } elseif ($displayStatus === 'cancelled') {
            $statusMessage = 'This sprint has been cancelled. No actions can be performed.';
            $statusType = 'dark';
        }
        
        if ($statusMessage) {
            $statusMessages[] = [
                'type' => $statusType,
                'icon' => 'exclamation-triangle',
                'message' => $statusMessage
            ];
        }
    }
    
    // Dynamic status messages for development tools
    if ($canManageSprint && $displayStatus === 'active') {
        // Add div to show dynamic API status messages
        $statusMessages[] = [
            'type' => 'dynamic',
            'content' => '<div id="api-status-messages"></div>'
        ];
    }
    
    // Claude Code configuration checks
    if (!$project['dev_system_url'] && $canManageSprint) {
        $statusMessages[] = [
            'type' => 'danger',
            'icon' => 'exclamation-octagon',
            'class' => 'claude-api-status',
            'message' => 'No Claude Code development system URL configured. <a href="/project-detail.php?id=' . $project['id'] . '" class="alert-link">Configure it in project settings</a> to use Claude Code.'
        ];
    }
    
    if (!$project['skilliks_api_key'] && $canManageSprint && $project['dev_system_url']) {
        $statusMessages[] = [
            'type' => 'danger',
            'icon' => 'key',
            'class' => 'claude-api-status',
            'message' => 'No Skilliks API key configured for Claude Code. <a href="/project-edit.php?id=' . $project['id'] . '" class="alert-link">Configure it in project settings</a>.'
        ];
    }
    
    // Skilliks Coder configuration checks
    if (empty($project['skilliks_system_url']) && $canManageSprint) {
        $statusMessages[] = [
            'type' => 'danger',
            'icon' => 'exclamation-octagon',
            'class' => 'skilliks-api-status',
            'style' => 'display: none;',
            'message' => 'No Skilliks Coder system URL configured. <a href="/project-edit.php?id=' . $project['id'] . '" class="alert-link">Configure it in project settings</a> to use Skilliks Coder.'
        ];
    }
    
    if (empty($project['skilliks_agent_api']) && $canManageSprint) {
        $statusMessages[] = [
            'type' => 'danger',
            'icon' => 'key',
            'class' => 'skilliks-api-status',
            'style' => 'display: none;',
            'message' => 'No Skilliks Agent API key configured. <a href="/project-edit.php?id=' . $project['id'] . '" class="alert-link">Configure it in project settings</a> to use Skilliks Coder.'
        ];
    }
    ?>
    
    <?php if (!empty($statusMessages)): ?>
    <div class="mb-4">
        <?php foreach ($statusMessages as $msg): ?>
            <?php if (isset($msg['type']) && $msg['type'] === 'dynamic'): ?>
                <?php echo $msg['content']; ?>
            <?php else: ?>
                <div class="alert alert-<?php echo $msg['type']; ?> d-flex align-items-center <?php echo isset($msg['class']) ? $msg['class'] : ''; ?>" 
                     role="alert" 
                     <?php echo isset($msg['style']) ? 'style="' . $msg['style'] . '"' : ''; ?>>
                    <i class="bi bi-<?php echo $msg['icon']; ?> me-2"></i>
                    <div><?php echo $msg['message']; ?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sprint Backlog -->
        <div class="col-lg-7 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sprint Backlog (<?php echo count($sprintItems); ?> items)</h5>
                    <?php if ($canManageSprint && $displayStatus !== 'completed'): ?>
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshSprintBacklog()">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div id="sprint-backlog-items">
                        <?php if (empty($sprintItems)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No items in this sprint yet. 
                                Add items from the product backlog.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($sprintItems as $item): ?>
                                <div class="list-group-item" data-work-item-id="<?php echo $item['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <span class="badge bg-secondary me-1"><?php echo ucfirst($item['type']); ?></span>
                                                <a href="/work-item-detail.php?id=<?php echo $item['id']; ?>" class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                            </h6>
                                            
                                            <?php if (!empty($item['description'])): ?>
                                            <div class="mt-2 mb-2">
                                                <div class="description-content" id="desc-<?php echo $item['id']; ?>">
                                                    <p class="text-muted small mb-1"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                                </div>
                                                <div class="description-edit d-none" id="edit-<?php echo $item['id']; ?>">
                                                    <textarea class="form-control form-control-sm" rows="3" id="textarea-<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['description']); ?></textarea>
                                                    <div class="mt-1">
                                                        <button class="btn btn-sm btn-success" onclick="saveDescription(<?php echo $item['id']; ?>)">
                                                            <i class="bi bi-check"></i> Save
                                                        </button>
                                                        <button class="btn btn-sm btn-secondary" onclick="cancelEdit(<?php echo $item['id']; ?>)">
                                                            <i class="bi bi-x"></i> Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php if ($canManageSprint): ?>
                                                <button class="btn btn-link btn-sm p-0 edit-btn" id="edit-btn-<?php echo $item['id']; ?>" onclick="editDescription(<?php echo $item['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit description
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">
                                                <?php if ($item['assignee_id']): ?>
                                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($item['assignee_first_name'] . ' ' . $item['assignee_last_name']); ?>
                                                <?php else: ?>
                                                    <i class="bi bi-person"></i> Unassigned
                                                <?php endif; ?>
                                                
                                                <?php if ($item['story_points']): ?>
                                                    <span class="ms-2"><i class="bi bi-diamond"></i> <?php echo $item['story_points']; ?> pts</span>
                                                <?php endif; ?>
                                            </small>
                                            
                                            <div class="mt-1">
                                                <span class="badge bg-<?php 
                                                    echo $item['status'] === 'done' ? 'success' : 
                                                        ($item['status'] === 'in_progress' ? 'warning' : 
                                                        ($item['status'] === 'in_review' ? 'info' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                </span>
                                                <span class="badge bg-<?php 
                                                    echo $item['priority'] === 'highest' ? 'danger' :
                                                        ($item['priority'] === 'high' ? 'warning' : 
                                                        ($item['priority'] === 'medium' ? 'info' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($item['priority']); ?>
                                                </span>
                                                
                                                <?php if (isset($promptsByWorkItem[$item['id']])): ?>
                                                    <span class="badge bg-info" title="Has <?php echo count($promptsByWorkItem[$item['id']]); ?> prompt(s)">
                                                        <i class="bi bi-robot"></i> <?php echo count($promptsByWorkItem[$item['id']]); ?> Prompt<?php echo count($promptsByWorkItem[$item['id']]) > 1 ? 's' : ''; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($canManageSprint && $displayStatus !== 'completed'): ?>
                                        <div class="d-flex flex-column gap-1 ms-2">
                                            <button class="btn btn-sm btn-success"
                                                    hx-post="/htmx/add-work-item-prompt.php"
                                                    hx-target="#work-item-prompt-<?php echo $item['id']; ?>"
                                                    hx-swap="innerHTML"
                                                    hx-vals='{"work_item_id": "<?php echo $item['id']; ?>", "sprint_id": "<?php echo $sprintId; ?>"}'
                                                    hx-post="/htmx/generate-work-item-prompt.php"
                                                    hx-target="#work-item-prompt-<?php echo $item['id']; ?>"
                                                    hx-swap="innerHTML"
                                                    hx-vals='{"work_item_id": "<?php echo $item['id']; ?>", "sprint_id": "<?php echo $sprintId; ?>"}'
                                                    title="Generate AI prompt for this item">
                                                <i class="bi bi-code-slash"></i>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="work-item-prompt-<?php echo $item['id']; ?>" class="mt-2"></div>
                                    
                                    <?php if (isset($promptsByWorkItem[$item['id']])): ?>
                                    <div class="mt-3 border-top pt-3">
                                        <h6 class="mb-2"><i class="bi bi-robot"></i> Prompts for this item:</h6>
                                        <?php foreach ($promptsByWorkItem[$item['id']] as $promptIndex => $prompt): ?>
                                        <div class="card mb-2 <?php echo $prompt['parent_prompt_id'] ? 'ms-3 border-start border-3 border-info' : ''; ?>">
                                            <div class="card-body p-2">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <?php if ($prompt['parent_prompt_id']): ?>
                                                            <i class="bi bi-arrow-return-right text-info" title="Follow-up prompt"></i>
                                                        <?php endif; ?>
                                                        <strong>Prompt #<?php echo $promptIndex + 1; ?></strong>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $statusClasses = [
                                                            'pending' => 'secondary',
                                                            'executing' => 'warning',
                                                            'test-ready' => 'info',
                                                            'completed' => 'success',
                                                            'failed' => 'danger',
                                                            'cancelled' => 'dark'
                                                        ];
                                                        $statusClass = $statusClasses[$prompt['status']] ?? 'secondary';
                                                        $statusIcon = [
                                                            'pending' => 'clock',
                                                            'executing' => 'arrow-clockwise',
                                                            'test-ready' => 'clipboard-check',
                                                            'completed' => 'check-circle',
                                                            'failed' => 'x-circle',
                                                            'cancelled' => 'dash-circle'
                                                        ];
                                                        $icon = $statusIcon[$prompt['status']] ?? 'question-circle';
                                                        ?>
                                                        <span class="badge bg-<?php echo $statusClass; ?> small">
                                                            <i class="bi bi-<?php echo $icon; ?>"></i> <?php echo ucfirst($prompt['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="small">
                                                    <div id="prompt-view-<?php echo $prompt['id']; ?>">
                                                        <pre class="mb-1 p-2 bg-light rounded" style="font-size: 0.8rem; white-space: pre-wrap; word-wrap: break-word; word-break: break-word; max-height: 100px; overflow-y: auto; overflow-x: hidden;"><?php echo htmlspecialchars(trim($prompt['prompt_text'])); ?></pre>
                                                    </div>
                                                    <div id="prompt-edit-<?php echo $prompt['id']; ?>" class="d-none">
                                                        <textarea class="form-control form-control-sm" rows="4" id="prompt-text-<?php echo $prompt['id']; ?>"><?php echo htmlspecialchars(trim($prompt['prompt_text'])); ?></textarea>
                                                        <div class="mt-1">
                                                            <button class="btn btn-sm btn-success" onclick="savePrompt(<?php echo $prompt['id']; ?>)">
                                                                <i class="bi bi-check"></i> Save
                                                            </button>
                                                            <button class="btn btn-sm btn-secondary" onclick="cancelEditPrompt(<?php echo $prompt['id']; ?>)">
                                                                <i class="bi bi-x"></i> Cancel
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php 
                                                $hasClaudeConfig = !empty($project['dev_system_url']) && !empty($project['skilliks_api_key']);
                                                $hasSkilliksConfig = !empty($project['skilliks_system_url']) && !empty($project['skilliks_agent_api']);
                                                $canSendPrompts = $canManageSprint && ($hasClaudeConfig || $hasSkilliksConfig) && $displayStatus === 'active';
                                                ?>
                                                <?php if ($canSendPrompts): ?>
                                                <div class="mt-2 d-flex flex-wrap gap-1">
                                                    <?php if (in_array($prompt['status'], ['pending', 'failed'])): ?>
                                                    <button class="btn btn-sm btn-primary btn-sm"
                                                            hx-post="/htmx/send-single-prompt.php"
                                                            hx-target="#prompt-action-result-<?php echo $prompt['id']; ?>"
                                                            hx-swap="innerHTML"
                                                            hx-vals='js:{prompt_id: "<?php echo $prompt['id']; ?>", dev_tool: getSelectedDevTool()}'
                                                            hx-indicator="#prompt-spinner-<?php echo $prompt['id']; ?>">
                                                        <i class="bi bi-send"></i> Send
                                                    </button>
                                                    <div id="prompt-spinner-<?php echo $prompt['id']; ?>" class="spinner-border spinner-border-sm htmx-indicator" role="status">
                                                        <span class="visually-hidden">Sending...</span>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    // Debug output
                                                    $showCheckButton = in_array($prompt['status'], ['executing', 'test-ready', 'completed', 'failed']) && 
                                                                      ((!empty($prompt['pid']) && !empty($prompt['log_file_name'])) || !empty($prompt['session_id']));
                                                    /*
                                                    echo "<!-- Debug Check Button: ";
                                                    echo "Status: " . $prompt['status'] . ", ";
                                                    echo "PID: " . ($prompt['pid'] ?? 'null') . ", ";
                                                    echo "Log: " . ($prompt['log_file_name'] ?? 'null') . ", ";
                                                    echo "SessionID: " . ($prompt['session_id'] ?? 'null') . ", ";
                                                    echo "SessionIdCol: " . ($sessionIdColumnExists ? 'yes' : 'no') . ", ";
                                                    echo "Show: " . ($showCheckButton ? 'yes' : 'no');
                                                    echo " -->";
                                                    */
                                                    ?>
                                                    <?php if ($showCheckButton): ?>
                                                    <button class="btn btn-sm btn-info btn-sm"
                                                            hx-post="/htmx/check-prompt-status.php"
                                                            hx-target="#prompt-action-result-<?php echo $prompt['id']; ?>"
                                                            hx-swap="innerHTML"
                                                            hx-vals='{"prompt_id": "<?php echo $prompt['id']; ?>"}'
                                                            hx-indicator="#prompt-status-spinner-<?php echo $prompt['id']; ?>">
                                                        <i class="bi bi-arrow-clockwise"></i> Check
                                                    </button>
                                                    <div id="prompt-status-spinner-<?php echo $prompt['id']; ?>" class="spinner-border spinner-border-sm htmx-indicator" role="status">
                                                        <span class="visually-hidden">Checking...</span>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($prompt['status'], ['completed', 'test-ready'])): ?>
                                                    <button class="btn btn-sm btn-primary btn-sm" 
                                                            onclick="showPromptResults(<?php echo $prompt['id']; ?>)"
                                                            id="results-btn-<?php echo $prompt['id']; ?>">
                                                        <i class="bi bi-list-check"></i> Show Results
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php
                                                    // Show Complete button only for executing and test-ready
                                                    if (in_array($prompt['status'], ['executing', 'test-ready'])):
                                                    ?>
                                                    <button class="btn btn-sm btn-success btn-sm"
                                                            onclick="toggleCompletionForm(<?php echo $prompt['id']; ?>)"
                                                            id="complete-btn-<?php echo $prompt['id']; ?>">
                                                        <i class="bi bi-check-circle"></i> Complete
                                                    </button>
                                                    <?php
                                                    // Show Delete button for failed or never sent prompts
                                                    elseif (in_array($prompt['status'], ['pending', 'failed'])):
                                                    ?>
                                                    <button class="btn btn-sm btn-danger btn-sm"
                                                            hx-post="/htmx/delete-prompt.php"
                                                            hx-target="#prompt-action-result-<?php echo $prompt['id']; ?>"
                                                            hx-swap="innerHTML"
                                                            hx-vals='{"prompt_id": "<?php echo $prompt['id']; ?>"}'
                                                            hx-confirm="Are you sure you want to delete this prompt?">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($prompt['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-warning btn-sm" onclick="editPrompt(<?php echo $prompt['id']; ?>)">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($prompt['status'], ['completed', 'test-ready'])): ?>
                                                    <button class="btn btn-sm btn-primary btn-sm"
                                                            onclick="showFollowUpPromptForm(<?php echo $prompt['id']; ?>)">
                                                        <i class="bi bi-arrow-return-right"></i> Add Follow-up
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <div id="prompt-action-result-<?php echo $prompt['id']; ?>" class="mt-1"></div>
                                                <div id="followup-container-<?php echo $prompt['id']; ?>"></div>
                                                
                                                <!-- Completion Form -->
                                                <div id="completion-form-<?php echo $prompt['id']; ?>" class="d-none mt-3">
                                                    <div class="card border-success">
                                                        <div class="card-header bg-success text-white">
                                                            <h6 class="mb-0">Complete Prompt</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <!-- Rating Section -->
                                                            <div class="mb-3">
                                                                <label class="form-label">Rating:</label>
                                                                <div class="star-rating" id="completion-rating-<?php echo $prompt['id']; ?>">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="bi bi-star star-icon" 
                                                                           data-rating="<?php echo $i; ?>"
                                                                           style="cursor: pointer; color: #ddd; font-size: 1.5rem;"
                                                                           onclick="setCompletionRating(<?php echo $prompt['id']; ?>, <?php echo $i; ?>)"></i>
                                                                    <?php endfor; ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Test Notes -->
                                                            <div class="mb-3">
                                                                <label class="form-label" for="test-notes-<?php echo $prompt['id']; ?>">Test Notes:</label>
                                                                <textarea class="form-control" 
                                                                          id="test-notes-<?php echo $prompt['id']; ?>"
                                                                          rows="3"
                                                                          placeholder="Enter any testing observations or results..."></textarea>
                                                            </div>
                                                            
                                                            <!-- Developer Notes -->
                                                            <div class="mb-3">
                                                                <label class="form-label" for="dev-notes-<?php echo $prompt['id']; ?>">Developer Notes:</label>
                                                                <textarea class="form-control" 
                                                                          id="dev-notes-<?php echo $prompt['id']; ?>"
                                                                          rows="3"
                                                                          placeholder="Enter any implementation notes or technical details..."></textarea>
                                                            </div>
                                                            
                                                            <!-- Action Buttons -->
                                                            <div class="d-flex gap-2">
                                                                <button class="btn btn-success"
                                                                        onclick="submitPromptCompletion(<?php echo $prompt['id']; ?>)">
                                                                    <i class="bi bi-check-circle"></i> Mark Complete
                                                                </button>
                                                                <button class="btn btn-secondary"
                                                                        onclick="toggleCompletionForm(<?php echo $prompt['id']; ?>)">
                                                                    <i class="bi bi-x"></i> Cancel
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php elseif ($canManageSprint && !$hasClaudeConfig && !$hasSkilliksConfig && $displayStatus === 'active'): ?>
                                                <div class="mt-2">
                                                    <small class="text-warning">
                                                        <i class="bi bi-exclamation-triangle"></i> 
                                                        No development tools configured. Please configure Claude Code or Skilliks Coder in 
                                                        <a href="/project-edit.php?id=<?php echo $project['id']; ?>" class="text-warning">project settings</a>.
                                                    </small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div><!-- /.list-group-item -->
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Executed Prompts -->
        <div class="col-lg-5 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Execution History</h5>
                    <?php if ($totalCost > 0): ?>
                    <span class="badge bg-success" title="Total cost for all prompts in this sprint">
                        <i class="bi bi-currency-dollar"></i> <?php echo number_format($totalCost, 4); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="card-body execution-history">
                    <?php if (empty($executedPrompts)): ?>
                        <p class="text-muted text-center">No prompts executed yet</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($executedPrompts as $prompt): ?>
                                <div class="list-group-item px-0" id="executed-prompt-<?php echo $prompt['id']; ?>">
                                    <div>
                                        <div class="d-flex align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small">
                                                    <?php if ($prompt['work_item_title']): ?>
                                                        <?php echo htmlspecialchars($prompt['work_item_title']); ?>
                                                    <?php else: ?>
                                                        Manual Prompt
                                                    <?php endif; ?>
                                                </h6>
                                                <div class="d-flex align-items-center mb-2">
                                                    <?php
                                                    $statusClass = 'secondary';
                                                    $statusIcon = 'clock';
                                                    if ($prompt['status'] === 'completed') {
                                                        $statusClass = 'success';
                                                        $statusIcon = 'check-circle';
                                                    } elseif ($prompt['status'] === 'failed') {
                                                        $statusClass = 'danger';
                                                        $statusIcon = 'x-circle';
                                                    } elseif ($prompt['status'] === 'executing') {
                                                        $statusClass = 'warning';
                                                        $statusIcon = 'arrow-clockwise';
                                                    } elseif ($prompt['status'] === 'test-ready') {
                                                        $statusClass = 'info';
                                                        $statusIcon = 'clipboard-check';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass; ?> me-2">
                                                        <i class="bi bi-<?php echo $statusIcon; ?>"></i>
                                                    </span>
                                                    <span class="badge bg-secondary me-2" title="Prompt ID">
                                                        #<?php echo $prompt['id']; ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php echo date('H:i', strtotime($prompt['executed_at'])); ?>
                                                    </small>
                                                    <?php if (!empty($prompt['total_cost_usd']) && $prompt['total_cost_usd'] > 0): ?>
                                                    <span class="badge bg-success ms-2" title="Prompt cost">
                                                        $<?php echo number_format($prompt['total_cost_usd'], 4); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($prompt['has_followups'] > 0): ?>
                                                    <span class="badge bg-info ms-1" title="Has <?php echo $prompt['has_followups']; ?> follow-up(s)">
                                                        <i class="bi bi-arrow-return-right"></i> <?php echo $prompt['has_followups']; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($prompt['parent_prompt_id']): ?>
                                                    <span class="badge bg-warning ms-1" title="This is a follow-up prompt">
                                                        <i class="bi bi-arrow-return-left"></i> Follow-up
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($canManageSprint && !empty($prompt['response_text'])): ?>
                                                        <?php
                                                        // Check if response can be repaired (array or single result object)
                                                        $canRepair = false;
                                                        $responseData = json_decode($prompt['response_text'], true);
                                                        if (json_last_error() === JSON_ERROR_NONE && is_array($responseData) && !empty($responseData)) {
                                                            // Check for array format (Claude messages)
                                                            if (isset($responseData[0]) && isset($responseData[0]['type'])) {
                                                                $canRepair = true;
                                                            }
                                                            // Check for single result object with usage data
                                                            elseif (isset($responseData['type']) && $responseData['type'] === 'result' &&
                                                                   isset($responseData['usage']) && isset($responseData['total_cost_usd'])) {
                                                                // Can repair if token data is missing in DB
                                                                $canRepair = is_null($prompt['input_tokens']) || is_null($prompt['output_tokens']) || is_null($prompt['total_cost_usd']);
                                                            }
                                                        }
                                                        ?>
                                                        <?php if ($canRepair): ?>
                                                        <button class="btn btn-sm btn-outline-warning ms-auto me-1"
                                                                hx-post="/htmx/repair-ai-tables.php"
                                                                hx-target="#repair-result-<?php echo $prompt['id']; ?>"
                                                                hx-swap="innerHTML"
                                                                hx-vals='{"prompt_id": "<?php echo $prompt['id']; ?>"}'
                                                                hx-confirm="This will reprocess the response array and rebuild the AI tables. Continue?"
                                                                title="Repair AI tables">
                                                            <i class="bi bi-wrench"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-secondary <?php echo (!$canManageSprint || empty($prompt['response_text'])) ? 'ms-auto' : ''; ?>"
                                                            onclick="toggleExecutionDetails(<?php echo $prompt['id']; ?>)"
                                                            id="execution-toggle-btn-<?php echo $prompt['id']; ?>"
                                                            title="Toggle details">
                                                        <i class="bi bi-chevron-down"></i>
                                                    </button>
                                                </div>
                                                <div id="repair-result-<?php echo $prompt['id']; ?>"></div>
                                                <div class="mb-2">
                                                    <h6 class="small mb-1">Prompt:</h6>
                                                    <pre class="mb-0 small p-2 bg-light rounded" style="font-size: 0.8rem; white-space: pre-wrap; word-wrap: break-word; word-break: break-word; max-height: 200px; overflow-y: auto; overflow-x: hidden;"><?php echo htmlspecialchars(trim($prompt['prompt_text'])); ?></pre>
                                                    <?php if ($prompt['parent_prompt_id'] && $prompt['parent_prompt_text']): ?>
                                                    <div class="mt-2 ms-3 small text-muted">
                                                        <i class="bi bi-arrow-return-left"></i> <strong>Parent Prompt (ID: <?php echo $prompt['parent_prompt_id']; ?>):</strong>
                                                        <pre class="mb-0 p-2 bg-light rounded" style="font-size: 0.75rem; white-space: pre-wrap; word-wrap: break-word; word-break: break-word; max-height: 100px; overflow-y: auto; overflow-x: hidden;"><?php echo htmlspecialchars(trim($prompt['parent_prompt_text'])); ?></pre>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                            
                                            <!-- Collapsible details section -->
                                            <div id="execution-details-<?php echo $prompt['id']; ?>" class="collapse mt-3">
                                                <?php if (in_array($prompt['status'], ['completed', 'test-ready', 'executing'])): ?>
                                                    <?php
                                                    // Query AI messages and content for this prompt
                                                    $messagesQuery = $db->prepare("
                                                        SELECT 
                                                            m.session_id,
                                                            m.sequence_number,
                                                            m.type as message_type,
                                                            m.subtype,
                                                            m.role,
                                                            c.content_type,
                                                            c.content_text,
                                                            c.tool_name,
                                                            tu.input_tokens,
                                                            tu.output_tokens,
                                                            tu.total_tokens,
                                                            tu.cost_usd
                                                        FROM ai_messages m
                                                        LEFT JOIN ai_message_content c ON m.id = c.message_id
                                                        LEFT JOIN ai_token_usage tu ON m.id = tu.message_id
                                                        WHERE m.prompt_id = ?
                                                        ORDER BY m.sequence_number, c.id
                                                    ");
                                                    $messagesQuery->execute([$prompt['id']]);
                                                    $aiMessages = $messagesQuery->fetchAll();
                                                    ?>
                                                    
                                                    <?php if (!empty($aiMessages)): ?>
                                                        <div class="mt-2 p-2 bg-light rounded">
                                                            <h6 class="small mb-1">AI Response Details:</h6>
                                                            <div class="small">
                                                                <div class="mb-2">
                                                                    <strong>Session ID:</strong> 
                                                                    <span class="text-muted"><?php echo htmlspecialchars($aiMessages[0]['session_id'] ?? 'N/A'); ?></span>
                                                                </div>
                                                                <?php foreach ($aiMessages as $message): ?>
                                                                    <div class="mb-2 p-2 border-start border-3 <?php 
                                                                        $contentType = $message['content_type'] ?? '';
                                                                        $messageType = $message['message_type'] ?? '';
                                                                        echo $messageType === 'system' ? 'border-success' :
                                                                            ($contentType === 'tool_use' ? 'border-info' : 
                                                                            ($contentType === 'tool_result' ? 'border-warning' : 'border-secondary')); 
                                                                    ?>">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                            <div>
                                                                                <?php 
                                                                                // Determine badge color and text
                                                                                if ($messageType === 'system') {
                                                                                    $badgeClass = 'bg-success';
                                                                                    $badgeText = $message['content_type'] ?? ($message['subtype'] ? $messageType . '/' . $message['subtype'] : $messageType);
                                                                                } else {
                                                                                    $badgeClass = 'bg-secondary';
                                                                                    $badgeText = $message['content_type'] ?? $messageType;
                                                                                }
                                                                                ?>
                                                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($badgeText ?? ''); ?></span>
                                                                                <?php if ($message['tool_name']): ?>
                                                                                    <span class="badge bg-info ms-1"><?php echo htmlspecialchars($message['tool_name'] ?? ''); ?></span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <small class="text-muted">Seq: <?php echo $message['sequence_number']; ?></small>
                                                                            <?php if ($message['input_tokens'] || $message['output_tokens'] || $message['total_tokens'] || $message['cost_usd']): ?>
                                                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                                                <small>
                                                                                    Input: <?php echo $message['input_tokens'] ?? 0; ?> | 
                                                                                    Output: <?php echo $message['output_tokens'] ?? 0; ?> | 
                                                                                    Total: <?php echo $message['total_tokens'] ?? 0; ?> | 
                                                                                    Cost: $<?php echo number_format($message['cost_usd'] ?? 0, 6); ?>
                                                                                </small>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <?php if ($message['content_text']): ?>
                                                                            <div class="mt-1" style="max-height: 200px; overflow-y: auto;">
                                                                                <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word; font-size: 0.75rem;"><?php echo htmlspecialchars($message['content_text']); ?></pre>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Fall back to original response_text if no AI messages found -->
                                                        <?php if (!empty($prompt['response_text'])): ?>
                                                            <div class="mt-2 p-2 bg-light rounded">
                                                                <h6 class="small mb-1">Response:</h6>
                                                                <pre class="mb-0 small" style="white-space: pre-wrap; word-wrap: break-word; word-break: break-word; max-height: 300px; overflow-y: auto; overflow-x: hidden;"><?php echo htmlspecialchars(trim($prompt['response_text'])); ?></pre>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php elseif ($prompt['status'] === 'failed' && !empty($prompt['error_message'])): ?>
                                                    <button class="btn btn-sm btn-link p-0 mt-1 text-danger" 
                                                            onclick="viewPromptError(<?php echo $prompt['id']; ?>)">
                                                        <i class="bi bi-exclamation-circle"></i> View Error
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Rating Section -->
                                                <div class="mt-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <small class="text-muted">Rating:</small>
                                                        <div class="star-rating" data-prompt-id="<?php echo $prompt['id']; ?>">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo ($prompt['rating'] && $i <= $prompt['rating']) ? '-fill' : ''; ?> star-icon" 
                                                                   data-rating="<?php echo $i; ?>"
                                                                   style="cursor: pointer; color: <?php echo ($prompt['rating'] && $i <= $prompt['rating']) ? '#ffc107' : '#ddd'; ?>;"
                                                                   onclick="ratePrompt(<?php echo $prompt['id']; ?>, <?php echo $i; ?>)"></i>
                                                            <?php endfor; ?>
                                                            <?php if ($prompt['rating'] && $prompt['rating_first_name']): ?>
                                                                <small class="text-muted ms-2">
                                                                    by <?php echo htmlspecialchars($prompt['rating_first_name'] . ' ' . $prompt['rating_last_name']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                
                                                <!-- Test Notes Section -->
                                                <div class="mt-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">Test Notes:</small>
                                                        <?php if ($canManageSprint): ?>
                                                            <button class="btn btn-link btn-sm p-0" onclick="toggleNoteEdit('test', <?php echo $prompt['id']; ?>)">
                                                                <i class="bi bi-pencil"></i> <?php echo $prompt['test_notes'] ? 'Edit' : 'Add'; ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div id="test-notes-display-<?php echo $prompt['id']; ?>" class="small <?php echo !$prompt['test_notes'] ? 'd-none' : ''; ?>">
                                                        <div class="p-2 bg-info bg-opacity-10 rounded mt-1">
                                                            <?php echo nl2br(htmlspecialchars($prompt['test_notes'] ?? '')); ?>
                                                            <?php if ($prompt['test_notes'] && $prompt['test_notes_first_name']): ?>
                                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                                    - <?php echo htmlspecialchars($prompt['test_notes_first_name'] . ' ' . $prompt['test_notes_last_name']); ?>
                                                                    <?php if ($prompt['test_notes_updated_at']): ?>
                                                                        at <?php echo date('M j, H:i', strtotime($prompt['test_notes_updated_at'])); ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div id="test-notes-edit-<?php echo $prompt['id']; ?>" class="d-none mt-1">
                                                        <textarea class="form-control form-control-sm" rows="2" id="test-notes-textarea-<?php echo $prompt['id']; ?>"><?php echo htmlspecialchars($prompt['test_notes'] ?? ''); ?></textarea>
                                                        <div class="mt-1">
                                                            <button class="btn btn-sm btn-success btn-sm" onclick="saveNote('test', <?php echo $prompt['id']; ?>)">Save</button>
                                                            <button class="btn btn-sm btn-secondary btn-sm" onclick="toggleNoteEdit('test', <?php echo $prompt['id']; ?>)">Cancel</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Developer Notes Section -->
                                                <div class="mt-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">Developer Notes:</small>
                                                        <?php if ($canManageSprint): ?>
                                                            <button class="btn btn-link btn-sm p-0" onclick="toggleNoteEdit('developer', <?php echo $prompt['id']; ?>)">
                                                                <i class="bi bi-pencil"></i> <?php echo $prompt['developer_notes'] ? 'Edit' : 'Add'; ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div id="developer-notes-display-<?php echo $prompt['id']; ?>" class="small <?php echo !$prompt['developer_notes'] ? 'd-none' : ''; ?>">
                                                        <div class="p-2 bg-success bg-opacity-10 rounded mt-1">
                                                            <?php echo nl2br(htmlspecialchars($prompt['developer_notes'] ?? '')); ?>
                                                            <?php if ($prompt['developer_notes'] && $prompt['developer_notes_first_name']): ?>
                                                                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                                                    - <?php echo htmlspecialchars($prompt['developer_notes_first_name'] . ' ' . $prompt['developer_notes_last_name']); ?>
                                                                    <?php if ($prompt['developer_notes_updated_at']): ?>
                                                                        at <?php echo date('M j, H:i', strtotime($prompt['developer_notes_updated_at'])); ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div id="developer-notes-edit-<?php echo $prompt['id']; ?>" class="d-none mt-1">
                                                        <textarea class="form-control form-control-sm" rows="2" id="developer-notes-textarea-<?php echo $prompt['id']; ?>"><?php echo htmlspecialchars($prompt['developer_notes'] ?? ''); ?></textarea>
                                                        <div class="mt-1">
                                                            <button class="btn btn-sm btn-success btn-sm" onclick="saveNote('developer', <?php echo $prompt['id']; ?>)">Save</button>
                                                            <button class="btn btn-sm btn-secondary btn-sm" onclick="toggleNoteEdit('developer', <?php echo $prompt['id']; ?>)">Cancel</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    </div>
                                </div><!-- /.list-group-item -->
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Product Backlog -->
        <?php if ($displayStatus !== 'active'): ?>
        <div class="col-lg-12 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Product Backlog (<?php echo count($productBacklog); ?> available)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($productBacklog)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No items available in the product backlog. 
                            <a href="/work-items.php?project=<?php echo $sprint['project_id']; ?>">Create and approve work items</a>.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($productBacklog as $item): ?>
                            <div class="list-group-item" data-work-item-id="<?php echo $item['id']; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <span class="badge bg-secondary me-1"><?php echo ucfirst($item['type']); ?></span>
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($item['reporter_first_name'] . ' ' . $item['reporter_last_name']); ?>
                                            
                                            <?php if ($item['story_points']): ?>
                                                <span class="ms-2"><i class="bi bi-diamond"></i> <?php echo $item['story_points']; ?> pts</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($item['backlog_priority'] > 0): ?>
                                                <span class="ms-2"><i class="bi bi-sort-numeric-down"></i> Priority: <?php echo $item['backlog_priority']; ?></span>
                                            <?php endif; ?>
                                        </small>
                                        
                                        <div class="mt-1">
                                            <span class="badge bg-<?php 
                                                echo $item['priority'] === 'highest' ? 'danger' :
                                                    ($item['priority'] === 'high' ? 'warning' : 
                                                    ($item['priority'] === 'medium' ? 'info' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($item['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($canManageSprint && $displayStatus !== 'completed'): ?>
                                    <button class="btn btn-sm btn-outline-primary"
                                            hx-post="/htmx/add-to-sprint.php"
                                            hx-target="#sprint-messages"
                                            hx-swap="innerHTML"
                                            hx-vals='{"work_item_id": "<?php echo $item['id']; ?>", "sprint_id": "<?php echo $sprintId; ?>"}'
                                            hx-on:htmx:after-request="refreshSprintBacklog()">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div id="sprint-messages" class="mt-3"></div>
    
    <!-- Status Update Section -->
    <div id="prompt-status-updates" class="mt-3">
        <div id="executing-prompts-status"></div>
    </div>
</div>

<style>
.description-content {
    position: relative;
}

.description-content p {
    margin-bottom: 0.5rem;
}

.description-edit textarea {
    font-size: 0.875rem;
}

.list-group-item:hover .edit-btn {
    opacity: 1;
}

.edit-btn {
    opacity: 0.6;
    transition: opacity 0.2s;
}

.edit-btn:hover {
    opacity: 1;
    text-decoration: underline;
}


.list-group-item .card {
    font-size: 0.875rem;
}

/* Star rating styles */
.star-rating .star-icon {
    font-size: 1.2rem;
    transition: color 0.2s ease;
}

.star-rating .star-icon:hover {
    color: #ffc107 !important;
}

/* Notes section styles */
.bg-info.bg-opacity-10 {
    background-color: rgba(13, 202, 240, 0.1) !important;
}

.bg-success.bg-opacity-10 {
    background-color: rgba(25, 135, 84, 0.1) !important;
}

/* Execution history styles */
#executed-prompt- .list-group-item {
    border-left: 3px solid transparent;
    transition: border-color 0.2s ease;
}

#executed-prompt- .list-group-item:hover {
    border-left-color: #0d6efd;
}

.collapse {
    transition: height 0.35s ease;
}

/* Execution history button styling */
.execution-history .btn-outline-secondary {
    border-color: #dee2e6;
    padding: 0.25rem 0.5rem;
}

/* Fix text wrapping in execution history */
.execution-history .list-group-item {
    overflow-wrap: break-word;
    word-wrap: break-word;
    word-break: break-word;
}

.execution-history h6 {
    overflow-wrap: break-word;
    word-wrap: break-word;
    word-break: break-word;
}
</style>

<script>
// Store the selected development tool in session storage
let selectedDevTool = sessionStorage.getItem('selectedDevTool') || 'claude';
document.addEventListener('DOMContentLoaded', function() {
    const devToolSelect = document.getElementById('dev-tool-select');
    if (devToolSelect) {
        devToolSelect.value = selectedDevTool;
    }
    // Update API status messages on page load
    updateAPIStatusMessages();
});

function updateDevToolSelection() {
    const devToolSelect = document.getElementById('dev-tool-select');
    selectedDevTool = devToolSelect.value;
    sessionStorage.setItem('selectedDevTool', selectedDevTool);
    
    // Update any visible messages about API configuration
    updateAPIStatusMessages();
}

function getSelectedDevTool() {
    return selectedDevTool;
}

function updateAPIStatusMessages() {
    // Show/hide API status messages based on selected tool
    const claudeMessages = document.querySelectorAll('.claude-api-status');
    const skilliksMessages = document.querySelectorAll('.skilliks-api-status');
    
    if (selectedDevTool === 'claude') {
        claudeMessages.forEach(msg => msg.style.display = 'flex');
        skilliksMessages.forEach(msg => msg.style.display = 'none');
    } else if (selectedDevTool === 'skilliks') {
        claudeMessages.forEach(msg => msg.style.display = 'none');
        skilliksMessages.forEach(msg => msg.style.display = 'flex');
    }
}

function refreshSprintBacklog() {
    // Reload the page to refresh both sprint backlog and product backlog
    window.location.reload();
}

function editDescription(id) {
    document.getElementById('desc-' + id).classList.add('d-none');
    document.getElementById('edit-' + id).classList.remove('d-none');
    document.getElementById('edit-btn-' + id).classList.add('d-none');
}

function cancelEdit(id) {
    document.getElementById('desc-' + id).classList.remove('d-none');
    document.getElementById('edit-' + id).classList.add('d-none');
    document.getElementById('edit-btn-' + id).classList.remove('d-none');
}

function saveDescription(id) {
    const description = document.getElementById('textarea-' + id).value;
    
    // Use HTMX to save the description
    fetch('/htmx/update-work-item-description.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'work_item_id=' + id + '&description=' + encodeURIComponent(description)
    })
    .then(response => response.text())
    .then(html => {
        // Update the description content
        document.getElementById('desc-' + id).innerHTML = '<p class="text-muted small mb-1">' + description.replace(/\n/g, '<br>') + '</p>';
        // Hide edit form and show content
        cancelEdit(id);
        // Show success message
        document.getElementById('sprint-messages').innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update description');
    });
}

// Listen for HTMX events to refresh after adding/removing items
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.successful && 
        (event.detail.pathInfo.requestPath.includes('add-to-sprint') || 
         event.detail.pathInfo.requestPath.includes('remove-from-sprint'))) {
        setTimeout(refreshSprintBacklog, 1000);
    }
});
</script>

<script>
// Browser notification system
let notificationPermission = 'default';
let pollingInterval = null;

// Check notification permission on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if browser supports notifications
    if (!("Notification" in window)) {
        console.log("This browser does not support notifications");
        return;
    }
    
    // Check current permission status
    notificationPermission = Notification.permission;
    
    // Show notification permission button if not granted
    if (notificationPermission === 'default') {
        showNotificationPrompt();
    } else if (notificationPermission === 'granted') {
        startPolling();
    }
});

function showNotificationPrompt() {
    const container = document.getElementById('sprint-messages');
    if (container) {
        container.innerHTML = `
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-bell"></i> Enable browser notifications to get alerts when prompts complete.
                <button type="button" class="btn btn-sm btn-primary ms-2" onclick="requestNotificationPermission()">
                    Enable Notifications
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
}

function requestNotificationPermission() {
    Notification.requestPermission().then(function(permission) {
        notificationPermission = permission;
        if (permission === 'granted') {
            document.getElementById('sprint-messages').innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> Notifications enabled! You'll be notified when prompts complete.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            startPolling();
        } else if (permission === 'denied') {
            document.getElementById('sprint-messages').innerHTML = `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> Notifications blocked. Enable them in your browser settings.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    });
}

function startPolling() {
    // Check for notifications immediately
    checkForNotifications();
    
    // Then check every 30 seconds
    pollingInterval = setInterval(checkForNotifications, 30000);
}

function checkForNotifications() {
    fetch('/htmx/check-notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    showBrowserNotification(notification);
                    markNotificationRead(notification.id);
                });
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

function showBrowserNotification(notificationData) {
    const title = notificationData.status === 'completed' 
        ? 'Prompt Completed!' 
        : 'Prompt Failed';
    
    const body = `${notificationData.project_name} - ${notificationData.sprint_name}\n` +
                 `${notificationData.work_item_title || 'Manual prompt'}\n` +
                 `${notificationData.prompt_text}`;
    
    const options = {
        body: body,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: 'prompt-' + notificationData.prompt_id,
        requireInteraction: true,
        data: {
            prompt_id: notificationData.prompt_id,
            sprint_id: notificationData.sprint_id
        }
    };
    
    const notification = new Notification(title, options);
    
    // Handle notification click
    notification.onclick = function(event) {
        event.preventDefault();
        window.focus();
        // Optionally scroll to the prompt in the accordion
        const promptElement = document.querySelector('#collapse' + notificationData.prompt_id);
        if (promptElement) {
            promptElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Expand the accordion if collapsed
            const bsCollapse = new bootstrap.Collapse(promptElement, { show: true });
        }
        notification.close();
    };
    
    // Play sound if enabled (check user preference)
    <?php
    $userStmt = $db->prepare("SELECT notification_sound FROM users WHERE id = ?");
    $userStmt->execute([$currentUserId]);
    $userPrefs = $userStmt->fetch();
    if ($userPrefs && $userPrefs['notification_sound']):
    ?>
    playNotificationSound();
    <?php endif; ?>
}

function markNotificationRead(notificationId) {
    fetch('/htmx/mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    });
}

function playNotificationSound() {
    // Create a simple beep sound
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = 800;
    oscillator.type = 'sine';
    gainNode.gain.value = 0.1;
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.2);
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
});

// Auto-refresh executing prompts status
let executingPromptsInterval = null;
let hasExecutingPrompts = false;

// Check if we have executing prompts on page load
document.addEventListener('DOMContentLoaded', function() {
    checkExecutingPrompts();
});

function checkExecutingPrompts() {
    // Find all prompts with executing status
    const executingBadges = document.querySelectorAll('.badge.bg-warning');
    hasExecutingPrompts = false;
    
    executingBadges.forEach(badge => {
        if (badge.textContent.includes('Executing')) {
            hasExecutingPrompts = true;
        }
    });
    
    if (hasExecutingPrompts) {
        startExecutingPromptsPolling();
        updateExecutingPromptsStatus();
    }
}

function startExecutingPromptsPolling() {
    if (!executingPromptsInterval) {
        // Check every 15 seconds for executing prompts
        executingPromptsInterval = setInterval(updateExecutingPromptsStatus, 15000);
    }
}

function updateExecutingPromptsStatus() {
    // Get sprint ID from the page
    const sprintId = <?php echo json_encode($sprintId); ?>;
    
    fetch('/htmx/check-executing-prompts.php?sprint_id=' + sprintId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('executing-prompts-status').innerHTML = html;
            
            // Check if any prompts completed
            if (html.includes('refresh-page')) {
                setTimeout(() => window.location.reload(), 2000);
            }
        })
        .catch(error => console.error('Error checking executing prompts:', error));
}

// Stop polling when no executing prompts
function stopExecutingPromptsPolling() {
    if (executingPromptsInterval) {
        clearInterval(executingPromptsInterval);
        executingPromptsInterval = null;
    }
}

function showSprintNotActiveModal() {
    const modal = new bootstrap.Modal(document.getElementById('sprintNotActiveModal'));
    modal.show();
}

// View prompt response in a modal
function viewPromptResponse(promptId) {
    // Fetch the prompt details
    fetch(`/htmx/get-prompt-details.php?id=${promptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPromptModal('Prompt Response', data.prompt.response_text || data.prompt.response, 'success');
            } else {
                alert('Failed to load prompt response');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load prompt response');
        });
}

// View prompt error in a modal
function viewPromptError(promptId) {
    // Fetch the prompt details
    fetch(`/htmx/get-prompt-details.php?id=${promptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPromptModal('Prompt Error', data.prompt.error_message, 'danger');
            } else {
                alert('Failed to load prompt error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load prompt error');
        });
}

// Show prompt details in a modal
function showPromptModal(title, content, type = 'info', isHtml = false) {
    const modalHtml = `
        <div class="modal fade" id="promptDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${isHtml ? content : `<pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(content)}</pre>`}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('promptDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('promptDetailsModal'));
    modal.show();
    
    // Clean up on hide
    document.getElementById('promptDetailsModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Toggle note edit mode
function toggleNoteEdit(type, promptId) {
    const displayDiv = document.getElementById(`${type}-notes-display-${promptId}`);
    const editDiv = document.getElementById(`${type}-notes-edit-${promptId}`);
    
    if (editDiv.classList.contains('d-none')) {
        editDiv.classList.remove('d-none');
        displayDiv.classList.add('d-none');
    } else {
        editDiv.classList.add('d-none');
        if (document.getElementById(`${type}-notes-textarea-${promptId}`).value.trim()) {
            displayDiv.classList.remove('d-none');
        }
    }
}

// Save note
function saveNote(type, promptId) {
    const textarea = document.getElementById(`${type}-notes-textarea-${promptId}`);
    const notes = textarea.value.trim();
    
    fetch('/htmx/update-prompt-notes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `prompt_id=${promptId}&type=${type}&notes=${encodeURIComponent(notes)}`
    })
    .then(response => response.text())
    .then(html => {
        // Reload the page to show updated notes
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save notes');
    });
}

// Rate prompt
function ratePrompt(promptId, rating) {
    fetch('/htmx/rate-prompt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `prompt_id=${promptId}&rating=${rating}`
    })
    .then(response => response.text())
    .then(html => {
        // Update stars display
        const starContainer = document.querySelector(`[data-prompt-id="${promptId}"]`);
        const stars = starContainer.querySelectorAll('.star-icon');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.remove('bi-star');
                star.classList.add('bi-star-fill');
                star.style.color = '#ffc107';
            } else {
                star.classList.remove('bi-star-fill');
                star.classList.add('bi-star');
                star.style.color = '#ddd';
            }
        });
        
        // Show success message
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        document.getElementById('sprint-messages').innerHTML = tempDiv.innerHTML;
        
        // Reload after a short delay to show attribution
        setTimeout(() => window.location.reload(), 1000);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save rating');
    });
}

// Toggle prompt details
function togglePromptDetails(promptId) {
    const detailsDiv = document.getElementById(`prompt-details-${promptId}`);
    const toggleBtn = document.getElementById(`toggle-btn-${promptId}`);
    const icon = toggleBtn.querySelector('i');
    
    if (detailsDiv.classList.contains('d-none')) {
        // Show details
        detailsDiv.classList.remove('d-none');
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-up');
        toggleBtn.innerHTML = '<i class="bi bi-chevron-up"></i> Hide';
    } else {
        // Hide details
        detailsDiv.classList.add('d-none');
        icon.classList.remove('bi-chevron-up');
        icon.classList.add('bi-chevron-down');
        toggleBtn.innerHTML = '<i class="bi bi-chevron-down"></i> Details';
    }
}

// Toggle execution history details
function toggleExecutionDetails(promptId) {
    const detailsDiv = document.getElementById(`execution-details-${promptId}`);
    const toggleBtn = document.getElementById(`execution-toggle-btn-${promptId}`);
    
    // Toggle Bootstrap collapse
    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(detailsDiv);
    bsCollapse.toggle();
}

// Initialize collapse event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Find all execution detail divs
    const executionDetails = document.querySelectorAll('[id^="execution-details-"]');
    
    executionDetails.forEach(detailsDiv => {
        const promptId = detailsDiv.id.replace('execution-details-', '');
        const toggleBtn = document.getElementById(`execution-toggle-btn-${promptId}`);
        
        // Listen for Bootstrap collapse events
        detailsDiv.addEventListener('show.bs.collapse', function() {
            toggleBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
        });
        
        detailsDiv.addEventListener('hide.bs.collapse', function() {
            toggleBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
        });
    });
    
    // Check for incomplete prompts and update modal
    const incompletePromptsCount = <?php echo json_encode($incompletePromptsCount); ?>;
    const statusSelect = document.getElementById('sprint_status');
    
    if (statusSelect && incompletePromptsCount > 0) {
        // Listen for status selection changes
        statusSelect.addEventListener('change', function() {
            if (this.value === 'completed') {
                alert('Cannot select "Completed" status. This sprint has ' + incompletePromptsCount + 
                      ' incomplete prompt' + (incompletePromptsCount > 1 ? 's' : '') + 
                      '. Please complete or delete all prompts first.');
                this.value = '<?php echo $sprint['status']; ?>'; // Reset to current status
            }
        });
    }
});

// Edit prompt functions
function editPrompt(promptId) {
    document.getElementById(`prompt-view-${promptId}`).classList.add('d-none');
    document.getElementById(`prompt-edit-${promptId}`).classList.remove('d-none');
}

function cancelEditPrompt(promptId) {
    document.getElementById(`prompt-view-${promptId}`).classList.remove('d-none');
    document.getElementById(`prompt-edit-${promptId}`).classList.add('d-none');
}

function savePrompt(promptId) {
    const promptText = document.getElementById(`prompt-text-${promptId}`).value.trim();
    
    if (!promptText) {
        alert('Prompt text cannot be empty');
        return;
    }
    
    fetch('/htmx/update-prompt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `prompt_id=${promptId}&prompt_text=${encodeURIComponent(promptText)}`
    })
    .then(response => response.text())
    .then(html => {
        // Show success message
        document.getElementById('sprint-messages').innerHTML = html;
        
        // Update the display
        document.querySelector(`#prompt-view-${promptId} pre`).textContent = promptText;
        
        // Switch back to view mode
        cancelEditPrompt(promptId);
        
        // Optionally reload to ensure consistency
        setTimeout(() => window.location.reload(), 1000);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update prompt');
    });
}

// Show follow-up prompt form
function showFollowUpPromptForm(promptId) {
    fetch('/htmx/add-followup-prompt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `parent_prompt_id=${promptId}&mode=form`
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById(`followup-container-${promptId}`).innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load follow-up form');
    });
}

// Submit follow-up form
function submitFollowUpForm(event, promptId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    fetch('/htmx/add-followup-prompt.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById(`followup-container-${promptId}`).innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save follow-up prompt');
    });
    
    return false;
}

// Toggle completion form
function toggleCompletionForm(promptId) {
    const form = document.getElementById(`completion-form-${promptId}`);
    const completeBtn = document.getElementById(`complete-btn-${promptId}`);
    
    if (form.classList.contains('d-none')) {
        form.classList.remove('d-none');
        completeBtn.classList.add('d-none');
    } else {
        form.classList.add('d-none');
        completeBtn.classList.remove('d-none');
        // Reset form
        resetCompletionForm(promptId);
    }
}

// Set rating for completion
let completionRatings = {};
function setCompletionRating(promptId, rating) {
    completionRatings[promptId] = rating;
    const stars = document.querySelectorAll(`#completion-rating-${promptId} .star-icon`);
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.remove('bi-star');
            star.classList.add('bi-star-fill');
            star.style.color = '#ffc107';
        } else {
            star.classList.remove('bi-star-fill');
            star.classList.add('bi-star');
            star.style.color = '#ddd';
        }
    });
}

// Reset completion form
function resetCompletionForm(promptId) {
    // Reset rating
    completionRatings[promptId] = 0;
    const stars = document.querySelectorAll(`#completion-rating-${promptId} .star-icon`);
    stars.forEach(star => {
        star.classList.remove('bi-star-fill');
        star.classList.add('bi-star');
        star.style.color = '#ddd';
    });
    
    // Clear text areas
    document.getElementById(`test-notes-${promptId}`).value = '';
    document.getElementById(`dev-notes-${promptId}`).value = '';
}

// Submit prompt completion
function submitPromptCompletion(promptId) {
    const rating = completionRatings[promptId] || 0;
    const testNotes = document.getElementById(`test-notes-${promptId}`).value.trim();
    const devNotes = document.getElementById(`dev-notes-${promptId}`).value.trim();
    
    // Prepare the data
    const formData = new URLSearchParams();
    formData.append('prompt_id', promptId);
    formData.append('rating', rating);
    formData.append('test_notes', testNotes);
    formData.append('developer_notes', devNotes);
    
    // Submit the completion
    fetch('/htmx/mark-prompt-complete-with-details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Show success message
        document.getElementById(`prompt-action-result-${promptId}`).innerHTML = html;
        
        // Hide the form
        toggleCompletionForm(promptId);
        
        // Reload after a short delay
        setTimeout(() => window.location.reload(), 1500);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to complete prompt');
    });
}

// Show prompt results (AI messages)
function showPromptResults(promptId) {
    // Fetch the prompt messages
    fetch(`/htmx/get-prompt-messages.php?prompt_id=${promptId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                let modalContent = '<div class="list-group">';
                
                // Process messages in sequence order
                data.messages.forEach((message, index) => {
                    modalContent += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-secondary me-1">#${index + 1}</span>`;
                    
                    if (message.content_type === 'text') {
                        modalContent += `<span class="badge bg-primary">Text Response</span>`;
                    } else if (message.content_type === 'tool_use') {
                        modalContent += `<span class="badge bg-info">Tool: ${escapeHtml(message.tool_name || 'Unknown')}</span>`;
                    } else if (message.content_type === 'tool_result') {
                        modalContent += `<span class="badge bg-warning">Tool Result</span>`;
                    } else if (message.message_type === 'system') {
                        modalContent += `<span class="badge bg-success">System</span>`;
                    } else {
                        modalContent += `<span class="badge bg-secondary">${escapeHtml(message.content_type || message.message_type || 'Unknown')}</span>`;
                    }
                    
                    modalContent += `
                                </div>
                                <div>
                                    <small class="text-muted">Seq: ${message.sequence_number}</small>`;
                    
                    // Add token usage information if available
                    if (message.input_tokens || message.output_tokens || message.total_tokens || message.cost_usd) {
                        modalContent += `
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <span>Input: ${message.input_tokens || 0}</span> | 
                                        <span>Output: ${message.output_tokens || 0}</span> | 
                                        <span>Total: ${message.total_tokens || 0}</span> | 
                                        <span>Cost: $${message.cost_usd ? parseFloat(message.cost_usd).toFixed(6) : '0.000000'}</span>
                                    </div>`;
                    }
                    
                    modalContent += `
                                </div>
                            </div>`;
                    
                    if (message.content_text) {
                        modalContent += `
                            <div class="mt-1">
                                <pre class="mb-0 p-2 bg-light rounded" style="white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; font-size: 0.875rem;">${escapeHtml(message.content_text)}</pre>
                            </div>`;
                    }
                    
                    modalContent += '</div>';
                });
                
                modalContent += '</div>';
                
                // Show in modal
                showPromptModal('Prompt Results', modalContent, 'info', true);
            } else {
                showPromptModal('No Results', 'No messages found for this prompt.', 'warning');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load prompt results');
        });
}
</script>

<!-- Sprint Not Active Modal -->
<div class="modal fade" id="sprintNotActiveModal" tabindex="-1" aria-labelledby="sprintNotActiveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sprintNotActiveModalLabel">Sprint Not Active</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Cannot send prompts to development system.</strong>
                </div>
                <p class="mt-3">
                    This sprint must be in <strong>Active</strong> status to send prompts to the development system.
                </p>
                <p>
                    Current sprint status: <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($displayStatus); ?></span>
                </p>
                <?php if ($canManageSprint): ?>
                <p class="mb-0">
                    You can change the sprint status using the "Change Status" button in the sprint header.
                </p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($canManageSprint): ?>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                    Change Status
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<?php if ($canManageSprint): ?>
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/htmx/update-sprint-status.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">Change Sprint Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="sprint_id" value="<?php echo $sprintId; ?>">
                    <input type="hidden" name="project_id" value="<?php echo $sprint['project_id']; ?>">
                    
                    <div class="mb-3">
                        <label for="sprint_status" class="form-label">New Status</label>
                        <select class="form-select" id="sprint_status" name="status" required>
                            <option value="">Select status...</option>
                            <option value="planning" <?php echo $sprint['status'] === 'planning' ? 'selected' : ''; ?>>Planning</option>
                            <option value="active" <?php echo $sprint['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $sprint['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $sprint['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <?php if ($incompletePromptsCount > 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> This sprint has <?php echo $incompletePromptsCount; ?> incomplete prompt<?php echo $incompletePromptsCount > 1 ? 's' : ''; ?>. 
                        You cannot mark the sprint as completed until all prompts are either completed or deleted.
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Status meanings:
                        <ul class="mb-0 mt-2">
                            <li><strong>Planning:</strong> Sprint is being prepared, items can be added/removed</li>
                            <li><strong>Active:</strong> Sprint is in progress, appears on dashboard</li>
                            <li><strong>Completed:</strong> Sprint finished successfully<?php if ($incompletePromptsCount > 0): ?> <span class="text-danger">(requires all prompts to be completed)</span><?php endif; ?></li>
                            <li><strong>Cancelled:</strong> Sprint was terminated early</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>