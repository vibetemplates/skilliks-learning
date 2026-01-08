<?php
// Determine the base path - when accessed through router, we're in document root
$basePath = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../' : '';

require_once $basePath . 'includes/session.php';
require_once $basePath . 'config/database.php';
require_once $basePath . 'config/functions.php';
require_once $basePath . 'classes/User.php';
requireLogin();

$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

// Get database connection
$db = getDB();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $is_standard = isset($_POST['is_standard']) ? 1 : 0;
        $project_id = !$is_standard && !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        
        if (empty($name)) {
            $message = 'Category name is required.';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO requirements_category (name, is_standard, project_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $is_standard, $project_id]);
                $message = 'Category created successfully.';
                $messageType = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = 'A category with this name already exists.';
                } else {
                    $message = 'Error creating category: ' . $e->getMessage();
                }
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $is_standard = isset($_POST['is_standard']) ? 1 : 0;
        $project_id = !$is_standard && !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        
        if (empty($name)) {
            $message = 'Category name is required.';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $db->prepare("UPDATE requirements_category SET name = ?, is_standard = ?, project_id = ? WHERE id = ?");
                $stmt->execute([$name, $is_standard, $project_id, $id]);
                $message = 'Category updated successfully.';
                $messageType = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = 'A category with this name already exists.';
                } else {
                    $message = 'Error updating category: ' . $e->getMessage();
                }
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        try {
            // Check if category is in use
            $stmt = $db->prepare("SELECT COUNT(*) FROM project_requirements WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $message = "Cannot delete category. It is being used by $count requirement(s).";
                $messageType = 'warning';
            } else {
                $stmt = $db->prepare("DELETE FROM requirements_category WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Category deleted successfully.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error deleting category: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch all categories
$stmt = $db->query("
    SELECT rc.*, p.name as project_name, 
           (SELECT COUNT(*) FROM project_requirements WHERE category_id = rc.id) as usage_count
    FROM requirements_category rc
    LEFT JOIN projects p ON rc.project_id = p.id
    ORDER BY rc.is_standard DESC, rc.name ASC
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all projects for dropdown
$communityId = getCurrentCommunityId();
$stmt = $db->prepare("SELECT id, name FROM projects WHERE community_id = ? ORDER BY name");
$stmt->execute([$communityId]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Requirements Categories';
include $basePath . 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/admin/">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Requirements Categories</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Requirements Categories</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class="bi bi-plus-circle"></i> Add Category
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Project</th>
                                    <th>Usage</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td>
                                        <?php if ($category['is_standard']): ?>
                                            <span class="badge bg-primary">Standard</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Project-specific</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $category['project_name'] ? htmlspecialchars($category['project_name']) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $category['usage_count']; ?> requirements</span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-btn" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-is-standard="<?php echo $category['is_standard']; ?>"
                                                data-project-id="<?php echo $category['project_id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-usage="<?php echo $category['usage_count']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
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
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="categoryForm">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="id" id="categoryId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isStandard" name="is_standard" checked>
                            <label class="form-check-label" for="isStandard">
                                Standard Category (available to all projects)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="projectSelect" style="display: none;">
                        <label for="projectId" class="form-label">Project</label>
                        <select class="form-select" id="projectId" name="project_id">
                            <option value="">Select a project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p>Are you sure you want to delete the category "<span id="deleteName"></span>"?</p>
                    <p class="text-muted" id="deleteWarning"></p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryModal = document.getElementById('categoryModal');
    const isStandardCheckbox = document.getElementById('isStandard');
    const projectSelect = document.getElementById('projectSelect');
    
    // Toggle project select based on standard checkbox
    isStandardCheckbox.addEventListener('change', function() {
        if (this.checked) {
            projectSelect.style.display = 'none';
            document.getElementById('projectId').value = '';
        } else {
            projectSelect.style.display = 'block';
        }
    });
    
    // Reset modal when opening for new category
    categoryModal.addEventListener('show.bs.modal', function(event) {
        if (!event.relatedTarget || !event.relatedTarget.classList.contains('edit-btn')) {
            document.getElementById('categoryModalLabel').textContent = 'Add Category';
            document.getElementById('modalAction').value = 'create';
            document.getElementById('categoryForm').reset();
            isStandardCheckbox.checked = true;
            projectSelect.style.display = 'none';
        }
    });
    
    // Edit button click
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const modal = new bootstrap.Modal(categoryModal);
            document.getElementById('categoryModalLabel').textContent = 'Edit Category';
            document.getElementById('modalAction').value = 'update';
            document.getElementById('categoryId').value = this.dataset.id;
            document.getElementById('categoryName').value = this.dataset.name;
            
            const isStandard = this.dataset.isStandard === '1';
            isStandardCheckbox.checked = isStandard;
            
            if (!isStandard) {
                projectSelect.style.display = 'block';
                document.getElementById('projectId').value = this.dataset.projectId || '';
            } else {
                projectSelect.style.display = 'none';
            }
            
            modal.show();
        });
    });
    
    // Delete button click
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            document.getElementById('deleteId').value = this.dataset.id;
            document.getElementById('deleteName').textContent = this.dataset.name;
            
            const usage = parseInt(this.dataset.usage);
            const warning = document.getElementById('deleteWarning');
            if (usage > 0) {
                warning.textContent = `This category is used by ${usage} requirement(s) and cannot be deleted.`;
                warning.classList.add('text-danger');
            } else {
                warning.textContent = 'This action cannot be undone.';
                warning.classList.remove('text-danger');
            }
            
            modal.show();
        });
    });
});
</script>

<?php include $basePath . 'includes/footer.php'; ?>