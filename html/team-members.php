<?php
/**
 * Team Members Page
 * 
 * Lists all team members in the system
 */

$page_title = 'Team Members';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$userObj = new User();
$currentUserId = getCurrentUserId();
$isAdmin = $userObj->isAdmin($currentUserId);

// Handle role update requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $userId = $_POST['user_id'] ?? null;
    $newRole = $_POST['new_role'] ?? null;
    
    if ($userId && $newRole) {
        $result = $userObj->updateRole($userId, $newRole, $currentUserId);
        if ($result['success']) {
            setFlashMessage('success', 'User role updated successfully!');
        } else {
            setFlashMessage('error', $result['error']);
        }
    } else {
        setFlashMessage('error', 'Invalid role update request.');
    }
    
    // Redirect to avoid form resubmission
    $redirect_url = !empty($_GET['search']) ? "/team-members.php?search=" . urlencode($_GET['search']) : "/team-members.php";
    header("Location: {$redirect_url}");
    exit;
}

// Get search term if provided
$search = $_GET['search'] ?? '';

// Get all team members with optional search
try {
    $db = getDB();
    
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.student_id, u.github_username, 
                   u.bio, u.skills, u.created_at, u.skill_level, u.years_programming_experience,
                   u.years_project_management_experience, u.programming_languages, u.database_technologies,
                   u.timezone, u.best_meeting_times,
                   u.ai_assisted_coding_current, u.ai_assisted_coding_goal,
                   u.mcp_servers_current, u.mcp_servers_goal,
                   u.ai_automations_current, u.ai_automations_goal,
                   COUNT(DISTINCT pm.project_id) as project_count,
                   COUNT(DISTINCT t.id) as task_count
            FROM users u
            LEFT JOIN project_members pm ON u.id = pm.user_id AND pm.status = 'approved'
            LEFT JOIN tasks t ON u.id = t.assignee_id
            WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)
            GROUP BY u.id
            ORDER BY u.first_name, u.last_name
        ");
        $searchTerm = "%{$search}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.student_id, u.github_username, 
                   u.bio, u.skills, u.created_at, u.skill_level, u.years_programming_experience,
                   u.years_project_management_experience, u.programming_languages, u.database_technologies,
                   u.timezone, u.best_meeting_times,
                   u.ai_assisted_coding_current, u.ai_assisted_coding_goal,
                   u.mcp_servers_current, u.mcp_servers_goal,
                   u.ai_automations_current, u.ai_automations_goal,
                   COUNT(DISTINCT pm.project_id) as project_count,
                   COUNT(DISTINCT t.id) as task_count
            FROM users u
            LEFT JOIN project_members pm ON u.id = pm.user_id AND pm.status = 'approved'
            LEFT JOIN tasks t ON u.id = t.assignee_id
            GROUP BY u.id
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
    }
    
    $teamMembers = $stmt->fetchAll();
} catch (PDOException $e) {
    $teamMembers = [];
    error_log("Team members query error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

    
        
        
        
        <main class="container-fluid px-4 py-3">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Team Members</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="input-group">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control" placeholder="Search team members..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="/team-members.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>


            <?php if (!empty($search)): ?>
            <div class="alert alert-info fade-in">
                <i class="bi bi-info-circle"></i>
                Showing results for "<?php echo htmlspecialchars($search); ?>" (<?php echo count($teamMembers); ?> found)
            </div>
            <?php endif; ?>

            <!-- Team Members List -->
            <?php if (empty($teamMembers)): ?>
                <div class="alert alert-info fade-in">
                    <i class="bi bi-info-circle"></i>
                    <?php if (!empty($search)): ?>
                        No team members found matching your search criteria.
                    <?php else: ?>
                        No team members found.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($teamMembers as $member): ?>
                        <div class="list-group-item mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="d-flex align-items-start flex-grow-1">
                                    <div class="me-3" style="width: 50px; height: 50px;">
                                        <?php if (!empty($member['profile_photo'])): ?>
                                            <img src="/uploads/avatars/<?php echo htmlspecialchars($member['profile_photo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
                                                 class="rounded-circle" 
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'bg-primary text-white rounded-circle d-flex align-items-center justify-content-center\' style=\'width: 50px; height: 50px; font-size: 1.2em;\'><?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?></div>';">
                                        <?php else: ?>
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px; font-size: 1.2em;">
                                                <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1">
                                            <a href="/team-member?id=<?php echo $member['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            </a>
                                        </h5>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($member['email']); ?></p>
                                        
                                        <div class="mb-2">
                                            <?php if ($isAdmin && $member['id'] != $currentUserId): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                                    <select name="new_role" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                        <option value="member" <?php echo $member['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                                                        <option value="project_manager" <?php echo $member['role'] === 'project_manager' ? 'selected' : ''; ?>>Project Manager</option>
                                                        <option value="admin" <?php echo $member['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-<?php 
                                                    echo $member['role'] === 'admin' ? 'danger' : 
                                                         ($member['role'] === 'project_manager' ? 'warning' : 'primary'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $member['role'])); ?>
                                                    <?php if ($member['id'] == $currentUserId): ?>
                                                        <small>(You)</small>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    
                                        <?php if (!empty($member['student_id'])): ?>
                                            <small class="text-muted me-3">
                                                <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($member['student_id']); ?>
                                            </small>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($member['github_username'])): ?>
                                            <small class="text-muted me-3">
                                                <i class="bi bi-git"></i> 
                                                <a href="https://git.kineticseas.com/<?php echo htmlspecialchars($member['github_username']); ?>" 
                                                   target="_blank" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($member['github_username']); ?>
                                                </a>
                                            </small>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($member['timezone'])): ?>
                                            <small class="text-muted me-3">
                                                <i class="bi bi-clock"></i> <?php echo htmlspecialchars($member['timezone']); ?>
                                            </small>
                                        <?php endif; ?>
                                    
                                        
                                        <!-- Skills and Experience -->
                                        <div class="mt-2">
                                            <?php if (!empty($member['skill_level'])): ?>
                                                <span class="badge bg-info me-1"><?php echo ucfirst($member['skill_level']); ?></span>
                                            <?php endif; ?>
                                    
                                            <?php if ($member['years_programming_experience'] > 0): ?>
                                                <span class="badge bg-secondary me-1"><?php echo $member['years_programming_experience']; ?> yr<?php echo $member['years_programming_experience'] != 1 ? 's' : ''; ?> coding</span>
                                            <?php endif; ?>
                                            <?php if ($member['years_project_management_experience'] > 0): ?>
                                                <span class="badge bg-secondary me-1"><?php echo $member['years_project_management_experience']; ?> yr<?php echo $member['years_project_management_experience'] != 1 ? 's' : ''; ?> PM</span>
                                            <?php endif; ?>
                                        </div>
                                    
                                        
                                        <?php if (!empty($member['programming_languages'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted d-block">Languages:</small>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php 
                                                    $languages = explode(',', $member['programming_languages']);
                                                    foreach (array_slice($languages, 0, 4) as $lang): 
                                                        $lang = trim($lang);
                                                        if (!empty($lang)):
                                                    ?>
                                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($lang); ?></span>
                                                    <?php 
                                                        endif;
                                                    endforeach;
                                                    if (count($languages) > 4):
                                                    ?>
                                                        <span class="badge bg-light text-dark">+<?php echo count($languages) - 4; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    
                                        
                                        <?php if (!empty($member['database_technologies'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted d-block">Databases:</small>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php 
                                                    $databases = explode(',', $member['database_technologies']);
                                                    foreach (array_slice($databases, 0, 3) as $db): 
                                                        $db = trim($db);
                                                        if (!empty($db)):
                                                    ?>
                                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($db); ?></span>
                                                    <?php 
                                                        endif;
                                                    endforeach;
                                                    if (count($databases) > 3):
                                                    ?>
                                                        <span class="badge bg-light text-dark">+<?php echo count($databases) - 3; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    
                                        
                                        <?php 
                                        // Display top technology skills (those with current level > 0.3)
                                        $topSkills = [];
                                        $skillMap = [
                                            'ai_assisted_coding_current' => 'AI Coding',
                                            'mcp_servers_current' => 'MCP Servers',
                                            'ai_automations_current' => 'AI Automations',
                                            'startup_operations_current' => 'Startup Ops',
                                            'ai_security_current' => 'AI Security',
                                            'ai_infrastructure_current' => 'AI Infrastructure',
                                            'rag_current' => 'RAG',
                                            'local_models_current' => 'Local Models',
                                            'supervised_fine_tuning_current' => 'Fine Tuning'
                                        ];
                                        
                                        foreach ($skillMap as $field => $label) {
                                            if (!empty($member[$field]) && $member[$field] > 0.3) {
                                                $topSkills[] = $label . ' (' . number_format($member[$field], 1) . ')';
                                            }
                                        }
                                        ?>
                                        
                                        <?php if (!empty($topSkills)): ?>
                                            <div class="mt-2">
                                                <small class="text-muted d-block">AI/Tech Skills:</small>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach (array_slice($topSkills, 0, 3) as $skill): ?>
                                                        <span class="badge bg-success"><?php echo htmlspecialchars($skill); ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($topSkills) > 3): ?>
                                                        <span class="badge bg-success">+<?php echo count($topSkills) - 3; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    
                                        
                                        <?php if (!empty($member['bio'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted"><?php echo htmlspecialchars($member['bio']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Stats and Actions -->
                                <div class="flex-shrink-0 text-end">
                                    <div class="mb-2">
                                        <span class="badge bg-primary me-1"><?php echo $member['project_count']; ?> projects</span>
                                        <span class="badge bg-success"><?php echo $member['task_count']; ?> tasks</span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted d-block">
                                            <i class="bi bi-calendar"></i> Joined <?php echo date('M Y', strtotime($member['created_at'])); ?>
                                        </small>
                                    </div>
                                    <a href="/team-member?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-person-lines-fill"></i> View Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

</main>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>