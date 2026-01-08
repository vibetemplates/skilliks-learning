<?php
/**
 * Skills Management Admin Page
 * 
 * Allows admins to manage the master list of skills
 */

$page_title = 'Skills Management';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// Require login
requireLogin();

// Check if user is admin
$currentUserRole = getCurrentUserRole();
if ($currentUserRole !== 'admin') {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('/dashboard');
}

$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if ($name && $category) {
                    try {
                        $stmt = $db->prepare("INSERT INTO skills (name, category, description, created_by) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $category, $description, getCurrentUserId()]);
                        setFlashMessage('success', 'Skill added successfully!');
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Duplicate entry
                            setFlashMessage('error', 'A skill with this name already exists.');
                        } else {
                            setFlashMessage('error', 'Failed to add skill.');
                        }
                    }
                } else {
                    setFlashMessage('error', 'Name and category are required.');
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id && $name && $category) {
                    try {
                        $stmt = $db->prepare("UPDATE skills SET name = ?, category = ?, description = ?, is_active = ? WHERE id = ?");
                        $stmt->execute([$name, $category, $description, $is_active, $id]);
                        setFlashMessage('success', 'Skill updated successfully!');
                    } catch (PDOException $e) {
                        setFlashMessage('error', 'Failed to update skill.');
                    }
                } else {
                    setFlashMessage('error', 'Invalid skill data.');
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    try {
                        // Check if skill is in use
                        $stmt = $db->prepare("SELECT COUNT(*) FROM project_skills WHERE skill_id = ?");
                        $stmt->execute([$id]);
                        $projectCount = $stmt->fetchColumn();
                        
                        $stmt = $db->prepare("SELECT COUNT(*) FROM course_skills WHERE skill_id = ?");
                        $stmt->execute([$id]);
                        $courseCount = $stmt->fetchColumn();
                        
                        if ($projectCount > 0 || $courseCount > 0) {
                            setFlashMessage('error', 'Cannot delete skill. It is being used by ' . $projectCount . ' project(s) and ' . $courseCount . ' course(s).');
                        } else {
                            $stmt = $db->prepare("DELETE FROM skills WHERE id = ?");
                            $stmt->execute([$id]);
                            setFlashMessage('success', 'Skill deleted successfully!');
                        }
                    } catch (PDOException $e) {
                        setFlashMessage('error', 'Failed to delete skill.');
                    }
                }
                break;
        }
    }
    
    header('Location: /admin/skills');
    exit;
}

// Get all skills
$stmt = $db->query("
    SELECT s.*, 
           COUNT(DISTINCT ps.project_id) as project_count,
           COUNT(DISTINCT cs.course_id) as course_count
    FROM skills s
    LEFT JOIN project_skills ps ON s.id = ps.skill_id
    LEFT JOIN course_skills cs ON s.id = cs.skill_id
    GROUP BY s.id
    ORDER BY s.category, s.name
");
$skills = $stmt->fetchAll();

// Group skills by category
$skillsByCategory = [];
foreach ($skills as $skill) {
    $skillsByCategory[$skill['category']][] = $skill;
}

// Get unique categories
$categories = array_unique(array_column($skills, 'category'));
sort($categories);

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Skills Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                <i class="bi bi-plus-circle"></i> Add New Skill
            </button>
        </div>
    </div>

    <!-- Categories and Skills -->
    <?php foreach ($skillsByCategory as $category => $categorySkills): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($category); ?>
                    <span class="badge bg-secondary ms-2"><?php echo count($categorySkills); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorySkills as $skill): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($skill['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($skill['description'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($skill['project_count'] > 0): ?>
                                            <span class="badge bg-primary"><?php echo $skill['project_count']; ?> project(s)</span>
                                        <?php endif; ?>
                                        <?php if ($skill['course_count'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $skill['course_count']; ?> course(s)</span>
                                        <?php endif; ?>
                                        <?php if ($skill['project_count'] == 0 && $skill['course_count'] == 0): ?>
                                            <span class="text-muted">Not in use</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($skill['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editSkill(<?php echo htmlspecialchars(json_encode($skill)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($skill['project_count'] == 0 && $skill['course_count'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteSkill(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name'], ENT_QUOTES); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</main>

<!-- Add Skill Modal -->
<div class="modal fade" id="addSkillModal" tabindex="-1" aria-labelledby="addSkillModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSkillModalLabel">Add New Skill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/admin/skills">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Skill Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Category *</label>
                        <input type="text" class="form-control" id="category" name="category" list="categoryList" required>
                        <datalist id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Skill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Skill Modal -->
<div class="modal fade" id="editSkillModal" tabindex="-1" aria-labelledby="editSkillModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSkillModalLabel">Edit Skill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/admin/skills">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Skill Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">Category *</label>
                        <input type="text" class="form-control" id="edit_category" name="category" list="editCategoryList" required>
                        <datalist id="editCategoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="edit_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Skill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Skill Form -->
<form id="deleteForm" method="POST" action="/admin/skills" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function editSkill(skill) {
    document.getElementById('edit_id').value = skill.id;
    document.getElementById('edit_name').value = skill.name;
    document.getElementById('edit_category').value = skill.category;
    document.getElementById('edit_description').value = skill.description || '';
    document.getElementById('edit_is_active').checked = skill.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editSkillModal'));
    modal.show();
}

function deleteSkill(id, name) {
    if (confirm(`Are you sure you want to delete the skill "${name}"?`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>