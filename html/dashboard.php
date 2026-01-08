<?php
/**
 * Dashboard Page
 * 
 * Main dashboard based on Bootstrap dashboard example
 */

$page_title = 'Dashboard';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/Task.php';
require_once 'classes/BlogPost.php';
require_once 'classes/BlogCategory.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Initialize classes
$blogPost = new BlogPost();
$blogCategory = new BlogCategory();
$community = new Community();

// Get current community data
$currentCommunity = $currentCommunityId ? $community->getById($currentCommunityId) : null;
$communityStats = $currentCommunityId ? $community->getStatistics($currentCommunityId) : ['members' => 0, 'projects' => 0, 'posts' => 0];
$communityMembers = $currentCommunityId ? $community->getMembers($currentCommunityId, ['limit' => 6]) : [];

// Get all communities the user belongs to
$userCommunities = $community->getUserCommunities($currentUserId);

// Get user's plan
$userPlan = 'all'; // default
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT plan FROM users WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $userData = $stmt->fetch();
    if ($userData && $userData['plan']) {
        $userPlan = $userData['plan'];
    }
} catch (PDOException $e) {
    error_log("Error fetching user plan: " . $e->getMessage());
}

// Get filters from query string
$categoryId = $_GET['category'] ?? null;
$search = $_GET['search'] ?? '';
$orderBy = $_GET['order'] ?? 'recent';

// Build filters for community posts
$filters = [
    'community_id' => $currentCommunityId,
    'status' => 'published',
    'order_by' => $orderBy,
    'search' => $search,
    'limit' => 50,  // Increased to include all posts including featured
    'offset' => 0
];

// Handle special "general" category (posts without categories)
if ($categoryId === 'general') {
    $filters['no_category'] = true;
} elseif ($categoryId) {
    $filters['category_id'] = $categoryId;
}

// Get community posts (including featured posts)
// If no community is selected, return empty array instead of all posts
if ($currentCommunityId === null) {
    $posts = [];
} else {
    $posts = $blogPost->getList($filters);
}

// Check which posts the user has liked
$likedPosts = [];
if ($currentUserId) {
    foreach ($posts as $post) {
        if ($blogPost->hasLiked($post['id'], $currentUserId)) {
            $likedPosts[$post['id']] = true;
        }
    }
}

// Get categories for sidebar
$categories = $blogCategory->getByCommunity($currentCommunityId);

// Get total post count for "All Posts" tab
$totalPostsFilter = [
    'community_id' => $currentCommunityId,
    'status' => 'published',
    'search' => $search
];
// If no community is selected, return empty array instead of all posts
if ($currentCommunityId === null) {
    $totalPosts = [];
} else {
    $totalPosts = $blogPost->getList($totalPostsFilter);
}

// Check if user can create posts
$canCreatePost = false;
$userRole = $community->isMember($currentCommunityId, $currentUserId);
if ($userRole === 'admin') {
    $canCreatePost = true;
}

// Get user stats from database
try {
    $db = getDB();
    
    // Count user's active projects in current community
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT pm.project_id) as project_count
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.user_id = ? AND pm.status = 'approved' AND p.status = 'active' 
        AND p.community_id = ?
    ");
    $stmt->execute([$currentUserId, $currentCommunityId]);
    $projectCount = $stmt->fetch()['project_count'];
    
    // Count user's assigned tasks in current community
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ta.task_id) as task_count
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE ta.user_id = ? AND ta.unassigned_at IS NULL
        AND t.community_id = ?
    ");
    $stmt->execute([$currentUserId, $currentCommunityId]);
    $taskCount = $stmt->fetch()['task_count'];
    
    // Count user's feature submissions in current community
    $stmt = $db->prepare("
        SELECT COUNT(*) as feature_count
        FROM features f
        WHERE f.submitted_by = ? AND f.community_id = ?
    ");
    $stmt->execute([$currentUserId, $currentCommunityId]);
    $featureCount = $stmt->fetch()['feature_count'];
    
    // Count user's enrolled courses in current community
    $stmt = $db->prepare("
        SELECT COUNT(*) as course_count
        FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.id
        WHERE ce.user_id = ? AND ce.status IN ('enrolled', 'in_progress', 'completed')
        AND c.community_id = ?
    ");
    $stmt->execute([$currentUserId, $currentCommunityId]);
    $courseCount = $stmt->fetch()['course_count'];
    
    // Count user's completed tasks in current community
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT ta.task_id) as completed_count
        FROM task_assignments ta
        JOIN tasks t ON ta.task_id = t.id
        WHERE ta.user_id = ? AND ta.unassigned_at IS NULL AND t.status = 'done'
        AND t.community_id = ?
    ");
    $stmt->execute([$currentUserId, $currentCommunityId]);
    $completedCount = $stmt->fetch()['completed_count'];
    
    // Count user's recommended courses
    $recommendedCount = 0;
    try {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT cr.course_id) as recommended_count
            FROM course_recommendations cr
            JOIN courses c ON cr.course_id = c.id
            WHERE cr.user_id = ? 
            AND cr.is_active = 1 
            AND cr.dismissed_at IS NULL
            AND cr.enrolled_at IS NULL
        ");
        $stmt->execute([$currentUserId]);
        $recommendedCount = $stmt->fetch()['recommended_count'];
    } catch (PDOException $e) {
        // Table might not exist, default to 0
        $recommendedCount = 0;
    }
    
    $stats = [
        'projects' => $projectCount,
        'tasks' => $taskCount,
        'features' => $featureCount,
        'completed' => $completedCount,
        'courses' => $courseCount,
        'recommended' => $recommendedCount
    ];
} catch (PDOException $e) {
    error_log("Dashboard stats query error: " . $e->getMessage());
    $stats = [
        'projects' => 0,
        'tasks' => 0,
        'features' => 0,
        'completed' => 0,
        'courses' => 0,
        'recommended' => 0
    ];
}

// Get system status message
$systemMessage = null;
try {
    $stmt = $db->prepare("
        SELECT message 
        FROM system_status 
        WHERE is_active = 1 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $systemMessage = $result['message'];
    }
} catch (PDOException $e) {
    error_log("System status query error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<style>
.post-card {
    transition: all 0.2s ease;
}
.post-card:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
}
.post-card .row {
    align-items: center;
}
.like-button {
    transition: all 0.2s ease;
}
.like-button:hover {
    color: #dc3545 !important;
}
.like-button.liked {
    color: #dc3545 !important;
}
#course-progress-card .course-section {
    border-left: 3px solid #0d6efd;
    padding-left: 10px;
}
#course-progress-card .course-section:not(:last-child) {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 15px;
}
#course-progress-card .list-unstyled li {
    font-size: 0.95rem;
}
#course-progress-card .badge {
    font-size: 0.75rem;
}
/* Dark background stat cards */
/* Removed conflicting color rules - using inline styles instead */
</style>

<!-- Main content -->
<main class="container-fluid px-4">
            <div id="dashboard-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div class="d-flex align-items-center">
                    <h1 class="h2 mb-0"><?php echo $currentCommunity ? htmlspecialchars($currentCommunity['name']) : 'Dashboard'; ?></h1>
                </div>
                <?php if ($canCreatePost && $userPlan !== 'developer'): ?>
                <div id="dashboard-toolbar" class="btn-toolbar mb-2 mb-md-0">
                    <a href="blog-edit.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>New Community Post
                    </a>
                </div>
                <?php endif; ?>
            </div>


            <?php if ($userPlan !== 'developer'): ?>
            <!-- Stats cards -->
            <div id="dashboard-stats-row" class="row" 
                 hx-get="/htmx/dashboard-stats.php" 
                 hx-trigger="load, every 30s"
                 hx-swap="innerHTML">
                <div id="stats-card-projects" class="col-xl-3 col-md-6 mb-4">
                    <a href="/project-categories" class="text-decoration-none">
                        <div id="projects-card" class="card border-left-success shadow h-100 py-2" style="background-color: #6c757d;">
                            <div id="projects-card-body" class="card-body">
                                <div id="projects-card-content" class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: black !important;">Projects</div>
                                        <div class="h5 mb-0 font-weight-bold" style="color: black !important;"><?php echo $stats['projects']; ?></div>
                                        <small style="color: black !important;">Active projects</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-folder fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <?php if ($currentCommunityId !== null): ?>
                <div id="stats-card-courses" class="col-xl-3 col-md-6 mb-4">
                    <a href="/programs" class="text-decoration-none">
                        <div id="courses-card" class="card border-left-primary shadow h-100 py-2" style="background-color: #6c757d;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: black !important;">Courses</div>
                                        <div class="h5 mb-0 font-weight-bold" style="color: black !important;"><?php echo $stats['courses']; ?></div>
                                        <small style="color: black !important;">Enrolled courses</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-book fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div id="stats-card-learning-plan" class="col-xl-3 col-md-6 mb-4">
                    <a href="/recommended-courses" class="text-decoration-none">
                        <div id="learning-plan-card" class="card border-left-warning shadow h-100 py-2" style="background-color: #6c757d;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: black !important;">Learning Plan</div>
                                        <div class="h5 mb-0 font-weight-bold" style="color: black !important;"><?php echo $stats['recommended'] ?? 0; ?></div>
                                        <small style="color: black !important;">Recommended courses</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-mortarboard fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <div id="stats-card-survey" class="col-xl-3 col-md-6 mb-4">
                    <a href="survey" class="text-decoration-none">
                        <div id="survey-card" class="card border-left-info shadow h-100 py-2" style="background-color: #6c757d;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: black !important;">Skills Survey</div>
                                        <div class="h5 mb-0 font-weight-bold" style="color: black !important;">
                                            <?php 
                                            // Check survey completion status
                                            try {
                                                $stmt = $db->prepare("
                                                    SELECT sc.completion_percentage 
                                                    FROM survey_completions sc
                                                    JOIN surveys s ON sc.survey_id = s.id
                                                    WHERE sc.user_id = ? AND s.type = 'skills' AND s.community_id = ?
                                                    ORDER BY sc.started_at DESC
                                                    LIMIT 1
                                                ");
                                                $stmt->execute([$currentUserId, $currentCommunityId]);
                                                $completion = $stmt->fetch();
                                                echo $completion ? $completion['completion_percentage'] . '%' : 'Start';
                                            } catch (PDOException $e) {
                                                echo 'Start';
                                            }
                                            ?>
                                        </div>
                                        <small style="color: black !important;">Complete your profile</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clipboard-check fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Active Sprints Section -->
            <?php
            // Get active sprints where current user is the project manager or sprint creator
            try {
                $sprintStmt = $db->prepare("
                    SELECT s.*, p.name as project_name, p.id as project_id,
                           COUNT(DISTINCT swi.work_item_id) as item_count,
                           COUNT(DISTINCT CASE WHEN wi.status = 'done' THEN wi.id END) as completed_items,
                           (SELECT COUNT(*) FROM project_dev_prompts pdp 
                            WHERE pdp.sprint_id = s.id AND pdp.status = 'executing') as executing_prompts,
                           (SELECT COUNT(*) FROM project_dev_prompts pdp 
                            WHERE pdp.sprint_id = s.id AND pdp.status = 'failed'
                            AND NOT EXISTS (
                                SELECT 1 FROM project_dev_prompts pdp2 
                                WHERE pdp2.sprint_id = s.id 
                                AND pdp2.status IN ('executing', 'completed')
                                AND pdp2.created_at > pdp.created_at
                            )) as has_error,
                           (SELECT COUNT(*) FROM project_dev_prompts pdp 
                            WHERE pdp.sprint_id = s.id) as total_prompts
                    FROM sprints s
                    JOIN projects p ON s.project_id = p.id
                    LEFT JOIN sprint_work_items swi ON s.id = swi.sprint_id
                    LEFT JOIN work_items wi ON swi.work_item_id = wi.id
                    WHERE s.status = 'active'
                    AND (p.project_manager_id = ? OR s.created_by = ?)
                    AND p.community_id = ?
                    GROUP BY s.id
                    ORDER BY s.start_date DESC
                ");
                $sprintStmt->execute([$currentUserId, $currentUserId, $currentCommunityId]);
                $activeSprints = $sprintStmt->fetchAll();
            } catch (PDOException $e) {
                $activeSprints = [];
                error_log("Active sprints query error: " . $e->getMessage());
            }
            ?>
            
            <?php if (!empty($activeSprints)): ?>
            <div id="active-sprints-section" class="row mb-4">
                <div class="col-12">
                    <h3 class="h4 mb-3">My Active Sprints</h3>
                </div>
                <?php foreach ($activeSprints as $sprint): 
                    $progress = $sprint['item_count'] > 0 ? round(($sprint['completed_items'] / $sprint['item_count']) * 100) : 0;
                    $daysLeft = max(0, floor((strtotime($sprint['end_date']) - time()) / 86400));
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                    <a href="/sprint-dashboard.php?id=<?php echo $sprint['id']; ?>" class="text-decoration-none">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            <?php echo htmlspecialchars($sprint['project_name']); ?>
                                        </div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                                            <?php echo htmlspecialchars($sprint['name']); ?>
                                        </div>
                                        <?php
                                        // Determine prompt status
                                        $promptStatus = 'ready';
                                        $promptStatusClass = 'success';
                                        $promptStatusIcon = 'check-circle';
                                        
                                        if ($sprint['executing_prompts'] > 0) {
                                            $promptStatus = 'executing';
                                            $promptStatusClass = 'warning';
                                            $promptStatusIcon = 'arrow-clockwise';
                                        } elseif ($sprint['has_error'] > 0) {
                                            $promptStatus = 'error';
                                            $promptStatusClass = 'danger';
                                            $promptStatusIcon = 'exclamation-circle';
                                        } elseif ($progress >= 100) {
                                            $promptStatus = 'complete';
                                            $promptStatusClass = 'primary';
                                            $promptStatusIcon = 'check-circle-fill';
                                        } elseif ($sprint['total_prompts'] == 0) {
                                            $promptStatus = 'no prompts';
                                            $promptStatusClass = 'secondary';
                                            $promptStatusIcon = 'dash-circle';
                                        }
                                        ?>
                                        <div class="mt-2 mb-2">
                                            <span class="badge bg-<?php echo $promptStatusClass; ?>">
                                                <i class="bi bi-<?php echo $promptStatusIcon; ?> me-1"></i>
                                                <?php echo ucfirst($promptStatus); ?>
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $progress; ?>%"
                                                     aria-valuenow="<?php echo $progress; ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                            <small class="text-muted mt-1 d-block">
                                                <?php echo $sprint['completed_items']; ?>/<?php echo $sprint['item_count']; ?> items • 
                                                <?php echo $daysLeft; ?> days left
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-kanban fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php /* Commented out system status banner
            <?php if ($systemMessage): ?>
            <!-- System Status Banner -->
            <div id="system-status-banner" class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-info d-flex align-items-center system-status-banner" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($systemMessage); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            */ ?>

            <?php if ($userPlan !== 'developer'): ?>
            <!-- Community Posts Section -->
            <div id="community-posts-section" class="row">
                <div id="posts-main-column" class="col-lg-8">
                    <!-- Category Tabs -->
                    <div id="category-tabs-container" class="mb-4">
                        <h3 class="h4 mb-3">Community Posts</h3>
                        <ul class="nav nav-tabs mb-0">
                            <li class="nav-item">
                                <a class="nav-link <?php echo !$categoryId ? 'active' : ''; ?>" 
                                   href="dashboard<?php echo $search ? '?search='.urlencode($search) : ''; ?>">
                                    All Posts
                                    <span class="badge bg-secondary ms-1"><?php echo count($totalPosts); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $categoryId == 'general' ? 'active' : ''; ?>" 
                                   href="?category=general<?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                    <i class="bi bi-chat-square-text me-1" style="color: #6c757d"></i>
                                    General Topics
                                    <span class="badge bg-secondary ms-1">
                                        <?php 
                                        // Count posts without categories
                                        $generalCount = 0;
                                        foreach ($totalPosts as $post) {
                                            $postCategories = $blogPost->getCategories($post['id']);
                                            if (empty($postCategories)) {
                                                $generalCount++;
                                            }
                                        }
                                        echo $generalCount;
                                        ?>
                                    </span>
                                </a>
                            </li>
                            <?php foreach ($categories as $category): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $categoryId == $category['id'] ? 'active' : ''; ?>" 
                                   href="?category=<?php echo $category['id']; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                    <?php if ($category['icon']): ?>
                                    <i class="<?php echo htmlspecialchars($category['icon']); ?> me-1" 
                                       style="color: <?php echo htmlspecialchars($category['color']); ?>"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                    <span class="badge bg-secondary ms-1"><?php echo $category['post_count']; ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <!-- Community Posts List -->
                    <div id="posts-list-card" class="card shadow mb-4">
                        <div id="posts-card-body" class="card-body">
                            <!-- Search Bar with Create Post Button -->
                            <div id="posts-search-bar" class="mb-4 d-flex gap-2" x-data="{ searchTerm: '<?php echo htmlspecialchars($search); ?>' }">
                                <form method="get" action="" class="flex-grow-1">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Search posts..." 
                                               x-model="searchTerm"
                                               @keyup.enter="$el.form.submit()"
                                               style="max-width: 300px;">
                                        <?php if ($categoryId): ?>
                                        <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                                        <?php endif; ?>
                                        <button class="btn btn-outline-secondary" type="submit" :disabled="searchTerm.length === 0 && '<?php echo $search; ?>' === ''">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </form>
                                <?php if ($canCreatePost && $userPlan !== 'developer'): ?>
                                <a href="blog-edit.php" class="btn btn-primary" title="Create New Post">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (empty($posts)): ?>
                            <div id="no-posts-message" class="text-center py-5">
                                <i class="bi bi-newspaper display-1 text-muted"></i>
                                <h3 class="mt-3">No posts yet</h3>
                                <p class="text-muted">Be the first to share something with the community!</p>
                                <?php if ($canCreatePost): ?>
                                <a href="blog-edit.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-plus-circle me-2"></i>Create First Post
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                                
                            <div id="community-posts-list" class="community-posts">
                                <?php foreach ($posts as $post): ?>
                                <article class="border-bottom pb-4 mb-4 post-card" onclick="window.location.href='blog-detail.php?id=<?php echo $post['id']; ?>'" style="cursor: pointer;">
                                    <div class="row">
                                        <div class="col-md-<?php echo ($post['featured_image'] || $post['video_url'] || $post['video_embed_code']) ? '8' : '12'; ?>">
                                            <div id="post-header-<?php echo $post['id']; ?>" class="d-flex justify-content-between align-items-start mb-3">
                                                <div id="post-author-<?php echo $post['id']; ?>" class="d-flex align-items-center">
                                                    <?php if ($post['author_avatar']): ?>
                                                    <img src="serve-avatar.php?user_id=<?php echo $post['author_id']; ?>" 
                                                         class="rounded-circle me-3" width="48" height="48"
                                                         alt="<?php echo htmlspecialchars($post['author_name']); ?>">
                                                    <?php else: ?>
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" 
                                                         style="width: 48px; height: 48px;">
                                                        <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($post['author_name']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo timeAgo($post['published_at']); ?>
                                                            <?php if ($post['is_pinned']): ?>
                                                            <i class="bi bi-pin-angle-fill ms-1" title="Pinned"></i>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($post['is_featured']): ?>
                                                    <span class="badge bg-warning me-2">
                                                        <i class="bi bi-star-fill me-1"></i>Featured
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($post['video_url']): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="bi bi-play-circle me-1"></i>Video
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <h2 class="h5 mb-2">
                                                <?php echo htmlspecialchars($post['title']); ?>
                                            </h2>

                                            <p class="text-muted mb-3">
                                                <?php 
                                                $excerpt = $post['excerpt'] ?: strip_tags($post['content']);
                                                echo htmlspecialchars(substr($excerpt, 0, 200)); 
                                                ?>...
                                            </p>

                                            <div id="post-footer-<?php echo $post['id']; ?>" class="d-flex justify-content-between align-items-center">
                                                <div id="post-interactions-<?php echo $post['id']; ?>" class="d-flex gap-3">
                                                    <span class="text-muted">
                                                        <i class="bi bi-eye me-1"></i><?php echo number_format($post['view_count']); ?>
                                                    </span>
                                                    <a href="#" class="text-decoration-none like-button <?php echo isset($likedPosts[$post['id']]) ? 'liked' : 'text-muted'; ?>" 
                                                       hx-post="/htmx/toggle-like.php" 
                                                       hx-vals='{"post_id": "<?php echo $post['id']; ?>"}'
                                                       hx-swap="outerHTML"
                                                       onclick="event.preventDefault(); event.stopPropagation();">
                                                        <i class="bi <?php echo isset($likedPosts[$post['id']]) ? 'bi-heart-fill' : 'bi-heart'; ?> me-1"></i>
                                                        <span class="like-count"><?php echo number_format($post['like_count']); ?></span>
                                                    </a>
                                                    <span class="text-muted">
                                                        <i class="bi bi-chat me-1"></i><?php echo number_format($post['comment_count']); ?>
                                                    </span>
                                                </div>
                                                <?php if ($post['tags']): ?>
                                                <div id="post-tags-<?php echo $post['id']; ?>" class="tags">
                                                    <?php 
                                                    $tags = explode(',', $post['tags']);
                                                    foreach (array_slice($tags, 0, 3) as $tag): 
                                                    ?>
                                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($post['last_comment_at'] && $post['comment_count'] > 0): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-chat-left-text me-1"></i>
                                                    Last comment 
                                                    <?php if ($post['last_commenter_name']): ?>
                                                    by <?php echo htmlspecialchars($post['last_commenter_name']); ?>
                                                    <?php endif; ?>
                                                    <?php echo timeAgo($post['last_comment_at']); ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($post['featured_image'] || $post['video_url'] || $post['video_embed_code']): ?>
                                        <div class="col-md-4">
                                            <?php if ($post['video_url'] || $post['video_embed_code']): ?>
                                            <div class="position-relative">
                                                <?php 
                                                $thumbnail_url = $post['featured_image'];
                                                
                                                // If no featured image, try to get YouTube thumbnail
                                                if (!$thumbnail_url && $post['video_url']) {
                                                    $youtube_id = null;
                                                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $post['video_url'], $matches)) {
                                                        $youtube_id = $matches[1];
                                                        $thumbnail_url = "https://img.youtube.com/vi/{$youtube_id}/mqdefault.jpg";
                                                    }
                                                }
                                                
                                                // Check video_embed_code for YouTube ID if still no thumbnail
                                                if (!$thumbnail_url && $post['video_embed_code']) {
                                                    if (preg_match('/(?:youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $post['video_embed_code'], $matches)) {
                                                        $youtube_id = $matches[1];
                                                        $thumbnail_url = "https://img.youtube.com/vi/{$youtube_id}/mqdefault.jpg";
                                                    }
                                                }
                                                ?>
                                                
                                                <?php if ($thumbnail_url): ?>
                                                <img src="<?php echo htmlspecialchars($thumbnail_url); ?>" 
                                                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($post['title']); ?>"
                                                     style="width: 100%; height: 150px; object-fit: cover;">
                                                <?php else: ?>
                                                <div class="bg-secondary rounded d-flex align-items-center justify-content-center" style="width: 100%; height: 150px;">
                                                    <i class="bi bi-play-circle display-4 text-white"></i>
                                                </div>
                                                <?php endif; ?>
                                                <div class="position-absolute top-50 start-50 translate-middle">
                                                    <i class="bi bi-play-circle-fill text-white" style="font-size: 2.5rem; filter: drop-shadow(0 0 10px rgba(0,0,0,0.5));"></i>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                                 class="img-fluid rounded" alt="<?php echo htmlspecialchars($post['title']); ?>"
                                                 style="width: 100%; height: 150px; object-fit: cover;">
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="sidebar-column" class="col-lg-4">
                    <!-- Community Info Card -->
                    <?php if ($currentCommunity): ?>
                    <div id="community-info-card" class="card shadow mb-4">
                        <div id="community-info-body" class="card-body">
                            <div id="community-header" class="d-flex align-items-center mb-3">
                                <?php if ($currentCommunity['logo_url']): ?>
                                <img src="<?php echo htmlspecialchars($currentCommunity['logo_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($currentCommunity['name']); ?>" 
                                     class="rounded-circle me-3" 
                                     style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px; font-size: 20px;">
                                    <?php echo strtoupper(substr($currentCommunity['name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($currentCommunity['name']); ?></h5>
                                    <small class="text-muted"><?php echo htmlspecialchars($currentCommunity['slug']); ?>.com</small>
                                </div>
                            </div>                            
                            <?php if ($currentCommunity['description']): ?>
                            <p class="text-muted small mb-3"><?php echo htmlspecialchars($currentCommunity['description']); ?></p>
                            <?php endif; ?>
                            
                            <div id="community-stats" class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo number_format($communityStats['members']); ?></div>
                                    <small class="text-muted">Members</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo number_format($communityStats['active_members']); ?></div>
                                    <small class="text-muted">Online</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo count(array_filter($communityMembers, function($m) { return in_array($m['role'], ['admin', 'owner']); })); ?></div>
                                    <small class="text-muted">Admins</small>
                                </div>
                            </div>
                            
                            <div id="community-members-avatars" class="mb-3 text-center">
                                <?php foreach (array_slice($communityMembers, 0, 6) as $member): ?>
                                <?php if ($member['avatar_url']): ?>
                                <img src="serve-avatar.php?user_id=<?php echo $member['user_id']; ?>" 
                                     class="rounded-circle" 
                                     style="width: 32px; height: 32px; object-fit: cover; margin: 0 -5px; border: 2px solid white;"
                                     alt="<?php echo htmlspecialchars($member['name']); ?>"
                                     title="<?php echo htmlspecialchars($member['name']); ?>">
                                <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" 
                                     style="width: 32px; height: 32px; margin: 0 -5px; border: 2px solid white; font-size: 12px;"
                                     title="<?php echo htmlspecialchars($member['name']); ?>">
                                    <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($communityStats['members'] > 6): ?>
                                <div class="rounded-circle bg-light text-muted d-inline-flex align-items-center justify-content-center" 
                                     style="width: 32px; height: 32px; margin: 0 -5px; border: 2px solid white; font-size: 11px;">
                                    +<?php echo $communityStats['members'] - 6; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($canCreatePost || $community->isAdmin($currentCommunityId, $currentUserId)): ?>
                            <div id="community-settings-button" class="text-center">
                                <a href="admin/communities.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-gear me-1"></i>SETTINGS
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Course Progress Card -->
                    <div id="course-progress-card" class="card shadow mb-4" x-data="{ expanded: true }">
                        <div id="course-progress-header" class="card-header" @click="expanded = !expanded" style="cursor: pointer;">
                            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-mortarboard me-2"></i>My Course Progress</span>
                                <i class="bi" :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            </h5>
                        </div>
                        <div id="course-progress-body" class="card-body" x-show="expanded" x-transition
                             hx-get="/htmx/course-progress.php"
                             hx-trigger="load, every 60s"
                             hx-swap="innerHTML">
                            <?php
                            // Fetch enrolled courses with lesson progress and quiz scores
                            try {
                                $stmt = $db->prepare("
                                    SELECT 
                                        c.id as course_id,
                                        c.title as course_title,
                                        l.id as lesson_id,
                                        l.title as lesson_title,
                                        l.lesson_type,
                                        lp.status as lesson_status,
                                        lp.completed_at,
                                        lp.started_at,
                                        (SELECT COUNT(*) FROM quiz_attempts qa 
                                         JOIN quizzes q ON qa.quiz_id = q.id 
                                         WHERE q.lesson_id = l.id 
                                         AND qa.user_id = ce.user_id
                                         AND qa.status = 'completed') as quiz_attempt_count
                                    FROM course_enrollments ce
                                    INNER JOIN courses c ON ce.course_id = c.id
                                    INNER JOIN lessons l ON l.course_id = c.id
                                    LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ce.user_id
                                    WHERE ce.user_id = ? 
                                    AND ce.status IN ('enrolled', 'in_progress', 'completed')
                                    AND c.status = 'published'
                                    AND l.status = 'published'
                                    AND (
                                        lp.id IS NOT NULL  -- Lesson has been started
                                        OR EXISTS (        -- OR quiz has been attempted
                                            SELECT 1 FROM quiz_attempts qa2
                                            JOIN quizzes q2 ON qa2.quiz_id = q2.id
                                            WHERE q2.lesson_id = l.id
                                            AND qa2.user_id = ce.user_id
                                            AND qa2.status = 'completed'
                                        )
                                    )
                                    ORDER BY c.title, l.order_index
                                ");
                                $stmt->execute([$currentUserId]);
                                $courseProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (empty($courseProgress)) {
                                    // Check if user is enrolled but hasn't started any lessons
                                    $enrollmentCheck = $db->prepare("
                                        SELECT COUNT(*) as enrolled_count
                                        FROM course_enrollments ce
                                        WHERE ce.user_id = ? 
                                        AND ce.status IN ('enrolled', 'in_progress', 'completed')
                                    ");
                                    $enrollmentCheck->execute([$currentUserId]);
                                    $enrolledCount = $enrollmentCheck->fetch()['enrolled_count'];
                                    
                                    if ($enrolledCount > 0) {
                                        echo '<p class="text-muted mb-0">You are enrolled in courses but haven\'t started any lessons yet.</p>';
                                        echo '<a href="/my-courses" class="btn btn-primary btn-sm mt-3"><i class="bi bi-play-circle me-2"></i>View My Courses</a>';
                                    } else {
                                        echo '<p class="text-muted mb-0">You are not enrolled in any courses yet.</p>';
                                        echo '<a href="/programs" class="btn btn-primary btn-sm mt-3"><i class="bi bi-plus-circle me-2"></i>Browse Courses</a>';
                                    }
                                } else {
                                    $currentCourseId = null;
                                    foreach ($courseProgress as $progress) {
                                        // Start new course section
                                        if ($currentCourseId !== $progress['course_id']) {
                                            if ($currentCourseId !== null) {
                                                echo '</ul></div>'; // Close previous course
                                            }
                                            $currentCourseId = $progress['course_id'];
                                            echo '<div class="course-section mb-3">';
                                            echo '<h6 class="fw-bold mb-2">' . htmlspecialchars($progress['course_title']) . '</h6>';
                                            echo '<ul class="list-unstyled ms-3">';
                                        }
                                        
                                        // Display lesson with quiz score if applicable
                                        echo '<li class="mb-2">';
                                        echo '<div class="d-flex justify-content-between align-items-center">';
                                        echo '<div>';
                                        
                                        // Lesson status icon
                                        if ($progress['lesson_status'] === 'completed') {
                                            echo '<i class="bi bi-check-circle-fill text-success me-2"></i>';
                                        } else {
                                            echo '<i class="bi bi-dot text-muted me-2"></i>';
                                        }
                                        
                                        echo '<span>' . htmlspecialchars($progress['lesson_title']) . '</span>';
                                        echo '</div>';
                                        
                                        // Check if this lesson has a quiz and display score
                                        echo '<div class="text-end">';
                                        
                                        // Try to fetch quiz score for any lesson that has a quiz
                                        try {
                                            // First check if a quiz exists for this lesson
                                            $quizCheckStmt = $db->prepare("SELECT id FROM quizzes WHERE lesson_id = ? LIMIT 1");
                                            $quizCheckStmt->execute([$progress['lesson_id']]);
                                            $quizExists = $quizCheckStmt->fetch();
                                            
                                            if ($quizExists) {
                                                // Fetch quiz score
                                                $scoreStmt = $db->prepare("
                                                    SELECT 
                                                        qa.score_achieved,
                                                        qa.points_earned,
                                                        qa.total_points,
                                                        COUNT(qr.id) as total_questions,
                                                        SUM(CASE WHEN qr.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                                                    FROM quiz_attempts qa
                                                    JOIN quizzes q ON qa.quiz_id = q.id
                                                    LEFT JOIN quiz_responses qr ON qr.attempt_id = qa.id
                                                    WHERE qa.user_id = ? 
                                                    AND q.lesson_id = ? 
                                                    AND qa.status = 'completed'
                                                    GROUP BY qa.id
                                                    ORDER BY qa.end_time DESC 
                                                    LIMIT 1
                                                ");
                                                $scoreStmt->execute([$currentUserId, $progress['lesson_id']]);
                                                $quizData = $scoreStmt->fetch();
                                                
                                                if ($quizData && $quizData['total_questions'] > 0) {
                                                    // Use correct_answers if available, otherwise calculate from score_achieved
                                                    $correctAnswers = $quizData['correct_answers'] ?? round($quizData['score_achieved'] * $quizData['total_questions'] / 100);
                                                    $percentage = $quizData['score_achieved'] ?? round(($correctAnswers / $quizData['total_questions']) * 100);
                                                    $scoreClass = $percentage >= 70 ? 'text-success' : ($percentage >= 50 ? 'text-warning' : 'text-danger');
                                                    echo '<small class="' . $scoreClass . ' fw-bold">';
                                                    echo $correctAnswers . '/' . $quizData['total_questions'] . ' (' . $percentage . '%)';
                                                    echo '</small>';
                                                } else {
                                                    echo '<small class="text-muted">Not attempted</small>';
                                                }
                                            }
                                        } catch (PDOException $e) {
                                            // Silently fail if tables don't exist
                                        }
                                        
                                        echo '</div>';
                                        
                                        echo '</div>';
                                        echo '</li>';
                                    }
                                    
                                    // Close last course
                                    if ($currentCourseId !== null) {
                                        echo '</ul></div>';
                                    }
                                }
                            } catch (PDOException $e) {
                                error_log("Course progress query error: " . $e->getMessage());
                                echo '<p class="text-danger">Unable to load course progress.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

</main>



<?php require_once 'includes/footer.php'; ?>