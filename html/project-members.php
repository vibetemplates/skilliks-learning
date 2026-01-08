<?php
/**
 * Project Team Members Page
 * 
 * Display and manage team members for a project
 */

$page_title = 'Team Members';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';

// Require login
requireLogin();

// Get project ID
$projectId = $_GET['project'] ?? null;
if (!$projectId) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$projectObj = new Project();
$project = $projectObj->findById($projectId);

if (!$project) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

$currentUserId = getCurrentUserId();
$isMember = $projectObj->isMember($projectId, $currentUserId);
$isCreator = $project['created_by'] == $currentUserId;

$userObj = new User();
$isProjectManagerOrAdmin = $userObj->isProjectManagerOrAdmin($currentUserId);

// Require member access
if (!$isMember && !$isProjectManagerOrAdmin) {
    setFlashMessage('error', 'You must be a project member to view team members.');
    header('Location: /project-detail?id=' . $projectId);
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($isProjectManagerOrAdmin || $isCreator)) {
    $db = getDB();
    
    if ($_POST['action'] === 'update_member_status') {
        $memberId = $_POST['member_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        if ($memberId && $status && in_array($status, ['approved', 'pending', 'rejected'])) {
            try {
                $stmt = $db->prepare("UPDATE project_members SET status = ? WHERE id = ? AND project_id = ?");
                $stmt->execute([$status, $memberId, $projectId]);
                setFlashMessage('success', 'Member status updated successfully!');
            } catch (PDOException $e) {
                error_log("Error updating member status: " . $e->getMessage());
                setFlashMessage('error', 'Failed to update member status.');
            }
        }
        header('Location: /project-members.php?project=' . $projectId);
        exit;
    }
    
    if ($_POST['action'] === 'remove_member') {
        $memberId = $_POST['member_id'] ?? null;
        $userId = $_POST['user_id'] ?? null;
        
        if ($memberId && $userId != $project['created_by']) { // Can't remove project creator
            try {
                $stmt = $db->prepare("DELETE FROM project_members WHERE id = ? AND project_id = ?");
                $stmt->execute([$memberId, $projectId]);
                setFlashMessage('success', 'Member removed from project.');
            } catch (PDOException $e) {
                error_log("Error removing member: " . $e->getMessage());
                setFlashMessage('error', 'Failed to remove member.');
            }
        }
        header('Location: /project-members.php?project=' . $projectId);
        exit;
    }
}

// Get project members
$members = $projectObj->getMembers($projectId);

// Get project creator info if not in members list
$creator = null;
$creatorInMembers = false;
foreach ($members as $member) {
    if ($member['user_id'] == $project['created_by']) {
        $creatorInMembers = true;
        break;
    }
}

if (!$creatorInMembers) {
    $creator = $userObj->findById($project['created_by']);
}

// Get pending members count
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND status = 'pending'");
    $stmt->execute([$projectId]);
    $pendingCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $pendingCount = 0;
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="pt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
            <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Team Members</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2">Team Members</h1>
        <?php if ($pendingCount > 0 && ($isProjectManagerOrAdmin || $isCreator)): ?>
            <span class="badge bg-warning">
                <?php echo $pendingCount; ?> Pending Request<?php echo $pendingCount > 1 ? 's' : ''; ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($creator): ?>
        <!-- Project Creator Card -->
        <div class="mb-4">
            <h5 class="text-muted mb-3">Project Creator</h5>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($creator['profile_photo'])): ?>
                            <img src="/uploads/profile/<?php echo htmlspecialchars($creator['profile_photo']); ?>" 
                                 alt="Profile" class="rounded-circle me-3" 
                                 style="width: 60px; height: 60px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" 
                                 style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <?php echo strtoupper(substr($creator['first_name'], 0, 1) . substr($creator['last_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']); ?>
                                <span class="badge bg-primary ms-2">Creator</span>
                            </h5>
                            <?php if (!empty($creator['github_username'])): ?>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-github"></i> @<?php echo htmlspecialchars($creator['github_username']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Team Members -->
    <h5 class="text-muted mb-3">Team Members</h5>
    <?php if (empty($members)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No team members yet.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($members as $member): ?>
                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <?php if (!empty($member['profile_photo'])): ?>
                                    <img src="/uploads/profile/<?php echo htmlspecialchars($member['profile_photo']); ?>" 
                                         alt="Profile" class="rounded-circle me-3" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" 
                                         style="width: 50px; height: 50px;">
                                        <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1 min-width-0">
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        <?php if ($member['user_id'] == $project['created_by']): ?>
                                            <span class="badge bg-primary ms-1">Creator</span>
                                        <?php endif; ?>
                                        <?php if ($member['status'] === 'pending'): ?>
                                            <span class="badge bg-warning ms-1">Pending</span>
                                        <?php elseif ($member['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger ms-1">Rejected</span>
                                        <?php endif; ?>
                                    </h6>
                                    <?php if (!empty($member['github_username'])): ?>
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-github"></i> @<?php echo htmlspecialchars($member['github_username']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="text-muted small mb-0">
                                        Joined <?php echo date('M j, Y', strtotime($member['joined_date'])); ?>
                                    </p>
                                    
                                    <?php 
                                    // Get member's skills for this project
                                    try {
                                        $stmt = $db->prepare("
                                            SELECT s.name, ps.importance_level 
                                            FROM project_skills ps
                                            JOIN skills s ON ps.skill_id = s.id
                                            JOIN user_skills us ON us.skill_id = s.id
                                            WHERE ps.project_id = ? AND us.user_id = ?
                                            ORDER BY 
                                                CASE ps.importance_level
                                                    WHEN 'required' THEN 1
                                                    WHEN 'preferred' THEN 2
                                                    WHEN 'optional' THEN 3
                                                END
                                            LIMIT 3
                                        ");
                                        $stmt->execute([$projectId, $member['user_id']]);
                                        $memberSkills = $stmt->fetchAll();
                                        
                                        if (!empty($memberSkills)): ?>
                                            <div class="mt-2">
                                                <?php foreach ($memberSkills as $skill): ?>
                                                    <span class="badge bg-light text-dark me-1 mb-1">
                                                        <?php echo htmlspecialchars($skill['name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif;
                                    } catch (PDOException $e) {
                                        // Silent fail for skills
                                    }
                                    ?>
                                </div>
                                
                                <?php if (($isProjectManagerOrAdmin || $isCreator) && $member['user_id'] != $project['created_by']): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if ($member['status'] === 'pending'): ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_member_status">
                                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                        <input type="hidden" name="status" value="approved">
                                                        <button type="submit" class="dropdown-item text-success">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_member_status">
                                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                        <input type="hidden" name="status" value="rejected">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove this member from the project?');">
                                                        <input type="hidden" name="action" value="remove_member">
                                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-person-x"></i> Remove
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>