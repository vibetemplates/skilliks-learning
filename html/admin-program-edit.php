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

// Get program ID
$programId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($programId <= 0) {
    setFlashMessage('error', 'Invalid program ID.');
    header('Location: /programs');
    exit;
}

$db = getDB();

// Get current community ID
$currentCommunityId = getCurrentCommunityId();

// Fetch existing program data
try {
    $stmt = $db->prepare("SELECT * FROM programs WHERE id = ? AND community_id = ?");
    $stmt->execute([$programId, $currentCommunityId]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$program) {
        setFlashMessage('error', 'Program not found in this community.');
        header('Location: /programs');
        exit;
    }
} catch (PDOException $e) {
    error_log("Program fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error fetching program data.');
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
            // Check if slug already exists (excluding current program)
            $checkStmt = $db->prepare("SELECT id FROM programs WHERE slug = ? AND id != ?");
            $checkStmt->execute([$slug, $programId]);
            if ($checkStmt->fetch()) {
                $errors[] = 'A program with this slug already exists.';
            } else {
                // Update program
                $stmt = $db->prepare("
                    UPDATE programs 
                    SET name = ?, slug = ?, description = ?, short_description = ?, 
                        thumbnail_url = ?, display_order = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $name,
                    $slug,
                    $description,
                    $short_description,
                    $thumbnail_url,
                    $display_order,
                    $is_active,
                    $programId
                ]);
                
                if ($result) {
                    setFlashMessage('success', 'Program updated successfully!');
                    header('Location: /programs');
                    exit;
                } else {
                    $errors[] = 'Failed to update program.';
                }
            }
        } catch (PDOException $e) {
            error_log("Program update error: " . $e->getMessage());
            $errors[] = 'Database error occurred while updating program.';
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $program;
}

// Get course count for this program
$courseStmt = $db->prepare("SELECT COUNT(*) as count FROM courses WHERE program_id = ?");
$courseStmt->execute([$programId]);
$courseCount = $courseStmt->fetch(PDO::FETCH_ASSOC)['count'];

$page_title = "Edit Program";
require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4" style="padding-top: 40px;">
    <div id="program-edit-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Program: <?php echo htmlspecialchars($program['name']); ?></h1>
        <div id="program-edit-toolbar" class="btn-toolbar mb-2 mb-md-0">
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
                    <form method="POST" action="/admin-program-edit.php?id=<?php echo $programId; ?>">
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
                            <?php if (!empty($_POST['thumbnail_url'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($_POST['thumbnail_url']); ?>" 
                                         alt="Current thumbnail" 
                                         style="max-width: 200px; max-height: 150px; object-fit: cover;"
                                         class="border rounded">
                                </div>
                            <?php endif; ?>
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
                                               <?php echo (!empty($_POST['is_active'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active (visible to users)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Update Program
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
            <div id="info-card" class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Program Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Created:</dt>
                        <dd class="col-sm-7"><?php echo date('M j, Y', strtotime($program['created_at'])); ?></dd>
                        
                        <dt class="col-sm-5">Last Updated:</dt>
                        <dd class="col-sm-7"><?php echo date('M j, Y', strtotime($program['updated_at'])); ?></dd>
                        
                        <dt class="col-sm-5">Total Courses:</dt>
                        <dd class="col-sm-7"><?php echo $courseCount; ?></dd>
                    </dl>
                </div>
            </div>

            <div id="help-card" class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Quick Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>Changing the program name will not affect existing course assignments</li>
                        <li>The URL slug should remain consistent to avoid broken links</li>
                        <li>Inactive programs are hidden from users but courses remain accessible</li>
                        <li>You cannot delete a program that has courses assigned to it</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>