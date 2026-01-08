<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$userObj = new User();

// Check admin access
if (!$userObj->isAdmin($currentUserId)) {
    setFlashMessage('error', 'Access denied. Admin privileges required.');
    header('Location: /programs');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Auto-generate slug if not provided
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
    }
    
    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = 'Program name is required.';
    }
    if (empty($slug)) {
        $errors[] = 'Program slug is required.';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check if slug already exists
            $checkStmt = $db->prepare("SELECT id FROM programs WHERE slug = ?");
            $checkStmt->execute([$slug]);
            if ($checkStmt->fetch()) {
                $errors[] = 'A program with this slug already exists.';
            } else {
                // Get current community ID
                $currentCommunityId = getCurrentCommunityId();
                
                // Insert new program
                $stmt = $db->prepare("
                    INSERT INTO programs (community_id, name, slug, description, short_description, thumbnail_url, display_order, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $result = $stmt->execute([
                    $currentCommunityId,
                    $name,
                    $slug,
                    $description,
                    $short_description,
                    $thumbnail_url,
                    $display_order,
                    $is_active
                ]);
                
                if ($result) {
                    setFlashMessage('success', 'Program created successfully!');
                    header('Location: /programs');
                    exit;
                } else {
                    $errors[] = 'Failed to create program.';
                }
            }
        } catch (PDOException $e) {
            error_log("Program creation error: " . $e->getMessage());
            $errors[] = 'Database error occurred while creating program.';
        }
    }
}

$page_title = "Create New Program";
require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4" style="padding-top: 40px;">
    <div id="program-create-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Create New Program</h1>
        <div id="program-create-toolbar" class="btn-toolbar mb-2 mb-md-0">
            <a href="/programs" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Programs
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div id="error-alert" class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div id="program-form-card" class="card">
                <div class="card-body">
                    <form method="POST" action="/admin-program-create.php">
                        <div class="mb-3">
                            <label for="name" class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            <div class="form-text">Enter the display name for the program</div>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">URL Slug</label>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?php echo htmlspecialchars($_POST['slug'] ?? ''); ?>" 
                                   pattern="[a-z0-9-]+" title="Only lowercase letters, numbers, and hyphens">
                            <div class="form-text">Leave blank to auto-generate from name. Only lowercase letters, numbers, and hyphens allowed.</div>
                        </div>

                        <div class="mb-3">
                            <label for="short_description" class="form-label">Short Description</label>
                            <input type="text" class="form-control" id="short_description" name="short_description" 
                                   value="<?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?>" maxlength="500">
                            <div class="form-text">Brief description shown on program cards (max 500 characters)</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Full Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">Detailed program description</div>
                        </div>

                        <div class="mb-3">
                            <label for="thumbnail_url" class="form-label">Thumbnail URL</label>
                            <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" 
                                   value="<?php echo htmlspecialchars($_POST['thumbnail_url'] ?? ''); ?>">
                            <div class="form-text">URL to the program thumbnail image</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="display_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="display_order" name="display_order" 
                                           value="<?php echo htmlspecialchars($_POST['display_order'] ?? '0'); ?>" min="0">
                                    <div class="form-text">Lower numbers appear first</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo (isset($_POST['is_active']) || !isset($_POST['name'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active (visible to users)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Create Program
                            </button>
                            <a href="/programs" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div id="help-card" class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Quick Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>Programs are used to group related courses together</li>
                        <li>Each course must belong to exactly one program</li>
                        <li>URL slugs are used in web addresses and should be descriptive</li>
                        <li>Inactive programs won't be visible to users</li>
                        <li>You can edit program details after creation</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>