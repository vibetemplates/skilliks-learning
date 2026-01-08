<?php
/**
 * Members Page
 * 
 * Lists all members of the current community
 */

$page_title = 'Members';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$communityId = getCurrentCommunityId();
$db = getDB();

// Check if user is community admin
$community = new Community();
$isAdmin = $community->isAdmin($communityId, $currentUserId);
$isGlobalAdmin = isCurrentUserGlobalAdmin();

// Get current community name
$currentCommunity = $community->getById($communityId);
$currentCommunityName = $currentCommunity ? htmlspecialchars($currentCommunity['name']) : 'Community';

// Get search parameters and clean them
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$sortBy = $_GET['sort'] ?? 'name';
$viewMode = $_GET['view'] ?? 'table'; // table or grid

// Debug initial parameters
error_log("Initial params - search: '" . $search . "', roleFilter: '" . $roleFilter . "'");

// Convert empty strings to null for consistent handling
$search = $search === '' ? null : $search;
$roleFilter = $roleFilter === '' ? null : $roleFilter;

// Debug processed parameters
error_log("Processed params - search: " . ($search ?? 'NULL') . ", roleFilter: " . ($roleFilter ?? 'NULL'));

// Pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 25;
$offset = ($page - 1) * $pageSize;

// Build query
try {
    // First get total count for pagination
    $countSql = "SELECT COUNT(*) as total
        FROM community_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.community_id = :community_id
        AND cm.is_active = 1";
    
    $countParams = [':community_id' => $communityId];
    
    if ($search !== null) {
        $countSql .= " AND (u.first_name LIKE :search1 OR u.last_name LIKE :search2)";
        $countParams[':search1'] = '%' . $search . '%';
        $countParams[':search2'] = '%' . $search . '%';
    }
    
    if ($roleFilter !== null) {
        $countSql .= " AND cm.role = :role";
        $countParams[':role'] = $roleFilter;
    }
    
    // Debug count query
    error_log("Count query SQL: " . $countSql);
    error_log("Count query params: " . print_r($countParams, true));
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($countParams);
    $totalMembers = $countStmt->fetchColumn();
    $totalPages = ceil($totalMembers / $pageSize);
    
    error_log("Count query completed successfully. Total members: " . $totalMembers);
    
    // Main query with pagination - build SQL and params together
    $mainSql = "SELECT 
            u.id,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.github_username,
            u.profile_photo,
            u.created_at as user_created_at,
            u.last_login,
            cm.role as community_role,
            cm.joined_at,
            cm.is_active,
            (SELECT COUNT(*) FROM project_members pm 
             JOIN projects p ON pm.project_id = p.id 
             WHERE pm.user_id = u.id 
             AND p.community_id = " . intval($communityId) . "
             AND pm.status = 'approved') as project_count,
            (SELECT COUNT(*) FROM tasks t 
             WHERE t.assignee_id = u.id 
             AND t.project_id IN (SELECT id FROM projects WHERE community_id = " . intval($communityId) . ")) as task_count,
            (SELECT COUNT(*) FROM course_enrollments ce 
             WHERE ce.user_id = u.id 
             AND ce.course_id IN (SELECT id FROM courses WHERE community_id = " . intval($communityId) . ")) as course_count,
            CASE WHEN ga.id IS NOT NULL THEN 1 ELSE 0 END as is_global_admin
        FROM community_members cm
        JOIN users u ON cm.user_id = u.id
        LEFT JOIN global_admins ga ON u.id = ga.user_id
        WHERE cm.community_id = " . intval($communityId) . "
        AND cm.is_active = 1";
    
    $mainParams = [];
    
    if ($search !== null) {
        $mainSql .= " AND (u.first_name LIKE :search1 OR u.last_name LIKE :search2)";
        $mainParams[':search1'] = '%' . $search . '%';
        $mainParams[':search2'] = '%' . $search . '%';
    }
    
    if ($roleFilter !== null) {
        $mainSql .= " AND cm.role = :role";
        $mainParams[':role'] = $roleFilter;
    }
    
    // Add sorting
    switch ($sortBy) {
        case 'role':
            $mainSql .= " ORDER BY FIELD(cm.role, 'owner', 'admin', 'moderator', 'member'), u.first_name ASC";
            break;
        case 'joined':
            $mainSql .= " ORDER BY cm.joined_at DESC";
            break;
        case 'activity':
            $mainSql .= " ORDER BY u.last_login DESC";
            break;
        default: // name
            $mainSql .= " ORDER BY u.first_name ASC, u.last_name ASC";
    }
    
    // Add pagination with literal values to avoid parameter binding issues
    $mainSql .= " LIMIT " . intval($pageSize) . " OFFSET " . intval($offset);
    
    // Debug logging
    error_log("Members query SQL: " . $mainSql);
    error_log("Members query params: " . print_r($mainParams, true));
    
    $stmt = $db->prepare($mainSql);
    $stmt->execute($mainParams);
    $members = $stmt->fetchAll();
    
    // Calculate display range
    $startIndex = $offset + 1;
    $endIndex = min($offset + $pageSize, $totalMembers);
    
} catch (PDOException $e) {
    error_log("Members list query error: " . $e->getMessage());
    error_log("Error occurred at: " . $e->getFile() . " line " . $e->getLine());
    error_log("Error trace: " . $e->getTraceAsString());
    setFlashMessage('error', 'Error loading members list.');
    $members = [];
    $totalMembers = 0;
    $totalPages = 0;
    $startIndex = 0;
    $endIndex = 0;
} catch (Exception $e) {
    error_log("General error in members.php: " . $e->getMessage());
    error_log("Error occurred at: " . $e->getFile() . " line " . $e->getLine());
    setFlashMessage('error', 'Error loading members list.');
    $members = [];
    $totalMembers = 0;
    $totalPages = 0;
    $startIndex = 0;
    $endIndex = 0;
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main id="members-main-content" class="container-fluid px-4">
    <div id="members-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><?php echo $currentCommunityName; ?> - Members</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2" role="group">
                <a href="?view=table<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?>" 
                   class="btn btn-sm <?php echo $viewMode === 'table' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-list"></i> Table
                </a>
                <a href="?view=grid<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?>" 
                   class="btn btn-sm <?php echo $viewMode === 'grid' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-grid-3x3-gap"></i> Grid
                </a>
            </div>
            <?php if ($isAdmin || $isGlobalAdmin): ?>
                <a href="/discover-communities.php" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Invite Members
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search and Filters -->
    <div id="members-search-filters" class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/members" class="row g-3">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name..." 
                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="owner" <?php echo $roleFilter === 'owner' ? 'selected' : ''; ?>>Owner</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="moderator" <?php echo $roleFilter === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                        <option value="member" <?php echo $roleFilter === 'member' ? 'selected' : ''; ?>>Member</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Name</option>
                        <option value="role" <?php echo $sortBy === 'role' ? 'selected' : ''; ?>>Role</option>
                        <option value="joined" <?php echo $sortBy === 'joined' ? 'selected' : ''; ?>>Recently Joined</option>
                        <option value="activity" <?php echo $sortBy === 'activity' ? 'selected' : ''; ?>>Last Active</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <a href="/members" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Members List -->
    <?php if ($viewMode === 'table'): ?>
    <div id="members-table-view" class="card">
        <div class="card-header">
            <h6 class="mb-0">
                <?php if ($totalMembers > 0): ?>
                    Showing <?php echo $startIndex; ?>-<?php echo $endIndex; ?> of <?php echo $totalMembers; ?> members
                <?php else: ?>
                    No members found
                <?php endif; ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Community Role</th>
                            <th>Contributions</th>
                            <th>Joined</th>
                            <th>Last Active</th>
                            <?php if ($isAdmin || $isGlobalAdmin): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="serve-avatar.php?user_id=<?php echo $member['id']; ?>" 
                                             alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                             class="rounded-circle me-2" 
                                             width="32" 
                                             height="32"
                                             style="object-fit: cover;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                            <?php if ($member['id'] === $currentUserId): ?>
                                                <small class="text-muted">(You)</small>
                                            <?php endif; ?>
                                            <?php if ($member['github_username']): ?>
                                                <br>
                                                <small>
                                                    <a href="https://github.com/<?php echo htmlspecialchars($member['github_username']); ?>" 
                                                       target="_blank" class="text-decoration-none">
                                                        <i class="bi bi-github"></i> <?php echo htmlspecialchars($member['github_username']); ?>
                                                    </a>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $roleColors = [
                                        'owner' => 'danger',
                                        'admin' => 'primary',
                                        'moderator' => 'warning',
                                        'member' => 'secondary'
                                    ];
                                    $roleColor = $roleColors[$member['community_role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $roleColor; ?>">
                                        <?php echo ucfirst($member['community_role']); ?>
                                    </span>
                                    <?php if ($member['is_global_admin']): ?>
                                        <span class="badge bg-danger ms-1" title="Global Administrator">
                                            <i class="bi bi-shield-fill"></i> Global Admin
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo $member['project_count']; ?> projects<br>
                                        <?php echo $member['task_count']; ?> tasks<br>
                                        <?php echo $member['course_count']; ?> courses
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($member['joined_at'])); ?><br>
                                        <?php echo getTimeAgo($member['joined_at']); ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php if ($member['last_login']): ?>
                                            <?php echo getTimeAgo($member['last_login']); ?>
                                        <?php else: ?>
                                            Never
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <?php if ($isAdmin || $isGlobalAdmin): ?>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="/team-member?id=<?php echo $member['id']; ?>" 
                                               class="btn btn-outline-primary" title="View Profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($member['id'] !== $currentUserId && ($isGlobalAdmin || $member['community_role'] !== 'owner')): ?>
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown" aria-expanded="false" title="Change Role">
                                                    <i class="bi bi-person-gear"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($member['community_role'] !== 'admin'): ?>
                                                        <li><a class="dropdown-item" href="#" 
                                                               onclick="changeRole(<?php echo $member['id']; ?>, 'admin')">
                                                            <i class="bi bi-star-fill text-primary"></i> Make Admin
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <?php if ($member['community_role'] !== 'moderator'): ?>
                                                        <li><a class="dropdown-item" href="#" 
                                                               onclick="changeRole(<?php echo $member['id']; ?>, 'moderator')">
                                                            <i class="bi bi-shield text-warning"></i> Make Moderator
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <?php if ($member['community_role'] !== 'member'): ?>
                                                        <li><a class="dropdown-item" href="#" 
                                                               onclick="changeRole(<?php echo $member['id']; ?>, 'member')">
                                                            <i class="bi bi-person text-secondary"></i> Make Member
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" 
                                                           onclick="removeMember(<?php echo $member['id']; ?>)">
                                                        <i class="bi bi-x-circle"></i> Remove from Community
                                                    </a></li>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="<?php echo ($isAdmin || $isGlobalAdmin) ? '6' : '5'; ?>" 
                                    class="text-center text-muted py-4">
                                    No members found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php else: // Grid view ?>
    
    <div id="members-grid-view">
        <div class="mb-3">
            <h6>
                <?php if ($totalMembers > 0): ?>
                    Showing <?php echo $startIndex; ?>-<?php echo $endIndex; ?> of <?php echo $totalMembers; ?> members
                <?php else: ?>
                    No members found
                <?php endif; ?>
            </h6>
        </div>
        <div class="row">
        <?php foreach ($members as $member): ?>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-4" id="member-card-<?php echo $member['id']; ?>">
            <div class="card h-100" id="member-card-inner-<?php echo $member['id']; ?>">
                <div class="card-body text-center" id="member-card-body-<?php echo $member['id']; ?>">
                    <div class="mb-3">
                        <img src="serve-avatar.php?user_id=<?php echo $member['id']; ?>" 
                             alt="<?php echo htmlspecialchars($member['name']); ?>" 
                             class="rounded-circle" 
                             width="80" 
                             height="80"
                             style="object-fit: cover;">
                    </div>
                    <h5 class="card-title mb-1">
                        <?php echo htmlspecialchars($member['name']); ?>
                        <?php if ($member['id'] === $currentUserId): ?>
                            <small class="text-muted">(You)</small>
                        <?php endif; ?>
                    </h5>
                    <div class="mb-2">
                        <?php
                        $roleColors = [
                            'owner' => 'danger',
                            'admin' => 'primary',
                            'moderator' => 'warning',
                            'member' => 'secondary'
                        ];
                        $roleColor = $roleColors[$member['community_role']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $roleColor; ?>">
                            <?php echo ucfirst($member['community_role']); ?>
                        </span>
                        <?php if ($member['is_global_admin']): ?>
                            <span class="badge bg-danger" title="Global Administrator">
                                <i class="bi bi-shield-fill"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($member['github_username']): ?>
                    <p class="mb-2">
                        <a href="https://github.com/<?php echo htmlspecialchars($member['github_username']); ?>" 
                           target="_blank" class="text-decoration-none">
                            <i class="bi bi-github"></i> <?php echo htmlspecialchars($member['github_username']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    <div class="text-muted small mb-3">
                        <div><?php echo $member['project_count']; ?> projects • <?php echo $member['task_count']; ?> tasks</div>
                        <div>Joined <?php echo date('M j, Y', strtotime($member['joined_at'])); ?></div>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="/team-member?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> View Profile
                        </a>
                        <?php if (($isAdmin || $isGlobalAdmin) && $member['id'] !== $currentUserId && ($isGlobalAdmin || $member['community_role'] !== 'owner')): ?>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-gear"></i> Manage
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($member['community_role'] !== 'admin'): ?>
                                        <li><a class="dropdown-item" href="#" 
                                               onclick="changeRole(<?php echo $member['id']; ?>, 'admin')">
                                            <i class="bi bi-star-fill text-primary"></i> Make Admin
                                        </a></li>
                                    <?php endif; ?>
                                    <?php if ($member['community_role'] !== 'moderator'): ?>
                                        <li><a class="dropdown-item" href="#" 
                                               onclick="changeRole(<?php echo $member['id']; ?>, 'moderator')">
                                            <i class="bi bi-shield text-warning"></i> Make Moderator
                                        </a></li>
                                    <?php endif; ?>
                                    <?php if ($member['community_role'] !== 'member'): ?>
                                        <li><a class="dropdown-item" href="#" 
                                               onclick="changeRole(<?php echo $member['id']; ?>, 'member')">
                                            <i class="bi bi-person text-secondary"></i> Make Member
                                        </a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" 
                                           onclick="removeMember(<?php echo $member['id']; ?>)">
                                        <i class="bi bi-x-circle"></i> Remove
                                    </a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($members)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    No members found matching your criteria.
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>
    
    <?php endif; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div id="members-pagination" class="d-flex justify-content-center mt-4">
        <nav aria-label="Members pagination">
            <ul class="pagination">
                <!-- Previous page -->
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo $viewMode !== 'table' ? '&view=' . urlencode($viewMode) : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link" aria-hidden="true">&laquo;</span>
                    </li>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                // Show first page if we're not starting from 1
                if ($startPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo $viewMode !== 'table' ? '&view=' . urlencode($viewMode) : ''; ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Page range -->
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo $viewMode !== 'table' ? '&view=' . urlencode($viewMode) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <!-- Show last page if we're not ending at the last page -->
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo $viewMode !== 'table' ? '&view=' . urlencode($viewMode) : ''; ?>"><?php echo $totalPages; ?></a>
                    </li>
                <?php endif; ?>
                
                <!-- Next page -->
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo $sortBy !== 'name' ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo $viewMode !== 'table' ? '&view=' . urlencode($viewMode) : ''; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link" aria-hidden="true">&raquo;</span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</main>

<script>
function changeRole(userId, newRole) {
    if (confirm('Are you sure you want to change this member\'s role?')) {
        // TODO: Implement role change via AJAX
        alert('Role change functionality to be implemented');
    }
}

function removeMember(userId) {
    if (confirm('Are you sure you want to remove this member from the community?')) {
        // TODO: Implement member removal via AJAX
        alert('Member removal functionality to be implemented');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>