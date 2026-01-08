<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/ProjectCategory.php';

requireLogin();

// Check if user is admin
if (!isCurrentUserAdmin()) {
    header('Location: project-categories');
    exit();
}

$projectCategory = new ProjectCategory();
$errors = [];
$success = false;

// Get category ID from URL
$categoryId = intval($_GET['id'] ?? 0);
if (!$categoryId) {
    header('Location: project-categories');
    exit();
}

// Get category data
$category = $projectCategory->findById($categoryId);
if (!$category) {
    $_SESSION['error_message'] = 'Category not found';
    header('Location: project-categories');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $skill_level = $_POST['skill_level'] ?? 'all';
    $display_order = intval($_POST['display_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    // Validate required fields
    if (empty($name)) {
        $errors[] = 'Category name is required';
    }
    
    // Check if category name already exists (excluding current category)
    $existing = $projectCategory->findByName($name);
    if ($existing && $existing['id'] != $categoryId) {
        $errors[] = 'A category with this name already exists';
    }
    
    if (empty($errors)) {
        $data = [
            'name' => $name,
            'description' => $description,
            'thumbnail_url' => $thumbnail_url,
            'skill_level' => $skill_level,
            'display_order' => $display_order,
            'status' => $status
        ];
        
        if ($projectCategory->update($categoryId, $data)) {
            $_SESSION['success_message'] = 'Category updated successfully!';
            header('Location: project-categories');
            exit();
        } else {
            $errors[] = 'Failed to update category';
        }
    }
}

$page_title = 'Edit Category';
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12 col-md-8 mx-auto">
            <div class="card" id="edit-category-card">
                <div class="card-header">
                    <h3 id="form-heading">Edit Project Category</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" id="error-alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="category-form">
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $category['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $category['description']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="thumbnail_url" class="form-label">Thumbnail URL</label>
                            <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" 
                                   value="<?php echo htmlspecialchars($_POST['thumbnail_url'] ?? $category['thumbnail_url'] ?? ''); ?>" 
                                   placeholder="https://example.com/image.jpg">
                            <small class="text-muted">Enter the URL of the thumbnail image</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="skill_level" class="form-label">Skill Level</label>
                            <select class="form-control" id="skill_level" name="skill_level">
                                <option value="all" <?php echo ($category['skill_level'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Levels</option>
                                <option value="beginner" <?php echo ($category['skill_level'] ?? '') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo ($category['skill_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo ($category['skill_level'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="display_order" name="display_order" 
                                   value="<?php echo htmlspecialchars($_POST['display_order'] ?? $category['display_order']); ?>" min="0">
                            <small class="text-muted">Lower numbers appear first</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="active" <?php echo ($category['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($category['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info" id="project-count-info">
                            <i class="bi bi-info-circle"></i> This category contains <strong><?php echo $projectCategory->getProjectCount($categoryId); ?></strong> project(s).
                        </div>
                        
                        <div class="d-flex justify-content-between" id="form-buttons">
                            <a href="project-categories" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Update Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>