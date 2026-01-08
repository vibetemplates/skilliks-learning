<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/ProjectCategory.php';

requireLogin();

$projectObj = new Project();
$projectCategory = new ProjectCategory();
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $git_repository_url = trim($_POST['git_repository_url'] ?? '');
    $dev_system_url = trim($_POST['dev_system_url'] ?? '');
    $project_category_id = (int)($_POST['project_category_id'] ?? 0);
    $slug = trim($_POST['slug'] ?? '');
    $visibility = trim($_POST['visibility'] ?? 'public');

    // Generate slug from name if not provided
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
    }

    // Validation
    if (empty($name)) {
        $errors[] = 'Project name is required';
    }

    if (empty($project_category_id)) {
        $errors[] = 'Project category is required';
    }

    // Check for duplicate slug if provided
    if (!empty($slug)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM projects WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $errors[] = 'URL slug already exists. Please choose a different slug';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking slug uniqueness';
        }
    }

    if (empty($errors)) {
        $data = [
            'name' => $name,
            'description' => $description,
            'team_size_limit' => 10,
            'git_repository_url' => $git_repository_url,
            'dev_system_url' => $dev_system_url,
            'project_category_id' => $project_category_id,
            'slug' => $slug,
            'visibility' => $visibility,
            'created_by' => getCurrentUserId()
        ];

        try {
            $projectId = $projectObj->create($data);
            if ($projectId) {
                setFlashMessage('success', 'Project created successfully!');
                header('Location: /project-detail.php?id=' . $projectId);
                exit();
            } else {
                $errors[] = 'Failed to create project';
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to create project: ' . htmlspecialchars($e->getMessage());
            error_log("Project creation exception: " . $e->getMessage());
        }
    }
}

$page_title = 'Create New Project';
include 'includes/header.php';
?>

<div class="container mt-4" id="create-project-container">
    <div class="row">
        <div class="col-12">
            <div class="card" id="create-project-card">
                <div class="card-header">
                    <h3 id="form-heading">Create New Project</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" id="error-alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="create-project-form">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6" id="left-column">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="project_category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="project_category_id" name="project_category_id" required>
                                        <option value="">Select a category</option>
                                        <?php
                                        $categories = $projectCategory->findAll(true);
                                        foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"
                                                    <?php echo (($_POST['project_category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="git_repository_url" class="form-label">Git Repository URL <span class="text-muted">(Optional)</span></label>
                                    <input type="url" class="form-control" id="git_repository_url" name="git_repository_url"
                                           value="<?php echo htmlspecialchars($_POST['git_repository_url'] ?? ''); ?>"
                                           placeholder="https://github.com/username/repository.git">
                                    <small class="form-text text-muted">You can add or change this later in project settings</small>
                                </div>

                                <div class="mb-3">
                                    <label for="dev_system_url" class="form-label">Development System URL <span class="text-muted">(Optional)</span></label>
                                    <input type="url" class="form-control" id="dev_system_url" name="dev_system_url"
                                           value="<?php echo htmlspecialchars($_POST['dev_system_url'] ?? ''); ?>"
                                           placeholder="https://dev.example.com">
                                    <small class="form-text text-muted">API URL for development system integration</small>
                                </div>

                                <div class="mb-3">
                                    <label for="visibility" class="form-label">Project Visibility <span class="text-danger">*</span></label>
                                    <select class="form-select" id="visibility" name="visibility" required>
                                        <option value="public" <?php echo (($_POST['visibility'] ?? 'public') === 'public') ? 'selected' : ''; ?>>
                                            Public - Visible to all community members
                                        </option>
                                        <option value="private" <?php echo (($_POST['visibility'] ?? '') === 'private') ? 'selected' : ''; ?>>
                                            Private - Visible only to invited members
                                        </option>
                                    </select>
                                    <small class="form-text text-muted">Private projects are only visible to administrators, the project creator, and invited members</small>
                                </div>
                            </div>

                            <!-- Right Column - Description Fields -->
                            <div class="col-md-6" id="right-column">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="20"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between" id="form-buttons">
                            <a href="project-categories" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Create Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
