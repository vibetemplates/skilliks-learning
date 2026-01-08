<?php
/**
 * Team Member Display Page
 * 
 * Shows detailed information about a specific team member
 */

$page_title = 'Team Member Profile';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Task.php';

// Require login
requireLogin();

// Get member ID from URL
$memberId = $_GET['id'] ?? null;
if (!$memberId) {
    setFlashMessage('error', 'Team member not found.');
    header('Location: /members');
    exit;
}

$userObj = new User();
$taskObj = new Task();
$currentUserId = getCurrentUserId();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);
$isAdmin = $userObj->isAdmin($currentUserId);

// Get member information
$member = $userObj->findById($memberId);
if (!$member) {
    setFlashMessage('error', 'Team member not found.');
    header('Location: /members');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle user update (only for admins)
    if (isset($_POST['action']) && $_POST['action'] === 'update_user' && $isAdmin) {
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
        $student_id = trim($_POST['student_id'] ?? '');
        $timezone = trim($_POST['timezone'] ?? '');
        $skill_level = trim($_POST['skill_level'] ?? '');
        
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
        if ($email !== $member['email']) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $memberId]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email already exists.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Error checking email uniqueness.';
            }
        }
        
        // Check for duplicate slug if provided and changed
        if (!empty($slug) && $slug !== ($member['slug'] ?? '')) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM users WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $memberId]);
                if ($stmt->fetch()) {
                    $errors[] = 'URL slug already exists. Please choose a different slug.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Error checking slug uniqueness.';
            }
        }
        
        if (empty($errors)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    UPDATE users SET 
                        first_name = ?, last_name = ?, email = ?, github_username = ?, 
                        slug = ?, bio = ?, location = ?, website = ?, 
                        linkedin_url = ?, twitter_handle = ?, student_id = ?, 
                        timezone = ?, skill_level = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $first_name, $last_name, $email, $github_username,
                    $slug, $bio, $location, $website,
                    $linkedin_url, $twitter_handle, $student_id,
                    $timezone, $skill_level, $memberId
                ]);
                
                if ($result) {
                    setFlashMessage('success', 'User updated successfully!');
                    header("Location: /team-member?id={$memberId}");
                    exit;
                } else {
                    setFlashMessage('error', 'Failed to update user. Please try again.');
                }
            } catch (PDOException $e) {
                error_log("User update error: " . $e->getMessage());
                setFlashMessage('error', 'Database error occurred while updating user.');
            }
        } else {
            setFlashMessage('error', implode(' ', $errors));
        }
    }
    
    // Handle task deletion (only for project managers and admins)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_task') {
        $taskId = $_POST['task_id'] ?? null;
        
        if (!$isProjectManagerOrAdmin) {
            setFlashMessage('error', 'You do not have permission to delete tasks.');
        } elseif ($taskId) {
            // Verify the task is assigned to this member
            $task = $taskObj->findById($taskId);
            if ($task && $task['assignee_id'] == $memberId) {
                if ($taskObj->delete($taskId)) {
                    setFlashMessage('success', 'Task deleted successfully.');
                } else {
                    setFlashMessage('error', 'Failed to delete task.');
                }
            } else {
                setFlashMessage('error', 'Task not found or not assigned to this member.');
            }
        }
        
        header("Location: /team-member?id={$memberId}");
        exit;
    }
}

// Get member's assigned tasks (only visible to project managers, admins, and the member themselves)
$memberTasks = [];
if ($isProjectManagerOrAdmin || $memberId == $currentUserId) {
    $memberTasks = $taskObj->findByAssignee($memberId);
}

// Get member's project statistics
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT pm.project_id) as project_count
        FROM project_members pm
        WHERE pm.user_id = ? AND pm.status = 'approved'
    ");
    $stmt->execute([$memberId]);
    $projectStats = $stmt->fetch();
} catch (PDOException $e) {
    $projectStats = ['project_count' => 0];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    
        

        
        
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/members">Team Members</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if ($isAdmin): ?>
                        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#editUserModal">
                            <i class="bi bi-pencil"></i> Edit Profile
                        </button>
                    <?php endif; ?>
                    <a href="/members" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Team Members
                    </a>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Profile Information -->
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mx-auto mb-3" style="width: 80px; height: 80px;">
                                <?php if (!empty($member['profile_photo'])): ?>
                                    <img src="/uploads/avatars/<?php echo htmlspecialchars($member['profile_photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                         class="rounded-circle" 
                                         style="width: 80px; height: 80px; object-fit: cover;"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'bg-primary text-white rounded-circle d-flex align-items-center justify-content-center\' style=\'width: 80px; height: 80px; font-size: 2em;\'><?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?></div>';">
                                <?php else: ?>
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 80px; height: 80px; font-size: 2em;">
                                        <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($member['email']); ?></p>
                            
                            <!-- Role Badge -->
                            <span class="badge bg-<?php 
                                echo $member['role'] === 'admin' ? 'danger' : 
                                     ($member['role'] === 'project_manager' ? 'warning' : 'primary'); 
                            ?> fs-6">
                                <?php echo ucfirst(str_replace('_', ' ', $member['role'])); ?>
                                <?php if ($member['id'] == $currentUserId): ?>
                                    <small>(You)</small>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($member['student_id'])): ?>
                                <div class="col-md-6 mb-3">
                                    <strong>Student ID:</strong>
                                    <div><?php echo htmlspecialchars($member['student_id']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['github_username'])): ?>
                                <div class="col-md-6 mb-3">
                                    <strong>Git Username:</strong>
                                    <div>
                                        <a href="https://git.kineticseas.com/<?php echo htmlspecialchars($member['github_username']); ?>" 
                                           target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($member['github_username']); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['timezone'])): ?>
                                <div class="col-md-6 mb-3">
                                    <strong>Timezone:</strong>
                                    <div><?php echo htmlspecialchars($member['timezone']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['skill_level'])): ?>
                                <div class="col-md-6 mb-3">
                                    <strong>Skill Level:</strong>
                                    <div><span class="badge bg-info"><?php echo ucfirst($member['skill_level']); ?></span></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($member['bio'])): ?>
                            <div class="mb-3">
                                <strong>About:</strong>
                                <div class="mt-1"><?php echo htmlspecialchars($member['bio']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information Row -->
            <div class="row">
                <!-- Quick Stats -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Quick Stats</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo $projectStats['project_count']; ?></h4>
                                    <small class="text-muted">Projects</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?php echo count($memberTasks); ?></h4>
                                    <small class="text-muted">Tasks</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Technology Skills Matrix -->
                    <?php 
                    $hasSkills = false;
                    $skillMap = [
                        'ai_assisted_coding_current' => 'AI Assisted Coding',
                        'mcp_servers_current' => 'MCP Servers',
                        'ai_automations_current' => 'AI Automations',
                        'startup_operations_current' => 'Startup Operations',
                        'ai_security_current' => 'AI Security',
                        'ai_infrastructure_current' => 'AI Infrastructure',
                        'rag_current' => 'Retrieval Augmented Generation',
                        'local_models_current' => 'Local Models',
                        'supervised_fine_tuning_current' => 'Supervised Fine Tuning'
                    ];
                    
                    foreach ($skillMap as $field => $label) {
                        if (!empty($member[$field]) && $member[$field] > 0) {
                            $hasSkills = true;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($hasSkills): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Technology Skills</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($skillMap as $field => $label): ?>
                                <?php if (!empty($member[$field]) && $member[$field] > 0): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo $label; ?>:</span>
                                    <span class="badge bg-primary"><?php echo number_format($member[$field], 1); ?></span>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Detailed Information -->
                <div class="col-lg-8">

                    <!-- Experience & Skills -->
                    <?php if ($member['years_programming_experience'] > 0 || $member['years_project_management_experience'] > 0 || !empty($member['programming_languages']) || !empty($member['database_technologies']) || !empty($member['skills'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Experience & Skills</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($member['years_programming_experience'] > 0 || $member['years_project_management_experience'] > 0): ?>
                            <div class="row mb-3">
                                <?php if ($member['years_programming_experience'] > 0): ?>
                                <div class="col-md-6">
                                    <strong>Programming Experience:</strong>
                                    <div><?php echo $member['years_programming_experience']; ?> year<?php echo $member['years_programming_experience'] != 1 ? 's' : ''; ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($member['years_project_management_experience'] > 0): ?>
                                <div class="col-md-6">
                                    <strong>Project Management Experience:</strong>
                                    <div><?php echo $member['years_project_management_experience']; ?> year<?php echo $member['years_project_management_experience'] != 1 ? 's' : ''; ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($member['programming_languages'])): ?>
                            <div class="mb-3">
                                <strong>Programming Languages:</strong>
                                <div class="mt-1">
                                    <?php 
                                    $languages = explode(',', $member['programming_languages']);
                                    foreach ($languages as $lang): 
                                        $lang = trim($lang);
                                        if (!empty($lang)):
                                    ?>
                                        <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($lang); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($member['database_technologies'])): ?>
                            <div class="mb-3">
                                <strong>Database Technologies:</strong>
                                <div class="mt-1">
                                    <?php 
                                    $databases = explode(',', $member['database_technologies']);
                                    foreach ($databases as $db): 
                                        $db = trim($db);
                                        if (!empty($db)):
                                    ?>
                                        <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($db); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($member['skills'])): ?>
                            <div class="mb-3">
                                <strong>Additional Skills:</strong>
                                <div class="mt-1">
                                    <?php 
                                    $skills = explode(',', $member['skills']);
                                    foreach ($skills as $skill): 
                                        $skill = trim($skill);
                                        if (!empty($skill)):
                                    ?>
                                        <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Assigned Tasks (Only visible to project managers, admins, and the member themselves) -->
                    <?php if ($isProjectManagerOrAdmin || $memberId == $currentUserId): ?>
                    <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        Assigned Tasks
                        <?php if ($memberId == $currentUserId): ?>
                            (Your Tasks)
                        <?php endif; ?>
                    </h5>
                    <span class="badge bg-secondary"><?php echo count($memberTasks); ?> total</span>
                </div>
                <div class="card-body">
                    <?php if (empty($memberTasks)): ?>
                        <div class="text-muted text-center py-3">
                            <i class="bi bi-inbox"></i> No assigned tasks
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Due Date</th>
                                        <?php if ($isProjectManagerOrAdmin): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memberTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                            <?php if (!empty($task['description'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '...' : ''; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($task['project_name'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($task['project_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Personal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $task['status'] === 'done' ? 'success' : ($task['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($task['due_date']): ?>
                                                <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No due date</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($isProjectManagerOrAdmin): ?>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                                <input type="hidden" name="action" value="delete_task">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Task">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Availability -->
                    <?php if (!empty($member['best_meeting_times'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Availability</h5>
                        </div>
                        <div class="card-body">
                            <strong>Best Times for Meetings:</strong>
                            <div class="mt-1"><?php echo nl2br(htmlspecialchars($member['best_meeting_times'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

</main>

<!-- Edit User Modal (only for admins) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/team-member?id=<?php echo $memberId; ?>">
                <input type="hidden" name="action" value="update_user">
                <div class="modal-body">
                    <!-- Basic Information -->
                    <h6 class="mb-3">Basic Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($member['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" 
                                   value="<?php echo htmlspecialchars($member['student_id'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">URL Slug</label>
                        <input type="text" class="form-control" id="slug" name="slug" 
                               value="<?php echo htmlspecialchars($member['slug'] ?? ''); ?>" 
                               placeholder="e.g., john-doe (leave blank to auto-generate)">
                        <small class="text-muted">Custom URL for this user's profile. Will be used as: /<?php echo htmlspecialchars($member['slug'] ?? 'their-custom-url'); ?></small>
                    </div>
                    
                    <!-- Professional Information -->
                    <h6 class="mb-3 mt-4">Professional Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="github_username" class="form-label">GitHub Username</label>
                            <input type="text" class="form-control" id="github_username" name="github_username" 
                                   value="<?php echo htmlspecialchars($member['github_username'] ?? ''); ?>"
                                   placeholder="username">
                        </div>
                        <div class="col-md-6">
                            <label for="skill_level" class="form-label">Skill Level</label>
                            <select class="form-control" id="skill_level" name="skill_level">
                                <option value="">Select skill level</option>
                                <option value="beginner" <?php echo ($member['skill_level'] ?? '') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo ($member['skill_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo ($member['skill_level'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                <option value="expert" <?php echo ($member['skill_level'] ?? '') === 'expert' ? 'selected' : ''; ?>>Expert</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3" 
                                  placeholder="Tell us about yourself"><?php echo htmlspecialchars($member['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Contact & Social -->
                    <h6 class="mb-3 mt-4">Contact & Social</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($member['location'] ?? ''); ?>"
                                   placeholder="City, Country">
                        </div>
                        <div class="col-md-6">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-control" id="timezone" name="timezone">
                                <option value="">Select timezone</option>
                                <?php
                                $timezones = timezone_identifiers_list();
                                foreach ($timezones as $tz) {
                                    $selected = ($member['timezone'] ?? '') === $tz ? 'selected' : '';
                                    echo "<option value=\"$tz\" $selected>$tz</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" 
                                   value="<?php echo htmlspecialchars($member['website'] ?? ''); ?>"
                                   placeholder="https://example.com">
                        </div>
                        <div class="col-md-6">
                            <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                            <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                   value="<?php echo htmlspecialchars($member['linkedin_url'] ?? ''); ?>"
                                   placeholder="https://linkedin.com/in/username">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="twitter_handle" class="form-label">Twitter Handle</label>
                        <input type="text" class="form-control" id="twitter_handle" name="twitter_handle" 
                               value="<?php echo htmlspecialchars($member['twitter_handle'] ?? ''); ?>"
                               placeholder="@username">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>