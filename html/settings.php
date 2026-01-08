<?php
/**
 * Settings Page
 * 
 * User account settings, preferences, and configuration
 */

$page_title = 'Settings';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$userObj = new User();
$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Handle different setting updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_account':
            $accountData = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'student_id' => trim($_POST['student_id'] ?? ''),
                'github_username' => trim($_POST['github_username'] ?? '')
            ];
            
            if (empty($accountData['first_name']) || empty($accountData['last_name']) || empty($accountData['email'])) {
                setFlashMessage('error', 'First name, last name, and email are required.');
            } else {
                $result = $userObj->updateAccount($currentUserId, $accountData);
                if ($result['success']) {
                    setFlashMessage('success', 'Account information updated successfully!');
                } else {
                    setFlashMessage('error', $result['error']);
                }
            }
            break;
            
        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                setFlashMessage('error', 'All password fields are required.');
            } elseif ($newPassword !== $confirmPassword) {
                setFlashMessage('error', 'New passwords do not match.');
            } elseif (strlen($newPassword) < 8) {
                setFlashMessage('error', 'New password must be at least 8 characters long.');
            } else {
                $result = $userObj->changePassword($currentUserId, $currentPassword, $newPassword);
                if ($result['success']) {
                    setFlashMessage('success', 'Password changed successfully!');
                } else {
                    setFlashMessage('error', $result['error']);
                }
            }
            break;
            
        case 'update_notifications':
            $notificationData = [
                'email_task_assigned' => isset($_POST['email_task_assigned']) ? 1 : 0,
                'email_task_completed' => isset($_POST['email_task_completed']) ? 1 : 0,
                'email_project_updates' => isset($_POST['email_project_updates']) ? 1 : 0,
                'email_feature_promoted' => isset($_POST['email_feature_promoted']) ? 1 : 0,
                'email_weekly_digest' => isset($_POST['email_weekly_digest']) ? 1 : 0,
                'browser_notifications' => isset($_POST['browser_notifications']) ? 1 : 0
            ];
            
            $result = $userObj->updateNotificationPreferences($currentUserId, $notificationData);
            if ($result['success']) {
                setFlashMessage('success', 'Notification preferences updated successfully!');
            } else {
                setFlashMessage('error', $result['error']);
            }
            break;
            
        case 'update_privacy':
            $privacyData = [
                'profile_public' => isset($_POST['profile_public']) ? 1 : 0,
                'show_email' => isset($_POST['show_email']) ? 1 : 0,
                'show_github' => isset($_POST['show_github']) ? 1 : 0,
                'show_skills' => isset($_POST['show_skills']) ? 1 : 0,
                'allow_direct_messages' => isset($_POST['allow_direct_messages']) ? 1 : 0
            ];
            
            $result = $userObj->updatePrivacySettings($currentUserId, $privacyData);
            if ($result['success']) {
                setFlashMessage('success', 'Privacy settings updated successfully!');
            } else {
                setFlashMessage('error', $result['error']);
            }
            break;
            
        case 'update_display':
            $displayData = [
                'theme_preference' => $_POST['theme_preference'] ?? 'auto',
                'items_per_page' => (int)($_POST['items_per_page'] ?? 20),
                'default_task_view' => $_POST['default_task_view'] ?? 'list',
                'show_completed_tasks' => isset($_POST['show_completed_tasks']) ? 1 : 0,
                'compact_mode' => isset($_POST['compact_mode']) ? 1 : 0
            ];
            
            $result = $userObj->updateDisplayPreferences($currentUserId, $displayData);
            if ($result['success']) {
                setFlashMessage('success', 'Display preferences updated successfully!');
            } else {
                setFlashMessage('error', $result['error']);
            }
            break;
            
        case 'update_plan':
            $plan = $_POST['plan'] ?? 'all';
            $result = $userObj->updatePlan($currentUserId, $plan);
            if ($result['success']) {
                setFlashMessage('success', 'Membership plan updated successfully!');
            } else {
                setFlashMessage('error', $result['error']);
            }
            break;
    }
    
    header('Location: /settings.php');
    exit;
}

// Get current user data and settings
$userProfile = $userObj->findById($currentUserId);
$notificationSettings = $userObj->getNotificationPreferences($currentUserId);
$privacySettings = $userObj->getPrivacySettings($currentUserId);
$displaySettings = $userObj->getDisplayPreferences($currentUserId);

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4 py-3">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Settings</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/profile.php" class="btn btn-outline-secondary">
                        <i class="bi bi-person-circle"></i> Edit Profile
                    </a>
                </div>
            </div>


            <!-- Settings Tabs -->
            <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                        <i class="bi bi-person-gear"></i> Account
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="bi bi-shield-lock"></i> Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                        <i class="bi bi-bell"></i> Notifications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                        <i class="bi bi-eye-slash"></i> Privacy
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="display-tab" data-bs-toggle="tab" data-bs-target="#display" type="button" role="tab">
                        <i class="bi bi-palette"></i> Display
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="plan-tab" data-bs-toggle="tab" data-bs-target="#plan" type="button" role="tab">
                        <i class="bi bi-card-checklist"></i> Membership Plan
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="settingsTabContent">
                <!-- Account Settings Tab -->
                <div class="tab-pane fade show active" id="account" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Account Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/settings.php">
                                <input type="hidden" name="action" value="update_account">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo htmlspecialchars($userProfile['first_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo htmlspecialchars($userProfile['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($userProfile['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="student_id" class="form-label">Student ID</label>
                                            <input type="text" class="form-control" id="student_id" name="student_id" 
                                                   value="<?php echo htmlspecialchars($userProfile['student_id'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="github_username" class="form-label">GitHub Username</label>
                                            <input type="text" class="form-control" id="github_username" name="github_username" 
                                                   value="<?php echo htmlspecialchars($userProfile['github_username'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="text-muted small">
                                        <strong>Account Created:</strong> <?php echo date('F j, Y g:i A', strtotime($userProfile['created_at'])); ?>
                                    </div>
                                    <?php 
                                    // Get user's role in current community
                                    $userRole = 'member'; // default
                                    if ($currentCommunityId) {
                                        try {
                                            $pdo = getDB();
                                            $stmt = $pdo->prepare("SELECT role FROM community_members WHERE user_id = ? AND community_id = ? AND is_active = 1");
                                            $stmt->execute([$currentUserId, $currentCommunityId]);
                                            $membership = $stmt->fetch();
                                            if ($membership) {
                                                $userRole = $membership['role'];
                                            }
                                        } catch (PDOException $e) {
                                            // Keep default role
                                        }
                                    }
                                    ?>
                                    <div class="text-muted small">
                                        <strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $userRole)); ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update Account
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/settings.php">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    <small class="text-muted">Password must be at least 8 characters long</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-shield-check"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Security Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-muted">
                                <p><strong>Last Login:</strong> <?php echo date('F j, Y g:i A', strtotime($userProfile['last_login'] ?? $userProfile['created_at'])); ?></p>
                                <p><strong>Password Security Tips:</strong></p>
                                <ul class="small">
                                    <li>Use a unique password for this account</li>
                                    <li>Include uppercase and lowercase letters, numbers, and symbols</li>
                                    <li>Avoid using personal information</li>
                                    <li>Consider using a password manager</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications Tab -->
                <div class="tab-pane fade" id="notifications" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Email Notifications</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/settings.php">
                                <input type="hidden" name="action" value="update_notifications">
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_task_assigned" name="email_task_assigned" 
                                               <?php echo ($notificationSettings['email_task_assigned'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_task_assigned">
                                            <strong>Task Assignments</strong><br>
                                            <small class="text-muted">Receive email when tasks are assigned to you</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_task_completed" name="email_task_completed" 
                                               <?php echo ($notificationSettings['email_task_completed'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_task_completed">
                                            <strong>Task Completions</strong><br>
                                            <small class="text-muted">Receive email when tasks in your projects are completed</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_project_updates" name="email_project_updates" 
                                               <?php echo ($notificationSettings['email_project_updates'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_project_updates">
                                            <strong>Project Updates</strong><br>
                                            <small class="text-muted">Receive email for important project announcements</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_feature_promoted" name="email_feature_promoted" 
                                               <?php echo ($notificationSettings['email_feature_promoted'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_feature_promoted">
                                            <strong>Feature Promotions</strong><br>
                                            <small class="text-muted">Receive email when your feature suggestions are promoted to tasks</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_weekly_digest" name="email_weekly_digest" 
                                               <?php echo ($notificationSettings['email_weekly_digest'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_weekly_digest">
                                            <strong>Weekly Digest</strong><br>
                                            <small class="text-muted">Receive weekly summary of your projects and tasks</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="browser_notifications" name="browser_notifications" 
                                               <?php echo ($notificationSettings['browser_notifications'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="browser_notifications">
                                            <strong>Browser Notifications</strong><br>
                                            <small class="text-muted">Show desktop notifications for important updates (requires permission)</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-bell-fill"></i> Update Notifications
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Privacy Tab -->
                <div class="tab-pane fade" id="privacy" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Privacy</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/settings.php">
                                <input type="hidden" name="action" value="update_privacy">
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="profile_public" name="profile_public" 
                                               <?php echo ($privacySettings['profile_public'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="profile_public">
                                            <strong>Public Profile</strong><br>
                                            <small class="text-muted">Allow other team members to view your full profile</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="show_email" name="show_email" 
                                               <?php echo ($privacySettings['show_email'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_email">
                                            <strong>Show Email Address</strong><br>
                                            <small class="text-muted">Display your email address on your public profile</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="show_github" name="show_github" 
                                               <?php echo ($privacySettings['show_github'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_github">
                                            <strong>Show GitHub Profile</strong><br>
                                            <small class="text-muted">Display link to your GitHub profile</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="show_skills" name="show_skills" 
                                               <?php echo ($privacySettings['show_skills'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_skills">
                                            <strong>Show Skills & Experience</strong><br>
                                            <small class="text-muted">Display your technical skills and experience levels</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="allow_direct_messages" name="allow_direct_messages" 
                                               <?php echo ($privacySettings['allow_direct_messages'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_direct_messages">
                                            <strong>Allow Direct Messages</strong><br>
                                            <small class="text-muted">Allow team members to send you direct messages</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-eye-slash-fill"></i> Update Privacy
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Display Tab -->
                <div class="tab-pane fade" id="display" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Display Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/settings.php">
                                <input type="hidden" name="action" value="update_display">
                                
                                <div class="mb-3">
                                    <label for="theme_preference" class="form-label">Theme</label>
                                    <select class="form-select" id="theme_preference" name="theme_preference">
                                        <option value="auto" <?php echo ($displaySettings['theme_preference'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Auto (Follow System)</option>
                                        <option value="light" <?php echo ($displaySettings['theme_preference'] ?? '') === 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo ($displaySettings['theme_preference'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    </select>
                                    <small class="text-muted">Choose your preferred color scheme</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="items_per_page" class="form-label">Items Per Page</label>
                                    <select class="form-select" id="items_per_page" name="items_per_page">
                                        <option value="10" <?php echo ($displaySettings['items_per_page'] ?? 20) == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="20" <?php echo ($displaySettings['items_per_page'] ?? 20) == 20 ? 'selected' : ''; ?>>20</option>
                                        <option value="50" <?php echo ($displaySettings['items_per_page'] ?? 20) == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo ($displaySettings['items_per_page'] ?? 20) == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                    <small class="text-muted">Number of items to show per page in lists</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="default_task_view" class="form-label">Default Task View</label>
                                    <select class="form-select" id="default_task_view" name="default_task_view">
                                        <option value="list" <?php echo ($displaySettings['default_task_view'] ?? 'list') === 'list' ? 'selected' : ''; ?>>List View</option>
                                        <option value="kanban" <?php echo ($displaySettings['default_task_view'] ?? '') === 'kanban' ? 'selected' : ''; ?>>Kanban Board</option>
                                        <option value="grid" <?php echo ($displaySettings['default_task_view'] ?? '') === 'grid' ? 'selected' : ''; ?>>Grid View</option>
                                    </select>
                                    <small class="text-muted">Your preferred view when opening the tasks page</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="show_completed_tasks" name="show_completed_tasks" 
                                               <?php echo ($displaySettings['show_completed_tasks'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_completed_tasks">
                                            <strong>Show Completed Tasks</strong><br>
                                            <small class="text-muted">Include completed tasks in task lists by default</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="compact_mode" name="compact_mode" 
                                               <?php echo ($displaySettings['compact_mode'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="compact_mode">
                                            <strong>Compact Mode</strong><br>
                                            <small class="text-muted">Use smaller spacing and condensed layouts</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-palette-fill"></i> Update Display
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Membership Plan Tab -->
                <div class="tab-pane fade" id="plan" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Membership Plan</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/settings.php">
                                <input type="hidden" name="action" value="update_plan">
                                
                                <div class="mb-3">
                                    <label for="plan" class="form-label">Select Your Membership Plan</label>
                                    <select class="form-select" id="plan" name="plan">
                                        <option value="all" <?php echo ($userProfile['plan'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All (Full Access)</option>
                                        <option value="developer" <?php echo ($userProfile['plan'] ?? '') === 'developer' ? 'selected' : ''; ?>>Developer</option>
                                        <option value="learner" <?php echo ($userProfile['plan'] ?? '') === 'learner' ? 'selected' : ''; ?>>Learner</option>
                                        <option value="manager" <?php echo ($userProfile['plan'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                    </select>
                                    <small class="text-muted">Choose the plan that best fits your role and needs</small>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Plan Descriptions:</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <strong>All:</strong> Full access to all features and content across all roles
                                        </li>
                                        <li class="mb-2">
                                            <strong>Developer:</strong> Focus on coding, development tools, and technical resources
                                        </li>
                                        <li class="mb-2">
                                            <strong>Learner:</strong> Access to learning materials, tutorials, and educational content
                                        </li>
                                        <li class="mb-2">
                                            <strong>Manager:</strong> Project management tools, team oversight, and administrative features
                                        </li>
                                    </ul>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update Plan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

</main>

<script>
// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    if (newPassword && confirmPassword) {
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
    
    // Browser notification permission request
    const browserNotificationCheckbox = document.getElementById('browser_notifications');
    if (browserNotificationCheckbox) {
        browserNotificationCheckbox.addEventListener('change', function() {
            if (this.checked && 'Notification' in window) {
                if (Notification.permission === 'default') {
                    Notification.requestPermission().then(function(permission) {
                        if (permission !== 'granted') {
                            browserNotificationCheckbox.checked = false;
                            alert('Browser notifications were denied. Please enable them in your browser settings if you want to receive notifications.');
                        }
                    });
                } else if (Notification.permission === 'denied') {
                    this.checked = false;
                    alert('Browser notifications are blocked. Please enable them in your browser settings.');
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>