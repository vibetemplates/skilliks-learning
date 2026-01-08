<?php
/**
 * Project Requirements Page
 * 
 * Manage project requirements and specifications
 */

$page_title = 'Project Requirements';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';

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

// Require member access
if (!$isMember && !$isProjectManagerOrAdmin) {
    setFlashMessage('error', 'You must be a project member to view requirements.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();
    
    if ($_POST['action'] === 'add' && ($isProjectManagerOrAdmin || $isCreator)) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'should_have';
        $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        
        if ($title) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO project_requirements (project_id, category_id, title, description, priority, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$projectId, $categoryId, $title, $description, $priority, $currentUserId]);
                setFlashMessage('success', 'Requirement added successfully!');
            } catch (PDOException $e) {
                error_log("Error adding requirement: " . $e->getMessage());
                setFlashMessage('error', 'Failed to add requirement.');
            }
        } else {
            setFlashMessage('error', 'Title is required.');
        }
        header('Location: /project-requirements.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'update_status' && ($isProjectManagerOrAdmin || $isCreator)) {
        $requirementId = $_POST['requirement_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if ($requirementId && $status) {
            try {
                $stmt = $db->prepare("
                    UPDATE project_requirements 
                    SET status = ?, updated_at = NOW() 
                    WHERE id = ? AND project_id = ?
                ");
                $stmt->execute([$status, $requirementId, $projectId]);
                setFlashMessage('success', 'Status updated successfully!');
            } catch (PDOException $e) {
                error_log("Error updating requirement status: " . $e->getMessage());
                setFlashMessage('error', 'Failed to update status.');
            }
        }
        header('Location: /project-requirements.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'delete' && ($isProjectManagerOrAdmin || $isCreator)) {
        $requirementId = $_POST['requirement_id'] ?? null;
        
        if ($requirementId) {
            try {
                $stmt = $db->prepare("DELETE FROM project_requirements WHERE id = ? AND project_id = ?");
                $stmt->execute([$requirementId, $projectId]);
                setFlashMessage('success', 'Requirement deleted successfully!');
            } catch (PDOException $e) {
                error_log("Error deleting requirement: " . $e->getMessage());
                setFlashMessage('error', 'Failed to delete requirement.');
            }
        }
        header('Location: /project-requirements.php?project=' . $projectId);
        exit;
    }
}

// Get requirements
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, rc.name as category_name
        FROM project_requirements r
        LEFT JOIN users u ON r.created_by = u.id
        LEFT JOIN requirements_category rc ON r.category_id = rc.id
        WHERE r.project_id = ? 
        ORDER BY 
            rc.name,
            CASE r.priority 
                WHEN 'must_have' THEN 1
                WHEN 'should_have' THEN 2
                WHEN 'could_have' THEN 3
                WHEN 'nice_to_have' THEN 4
            END,
            r.created_at DESC
    ");
    $stmt->execute([$projectId]);
    $requirements = $stmt->fetchAll();
    
    // Get available categories for this project
    $stmt = $db->prepare("
        SELECT id, name 
        FROM requirements_category 
        WHERE is_standard = TRUE OR project_id = ? 
        ORDER BY is_standard DESC, name ASC
    ");
    $stmt->execute([$projectId]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $requirements = [];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Requirements</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Project Requirements</h1>
        <div class="btn-group" role="group">
            <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                <a href="/project-survey?project_id=<?php echo $projectId; ?>&survey_type=requirements" class="btn btn-outline-primary">
                    <i class="bi bi-clipboard-check"></i> Requirements Survey
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                    <i class="bi bi-plus-circle"></i> Add Requirement
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Requirements List -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($requirements)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-list-check text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No requirements have been defined for this project yet.</p>
                        <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                                <i class="bi bi-plus-circle"></i> Add First Requirement
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php
                $priorityGroups = [];
                foreach ($requirements as $req) {
                    $priorityGroups[$req['priority']][] = $req;
                }
                
                $priorityLabels = [
                    'must_have' => ['label' => 'Must Have', 'class' => 'danger'],
                    'should_have' => ['label' => 'Should Have', 'class' => 'warning'],
                    'could_have' => ['label' => 'Could Have', 'class' => 'info'],
                    'nice_to_have' => ['label' => 'Nice to Have', 'class' => 'secondary']
                ];
                ?>
                
                <?php foreach ($priorityLabels as $priority => $config): ?>
                    <?php if (isset($priorityGroups[$priority])): ?>
                        <div class="mb-4">
                            <h4 class="mb-3">
                                <span class="badge bg-<?php echo $config['class']; ?>"><?php echo $config['label']; ?></span>
                            </h4>
                            <div class="row">
                                <?php foreach ($priorityGroups[$priority] as $req): ?>
                                    <div class="col-lg-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($req['title']); ?></h5>
                                                        <?php if ($req['category_name']): ?>
                                                            <small class="text-muted"><i class="bi bi-tag"></i> <?php echo htmlspecialchars($req['category_name']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                    $statusClasses = [
                                                        'pending' => 'secondary',
                                                        'in_progress' => 'primary',
                                                        'completed' => 'success',
                                                        'deferred' => 'dark'
                                                    ];
                                                    $statusClass = $statusClasses[$req['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?></span>
                                                </div>
                                                
                                                <?php if ($req['description']): ?>
                                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($req['description'])); ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center mt-3">
                                                    <small class="text-muted">
                                                        By <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?> • 
                                                        <?php echo date('M j, Y', strtotime($req['created_at'])); ?>
                                                    </small>
                                                    
                                                    <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                <i class="bi bi-three-dots"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li><h6 class="dropdown-header">Update Status</h6></li>
                                                                <?php foreach (['pending', 'in_progress', 'completed', 'deferred'] as $status): ?>
                                                                    <?php if ($status !== $req['status']): ?>
                                                                        <li>
                                                                            <form method="POST" class="d-inline">
                                                                                <input type="hidden" name="action" value="update_status">
                                                                                <input type="hidden" name="requirement_id" value="<?php echo $req['id']; ?>">
                                                                                <input type="hidden" name="status" value="<?php echo $status; ?>">
                                                                                <button type="submit" class="dropdown-item">
                                                                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this requirement?');">
                                                                        <input type="hidden" name="action" value="delete">
                                                                        <input type="hidden" name="requirement_id" value="<?php echo $req['id']; ?>">
                                                                        <button type="submit" class="dropdown-item text-danger">
                                                                            <i class="bi bi-trash"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Requirement Modal -->
<?php if ($isProjectManagerOrAdmin || $isCreator): ?>
<div class="modal fade" id="addRequirementModal" tabindex="-1" aria-labelledby="addRequirementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRequirementModalLabel">Add New Requirement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">No category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="must_have">Must Have - Critical for project success</option>
                            <option value="should_have" selected>Should Have - Important but not critical</option>
                            <option value="could_have">Could Have - Desirable but not necessary</option>
                            <option value="nice_to_have">Nice to Have - Can be left out</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Requirement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>