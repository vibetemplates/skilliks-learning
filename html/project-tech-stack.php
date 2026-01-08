<?php
/**
 * Project Tech Stack Page
 * 
 * Manage project technology stack
 */

$page_title = 'Project Tech Stack';
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
    setFlashMessage('error', 'You must be a project member to view tech stack.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();
    
    if ($_POST['action'] === 'add' && ($isProjectManagerOrAdmin || $isCreator)) {
        $technologyName = trim($_POST['technology_name'] ?? '');
        $category = $_POST['category'] ?? 'other';
        $version = trim($_POST['version'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
        
        if ($technologyName) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO project_tech_stack (project_id, technology_name, category, version, description, is_primary, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        category = VALUES(category),
                        version = VALUES(version),
                        description = VALUES(description),
                        is_primary = VALUES(is_primary),
                        updated_at = NOW()
                ");
                $stmt->execute([$projectId, $technologyName, $category, $version, $description, $isPrimary, $currentUserId]);
                setFlashMessage('success', 'Technology added successfully!');
            } catch (PDOException $e) {
                error_log("Error adding technology: " . $e->getMessage());
                setFlashMessage('error', 'Failed to add technology.');
            }
        } else {
            setFlashMessage('error', 'Technology name is required.');
        }
        header('Location: /project-tech-stack.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'delete' && ($isProjectManagerOrAdmin || $isCreator)) {
        $techId = $_POST['tech_id'] ?? null;
        
        if ($techId) {
            try {
                $stmt = $db->prepare("DELETE FROM project_tech_stack WHERE id = ? AND project_id = ?");
                $stmt->execute([$techId, $projectId]);
                setFlashMessage('success', 'Technology removed successfully!');
            } catch (PDOException $e) {
                error_log("Error deleting technology: " . $e->getMessage());
                setFlashMessage('error', 'Failed to remove technology.');
            }
        }
        header('Location: /project-tech-stack.php?project=' . $projectId);
        exit;
    }
}

// Get tech stack
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, u.first_name, u.last_name 
        FROM project_tech_stack t
        LEFT JOIN users u ON t.created_by = u.id
        WHERE t.project_id = ? 
        ORDER BY t.is_primary DESC, t.category, t.technology_name
    ");
    $stmt->execute([$projectId]);
    $technologies = $stmt->fetchAll();
} catch (PDOException $e) {
    $technologies = [];
}

// Group technologies by category
$techByCategory = [];
foreach ($technologies as $tech) {
    $techByCategory[$tech['category']][] = $tech;
}

$categoryLabels = [
    'frontend' => ['label' => 'Frontend', 'icon' => 'bi-window', 'color' => 'primary'],
    'backend' => ['label' => 'Backend', 'icon' => 'bi-server', 'color' => 'success'],
    'database' => ['label' => 'Database', 'icon' => 'bi-database', 'color' => 'warning'],
    'devops' => ['label' => 'DevOps', 'icon' => 'bi-gear', 'color' => 'info'],
    'testing' => ['label' => 'Testing', 'icon' => 'bi-check2-circle', 'color' => 'danger'],
    'other' => ['label' => 'Other', 'icon' => 'bi-box', 'color' => 'secondary']
];

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Tech Stack</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Technology Stack</h1>
        <div class="btn-group" role="group">
            <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                <a href="/project-survey?project_id=<?php echo $projectId; ?>&survey_type=tech-stack" class="btn btn-outline-primary">
                    <i class="bi bi-clipboard-check"></i> Tech Stack Survey
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTechModal">
                    <i class="bi bi-plus-circle"></i> Add Technology
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tech Stack Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Stack Overview</h5>
                    <div class="row text-center">
                        <?php foreach ($categoryLabels as $category => $config): ?>
                            <div class="col-md-2 col-sm-4 mb-3">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="<?php echo $config['icon']; ?> text-<?php echo $config['color']; ?>" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2 mb-0"><?php echo $config['label']; ?></h6>
                                    <span class="badge bg-<?php echo $config['color']; ?>"><?php echo count($techByCategory[$category] ?? []); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Technologies by Category -->
    <div class="row">
        <?php if (empty($technologies)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-stack text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No technologies have been added to this project yet.</p>
                        <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTechModal">
                                <i class="bi bi-plus-circle"></i> Add First Technology
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($categoryLabels as $category => $config): ?>
                <?php if (isset($techByCategory[$category])): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-<?php echo $config['color']; ?> text-white">
                                <h5 class="mb-0">
                                    <i class="<?php echo $config['icon']; ?>"></i> <?php echo $config['label']; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($techByCategory[$category] as $tech): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($tech['technology_name']); ?>
                                                        <?php if ($tech['is_primary']): ?>
                                                            <span class="badge bg-primary ms-1">Primary</span>
                                                        <?php endif; ?>
                                                        <?php if ($tech['version']): ?>
                                                            <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($tech['version']); ?></span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <?php if ($tech['description']): ?>
                                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($tech['description']); ?></p>
                                                    <?php endif; ?>
                                                    <small class="text-muted">
                                                        Added by <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?> • 
                                                        <?php echo date('M j, Y', strtotime($tech['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                                    <form method="POST" class="ms-2" onsubmit="return confirm('Remove this technology from the stack?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="tech_id" value="<?php echo $tech['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Add Technology Modal -->
<?php if ($isProjectManagerOrAdmin || $isCreator): ?>
<div class="modal fade" id="addTechModal" tabindex="-1" aria-labelledby="addTechModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTechModalLabel">Add Technology</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="technology_name" class="form-label">Technology Name *</label>
                        <input type="text" class="form-control" id="technology_name" name="technology_name" required
                               placeholder="e.g., React, Node.js, PostgreSQL">
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Category *</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="frontend">Frontend</option>
                            <option value="backend">Backend</option>
                            <option value="database">Database</option>
                            <option value="devops">DevOps</option>
                            <option value="testing">Testing</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="version" class="form-label">Version (Optional)</label>
                        <input type="text" class="form-control" id="version" name="version"
                               placeholder="e.g., 18.2.0, 14.x, latest">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Brief description of how this technology is used in the project"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary">
                            <label class="form-check-label" for="is_primary">
                                Mark as primary technology in this category
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Technology
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>