<?php
/**
 * Project Required Skills Page
 * 
 * Display and manage required skills for a project
 */

$page_title = 'Required Skills';
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
    setFlashMessage('error', 'You must be a project member to view required skills.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($isProjectManagerOrAdmin || $isCreator)) {
    $db = getDB();
    
    if ($_POST['action'] === 'add_skill') {
        $skillId = $_POST['skill_id'] ?? null;
        $importanceLevel = $_POST['importance_level'] ?? 'preferred';
        $notes = trim($_POST['notes'] ?? '');
        
        if ($skillId) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO project_skills (project_id, skill_id, importance_level, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        importance_level = VALUES(importance_level),
                        notes = VALUES(notes),
                        updated_at = NOW()
                ");
                $stmt->execute([$projectId, $skillId, $importanceLevel, $notes, $currentUserId]);
                setFlashMessage('success', 'Skill requirement added successfully!');
            } catch (PDOException $e) {
                error_log("Error adding skill requirement: " . $e->getMessage());
                setFlashMessage('error', 'Failed to add skill requirement.');
            }
        }
        header('Location: /project-skills.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'remove_skill') {
        $projectSkillId = $_POST['project_skill_id'] ?? null;
        
        if ($projectSkillId) {
            try {
                $stmt = $db->prepare("DELETE FROM project_skills WHERE id = ? AND project_id = ?");
                $stmt->execute([$projectSkillId, $projectId]);
                setFlashMessage('success', 'Skill requirement removed successfully!');
            } catch (PDOException $e) {
                error_log("Error removing skill requirement: " . $e->getMessage());
                setFlashMessage('error', 'Failed to remove skill requirement.');
            }
        }
        header('Location: /project-skills.php?project=' . $projectId);
        exit;
    }
}

// Get project skills
$projectSkills = $projectObj->getProjectSkills($projectId);

// Get all available skills for adding
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT s.* 
        FROM skills s 
        WHERE s.id NOT IN (SELECT skill_id FROM project_skills WHERE project_id = ?)
        ORDER BY s.category, s.name
    ");
    $stmt->execute([$projectId]);
    $availableSkills = $stmt->fetchAll();
} catch (PDOException $e) {
    $availableSkills = [];
}

// Group skills by importance
$skillsByImportance = [
    'required' => [],
    'preferred' => [],
    'optional' => []
];

foreach ($projectSkills as $skill) {
    $importance = $skill['importance_level'] ?? 'optional';
    $skillsByImportance[$importance][] = $skill;
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Required Skills</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Required Skills</h1>
        <?php if (($isProjectManagerOrAdmin || $isCreator) && !empty($availableSkills)): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                <i class="bi bi-plus-circle"></i> Add Skill
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($projectSkills)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-tools text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No skills have been defined for this project yet.</p>
                <?php if (($isProjectManagerOrAdmin || $isCreator) && !empty($availableSkills)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                        <i class="bi bi-plus-circle"></i> Add First Skill
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Required Skills -->
        <?php if (!empty($skillsByImportance['required'])): ?>
            <div class="mb-4">
                <h3><span class="badge bg-danger">Required Skills</span></h3>
                <div class="row mt-3">
                    <?php foreach ($skillsByImportance['required'] as $skill): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card h-100 border-danger">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($skill['name']); ?></h5>
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($skill['category']); ?>
                                    </p>
                                    <?php if (!empty($skill['description'])): ?>
                                        <p class="card-text"><?php echo htmlspecialchars($skill['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($skill['notes'])): ?>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($skill['notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                        <form method="POST" class="mt-3" onsubmit="return confirm('Remove this skill requirement?');">
                                            <input type="hidden" name="action" value="remove_skill">
                                            <input type="hidden" name="project_skill_id" value="<?php echo $skill['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Preferred Skills -->
        <?php if (!empty($skillsByImportance['preferred'])): ?>
            <div class="mb-4">
                <h3><span class="badge bg-warning">Preferred Skills</span></h3>
                <div class="row mt-3">
                    <?php foreach ($skillsByImportance['preferred'] as $skill): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($skill['name']); ?></h5>
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($skill['category']); ?>
                                    </p>
                                    <?php if (!empty($skill['description'])): ?>
                                        <p class="card-text"><?php echo htmlspecialchars($skill['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($skill['notes'])): ?>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($skill['notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                        <form method="POST" class="mt-3" onsubmit="return confirm('Remove this skill requirement?');">
                                            <input type="hidden" name="action" value="remove_skill">
                                            <input type="hidden" name="project_skill_id" value="<?php echo $skill['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Optional Skills -->
        <?php if (!empty($skillsByImportance['optional'])): ?>
            <div class="mb-4">
                <h3><span class="badge bg-secondary">Optional Skills</span></h3>
                <div class="row mt-3">
                    <?php foreach ($skillsByImportance['optional'] as $skill): ?>
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($skill['name']); ?></h5>
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($skill['category']); ?>
                                    </p>
                                    <?php if (!empty($skill['description'])): ?>
                                        <p class="card-text"><?php echo htmlspecialchars($skill['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($skill['notes'])): ?>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($skill['notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($isProjectManagerOrAdmin || $isCreator): ?>
                                        <form method="POST" class="mt-3" onsubmit="return confirm('Remove this skill requirement?');">
                                            <input type="hidden" name="action" value="remove_skill">
                                            <input type="hidden" name="project_skill_id" value="<?php echo $skill['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Add Skill Modal -->
<?php if (($isProjectManagerOrAdmin || $isCreator) && !empty($availableSkills)): ?>
<div class="modal fade" id="addSkillModal" tabindex="-1" aria-labelledby="addSkillModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSkillModalLabel">Add Required Skill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_skill">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="skill_id" class="form-label">Select Skill *</label>
                        <select class="form-select" id="skill_id" name="skill_id" required>
                            <option value="">Choose a skill...</option>
                            <?php 
                            $currentCategory = '';
                            foreach ($availableSkills as $skill): 
                                if ($skill['category'] !== $currentCategory):
                                    if ($currentCategory !== '') echo '</optgroup>';
                                    $currentCategory = $skill['category'];
                                    echo '<optgroup label="' . htmlspecialchars($currentCategory) . '">';
                                endif;
                            ?>
                                <option value="<?php echo $skill['id']; ?>">
                                    <?php echo htmlspecialchars($skill['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($currentCategory !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="importance_level" class="form-label">Importance Level *</label>
                        <select class="form-select" id="importance_level" name="importance_level" required>
                            <option value="required">Required - Must have this skill</option>
                            <option value="preferred" selected>Preferred - Should have this skill</option>
                            <option value="optional">Optional - Nice to have</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Any specific requirements or context for this skill..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Skill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>