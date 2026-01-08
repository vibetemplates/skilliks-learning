<?php
/**
 * Header Include
 * 
 * Common header for all pages
 */

// Ensure session is started
require_once __DIR__ . '/session.php';

// Get current page for navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get current community name and user's communities
$currentCommunityName = 'SkillikS'; // Default fallback
$userCommunities = [];
$communityId = null;

// Only load Community class and get community data if user is logged in
if (isLoggedIn()) {
    require_once dirname(__DIR__) . '/classes/Community.php';
    require_once dirname(__DIR__) . '/config/database.php';
    require_once dirname(__DIR__) . '/includes/messaging_functions.php';
    require_once dirname(__DIR__) . '/config/functions.php';
    
    // Update user's last activity every 2 minutes
    $userId = getCurrentUserId();
    $lastActivityUpdate = $_SESSION['last_activity_update'] ?? 0;
    $currentTime = time();
    
    if ($currentTime - $lastActivityUpdate > 120) { // Update every 2 minutes
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            $_SESSION['last_activity_update'] = $currentTime;
        } catch (PDOException $e) {
            error_log("Failed to update last activity: " . $e->getMessage());
        }
    }
    
    $communityId = getCurrentCommunityId();
    $community = new Community();
    
    // Get current community details
    if ($communityId) {
        $communityData = $community->getById($communityId);
        if ($communityData) {
            $currentCommunityName = htmlspecialchars($communityData['name']);
        }
    }
    
    // Get all user's communities
    $userCommunities = $community->getUserCommunities($_SESSION['user_id']);
    
    // Get user's plan
    $userPlan = 'all'; // default
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT plan FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();
        if ($userData && $userData['plan']) {
            $userPlan = $userData['plan'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user plan: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>SkillikS</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon.png">
    
    <!-- Consolidated CSS (Bootstrap + Custom) -->
    <link href="/assets/css/styles.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
    
    <!-- Minimal custom CSS for layout -->
    <style>
        .form-signin {
            width: 100%;
            max-width: 330px;
            padding: 15px;
            margin: auto;
        }
        .form-signin .form-floating:focus-within {
            z-index: 2;
        }
        body {
            min-height: 100vh;
        }
        
        /* Body padding for fixed navbar */
        body.bg-light {
            padding-top: 86px; /* Bootstrap navbar height + 30px extra */
        }
        
        /* Flash Messages Positioning */
        .flash-messages-container {
            position: fixed;
            top: 70px; /* Below fixed navbar */
            right: 20px;
            left: 20px; /* Full width */
            z-index: 1055; /* Above modals but below tooltips */
            pointer-events: none; /* Allow clicks through container */
            max-width: calc(100vw - 40px); /* Ensure it doesn't overflow */
        }
        
        .flash-messages-container .alert {
            pointer-events: auto; /* But allow clicks on alerts */
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Main content area - full width */
        main {
            margin-top: 0; /* Remove duplicate margin */
            padding: 0;
        }
        
        /* Main content positioning */
        .main-container {
            display: flex;
            flex-wrap: nowrap;
            min-height: calc(100vh - 56px);
            padding-top: 0;
        }
        
        .content-wrapper {
            flex: 1;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        
        /* Make the main content area scrollable but hide scrollbar */
        .content-wrapper main {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            /* Hide scrollbar for Chrome, Safari and Opera */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none;  /* Internet Explorer 10+ */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .content-wrapper main::-webkit-scrollbar {
            display: none;
        }
        
        /* Page wrapper adjustments */
        .page-wrapper {
            padding-top: 0;
        }
        
        /* For course detail page with its own wrapper */
        .course-detail-wrapper {
            padding-top: 0;
        }
        
        /* Community dropdown styling */
        .navbar-brand .dropdown-toggle::after {
            display: none;
        }
        
        .navbar-brand .dropdown-menu {
            min-width: 250px;
        }
        
        .navbar-brand .dropdown-item.active {
            background-color: var(--bs-dark);
            color: var(--bs-white);
        }
        
        .navbar-brand .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-light">

<?php if (isLoggedIn()): ?>
<!-- Navbar for logged in users -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <div class="navbar-brand d-flex align-items-center">
            <a href="/dashboard" class="text-white text-decoration-none d-flex align-items-center">
                <span>SkillikS</span>
            </a>
            <div class="dropdown ms-2">
                <button class="btn btn-sm btn-link text-white p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-chevron-down"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li>
                        <a class="dropdown-item" href="/discover-communities.php">
                            <i class="bi bi-compass"></i> Discover Communities
                        </a>
                    </li>
                    <?php if (!empty($userCommunities)): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">My Communities</h6></li>
                    <?php foreach ($userCommunities as $comm): ?>
                    <li>
                        <a class="dropdown-item community-switch <?php echo $comm['id'] == $communityId ? 'active' : ''; ?>" 
                           href="#" 
                           data-community-id="<?php echo $comm['id']; ?>">
                            <i class="bi bi-building"></i> <?php echo htmlspecialchars($comm['name']); ?>
                            <?php if ($comm['id'] == $communityId): ?>
                            <i class="bi bi-check-circle-fill ms-auto"></i>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (getCurrentCommunityId() !== null): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="/dashboard">
                        <i class="bi bi-speedometer2"></i> Community
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($current_page, ['programs', 'courses']) ? 'active' : ''; ?>" href="/programs">
                        <i class="bi bi-book"></i> Classroom
                    </a>
                </li>
                <?php if ($userPlan !== 'developer'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'recommended-courses' ? 'active' : ''; ?>" href="/recommended-courses">
                        <i class="bi bi-star"></i> Recommended
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['projects', 'project-categories', 'my-projects', 'pending-projects']) ? 'active' : ''; ?>" href="#" id="projectsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-folder"></i> Projects
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="projectsDropdown">
                        <li><a class="dropdown-item" href="/project-categories"><i class="bi bi-check-circle"></i> Active Projects</a></li>
                        <li><a class="dropdown-item" href="/my-projects"><i class="bi bi-person-circle"></i> My Projects</a></li>
                        <li><a class="dropdown-item" href="/pending-projects"><i class="bi bi-clock"></i> Pending Projects</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'calendar' ? 'active' : ''; ?>" href="/calendar">
                        <i class="bi bi-calendar"></i> Calendar
                    </a>
                </li>
                <?php if ($userPlan !== 'developer'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'members' ? 'active' : ''; ?>" href="/members">
                        <i class="bi bi-people"></i> Members
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'messages' ? 'active' : ''; ?>" href="/messages">
                        <i class="bi bi-chat"></i> Messages
                        <?php 
                        // Get unread message count
                        if (function_exists('getUnreadMessageCount') && function_exists('getCurrentUserId') && function_exists('getCurrentCommunityId')) {
                            $userId = getCurrentUserId();
                            $communityId = getCurrentCommunityId();
                            if ($userId && $communityId) {
                                $unreadCount = getUnreadMessageCount($userId, $communityId);
                                if ($unreadCount > 0) {
                                    echo '<span class="badge bg-danger ms-1">' . ($unreadCount > 99 ? '99+' : $unreadCount) . '</span>';
                                }
                            }
                        }
                        ?>
                    </a>
                </li>
                <!-- Temporarily removed leaderboard
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'leaderboard' ? 'active' : ''; ?>" href="/leaderboard.php">
                        <i class="bi bi-trophy"></i> Leaderboard
                    </a>
                </li>
                -->
                <!-- Temporarily removed knowledge
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'knowledge' ? 'active' : ''; ?>" href="/knowledge.php">
                        <i class="bi bi-chat-dots"></i> Knowledge
                    </a>
                </li>
                -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'about' ? 'active' : ''; ?>" href="/about">
                        <i class="bi bi-info-circle"></i> About
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex">
                <!-- Tickets dropdown for all users -->
                <div class="dropdown me-2">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-ticket-detailed"></i> Tickets
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/tickets/new.php"><i class="bi bi-plus-circle"></i> New Ticket</a></li>
                        <li><a class="dropdown-item" href="/tickets/open.php"><i class="bi bi-ticket-detailed"></i> Open Tickets</a></li>
                        <li><a class="dropdown-item" href="/tickets/closed.php"><i class="bi bi-ticket"></i> Closed Tickets</a></li>
                    </ul>
                </div>
                
                <?php if (isCurrentUserAdmin()): ?>
                <div class="dropdown me-2">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-lock"></i> Admin
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/admin/index.php"><i class="bi bi-speedometer2"></i> Admin Dashboard</a></li>
                        <li><a class="dropdown-item" href="/admin/users.php"><i class="bi bi-people-fill"></i> User Management</a></li>
                        <li><a class="dropdown-item" href="/admin/communities.php"><i class="bi bi-building"></i> Communities</a></li>
                        <li><a class="dropdown-item" href="/admin/courses"><i class="bi bi-mortarboard"></i> Course Admin</a></li>
                        <li><a class="dropdown-item" href="/admin/project-surveys"><i class="bi bi-clipboard-data"></i> Project Surveys</a></li>
                        <li><a class="dropdown-item" href="/admin/fetch-youtube-transcripts.php"><i class="bi bi-youtube"></i> YouTube Transcripts</a></li>
                        <li><a class="dropdown-item" href="/admin/generate-quiz.php"><i class="bi bi-question-circle"></i> Generate Quiz with AI</a></li>
                        <li><a class="dropdown-item" href="/admin/generate-skills-drill.php"><i class="bi bi-lightning"></i> Generate Skills Drill with AI</a></li>
                        <li><a class="dropdown-item" href="/admin/skills"><i class="bi bi-tools"></i> Skills Management</a></li>
                        <li><a class="dropdown-item" href="/admin/requirements-categories.php"><i class="bi bi-list-check"></i> Requirements Categories</a></li>
                        <li><a class="dropdown-item" href="/blog-categories.php"><i class="bi bi-tags"></i> Post Categories</a></li>
                    </ul>
                </div>
                <?php endif; ?>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <?php if (!empty($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true): ?>
                            <span class="badge bg-warning text-dark me-1">Impersonating</span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><a class="dropdown-item" href="/survey"><i class="bi bi-clipboard-check"></i> Take Survey</a></li>
                        <li><a class="dropdown-item" href="/tasks.php"><i class="bi bi-check2-square"></i> My Tasks</a></li>
                        <li><a class="dropdown-item" href="/kanban.php"><i class="bi bi-kanban"></i> Kanban Board</a></li>
                        <li><a class="dropdown-item" href="/api-keys.php"><i class="bi bi-key"></i> API Keys</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (!empty($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true): ?>
                        <li><a class="dropdown-item text-warning" href="/admin/end-impersonation.php"><i class="bi bi-arrow-return-left"></i> End Impersonation</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Impersonation Warning -->
<?php if (isLoggedIn() && isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating']): ?>
<div class="alert alert-warning d-flex align-items-center justify-content-between m-0 rounded-0 border-0" 
     style="position: fixed; top: 56px; left: 0; right: 0; z-index: 1040; padding: 10px 20px;">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Impersonation Mode:</strong>&nbsp;You are currently viewing the site as another user. 
        To return to your admin account, please logout.
    </div>
    <a href="/logout.php" class="btn btn-sm btn-outline-dark">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>
<style>
    /* Adjust body padding when impersonation banner is shown */
    body.impersonating {
        padding-top: 102px !important; /* 56px navbar + 46px impersonation banner */
    }
</style>
<script>
    document.body.classList.add('impersonating');
</script>
<?php endif; ?>

<!-- Flash Messages -->
<?php if (isLoggedIn()): ?>
<div class="flash-messages-container">
    <?php if ($success = getFlashMessage('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error = getFlashMessage('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($info = getFlashMessage('info')): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($info); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($warning = getFlashMessage('warning')): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($warning); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
    <!-- Flash Messages for non-logged in users -->
    <?php if ($success = getFlashMessage('success')): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error = getFlashMessage('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($info = getFlashMessage('info')): ?>
    <div class="alert alert-info alert-dismissible fade show m-3" role="alert">
        <?php echo htmlspecialchars($info); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($warning = getFlashMessage('warning')): ?>
    <div class="alert alert-warning alert-dismissible fade show m-3" role="alert">
        <?php echo htmlspecialchars($warning); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// Auto-dismiss alerts after 5 seconds (except errors and welcome banners)
document.addEventListener('DOMContentLoaded', function() {
    // Don't auto-dismiss error alerts, welcome banners, or system status banners
    const alerts = document.querySelectorAll('.alert:not(.alert-danger):not(.welcome-banner):not(.system-status-banner)'); 
    
    alerts.forEach(function(alert) {
        // Only auto-dismiss flash messages, not permanent UI elements
        if (alert.closest('.flash-messages-container') || alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
            // Skip welcome banners and system status banners even if they have alert-success or alert-info
            if (!alert.classList.contains('welcome-banner') && !alert.classList.contains('system-status-banner') && !alert.classList.contains('registration-requirement')) {
                // Set timer to auto-dismiss after 5 seconds
                setTimeout(function() {
                    // Use Bootstrap's alert instance to dismiss
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }, 5000);
            }
        }
    });
});

// Community switcher
document.addEventListener('DOMContentLoaded', function() {
    const communitySwitchLinks = document.querySelectorAll('.community-switch');
    
    communitySwitchLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const communityId = this.getAttribute('data-community-id');
            
            // Make AJAX request to switch community
            fetch('/api/switch-community.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ community_id: communityId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to reflect the new community
                    window.location.reload();
                } else {
                    alert('Failed to switch community: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to switch community');
            });
        });
    });
});
</script>