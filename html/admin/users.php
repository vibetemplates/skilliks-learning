<?php
/**
 * Admin - User Management
 * 
 * Manage system users and their roles
 */

$page_title = 'User Management - Admin';
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

// Handle global admin role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user = new User();
    
    switch ($_POST['action']) {
        case 'grant_global_admin':
            $userId = (int)$_POST['user_id'];
            $reason = $_POST['reason'] ?? '';
            
            if ($userId === $currentUserId) {
                setFlashMessage('danger', 'You cannot change your own admin status.');
            } else {
                $result = $user->updateGlobalRole($userId, true, $currentUserId, $reason);
                if ($result['success']) {
                    setFlashMessage('success', 'Global admin access granted successfully.');
                } else {
                    setFlashMessage('danger', $result['error'] ?? 'Failed to grant global admin access.');
                }
            }
            break;
            
        case 'revoke_global_admin':
            $userId = (int)$_POST['user_id'];
            $reason = $_POST['reason'] ?? '';
            
            if ($userId === $currentUserId) {
                setFlashMessage('danger', 'You cannot revoke your own admin access.');
            } else {
                $result = $user->updateGlobalRole($userId, false, $currentUserId, $reason);
                if ($result['success']) {
                    setFlashMessage('success', 'Global admin access revoked successfully.');
                } else {
                    setFlashMessage('danger', $result['error'] ?? 'Failed to revoke global admin access.');
                }
            }
            break;
    }
    redirect('/admin/users.php');
}

// Handle delete user via GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user = new User();
    $userId = (int)$_GET['id'];
    
    if ($userId === $currentUserId) {
        setFlashMessage('danger', 'You cannot delete your own account.');
    } else {
        $result = $user->delete($userId, $currentUserId);
        if ($result['success']) {
            setFlashMessage('success', 'User deleted successfully.');
        } else {
            setFlashMessage('danger', $result['error']);
        }
    }
    redirect('/admin/users.php');
}

// Get search parameters
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$communityFilter = $_GET['community'] ?? '';

// Build query
try {
    $db = getDB();
    
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM project_members pm WHERE pm.user_id = u.id AND pm.status = 'approved') as project_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = u.id) as task_count,
            (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.user_id = u.id) as course_count,
            c.name as default_community_name,
            CASE WHEN ga.id IS NOT NULL THEN 1 ELSE 0 END as is_global_admin,
            (SELECT COUNT(DISTINCT cm.community_id) FROM community_members cm WHERE cm.user_id = u.id AND cm.is_active = 1) as community_count
            FROM users u
            LEFT JOIN communities c ON u.default_community_id = c.id
            LEFT JOIN global_admins ga ON u.id = ga.user_id
            WHERE 1=1";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.student_id LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if ($roleFilter) {
        if ($roleFilter === 'global_admin') {
            $sql .= " AND ga.id IS NOT NULL";
        } else if ($roleFilter === 'regular') {
            $sql .= " AND ga.id IS NULL";
        }
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Get communities for filter
    $stmt = $db->query("SELECT id, name FROM communities WHERE is_active = 1 ORDER BY name");
    $communities = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("User list query error: " . $e->getMessage());
    setFlashMessage('danger', 'Error loading user list.');
    $users = [];
}

require_once '../includes/header.php';
?>

<main class="container-fluid px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">User Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/admin/users.php?action=create" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add New User
                    </a>
                </div>
            </div>
            
            <!-- Admin Role Management Info -->
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Role Management:</strong> You can grant or revoke global admin privileges using the buttons in the "Global Role" column. 
                Only admins can manage other users' roles.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="/admin/users.php" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, email, or ID..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="">All Users</option>
                                <option value="global_admin" <?php echo $roleFilter === 'global_admin' ? 'selected' : ''; ?>>Global Admin</option>
                                <option value="regular" <?php echo $roleFilter === 'regular' ? 'selected' : ''; ?>>Regular User</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <a href="/admin/users.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- User List -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Users (<?php echo count($users); ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Student ID</th>
                                    <th>Global Role</th>
                                    <th>Communities</th>
                                    <th>Stats</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($user['profile_photo'] && file_exists(dirname(__DIR__) . '/uploads/avatars/' . $user['profile_photo'])): ?>
                                                    <img src="/uploads/avatars/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                                         class="rounded-circle me-2" width="32" height="32" alt="Avatar">
                                                <?php else: ?>
                                                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 32px; height: 32px; font-size: 14px;">
                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                    <?php if ($user['id'] === $currentUserId): ?>
                                                        <small class="text-muted">(You)</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['student_id'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($user['is_global_admin']): ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-shield-fill"></i> Global Admin
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Regular User</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['id'] !== $currentUserId): ?>
                                                <?php if ($user['is_global_admin']): ?>
                                                    <button type="button" class="btn btn-sm btn-danger ms-2" 
                                                            onclick="revokeGlobalAdmin(<?php echo $user['id']; ?>)"
                                                            title="Revoke global admin privileges">
                                                        <i class="bi bi-shield-x"></i> Revoke Admin
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success ms-2" 
                                                            onclick="grantGlobalAdmin(<?php echo $user['id']; ?>)"
                                                            title="Grant global admin privileges">
                                                        <i class="bi bi-shield-plus"></i> Make Admin
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $user['community_count'] > 0 ? $user['community_count'] . ' communities' : 'None'; ?>
                                            <?php if ($user['default_community_name']): ?>
                                                <br><small class="text-muted">Default: <?php echo htmlspecialchars($user['default_community_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo $user['project_count']; ?> projects<br>
                                                <?php echo $user['task_count']; ?> tasks<br>
                                                <?php echo $user['course_count']; ?> courses
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?><br>
                                                <?php echo getTimeAgo($user['created_at']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="/team-member?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary btn-sm" title="View Profile">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="/admin/user-edit.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Edit User">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($user['id'] !== $currentUserId): ?>
                                                    <a href="/admin/impersonate.php?user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-warning btn-sm" 
                                                       title="Impersonate User">
                                                        <i class="bi bi-person-fill-gear"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" title="Delete User" 
                                                            onclick="if(confirm('Are you sure you want to delete this user?')) { window.location.href='/admin/users.php?action=delete&id=<?php echo $user['id']; ?>'; }">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

</main>

<!-- Modal for global admin reason -->
<div class="modal fade" id="adminReasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="adminRoleForm" method="POST" action="/admin/users.php">
                <input type="hidden" name="action" value="">
                <input type="hidden" name="user_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminReasonTitle">Update Global Admin Access</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for change (optional)</label>
                        <textarea class="form-control" name="reason" id="reason" rows="3" 
                                  placeholder="Enter reason for audit trail..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function grantGlobalAdmin(userId) {
    const modal = new bootstrap.Modal(document.getElementById('adminReasonModal'));
    const form = document.getElementById('adminRoleForm');
    const title = document.getElementById('adminReasonTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    form.querySelector('[name="action"]').value = 'grant_global_admin';
    form.querySelector('[name="user_id"]').value = userId;
    title.textContent = 'Grant Global Admin Access';
    submitBtn.textContent = 'Grant Access';
    submitBtn.className = 'btn btn-success';
    
    modal.show();
}

function revokeGlobalAdmin(userId) {
    const modal = new bootstrap.Modal(document.getElementById('adminReasonModal'));
    const form = document.getElementById('adminRoleForm');
    const title = document.getElementById('adminReasonTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    form.querySelector('[name="action"]').value = 'revoke_global_admin';
    form.querySelector('[name="user_id"]').value = userId;
    title.textContent = 'Revoke Global Admin Access';
    submitBtn.textContent = 'Revoke Access';
    submitBtn.className = 'btn btn-danger';
    
    modal.show();
}

// Use event delegation for impersonate buttons
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        // Check if clicked element or its parent is an impersonate button
        const btn = e.target.closest('.impersonate-btn');
        if (btn) {
            e.preventDefault();
            const userId = btn.getAttribute('data-user-id');
            const userName = btn.getAttribute('data-user-name');
            
            console.log('Impersonate button clicked for user:', userId, userName);
            
            if (confirm('Impersonate ' + userName + '? You will need to logout to return to your account.')) {
                console.log('User confirmed, redirecting to:', '/admin/impersonate.php?user_id=' + userId);
                window.location.href = '/admin/impersonate.php?user_id=' + userId;
            } else {
                console.log('User cancelled impersonation');
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>