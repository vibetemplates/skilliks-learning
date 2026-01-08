<?php
/**
 * Features Page
 * 
 * Lists and manages feature recommendations
 */

$page_title = 'Features';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$projectId = $_GET['project'] ?? null;
$projectObj = new Project();
$userObj = new User();
$project = null;
$canPromoteFeatures = $userObj->isProjectManagerOrAdmin(getCurrentUserId());

if ($projectId) {
    $project = $projectObj->findById($projectId);
    if (!$project || !$projectObj->isMember($projectId, getCurrentUserId())) {
        setFlashMessage('error', 'Access denied to this project.');
        header('Location: /projects.php');
        exit;
    }
}

// Handle feature submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'feature';
        $priority = $_POST['priority'] ?? 'medium';
        $feature_project_id = $_POST['project_id'] ?? $projectId;
        
        if (empty($title)) {
            setFlashMessage('error', 'Feature title is required.');
        } elseif (empty($description)) {
            setFlashMessage('error', 'Feature description is required.');
        } elseif (empty($feature_project_id)) {
            setFlashMessage('error', 'Project selection is required.');
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    INSERT INTO features (title, description, category, priority, project_id, submitted_by, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'proposed', NOW())
                ");
                $result = $stmt->execute([$title, $description, $category, $priority, $feature_project_id, getCurrentUserId()]);
                
                if ($result) {
                    setFlashMessage('success', 'Feature recommendation submitted successfully!');
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Feature creation failed - Error Info: " . print_r($errorInfo, true));
                    setFlashMessage('error', 'Failed to submit feature recommendation. Error: ' . $errorInfo[2]);
                }
            } catch (PDOException $e) {
                error_log("Feature creation PDO error: " . $e->getMessage());
                setFlashMessage('error', 'Database error submitting feature: ' . $e->getMessage());
            }
        }
        
        $redirect_url = $projectId ? "/features.php?project={$projectId}" : "/features.php";
        header("Location: {$redirect_url}");
        exit;
    }
    
    if ($_POST['action'] === 'vote') {
        $featureId = $_POST['feature_id'] ?? null;
        $voteType = $_POST['vote_type'] ?? 'up'; // up or down
        
        if ($featureId) {
            try {
                $db = getDB();
                // Check if user already voted
                $stmt = $db->prepare("SELECT id FROM feature_votes WHERE feature_id = ? AND user_id = ?");
                $stmt->execute([$featureId, getCurrentUserId()]);
                $existingVote = $stmt->fetch();
                
                if ($existingVote) {
                    // Update existing vote
                    $stmt = $db->prepare("UPDATE feature_votes SET vote_type = ? WHERE id = ?");
                    $result = $stmt->execute([$voteType, $existingVote['id']]);
                } else {
                    // Create new vote
                    $stmt = $db->prepare("INSERT INTO feature_votes (feature_id, user_id, vote_type, voted_at) VALUES (?, ?, ?, NOW())");
                    $result = $stmt->execute([$featureId, getCurrentUserId(), $voteType]);
                }
                
                if ($result) {
                    setFlashMessage('success', 'Vote recorded successfully!');
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Vote recording failed - Error Info: " . print_r($errorInfo, true));
                    setFlashMessage('error', 'Failed to record vote. Error: ' . $errorInfo[2]);
                }
            } catch (PDOException $e) {
                error_log("Vote recording PDO error: " . $e->getMessage());
                setFlashMessage('error', 'Database error recording vote: ' . $e->getMessage());
            }
        }
        
        $redirect_url = $projectId ? "/features.php?project={$projectId}" : "/features.php";
        header("Location: {$redirect_url}");
        exit;
    }
    
    if ($_POST['action'] === 'promote') {
        $featureId = $_POST['feature_id'] ?? null;
        $userObj = new User();
        
        // Check if user has permission to promote features
        if (!$userObj->isProjectManagerOrAdmin(getCurrentUserId())) {
            setFlashMessage('error', 'Only project managers and administrators can promote features to tasks.');
        } elseif ($featureId) {
            try {
                $db = getDB();
                
                // Get feature details
                $stmt = $db->prepare("SELECT * FROM features WHERE id = ?");
                $stmt->execute([$featureId]);
                $feature = $stmt->fetch();
                
                if (!$feature) {
                    setFlashMessage('error', 'Feature not found.');
                } else {
                    // Create task from feature
                    $stmt = $db->prepare("
                        INSERT INTO tasks (title, description, type, priority, project_id, reporter_id, status, created_at) 
                        VALUES (?, ?, 'feature', ?, ?, ?, 'todo', NOW())
                    ");
                    $result = $stmt->execute([
                        $feature['title'],
                        $feature['description'],
                        $feature['priority'],
                        $feature['project_id'],
                        getCurrentUserId()
                    ]);
                    
                    if ($result) {
                        // Update feature status to indicate it's been promoted
                        $stmt = $db->prepare("UPDATE features SET status = 'promoted' WHERE id = ?");
                        $stmt->execute([$featureId]);
                        
                        setFlashMessage('success', 'Feature successfully promoted to task!');
                    } else {
                        $errorInfo = $stmt->errorInfo();
                        error_log("Task creation from feature failed - Error Info: " . print_r($errorInfo, true));
                        setFlashMessage('error', 'Failed to create task from feature. Error: ' . $errorInfo[2]);
                    }
                }
            } catch (PDOException $e) {
                error_log("Feature promotion PDO error: " . $e->getMessage());
                setFlashMessage('error', 'Database error promoting feature: ' . $e->getMessage());
            }
        }
        
        $redirect_url = $projectId ? "/features.php?project={$projectId}" : "/features.php";
        header("Location: {$redirect_url}");
        exit;
    }
}

// Get features and separate by status
try {
    $db = getDB();
    $whereClause = $projectId ? "WHERE f.project_id = ?" : "";
    $params = $projectId ? [$projectId] : [];
    
    $stmt = $db->prepare("
        SELECT f.*, 
               u.first_name, u.last_name,
               COALESCE(SUM(CASE WHEN fv.vote_type = 'up' THEN 1 ELSE 0 END), 0) as upvotes,
               COALESCE(SUM(CASE WHEN fv.vote_type = 'down' THEN 1 ELSE 0 END), 0) as downvotes,
               (SELECT vote_type FROM feature_votes WHERE feature_id = f.id AND user_id = ?) as user_vote
        FROM features f
        LEFT JOIN users u ON f.submitted_by = u.id
        LEFT JOIN feature_votes fv ON f.id = fv.feature_id
        {$whereClause}
        GROUP BY f.id
        ORDER BY f.created_at DESC
    ");
    
    $params[] = getCurrentUserId();
    $stmt->execute($params);
    $allFeatures = $stmt->fetchAll();
    
    // Separate features by status
    $pendingFeatures = [];
    $promotedFeatures = [];
    
    foreach ($allFeatures as $feature) {
        if ($feature['status'] === 'promoted') {
            $promotedFeatures[] = $feature;
        } else {
            $pendingFeatures[] = $feature;
        }
    }
} catch (PDOException $e) {
    $pendingFeatures = [];
    $promotedFeatures = [];
}

// Get user's projects for dropdown
try {
    $userProjects = [];
    if (!$projectId) {
        $stmt = $db->prepare("
            SELECT p.id, p.name 
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = ?
            ORDER BY p.name
        ");
        $stmt->execute([getCurrentUserId()]);
        $userProjects = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $userProjects = [];
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
            <!-- Breadcrumb -->
            <?php if ($project): ?>
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
                    <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Features</li>
                </ol>
            </nav>
            <?php endif; ?>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    Feature Recommendations
                    <?php if ($project): ?>
                        - <?php echo htmlspecialchars($project['name']); ?>
                    <?php endif; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFeatureModal">
                        <i class="bi bi-plus-circle"></i> Suggest Feature
                    </button>
                </div>
            </div>


            <!-- Feature Tabs -->
            <ul class="nav nav-tabs mb-3" id="featureTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                        <i class="bi bi-clock"></i> Pending
                        <span class="badge bg-secondary ms-1"><?php echo count($pendingFeatures); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="promoted-tab" data-bs-toggle="tab" data-bs-target="#promoted" type="button" role="tab">
                        <i class="bi bi-check-circle"></i> Promoted
                        <span class="badge bg-success ms-1"><?php echo count($promotedFeatures); ?></span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="featureTabContent">
                <!-- Pending Features Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (empty($pendingFeatures)): ?>
                        <div class="alert alert-info fade-in">
                            <i class="bi bi-info-circle"></i>
                            No pending feature recommendations. Be the first to suggest a feature!
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pendingFeatures as $feature): ?>
                        <div class="col-12 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-1 text-center">
                                            <!-- Voting Section -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="vote">
                                                <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                                <input type="hidden" name="vote_type" value="up">
                                                <?php if ($projectId): ?>
                                                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-sm <?php echo $feature['user_vote'] === 'up' ? 'btn-success' : 'btn-outline-success'; ?> mb-1">
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                            </form>
                                            <div class="fw-bold"><?php echo $feature['upvotes'] - $feature['downvotes']; ?></div>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="vote">
                                                <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                                <input type="hidden" name="vote_type" value="down">
                                                <?php if ($projectId): ?>
                                                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-sm <?php echo $feature['user_vote'] === 'down' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-11">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($feature['title']); ?></h5>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div>
                                                        <span class="badge bg-<?php echo $feature['status'] === 'approved' ? 'success' : ($feature['status'] === 'rejected' ? 'danger' : ($feature['status'] === 'promoted' ? 'info' : 'warning')); ?>">
                                                            <?php echo ucfirst($feature['status']); ?>
                                                        </span>
                                                        <span class="badge bg-secondary"><?php echo ucfirst($feature['category']); ?></span>
                                                        <span class="badge bg-<?php echo $feature['priority'] === 'high' ? 'danger' : ($feature['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                                            <?php echo ucfirst($feature['priority']); ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($canPromoteFeatures && $feature['status'] !== 'promoted'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="promote">
                                                        <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                                        <?php if ($projectId): ?>
                                                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                                        <?php endif; ?>
                                                        <button type="submit" class="btn btn-sm btn-outline-primary" 
                                                                onclick="return confirm('Are you sure you want to promote this feature to a task?')"
                                                                title="Promote to Task">
                                                            <i class="bi bi-arrow-up-circle"></i> Promote
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <p class="card-text"><?php echo htmlspecialchars($feature['description']); ?></p>
                                            <div class="text-muted small">
                                                Suggested by <?php echo htmlspecialchars($feature['first_name'] . ' ' . $feature['last_name']); ?>
                                                on <?php echo date('M j, Y', strtotime($feature['created_at'])); ?>
                                                <?php if ($feature['status'] === 'promoted'): ?>
                                                <br><strong class="text-info">
                                                    <i class="bi bi-check-circle"></i> This feature has been promoted to a task
                                                </strong>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Promoted Features Tab -->
                <div class="tab-pane fade" id="promoted" role="tabpanel">
                    <?php if (empty($promotedFeatures)): ?>
                        <div class="alert alert-info fade-in">
                            <i class="bi bi-info-circle"></i>
                            No features have been promoted to tasks yet.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($promotedFeatures as $feature): ?>
                                <div class="col-12 mb-4">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-1 text-center">
                                                    <!-- Voting Section (read-only for promoted features) -->
                                                    <button class="btn btn-sm btn-outline-success mb-1" disabled>
                                                        <i class="bi bi-arrow-up"></i>
                                                    </button>
                                                    <div class="fw-bold text-muted"><?php echo $feature['upvotes'] - $feature['downvotes']; ?></div>
                                                    <button class="btn btn-sm btn-outline-danger" disabled>
                                                        <i class="bi bi-arrow-down"></i>
                                                    </button>
                                                </div>
                                                <div class="col-11">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($feature['title']); ?></h5>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div>
                                                                <span class="badge bg-success">
                                                                    <i class="bi bi-check-circle"></i> Promoted
                                                                </span>
                                                                <span class="badge bg-secondary"><?php echo ucfirst($feature['category']); ?></span>
                                                                <span class="badge bg-<?php echo $feature['priority'] === 'high' ? 'danger' : ($feature['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                                                    <?php echo ucfirst($feature['priority']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="card-text"><?php echo htmlspecialchars($feature['description']); ?></p>
                                                    <div class="text-muted small">
                                                        Suggested by <?php echo htmlspecialchars($feature['first_name'] . ' ' . $feature['last_name']); ?>
                                                        on <?php echo date('M j, Y', strtotime($feature['created_at'])); ?>
                                                        <br><strong class="text-success">
                                                            <i class="bi bi-check-circle"></i> This feature has been promoted to a task
                                                        </strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

</main>

<!-- Create Feature Modal -->
<div class="modal fade" id="createFeatureModal" tabindex="-1" aria-labelledby="createFeatureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createFeatureModalLabel">Suggest New Feature</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/features.php<?php echo $projectId ? '?project=' . $projectId : ''; ?>">
                <input type="hidden" name="action" value="create">
                <?php if ($projectId): ?>
                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                <?php endif; ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Feature Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>
                    <?php if (!$projectId): ?>
                    <div class="mb-3">
                        <label for="project_id" class="form-label">Project *</label>
                        <select class="form-select" id="project_id" name="project_id" required>
                            <option value="">Select a project...</option>
                            <?php foreach ($userProjects as $proj): ?>
                                <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="feature">Feature</option>
                                    <option value="enhancement">Enhancement</option>
                                    <option value="bug_fix">Bug Fix</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="submitFeatureBtn" class="btn btn-primary" disabled>Submit Feature</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#createFeatureModal form');
    const submitBtn = document.getElementById('submitFeatureBtn');
    const titleField = document.getElementById('title');
    const descriptionField = document.getElementById('description');
    const projectField = document.getElementById('project_id');
    
    function validateForm() {
        const title = titleField.value.trim();
        const description = descriptionField.value.trim();
        let projectValid = true;
        
        // Check project field only if it exists (when not in project context)
        if (projectField) {
            projectValid = projectField.value !== '';
        }
        
        const isValid = title !== '' && description !== '' && projectValid;
        
        submitBtn.disabled = !isValid;
        
        if (isValid) {
            submitBtn.classList.remove('btn-secondary');
            submitBtn.classList.add('btn-primary');
        } else {
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('btn-secondary');
        }
    }
    
    // Add event listeners to all required fields
    titleField.addEventListener('input', validateForm);
    descriptionField.addEventListener('input', validateForm);
    if (projectField) {
        projectField.addEventListener('change', validateForm);
    }
    
    // Initial validation
    validateForm();
    
    // Reset form when modal is closed
    document.getElementById('createFeatureModal').addEventListener('hidden.bs.modal', function() {
        form.reset();
        validateForm();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>