<?php
/**
 * Admin - Edit User
 * 
 * Edit user details including slug
 */

$page_title = 'Edit User - Admin';
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';

// Require login and global admin role
requireLogin();

$currentUserId = getCurrentUserId();

// Check if user is global admin
if (!isCurrentUserGlobalAdmin()) {
    setFlashMessage('danger', 'You do not have permission to access the admin area.');
    redirect('/dashboard');
}

// Get user ID
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$userId) {
    setFlashMessage('danger', 'Invalid user ID.');
    redirect('/admin/users.php');
}

// Get user details
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        setFlashMessage('danger', 'User not found.');
        redirect('/admin/users.php');
    }
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    setFlashMessage('danger', 'Error loading user.');
    redirect('/admin/users.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $github_username = trim($_POST['github_username'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $linkedin_url = trim($_POST['linkedin_url'] ?? '');
    $twitter_handle = trim($_POST['twitter_handle'] ?? '');
    
    // Generate slug from name if not provided
    if (empty($slug) && !empty($first_name) && !empty($last_name)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $first_name . '-' . $last_name));
        $slug = trim($slug, '-');
    }
    
    $errors = [];
    
    // Validation
    if (empty($first_name)) {
        $errors[] = 'First name is required.';
    }
    if (empty($last_name)) {
        $errors[] = 'Last name is required.';
    }
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    // Check for duplicate email if changed
    if ($email !== $user['email']) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking email uniqueness.';
        }
    }
    
    // Check for duplicate slug if provided and changed
    if (!empty($slug) && $slug !== ($user['slug'] ?? '')) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'URL slug already exists. Please choose a different slug.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking slug uniqueness.';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE users SET 
                    first_name = ?, last_name = ?, email = ?, github_username = ?, 
                    slug = ?, bio = ?, location = ?, website = ?, 
                    linkedin_url = ?, twitter_handle = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $first_name, $last_name, $email, $github_username,
                $slug, $bio, $location, $website,
                $linkedin_url, $twitter_handle, $userId
            ]);
            
            if ($result) {
                // Update local user data
                $user['first_name'] = $first_name;
                $user['last_name'] = $last_name;
                $user['email'] = $email;
                $user['github_username'] = $github_username;
                $user['slug'] = $slug;
                $user['bio'] = $bio;
                $user['location'] = $location;
                $user['website'] = $website;
                $user['linkedin_url'] = $linkedin_url;
                $user['twitter_handle'] = $twitter_handle;
                
                setFlashMessage('success', 'User updated successfully!');
            } else {
                $errors[] = 'Failed to update user. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            $errors[] = 'Database error occurred while updating user.';
        }
    }
}

require_once '../includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit User: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/team-member?id=<?php echo $userId; ?>" class="btn btn-outline-primary me-2">
                <i class="bi bi-eye"></i> View Profile
            </a>
            <a href="/admin/users.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/admin/user-edit.php?id=<?php echo $userId; ?>">
                        <!-- Basic Information -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="github_username" class="form-label">GitHub Username</label>
                                <input type="text" class="form-control" id="github_username" name="github_username" 
                                       value="<?php echo htmlspecialchars($user['github_username'] ?? ''); ?>"
                                       placeholder="username">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="slug" class="form-label">URL Slug</label>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?php echo htmlspecialchars($user['slug'] ?? ''); ?>" 
                                   placeholder="e.g., john-doe (leave blank to auto-generate)">
                            <small class="text-muted">Custom URL for this user's profile. Will be used as: /<?php echo htmlspecialchars($user['slug'] ?? 'their-custom-url'); ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      placeholder="Tell us about yourself"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>"
                                       placeholder="City, Country">
                            </div>
                            <div class="col-md-6">
                                <label for="website" class="form-label">Website</label>
                                <input type="url" class="form-control" id="website" name="website" 
                                       value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>"
                                       placeholder="https://example.com">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                       value="<?php echo htmlspecialchars($user['linkedin_url'] ?? ''); ?>"
                                       placeholder="https://linkedin.com/in/username">
                            </div>
                            <div class="col-md-6">
                                <label for="twitter_handle" class="form-label">Twitter Handle</label>
                                <input type="text" class="form-control" id="twitter_handle" name="twitter_handle" 
                                       value="<?php echo htmlspecialchars($user['twitter_handle'] ?? ''); ?>"
                                       placeholder="@username">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Update User
                            </button>
                            <a href="/admin/users.php" class="btn btn-secondary ms-2">
                                <i class="bi bi-x-lg"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">User Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get user statistics
                    try {
                        $stmt = $db->prepare("
                            SELECT 
                                (SELECT COUNT(*) FROM project_members WHERE user_id = ? AND status = 'approved') as project_count,
                                (SELECT COUNT(*) FROM tasks WHERE assignee_id = ?) as task_count,
                                (SELECT COUNT(*) FROM course_enrollments WHERE user_id = ?) as course_count,
                                (SELECT COUNT(DISTINCT community_id) FROM community_members WHERE user_id = ? AND is_active = 1) as community_count
                        ");
                        $stmt->execute([$userId, $userId, $userId, $userId]);
                        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $stats = ['project_count' => 0, 'task_count' => 0, 'course_count' => 0, 'community_count' => 0];
                    }
                    ?>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-folder"></i> 
                            <strong>Projects:</strong> <?php echo $stats['project_count']; ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-square"></i> 
                            <strong>Tasks:</strong> <?php echo $stats['task_count']; ?>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-book"></i> 
                            <strong>Courses:</strong> <?php echo $stats['course_count']; ?>
                        </li>
                        <li>
                            <i class="bi bi-people"></i> 
                            <strong>Communities:</strong> <?php echo $stats['community_count']; ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>User ID:</strong> <?php echo $userId; ?></p>
                    <p><strong>Registered:</strong> <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                    <?php if ($user['last_login']): ?>
                        <p><strong>Last Login:</strong> <?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?></p>
                    <?php else: ?>
                        <p><strong>Last Login:</strong> Never</p>
                    <?php endif; ?>
                    <p><strong>Status:</strong> 
                        <?php if ($user['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>