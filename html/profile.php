<?php
/**
 * Profile Edit Page
 * 
 * Allows users to edit their profile information
 */

$page_title = 'My Profile';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$userObj = new User();
$currentUserId = getCurrentUserId();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $profileData = [
        'skill_level' => $_POST['skill_level'] ?? 'beginner',
        'years_programming_experience' => (int)($_POST['years_programming_experience'] ?? 0),
        'years_project_management_experience' => (int)($_POST['years_project_management_experience'] ?? 0),
        'programming_languages' => trim($_POST['programming_languages'] ?? ''),
        'database_technologies' => trim($_POST['database_technologies'] ?? ''),
        'timezone' => trim($_POST['timezone'] ?? ''),
        'best_meeting_times' => trim($_POST['best_meeting_times'] ?? ''),
        'ai_assisted_coding_current' => (float)($_POST['ai_assisted_coding_current'] ?? 0.0),
        'ai_assisted_coding_goal' => (float)($_POST['ai_assisted_coding_goal'] ?? 0.0),
        'mcp_servers_current' => (float)($_POST['mcp_servers_current'] ?? 0.0),
        'mcp_servers_goal' => (float)($_POST['mcp_servers_goal'] ?? 0.0),
        'ai_automations_current' => (float)($_POST['ai_automations_current'] ?? 0.0),
        'ai_automations_goal' => (float)($_POST['ai_automations_goal'] ?? 0.0),
        'startup_operations_current' => (float)($_POST['startup_operations_current'] ?? 0.0),
        'startup_operations_goal' => (float)($_POST['startup_operations_goal'] ?? 0.0),
        'ai_security_current' => (float)($_POST['ai_security_current'] ?? 0.0),
        'ai_security_goal' => (float)($_POST['ai_security_goal'] ?? 0.0),
        'ai_infrastructure_current' => (float)($_POST['ai_infrastructure_current'] ?? 0.0),
        'ai_infrastructure_goal' => (float)($_POST['ai_infrastructure_goal'] ?? 0.0),
        'rag_current' => (float)($_POST['rag_current'] ?? 0.0),
        'rag_goal' => (float)($_POST['rag_goal'] ?? 0.0),
        'local_models_current' => (float)($_POST['local_models_current'] ?? 0.0),
        'local_models_goal' => (float)($_POST['local_models_goal'] ?? 0.0),
        'supervised_fine_tuning_current' => (float)($_POST['supervised_fine_tuning_current'] ?? 0.0),
        'supervised_fine_tuning_goal' => (float)($_POST['supervised_fine_tuning_goal'] ?? 0.0),
        'bio' => trim($_POST['bio'] ?? ''),
        'skills' => trim($_POST['skills'] ?? ''),
        'location_address' => trim($_POST['location_address'] ?? ''),
        'location_city' => trim($_POST['location_city'] ?? ''),
        'location_state' => trim($_POST['location_state'] ?? ''),
        'location_country' => trim($_POST['location_country'] ?? ''),
        'location_privacy' => $_POST['location_privacy'] ?? 'community'
    ];
    
    $result = $userObj->updateProfile($currentUserId, $profileData);
    if ($result['success']) {
        setFlashMessage('success', 'Profile updated successfully!');
    } else {
        setFlashMessage('error', $result['error']);
    }
    
    header('Location: /profile.php');
    exit;
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    // Debug logging
    error_log("Avatar upload attempt - POST data: " . print_r($_POST, true));
    error_log("Avatar upload attempt - FILES data: " . print_r($_FILES, true));
    
    if (isset($_FILES['avatar_upload']) && $_FILES['avatar_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        $result = $userObj->uploadAvatar($currentUserId, $_FILES['avatar_upload']);
        if ($result['success']) {
            setFlashMessage('success', 'Profile photo uploaded successfully!');
        } else {
            setFlashMessage('error', $result['error']);
        }
    } else {
        if (isset($_FILES['avatar_upload'])) {
            $errorMsg = 'File upload error: ';
            switch ($_FILES['avatar_upload']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $errorMsg .= 'File too large (exceeds upload_max_filesize)';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg .= 'File too large (exceeds MAX_FILE_SIZE)';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg .= 'File was only partially uploaded';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg .= 'No file was uploaded';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMsg .= 'Missing temporary folder';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMsg .= 'Failed to write file to disk';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMsg .= 'Upload stopped by extension';
                    break;
                default:
                    $errorMsg .= 'Unknown error';
            }
            setFlashMessage('error', $errorMsg);
        } else {
            setFlashMessage('error', 'No file data received.');
        }
    }
    
    header('Location: /profile.php');
    exit;
}

// Handle avatar removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_avatar') {
    $result = $userObj->removeAvatar($currentUserId);
    if ($result['success']) {
        setFlashMessage('success', 'Profile photo removed successfully!');
    } else {
        setFlashMessage('error', $result['error']);
    }
    
    header('Location: /profile.php');
    exit;
}

// Get current user profile
$userProfile = $userObj->findById($currentUserId);

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4 py-3">
            <div id="profile-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Profile</h1>
            </div>


            <?php if (!$userProfile): ?>
                <div id="profile-not-found-alert" class="alert alert-danger fade-in">
                    <i class="bi bi-exclamation-triangle"></i>
                    Profile not found.
                </div>
            <?php else: ?>
                    <!-- Basic Information -->
                    <div id="basic-info-card" class="card mb-4">
                        <div id="basic-info-header" class="card-header">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div id="basic-info-body" class="card-body">
                            <div id="basic-info-row1" class="row">
                                <div id="name-column" class="col-md-6">
                                    <div id="name-field" class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($userProfile['first_name'] . ' ' . $userProfile['last_name']); ?>" disabled>
                                        <small class="text-muted">Contact admin to change your name</small>
                                    </div>
                                </div>
                                <div id="email-column" class="col-md-6">
                                    <div id="email-field" class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($userProfile['email']); ?>" disabled>
                                        <small class="text-muted">Contact admin to change your email</small>
                                    </div>
                                </div>
                            </div>
                            <div id="basic-info-row2" class="row">
                                <div id="student-id-column" class="col-md-6">
                                    <div id="student-id-field" class="mb-3">
                                        <label for="student_id" class="form-label">Student ID</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($userProfile['student_id'] ?? ''); ?>" disabled>
                                        <small class="text-muted">Contact admin to change your student ID</small>
                                    </div>
                                </div>
                                <div id="github-username-column" class="col-md-6">
                                    <div id="github-username-field" class="mb-3">
                                        <label for="github_username" class="form-label">Git Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($userProfile['github_username'] ?? ''); ?>" disabled>
                                        <small class="text-muted">Contact admin to change your Git username</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Photo -->
                    <div id="profile-photo-card" class="card mb-4">
                        <div id="profile-photo-header" class="card-header">
                            <h5 class="mb-0">Profile Photo</h5>
                        </div>
                        <div id="profile-photo-body" class="card-body">
                            <?php include 'includes/avatar-upload-component.php'; ?>
                        </div>
                    </div>

                <form method="POST" action="/profile.php">
                    <input type="hidden" name="action" value="update_profile">

                    <!-- Skills & Experience -->
                    <div id="skills-experience-card" class="card mb-4">
                        <div id="skills-experience-header" class="card-header">
                            <h5 class="mb-0">Skills & Experience</h5>
                        </div>
                        <div id="skills-experience-body" class="card-body">
                            <div id="skills-row1" class="row">
                                <div id="skill-level-column" class="col-md-4">
                                    <div id="skill-level-field" class="mb-3">
                                        <label for="skill_level" class="form-label">Overall Skill Level</label>
                                        <select class="form-select" id="skill_level" name="skill_level">
                                            <option value="beginner" <?php echo ($userProfile['skill_level'] ?? 'beginner') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                            <option value="intermediate" <?php echo ($userProfile['skill_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                            <option value="advanced" <?php echo ($userProfile['skill_level'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                            <option value="expert" <?php echo ($userProfile['skill_level'] ?? '') === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="programming-exp-column" class="col-md-4">
                                    <div id="programming-exp-field" class="mb-3">
                                        <label for="years_programming_experience" class="form-label">Years Programming Experience</label>
                                        <input type="number" class="form-control" id="years_programming_experience" name="years_programming_experience" 
                                               value="<?php echo htmlspecialchars($userProfile['years_programming_experience'] ?? 0); ?>" min="0" max="50">
                                    </div>
                                </div>
                                <div id="project-mgmt-exp-column" class="col-md-4">
                                    <div id="project-mgmt-exp-field" class="mb-3">
                                        <label for="years_project_management_experience" class="form-label">Years Project Management Experience</label>
                                        <input type="number" class="form-control" id="years_project_management_experience" name="years_project_management_experience" 
                                               value="<?php echo htmlspecialchars($userProfile['years_project_management_experience'] ?? 0); ?>" min="0" max="50">
                                    </div>
                                </div>
                            </div>
                            <div id="skills-row2" class="row">
                                <div id="programming-langs-column" class="col-md-6">
                                    <div id="programming-langs-field" class="mb-3">
                                        <label for="programming_languages" class="form-label">Programming Languages</label>
                                        <textarea class="form-control" id="programming_languages" name="programming_languages" rows="3" 
                                                  placeholder="e.g., JavaScript, Python, Java, C++, PHP"><?php echo htmlspecialchars($userProfile['programming_languages'] ?? ''); ?></textarea>
                                        <small class="text-muted">Enter languages separated by commas</small>
                                    </div>
                                </div>
                                <div id="database-tech-column" class="col-md-6">
                                    <div id="database-tech-field" class="mb-3">
                                        <label for="database_technologies" class="form-label">Database Technologies</label>
                                        <textarea class="form-control" id="database_technologies" name="database_technologies" rows="3" 
                                                  placeholder="e.g., MySQL, PostgreSQL, MongoDB, Redis"><?php echo htmlspecialchars($userProfile['database_technologies'] ?? ''); ?></textarea>
                                        <small class="text-muted">Enter databases separated by commas</small>
                                    </div>
                                </div>
                            </div>
                            <div id="additional-skills-field" class="mb-3">
                                <label for="skills" class="form-label">Additional Skills</label>
                                <textarea class="form-control" id="skills" name="skills" rows="2" 
                                          placeholder="e.g., React, Docker, AWS, Git"><?php echo htmlspecialchars($userProfile['skills'] ?? ''); ?></textarea>
                                <small class="text-muted">Enter other technical skills separated by commas</small>
                            </div>
                        </div>
                    </div>

                    <!-- Availability & Location -->
                    <div id="availability-location-card" class="card mb-4">
                        <div id="availability-location-header" class="card-header">
                            <h5 class="mb-0">Availability & Location</h5>
                        </div>
                        <div id="availability-location-body" class="card-body">
                            <div id="availability-row1" class="row">
                                <div id="timezone-column" class="col-md-6">
                                    <div id="timezone-field" class="mb-3">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <option value="">Select your timezone...</option>
                                            <?php 
                                            $timezones = [
                                                'America/New_York' => 'Eastern Time (US & Canada)',
                                                'America/Chicago' => 'Central Time (US & Canada)', 
                                                'America/Denver' => 'Mountain Time (US & Canada)',
                                                'America/Los_Angeles' => 'Pacific Time (US & Canada)',
                                                'America/Anchorage' => 'Alaska Time',
                                                'Pacific/Honolulu' => 'Hawaii Time',
                                                'America/Toronto' => 'Eastern Time (Canada)',
                                                'America/Vancouver' => 'Pacific Time (Canada)',
                                                'Europe/London' => 'London (GMT/BST)',
                                                'Europe/Berlin' => 'Central European Time',
                                                'Europe/Paris' => 'Central European Time (Paris)',
                                                'Europe/Rome' => 'Central European Time (Rome)',
                                                'Europe/Madrid' => 'Central European Time (Madrid)',
                                                'Europe/Amsterdam' => 'Central European Time (Amsterdam)',
                                                'Europe/Stockholm' => 'Central European Time (Stockholm)',
                                                'Europe/Warsaw' => 'Central European Time (Warsaw)',
                                                'Europe/Moscow' => 'Moscow Time',
                                                'Asia/Tokyo' => 'Japan Standard Time',
                                                'Asia/Seoul' => 'Korea Standard Time',
                                                'Asia/Shanghai' => 'China Standard Time',
                                                'Asia/Hong_Kong' => 'Hong Kong Time',
                                                'Asia/Singapore' => 'Singapore Time',
                                                'Asia/Bangkok' => 'Indochina Time',
                                                'Asia/Kolkata' => 'India Standard Time',
                                                'Asia/Dubai' => 'Gulf Standard Time',
                                                'Australia/Sydney' => 'Australian Eastern Time',
                                                'Australia/Melbourne' => 'Australian Eastern Time (Melbourne)',
                                                'Australia/Brisbane' => 'Australian Eastern Time (Brisbane)',
                                                'Australia/Perth' => 'Australian Western Time',
                                                'Pacific/Auckland' => 'New Zealand Time',
                                                'America/Sao_Paulo' => 'Brazil Time',
                                                'America/Argentina/Buenos_Aires' => 'Argentina Time',
                                                'America/Mexico_City' => 'Central Time (Mexico)',
                                                'Africa/Cairo' => 'Eastern European Time',
                                                'Africa/Johannesburg' => 'South Africa Standard Time',
                                                'UTC' => 'Coordinated Universal Time (UTC)'
                                            ];
                                            
                                            $currentTimezone = $userProfile['timezone'] ?? '';
                                            foreach ($timezones as $value => $label): 
                                            ?>
                                                <option value="<?php echo htmlspecialchars($value); ?>" 
                                                        <?php echo $currentTimezone === $value ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div id="meeting-times-column" class="col-md-6">
                                    <div id="meeting-times-field" class="mb-3">
                                        <label for="best_meeting_times" class="form-label">Best Times for Meetings</label>
                                        <textarea class="form-control" id="best_meeting_times" name="best_meeting_times" rows="3" 
                                                  placeholder="e.g., Weekdays 9-11 AM EST, Tuesday/Thursday evenings"><?php echo htmlspecialchars($userProfile['best_meeting_times'] ?? ''); ?></textarea>
                                        <small class="text-muted">When are you typically available for team meetings?</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Location Information -->
                            <div id="location-row" class="row mt-4">
                                <div id="location-title-column" class="col-12">
                                    <h6 class="mb-3">Location Information</h6>
                                </div>
                                <div id="address-column" class="col-md-12">
                                    <div id="address-field" class="mb-3">
                                        <label for="location_address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="location_address" name="location_address" 
                                               value="<?php echo htmlspecialchars($userProfile['location_address'] ?? ''); ?>"
                                               placeholder="Enter your address (e.g., 123 Main St, City, State)">
                                        <small class="text-muted">This will be used to show your location on the community map</small>
                                    </div>
                                </div>
                                <div id="city-column" class="col-md-4">
                                    <div id="city-field" class="mb-3">
                                        <label for="location_city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="location_city" name="location_city" 
                                               value="<?php echo htmlspecialchars($userProfile['location_city'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div id="state-column" class="col-md-4">
                                    <div id="state-field" class="mb-3">
                                        <label for="location_state" class="form-label">State/Province</label>
                                        <input type="text" class="form-control" id="location_state" name="location_state" 
                                               value="<?php echo htmlspecialchars($userProfile['location_state'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div id="country-column" class="col-md-4">
                                    <div id="country-field" class="mb-3">
                                        <label for="location_country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="location_country" name="location_country" 
                                               value="<?php echo htmlspecialchars($userProfile['location_country'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div id="location-privacy-column" class="col-md-12">
                                    <div id="location-privacy-field" class="mb-3">
                                        <label for="location_privacy" class="form-label">Location Privacy</label>
                                        <select class="form-select" id="location_privacy" name="location_privacy">
                                            <option value="community" <?php echo ($userProfile['location_privacy'] ?? 'community') === 'community' ? 'selected' : ''; ?>>
                                                Community Members Only
                                            </option>
                                            <option value="public" <?php echo ($userProfile['location_privacy'] ?? '') === 'public' ? 'selected' : ''; ?>>
                                                Public (Anyone can see)
                                            </option>
                                            <option value="private" <?php echo ($userProfile['location_privacy'] ?? '') === 'private' ? 'selected' : ''; ?>>
                                                Private (Hidden from map)
                                            </option>
                                        </select>
                                        <small class="text-muted">Control who can see your location on the map</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Technology Skills Matrix -->
                    <div id="skills-matrix-card" class="card mb-4">
                        <div id="skills-matrix-header" class="card-header">
                            <h5 class="mb-0">Technology Skills Matrix</h5>
                            <small class="text-muted">Rate your current skill level and goal skill level (0.0 = No Experience, 1.0 = Expert Level)</small>
                        </div>
                        <div id="skills-matrix-body" class="card-body">
                            <div id="skills-matrix-table-wrapper" class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;">Technology</th>
                                            <th style="width: 30%;">Current Skill Level</th>
                                            <th style="width: 30%;">Goal Skill Level</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>AI Assisted Coding</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_assisted_coding_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_assisted_coding_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_assisted_coding_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_assisted_coding_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>MCP Servers</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="mcp_servers_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['mcp_servers_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="mcp_servers_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['mcp_servers_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>AI Automations</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_automations_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_automations_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_automations_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_automations_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Startup Operations</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="startup_operations_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['startup_operations_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="startup_operations_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['startup_operations_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>AI Security</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_security_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_security_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_security_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_security_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>AI Infrastructure</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_infrastructure_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_infrastructure_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="ai_infrastructure_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['ai_infrastructure_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Retrieval Augmented Generation</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="rag_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['rag_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="rag_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['rag_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Local Models</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="local_models_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['local_models_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="local_models_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['local_models_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Supervised Fine Tuning</strong></td>
                                            <td>
                                                <input type="number" class="form-control" name="supervised_fine_tuning_current" 
                                                       value="<?php echo htmlspecialchars($userProfile['supervised_fine_tuning_current'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="supervised_fine_tuning_goal" 
                                                       value="<?php echo htmlspecialchars($userProfile['supervised_fine_tuning_goal'] ?? 0.0); ?>" 
                                                       min="0" max="1" step="0.1">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Bio -->
                    <div id="bio-card" class="card mb-4">
                        <div id="bio-header" class="card-header">
                            <h5 class="mb-0">Bio</h5>
                        </div>
                        <div id="bio-body" class="card-body">
                            <div id="bio-field" class="mb-3">
                                <label for="bio" class="form-label">About Me</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" 
                                          placeholder="Tell others about yourself, your background, and what motivates you"><?php echo htmlspecialchars($userProfile['bio'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div id="form-actions" class="mb-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Profile
                        </button>
                        <a href="/team-members.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Team Members
                        </a>
                    </div>
                </form>
            <?php endif; ?>

</main>

<?php require_once 'includes/footer.php'; ?>