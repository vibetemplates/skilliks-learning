<?php
/**
 * Admin Dashboard
 * 
 * Central administration interface for system management
 */

$page_title = 'Admin Dashboard';
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/Community.php';

// Require login and admin role
requireLogin();

$currentUserId = getCurrentUserId();
$currentUserRole = getCurrentUserRole();

// Check if user is admin
if ($currentUserRole !== 'admin') {
    setFlashMessage('You do not have permission to access the admin area.', 'danger');
    redirect('/dashboard');
}

// Initialize variables with default values
$totalUsers = 0;
$usersByRole = [];
$totalCommunities = 0;
$totalProjects = 0;
$projectsByStatus = [];
$totalCourses = 0;
$totalEnrollments = 0;
$recentActivities = [];
$recentUsers = [];

// Get system statistics
try {
    $db = getDB();
    
    // User statistics
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $usersByRole = [];
    while ($row = $stmt->fetch()) {
        $usersByRole[$row['role']] = $row['count'];
    }
    
    // Community statistics
    $stmt = $db->query("SELECT COUNT(*) as total FROM communities WHERE is_active = 1");
    $totalCommunities = $stmt->fetch()['total'];
    
    // Project statistics
    $stmt = $db->query("SELECT COUNT(*) as total FROM projects");
    $totalProjects = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM projects GROUP BY status");
    $projectsByStatus = [];
    while ($row = $stmt->fetch()) {
        $projectsByStatus[$row['status']] = $row['count'];
    }
    
    // Course statistics
    $stmt = $db->query("SELECT COUNT(*) as total FROM courses");
    $totalCourses = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM course_enrollments");
    $totalEnrollments = $stmt->fetch()['total'];
    
    // Recent activity
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, u.email
        FROM activities a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();
    
    // Recent users
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentUsers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Admin dashboard query error: " . $e->getMessage());
    setFlashMessage('Error loading dashboard data.', 'danger');
}

require_once '../includes/header.php';
?>

<main class="container-fluid px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Admin Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Overview Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalUsers; ?></div>
                                    <small class="text-muted">
                                        <?php echo $usersByRole['admin'] ?? 0; ?> admins, 
                                        <?php echo $usersByRole['project_manager'] ?? 0; ?> managers, 
                                        <?php echo $usersByRole['member'] ?? 0; ?> members
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-people fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Communities</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalCommunities; ?></div>
                                    <small class="text-muted">Active communities</small>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-building fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Projects</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalProjects; ?></div>
                                    <small class="text-muted">
                                        <?php echo $projectsByStatus['active'] ?? 0; ?> active
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-folder fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Courses</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalCourses; ?></div>
                                    <small class="text-muted"><?php echo $totalEnrollments; ?> enrollments</small>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-book fs-2 text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity and Users -->
            <div class="row">
                <!-- Recent Activity -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent System Activity</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentActivities)): ?>
                                            <?php foreach ($recentActivities as $activity): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($activity['type']); ?>
                                                        <?php if ($activity['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($activity['description']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo getTimeAgo($activity['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No recent activity</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentUsers)): ?>
                                <?php foreach ($recentUsers as $user): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="font-weight-bold">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'project_manager' ? 'warning' : 'primary'); ?> ms-2">
                                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Joined <?php echo getTimeAgo($user['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">No recent users</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <a href="/admin/users.php?action=create" class="btn btn-primary btn-block">
                                        <i class="bi bi-person-plus"></i> Add New User
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/admin/communities.php?action=create" class="btn btn-success btn-block">
                                        <i class="bi bi-building-add"></i> Create Community
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/admin/courses?action=create" class="btn btn-info btn-block">
                                        <i class="bi bi-journal-plus"></i> Add Course
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/admin/reports.php" class="btn btn-warning btn-block">
                                        <i class="bi bi-file-earmark-bar-graph"></i> View Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

</main>

<?php require_once '../includes/footer.php'; ?>