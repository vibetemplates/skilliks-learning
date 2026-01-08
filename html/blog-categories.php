<?php
/**
 * Blog Categories Management Page
 * 
 * Allows community admins to manage blog categories
 */

$page_title = 'Manage Post Categories';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/BlogCategory.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Initialize classes
$blogCategory = new BlogCategory();
$community = new Community();

// Check if user is admin
$currentCommunity = $community->getById($currentCommunityId);
$userRole = $community->isMember($currentCommunityId, $currentUserId);
if ($userRole !== 'admin') {
    header('Location: dashboard');
    exit;
}

// Get all categories for the community
$categories = $blogCategory->getByCommunity($currentCommunityId, false); // Get all, including inactive

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div class="row mt-4">
        <div class="col-lg-10 mx-auto">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Manage Post Categories</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-circle me-2"></i>Add Category
                </button>
            </div>

            <!-- Categories Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-tags fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted">No categories yet. Add your first category to get started.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Category
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Posts</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <?php if ($category['icon']): ?>
                                        <i class="<?php echo htmlspecialchars($category['icon']); ?>" 
                                           style="color: <?php echo htmlspecialchars($category['color']); ?>"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo $category['post_count'] ?? 0; ?> posts
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($category['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($category['post_count'] == 0): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCategoryForm" onsubmit="saveCategory(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="color" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="color" name="color" value="#6c757d">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="icon" class="form-label">Icon (Bootstrap Icons)</label>
                                <input type="text" class="form-control" id="icon" name="icon" placeholder="bi-folder">
                                <small class="form-text">
                                    <a href="https://icons.getbootstrap.com/" target="_blank">Browse icons</a>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCategoryForm" onsubmit="updateCategory(event)">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_color" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="edit_color" name="color">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_icon" class="form-label">Icon (Bootstrap Icons)</label>
                                <input type="text" class="form-control" id="edit_icon" name="icon" placeholder="bi-folder">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add category
function saveCategory(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    fetch('api/blog-category-save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to add category');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the category');
    });
}

// Edit category
function editCategory(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description || '';
    document.getElementById('edit_color').value = category.color || '#6c757d';
    document.getElementById('edit_icon').value = category.icon || '';
    document.getElementById('edit_is_active').checked = category.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

// Update category
function updateCategory(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'update');
    
    fetch('api/blog-category-save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to update category');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the category');
    });
}

// Delete category
function deleteCategory(id, name) {
    if (!confirm(`Are you sure you want to delete the category "${name}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', 'delete');
    
    fetch('api/blog-category-save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to delete category');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the category');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>