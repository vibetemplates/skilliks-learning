<?php
/**
 * Sidebar Navigation Component
 * 
 * Reusable sidebar navigation that includes admin menu for admin users
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Check if user is admin
$isAdmin = getCurrentUserRole() === 'admin';
?>

<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard' && $current_dir !== 'admin' ? 'active' : ''; ?>" href="/dashboard">
                    <i class="bi bi-speedometer2"></i> Community
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'courses' && $current_dir !== 'admin' ? 'active' : ''; ?>" href="/courses">
                    <i class="bi bi-book"></i> Classroom
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'projects' && $current_dir !== 'admin' ? 'active' : ''; ?>" href="/projects.php">
                    <i class="bi bi-folder"></i> Projects
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'calendar' ? 'active' : ''; ?>" href="/calendar">
                    <i class="bi bi-calendar"></i> Calendar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'members' ? 'active' : ''; ?>" href="/members">
                    <i class="bi bi-people"></i> Members
                </a>
            </li>
            <!-- Temporarily removed leaderboard
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'leaderboard' ? 'active' : ''; ?>" href="/leaderboard.php">
                    <i class="bi bi-trophy"></i> Leaderboard
                </a>
            </li>
            -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'map' ? 'active' : ''; ?>" href="/map.php">
                    <i class="bi bi-geo-alt"></i> Map
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'about' ? 'active' : ''; ?>" href="/about">
                    <i class="bi bi-info-circle"></i> About
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'tasks' ? 'active' : ''; ?>" href="/tasks.php">
                    <i class="bi bi-check2-square"></i> My Tasks
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'kanban' ? 'active' : ''; ?>" href="/kanban.php">
                    <i class="bi bi-kanban"></i> Kanban Board
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>" href="/profile.php">
                    <i class="bi bi-person-circle"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings' && $current_dir !== 'admin' ? 'active' : ''; ?>" href="/settings.php">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
        </ul>

        <?php if ($isAdmin): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Administration</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_dir === 'admin' && $current_page === 'index' ? 'active' : ''; ?>" href="/admin/index.php">
                    <i class="bi bi-shield-lock"></i> Admin Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_dir === 'admin' && $current_page === 'users' ? 'active' : ''; ?>" href="/admin/users.php">
                    <i class="bi bi-people-fill"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_dir === 'admin' && $current_page === 'communities' ? 'active' : ''; ?>" href="/admin/communities.php">
                    <i class="bi bi-building"></i> Communities
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_dir === 'admin' && ($current_page === 'courses' || strpos($current_page, 'admin-course') === 0 || strpos($current_page, 'admin-lesson') === 0) ? 'active' : ''; ?>" href="/admin/courses">
                    <i class="bi bi-mortarboard"></i> Course Admin
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_dir === 'admin' && $current_page === 'generate-all-skills-drills' ? 'active' : ''; ?>" href="/admin/generate-all-skills-drills.php">
                    <i class="bi bi-lightning"></i> Generate Skills Drills
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</nav>