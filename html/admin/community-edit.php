<?php
/**
 * Community Edit Page
 * 
 * Allows admins to edit community details including slug and thumbnail
 */

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../config/functions.php';
require_once '../includes/session.php';
require_once '../classes/Community.php';

// Check if user is admin
if (!isCurrentUserAdmin()) {
    header('Location: /dashboard');
    exit;
}

$page_title = 'Edit Community';
$current_page = 'communities';

// Initialize Community class
$community = new Community();

// Get community ID
$communityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$communityId) {
    setFlashMessage('error', 'Invalid community ID.');
    header('Location: /admin/communities.php');
    exit;
}

// Get community details
$editCommunityData = $community->getById($communityId);

if (!$editCommunityData) {
    setFlashMessage('error', 'Community not found.');
    header('Location: /admin/communities.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $banner_url = trim($_POST['banner_url'] ?? '');
    $monthly_price = !empty($_POST['monthly_price']) ? floatval($_POST['monthly_price']) : null;
    $display_member_count = trim($_POST['display_member_count'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
    $auto_approve_from_email_list = isset($_POST['auto_approve_from_email_list']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Generate slug from name if not provided
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
    }
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Community name is required.';
    }
    
    if (empty($slug)) {
        $errors[] = 'URL slug is required.';
    }
    
    // Check for duplicate slug if changed
    if (!empty($slug) && $slug !== $editCommunityData['slug']) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM communities WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $communityId]);
            if ($stmt->fetch()) {
                $errors[] = 'URL slug already exists. Please choose a different slug.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking slug uniqueness.';
        }
    }
    
    if (empty($errors)) {
        // Update community
        $updateData = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'logo_url' => $logo_url,
            'banner_url' => $banner_url,
            'monthly_price' => $monthly_price,
            'display_member_count' => $display_member_count,
            'is_public' => $is_public,
            'requires_approval' => $requires_approval,
            'auto_approve_from_email_list' => $auto_approve_from_email_list,
            'is_active' => $is_active
        ];
        
        $result = $community->update($communityId, $updateData);
        
        if ($result) {
            setFlashMessage('success', 'Community updated successfully!');
            header('Location: /admin/communities.php');
            exit;
        } else {
            $errors[] = 'Failed to update community. Please try again.';
        }
    }
}

include '../includes/header.php';
?>

<main class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Community</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/admin/">Admin</a></li>
                    <li class="breadcrumb-item"><a href="/admin/communities.php">Communities</a></li>
                    <li class="breadcrumb-item active">Edit (ID: <?php echo $communityId; ?> - <?php echo htmlspecialchars($editCommunityData['name'] ?? ''); ?>)</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Display errors if any -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Community Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Community Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $editCommunityData['name'] ?? ''); ?>" required>
                            <div class="form-text">This will be displayed throughout the application.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="slug" class="form-label">URL Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?php echo htmlspecialchars($_POST['slug'] ?? $editCommunityData['slug'] ?? ''); ?>" 
                                   pattern="[a-z0-9-]+" required>
                            <div class="form-text">Used in URLs (e.g., /community/<?php echo htmlspecialchars($editCommunityData['slug'] ?? ''); ?>). Only lowercase letters, numbers, and hyphens allowed.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $editCommunityData['description'] ?? ''); ?></textarea>
                            <div class="form-text">A brief description of this community.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="logo_url" class="form-label">Logo URL</label>
                            <input type="url" class="form-control" id="logo_url" name="logo_url" 
                                   value="<?php echo htmlspecialchars($_POST['logo_url'] ?? $editCommunityData['logo_url'] ?? ''); ?>"
                                   placeholder="https://example.com/logo.png">
                            <div class="form-text">URL for the community logo image.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="banner_url" class="form-label">Banner URL</label>
                            <input type="url" class="form-control" id="banner_url" name="banner_url" 
                                   value="<?php echo htmlspecialchars($_POST['banner_url'] ?? $editCommunityData['banner_url'] ?? ''); ?>"
                                   placeholder="https://example.com/banner.jpg">
                            <div class="form-text">URL for the community banner image.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="monthly_price" class="form-label">Monthly Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="monthly_price" name="monthly_price" 
                                       value="<?php echo htmlspecialchars($_POST['monthly_price'] ?? $editCommunityData['monthly_price'] ?? ''); ?>"
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="form-text">Leave empty or set to 0 for free communities. Enter the monthly subscription price for paid communities.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="display_member_count" class="form-label">Display Member Count</label>
                            <input type="text" class="form-control" id="display_member_count" name="display_member_count" 
                                   value="<?php echo htmlspecialchars($_POST['display_member_count'] ?? $editCommunityData['display_member_count'] ?? ''); ?>"
                                   placeholder="e.g., 1000+ members, 50-100 members, Growing community">
                            <div class="form-text">Custom text to display for member count. Leave empty to show actual count.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_public" name="is_public"
                                       <?php echo (isset($_POST['is_public']) ? $_POST['is_public'] : $editCommunityData['is_public']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_public">
                                    Public Community
                                </label>
                                <div class="form-text">Public communities are visible to all users.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requires_approval" name="requires_approval"
                                       <?php echo (isset($_POST['requires_approval']) ? $_POST['requires_approval'] : $editCommunityData['requires_approval']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="requires_approval">
                                    Require Approval to Join
                                </label>
                                <div class="form-text">New members must be approved by a community admin.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto_approve_from_email_list" name="auto_approve_from_email_list"
                                       <?php echo (isset($_POST['auto_approve_from_email_list']) ? $_POST['auto_approve_from_email_list'] : $editCommunityData['auto_approve_from_email_list']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_approve_from_email_list">
                                    Auto-approve from Email List
                                </label>
                                <div class="form-text">Automatically approve members whose email address is in the free_community_emails table.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                       <?php echo (isset($_POST['is_active']) ? $_POST['is_active'] : $editCommunityData['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                                <div class="form-text">Inactive communities are hidden from users.</div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Community</button>
                            <a href="/admin/communities.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Community Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8"><?php echo date('M d, Y', strtotime($editCommunityData['created_at'])); ?></dd>
                        
                        <dt class="col-sm-4">Updated</dt>
                        <dd class="col-sm-8"><?php echo date('M d, Y', strtotime($editCommunityData['updated_at'])); ?></dd>
                        
                        <dt class="col-sm-4">Members</dt>
                        <dd class="col-sm-8"><span class="badge bg-info"><?php echo $editCommunityData['member_count'] ?? 0; ?></span></dd>
                        
                        <dt class="col-sm-4">Projects</dt>
                        <dd class="col-sm-8"><span class="badge bg-primary"><?php echo $editCommunityData['project_count'] ?? 0; ?></span></dd>
                        
                        <dt class="col-sm-4">Courses</dt>
                        <dd class="col-sm-8"><span class="badge bg-success"><?php echo $editCommunityData['course_count'] ?? 0; ?></span></dd>
                    </dl>
                    
                    <?php if (!empty($editCommunityData['logo_url'])): ?>
                        <hr>
                        <h6>Current Logo</h6>
                        <img src="<?php echo htmlspecialchars($editCommunityData['logo_url']); ?>" 
                             alt="Community Logo" class="img-fluid" style="max-height: 100px;">
                    <?php endif; ?>
                    
                    <?php if (!empty($editCommunityData['banner_url'])): ?>
                        <hr>
                        <h6>Current Banner</h6>
                        <img src="<?php echo htmlspecialchars($editCommunityData['banner_url']); ?>" 
                             alt="Community Banner" class="img-fluid" style="max-height: 150px;">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function(e) {
    const nameInput = e.target;
    const slugInput = document.getElementById('slug');
    
    // Only auto-generate if slug is empty or was previously auto-generated
    if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
        const slug = nameInput.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        
        slugInput.value = slug;
        slugInput.dataset.autoGenerated = 'true';
    }
});

// Mark slug as manually edited if user types in it
document.getElementById('slug').addEventListener('input', function(e) {
    if (e.target.value) {
        e.target.dataset.autoGenerated = 'false';
    }
});
</script>

<?php include '../includes/footer.php'; ?>