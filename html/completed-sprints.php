<?php
/**
 * Completed Sprints Page
 * 
 * Display all completed sprints for a project
 */

$page_title = 'Completed Sprints';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Sprint.php';
require_once 'classes/Project.php';
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

// Get completed sprints for this project
$db = getDB();
$stmt = $db->prepare("
    SELECT s.*, 
           u.first_name as created_by_first_name, 
           u.last_name as created_by_last_name,
           COUNT(DISTINCT wi.id) as item_count,
           SUM(wi.story_points) as total_points,
           COUNT(DISTINCT pdp.id) as prompt_count
    FROM sprints s
    LEFT JOIN users u ON s.created_by = u.id
    LEFT JOIN work_items wi ON wi.sprint_id = s.id
    LEFT JOIN project_dev_prompts pdp ON pdp.sprint_id = s.id AND pdp.status = 'completed'
    WHERE s.project_id = ? AND s.status = 'completed'
    GROUP BY s.id
    ORDER BY s.end_date DESC
");
$stmt->execute([$projectId]);
$completedSprints = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail.php?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item"><a href="/project-sprints.php?project=<?php echo $projectId; ?>">Sprints</a></li>
            <li class="breadcrumb-item active">Completed Sprints</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Completed Sprints for <?php echo htmlspecialchars($project['name']); ?></h1>
        <a href="/project-sprints.php?project=<?php echo $projectId; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to All Sprints
        </a>
    </div>

    <?php if (empty($completedSprints)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No completed sprints found for this project.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($completedSprints as $sprint): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($sprint['name']); ?></h5>
                            <span class="badge bg-primary">Completed</span>
                        </div>
                        <div class="card-body">
                            <?php if ($sprint['goal']): ?>
                                <p class="mb-3">
                                    <strong>Goal:</strong> <?php echo htmlspecialchars($sprint['goal']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Duration</small>
                                    <span>
                                        <?php echo date('M j', strtotime($sprint['start_date'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($sprint['end_date'])); ?>
                                    </span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Created by</small>
                                    <span><?php echo htmlspecialchars($sprint['created_by_first_name'] . ' ' . $sprint['created_by_last_name']); ?></span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-list-task me-2 text-primary"></i>
                                        <div>
                                            <small class="text-muted d-block">Work Items</small>
                                            <strong><?php echo $sprint['item_count']; ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-chat-dots me-2 text-success"></i>
                                        <div>
                                            <small class="text-muted d-block">Prompts Executed</small>
                                            <strong><?php echo $sprint['prompt_count']; ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($sprint['total_points'] > 0): ?>
                                <div class="col-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-diamond me-2 text-info"></i>
                                        <div>
                                            <small class="text-muted d-block">Story Points</small>
                                            <strong><?php echo $sprint['total_points']; ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-3 pt-3 border-top">
                                <a href="/sprint-dashboard.php?id=<?php echo $sprint['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>