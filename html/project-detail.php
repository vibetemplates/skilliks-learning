<?php
/**
 * Project Detail Page
 * 
 * Shows project details and allows joining/management
 */

$page_title = 'Project Details';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/FileManager.php';

// Require login
requireLogin();

// Get project ID
$projectId = $_GET['id'] ?? null;
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
$isAdmin = $userObj->isAdmin($currentUserId);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'join' && !$isMember) {
        $result = $projectObj->joinProject($projectId, $currentUserId);
        if ($result['success']) {
            setFlashMessage('success', 'Successfully joined the project!');
            $isMember = true;
        } else {
            error_log("Join project failed for user " . $currentUserId . " project " . $projectId . ": " . $result['error']);
            setFlashMessage('error', $result['error']);
        }
        header('Location: /project-detail?id=' . $projectId);
        exit;
    }
    
    // Handle status change (admin only)
    if ($_POST['action'] === 'change_status' && $isAdmin) {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['planning', 'active', 'completed', 'archived'])) {
            try {
                $db = getDB();
                $stmt = $db->prepare("UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $projectId]);
                setFlashMessage('success', 'Project status updated successfully!');
                
                // Refresh project data
                $project = $projectObj->findById($projectId);
            } catch (PDOException $e) {
                error_log("Status change failed: " . $e->getMessage());
                setFlashMessage('error', 'Failed to update project status.');
            }
        } else {
            setFlashMessage('error', 'Invalid status selected.');
        }
        header('Location: /project-detail?id=' . $projectId);
        exit;
    }
}

// Get project members
$members = $projectObj->getMembers($projectId);

// Get project files
$fileManager = new FileManager();
$projectFiles = $fileManager->getFiles('project', $projectId);

// Get project features
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT f.*, u.first_name, u.last_name 
        FROM features f
        LEFT JOIN users u ON f.submitted_by = u.id
        WHERE f.project_id = ? 
        ORDER BY 
            CASE f.priority 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            f.created_at DESC
    ");
    $stmt->execute([$projectId]);
    $projectFeatures = $stmt->fetchAll();
} catch (PDOException $e) {
    $projectFeatures = [];
}

// Get recommended courses for this project
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, pca.assignment_type, pca.due_date, pca.notes,
               u.first_name, u.last_name
        FROM project_course_assignments pca
        JOIN courses c ON pca.course_id = c.id
        LEFT JOIN users u ON pca.assigned_by = u.id
        WHERE pca.project_id = ? AND pca.is_active = 1
        ORDER BY 
            CASE pca.assignment_type 
                WHEN 'required' THEN 1
                WHEN 'recommended' THEN 2
                WHEN 'optional' THEN 3
            END,
            pca.assigned_at DESC
    ");
    $stmt->execute([$projectId]);
    $projectCourses = $stmt->fetchAll();
} catch (PDOException $e) {
    $projectCourses = [];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    
        

        
        
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="pt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($project['name']); ?></li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <div class="d-flex align-items-center">
                    <h1 class="h2 mb-0"><?php echo htmlspecialchars($project['name']); ?></h1>
                    <!-- Voting buttons -->
                    <div class="ms-4 d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-success vote-btn" 
                                data-type="project" data-id="<?php echo $projectId; ?>" data-vote="up"
                                title="Upvote this project">
                            <i class="bi bi-arrow-up-circle"></i>
                        </button>
                        <span class="mx-2 fs-5 vote-count" id="vote-count-project-<?php echo $projectId; ?>">
                            <?php echo $project['vote_count'] ?? 0; ?>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-danger vote-btn" 
                                data-type="project" data-id="<?php echo $projectId; ?>" data-vote="down"
                                title="Downvote this project">
                            <i class="bi bi-arrow-down-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if (!empty($project['test_url'])): ?>
                        <a href="<?php echo htmlspecialchars($project['test_url']); ?>" target="_blank" class="btn btn-primary me-2">
                            <i class="bi bi-play-circle"></i> Run Project
                        </a>
                    <?php endif; ?>
                    <?php if ($isAdmin || $isCreator): ?>
                        <a href="/project-edit?id=<?php echo $projectId; ?>" class="btn btn-secondary me-2">
                            <i class="bi bi-pencil"></i> Edit Project
                        </a>
                    <?php endif; ?>
                    <?php if (!$isMember): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="join">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Join Project
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-success fs-6">
                            <i class="bi bi-check-circle"></i> Member
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isMember): ?>
            <!-- Quick Actions for Members -->
            <div class="card mb-4" id="quick-actions-card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <!-- First row of buttons -->
                    <div class="row mb-3">
                        <div class="col-lg-2 mb-2">
                            <a href="/project-survey.php?project=<?php echo $projectId; ?>&survey_type=general" class="btn btn-secondary w-100">
                                <i class="bi bi-clipboard-check"></i> Project Survey
                            </a>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <a href="/work-items.php?project=<?php echo $projectId; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-kanban"></i> Work Items
                            </a>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <a href="/project-sprints.php?project=<?php echo $projectId; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-calendar3"></i> Sprints
                            </a>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <a href="/product-backlog.php?project=<?php echo $projectId; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-list-stars"></i> Product Backlog
                            </a>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <button type="button" class="btn btn-secondary w-100" onclick="showUploadModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                                <i class="bi bi-cloud-upload"></i> Upload Doc
                            </button>
                        </div>
                    </div>
                    <!-- Second row of buttons -->
                    <div class="row">
                        <div class="col-lg mb-2">
                            <a href="/project-features.php?project=<?php echo $projectId; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-stars"></i> Features
                            </a>
                        </div>
                        <div class="col-lg mb-2">
                            <a href="/project-artifacts.php?project=<?php echo $projectId; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-files"></i> Artifacts
                            </a>
                        </div>
                        <div class="col-lg mb-2">
                            <a href="/project-skills.php?project=<?php echo $projectId; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-tools"></i> Required Skills
                            </a>
                        </div>
                        <div class="col-lg mb-2">
                            <a href="/project-members.php?project=<?php echo $projectId; ?>" class="btn btn-secondary w-100">
                                <i class="bi bi-people"></i> Team Members
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Project Info Card -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Project Information</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?php echo htmlspecialchars($project['description'] ?: 'No description available'); ?></p>
                            
                            <div class="row mt-3">
                                <div class="col-sm-6">
                                    <strong>Course:</strong> 
                                    <?php if ($project['course_code']): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($project['course_code']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6">
                                    <strong>Status:</strong> 
                                    <?php 
                                    $statusClass = 'secondary';
                                    if ($project['status'] == 'active') $statusClass = 'success';
                                    elseif ($project['status'] == 'planning') $statusClass = 'warning';
                                    elseif ($project['status'] == 'completed') $statusClass = 'info';
                                    elseif ($project['status'] == 'archived') $statusClass = 'dark';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($project['status']); ?></span>
                                    
                                    <?php if ($isAdmin && $project['status'] == 'planning'): ?>
                                        <button type="button" class="btn btn-sm btn-success ms-2" data-bs-toggle="modal" data-bs-target="#statusChangeModal">
                                            <i class="bi bi-check-circle"></i> Activate
                                        </button>
                                    <?php elseif ($isAdmin): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#statusChangeModal">
                                            <i class="bi bi-arrow-repeat"></i> Change
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mt-2">
                                <div class="col-sm-6">
                                    <strong>Team Size:</strong> 
                                    <?php echo $project['member_count']; ?> members
                                </div>
                                <div class="col-sm-6">
                                    <strong>Created:</strong> 
                                    <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="row mt-2">
                                <div class="col-sm-6">
                                    <strong>Created by:</strong> 
                                    <?php echo htmlspecialchars($project['creator_first_name'] . ' ' . $project['creator_last_name']); ?>
                                </div>
                                <div class="col-sm-6">
                                    <strong>Created:</strong> 
                                    <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                </div>
                            </div>
                            
                            <?php if (isset($project['category_name'])): ?>
                            <div class="row mt-2">
                                <div class="col-sm-6">
                                    <strong>Category:</strong> 
                                    <a href="/projects.php?category=<?php echo $project['project_category_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($project['category_name']); ?>
                                    </a>
                                    <?php if ($project['category_skill_level'] && $project['category_skill_level'] !== 'all'): ?>
                                        <span class="badge bg-info ms-1"><?php echo ucfirst($project['category_skill_level']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($project['git_repository_url'])): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Git Repository:</strong> 
                                    <a href="<?php echo htmlspecialchars($project['git_repository_url']); ?>" target="_blank" class="text-decoration-none">
                                        <i class="bi bi-git"></i> <?php echo htmlspecialchars($project['git_repository_url']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($isProjectManagerOrAdmin): ?>
                            <div class="mt-3">
                                <a href="/project-git-settings.php?project_id=<?php echo $projectId; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-gear"></i> Configure Git Repository
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Comments Section -->
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Project Comments</h5>
                            <span class="badge bg-secondary" id="comment-count">0</span>
                        </div>
                        <div class="card-body">
                            <!-- Comment Form -->
                            <div class="mb-4">
                                <form id="comment-form">
                                    <div class="mb-3">
                                        <textarea class="form-control" id="comment-content" rows="3" 
                                                  placeholder="Share your thoughts about this project..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-chat-left-text"></i> Post Comment
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Comments List -->
                            <div id="comments-list">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading comments...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Stats -->
                <div class="col-lg-6">
                    <!-- Project Video Card -->
                    <?php if (!empty($project['video_url'])): ?>
                    <div class="card mb-3" id="project-video-card">
                        <div class="card-header">
                            <h5 class="mb-0">Project Video</h5>
                        </div>
                        <div class="card-body">
                            <div class="ratio ratio-16x9">
                                <?php 
                                $videoUrl = $project['video_url'];
                                // Convert YouTube watch URLs to embed URLs
                                $embedUrl = '';
                                
                                // Extract video ID from various YouTube URL formats
                                if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
                                    $videoId = '';
                                    
                                    // Handle youtube.com/watch?v=VIDEO_ID format
                                    if (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $videoUrl, $matches)) {
                                        $videoId = $matches[1];
                                    }
                                    // Handle youtu.be/VIDEO_ID format
                                    elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $videoUrl, $matches)) {
                                        $videoId = $matches[1];
                                    }
                                    // Handle youtube.com/embed/VIDEO_ID format
                                    elseif (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $videoUrl, $matches)) {
                                        $videoId = $matches[1];
                                    }
                                    
                                    if ($videoId) {
                                        $embedUrl = 'https://www.youtube.com/embed/' . $videoId;
                                    }
                                }
                                // Handle Vimeo URLs
                                elseif (strpos($videoUrl, 'vimeo.com') !== false) {
                                    // Extract video ID from vimeo.com/VIDEO_ID format
                                    if (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $matches)) {
                                        $videoId = $matches[1];
                                        $embedUrl = 'https://player.vimeo.com/video/' . $videoId;
                                    }
                                }
                                // If no matches, use URL as-is (might be already an embed URL)
                                else {
                                    $embedUrl = $videoUrl;
                                }
                                ?>
                                <iframe src="<?php echo htmlspecialchars($embedUrl); ?>" 
                                        title="Project Video" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                        allowfullscreen>
                                </iframe>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo $project['member_count']; ?></h4>
                                    <small class="text-muted">Members</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success">0</h4>
                                    <small class="text-muted">Tasks</small>
                                </div>
                            </div>
                            <div class="row text-center mt-3">
                                <div class="col-6">
                                    <h4 class="text-info">0</h4>
                                    <small class="text-muted">Features</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-warning">0</h4>
                                    <small class="text-muted">Activities</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recommended Courses - HIDDEN
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">Recommended Courses</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projectCourses)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-book text-muted" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-2">No courses have been linked to this project yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($projectCourses as $course): ?>
                                        <div class="col-12 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                <span class="badge bg-<?php echo $course['assignment_type'] === 'required' ? 'danger' : ($course['assignment_type'] === 'recommended' ? 'primary' : 'secondary'); ?>">
                                                    <?php echo ucfirst($course['assignment_type']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($course['course_code']): ?>
                                                <p class="text-muted small mb-1">
                                                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($course['course_code']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($course['short_description']): ?>
                                                <p class="card-text small mb-2">
                                                    <?php echo htmlspecialchars($course['short_description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <?php if ($course['difficulty_level']): ?>
                                                    <span class="badge bg-<?php echo $course['difficulty_level'] === 'beginner' ? 'success' : ($course['difficulty_level'] === 'intermediate' ? 'warning' : ($course['difficulty_level'] === 'advanced' ? 'danger' : 'dark')); ?>">
                                                        <?php echo ucfirst($course['difficulty_level']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($course['duration_hours'] > 0): ?>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> <?php echo $course['duration_hours']; ?>h
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($course['due_date']): ?>
                                                <p class="text-muted small mb-2">
                                                    <i class="bi bi-calendar-event"></i> Due: <?php echo date('M j, Y', strtotime($course['due_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($course['notes']): ?>
                                                <p class="text-muted small mb-2">
                                                    <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($course['notes']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <a href="/course-detail?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View Course
                                                </a>
                                                
                                                <?php if ($course['first_name'] && $course['last_name']): ?>
                                                    <small class="text-muted">
                                                        Added by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    -->
                    
                    <!-- Recommended Features - HIDDEN
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recommended Features</h5>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="showFeatureModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                                <i class="bi bi-plus-circle"></i> Add Recommendation
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projectFeatures)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-lightbulb text-muted" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-2">No feature recommendations yet.</p>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showFeatureModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-lightbulb"></i> Recommend First Feature
                                    </button>
                                </div>
                            <?php else: ?>
                                
                                    <?php foreach (array_slice($projectFeatures, 0, 4) as $feature): ?>
                                        <div class="col-12 mb-2">
                                            <div class="d-flex align-items-start p-2 border rounded">
                                                <div class="me-3 mt-1">
                                                    <span class="badge bg-<?php echo $feature['priority'] === 'critical' ? 'dark' : ($feature['priority'] === 'high' ? 'danger' : ($feature['priority'] === 'medium' ? 'warning' : 'info')); ?>">
                                                        <?php echo ucfirst($feature['priority']); ?>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1 min-width-0">
                                                    <div class="fw-bold small">
                                                        <?php echo htmlspecialchars($feature['title']); ?>
                                                    </div>
                                                    <div class="text-muted small mb-1">
                                                        <?php echo htmlspecialchars(substr($feature['description'], 0, 80) . (strlen($feature['description']) > 80 ? '...' : '')); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        By <?php echo htmlspecialchars($feature['first_name'] . ' ' . $feature['last_name']); ?> • 
                                                        <?php echo date('M j', strtotime($feature['created_at'])); ?>
                                                    </div>
                                                </div>
                                                <div class="ms-2 d-flex align-items-center">
                                                    <!-- Feature voting -->
                                                    <div class="d-flex align-items-center me-2">
                                                        <button type="button" class="btn btn-sm btn-outline-success vote-btn p-1" 
                                                                data-type="feature" data-id="<?php echo $feature['id']; ?>" data-vote="up"
                                                                title="Upvote this feature">
                                                            <i class="bi bi-arrow-up"></i>
                                                        </button>
                                                        <span class="mx-1 small vote-count" id="vote-count-feature-<?php echo $feature['id']; ?>">
                                                            <?php echo $feature['vote_count'] ?? 0; ?>
                                                        </span>
                                                        <button type="button" class="btn btn-sm btn-outline-danger vote-btn p-1" 
                                                                data-type="feature" data-id="<?php echo $feature['id']; ?>" data-vote="down"
                                                                title="Downvote this feature">
                                                            <i class="bi bi-arrow-down"></i>
                                                        </button>
                                                    </div>
                                                    <span class="badge bg-<?php echo $feature['status'] === 'approved' ? 'success' : ($feature['status'] === 'in_progress' ? 'primary' : ($feature['status'] === 'rejected' ? 'danger' : 'secondary')); ?>">
                                                        <?php echo ucfirst($feature['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($projectFeatures) > 4): ?>
                                    <div class="text-center mt-3">
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="showAllFeatures()">
                                            <i class="bi bi-lightbulb"></i> View All <?php echo count($projectFeatures); ?> Features
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    -->
                    
                    <!-- Project Artifacts - HIDDEN
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Project Artifacts</h5>
                            <?php if ($isMember): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="showProjectFiles(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                                    <i class="bi bi-files"></i> View All Files
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projectFiles)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-file-earmark text-muted" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-2">No artifacts uploaded yet.</p>
                                    <?php if ($isMember): ?>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="showUploadModal(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                                            <i class="bi bi-cloud-upload"></i> Upload First Document
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                
                                    <?php foreach (array_slice($projectFiles, 0, 4) as $file): ?>
                                        <div class="col-12 mb-2">
                                            <div class="d-flex align-items-center p-2 border rounded">
                                                <div class="me-3">
                                                    <i class="<?php echo $fileManager->getFileIcon($file['file_type']); ?> text-primary" style="font-size: 1.2rem;"></i>
                                                </div>
                                                <div class="flex-grow-1 min-width-0">
                                                    <div class="fw-bold small text-truncate" title="<?php echo htmlspecialchars($file['original_filename']); ?>">
                                                        <?php echo htmlspecialchars($file['original_filename']); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?php echo $fileManager->formatFileSize($file['file_size']); ?> • 
                                                        <?php echo date('M j', strtotime($file['upload_date'])); ?>
                                                    </div>
                                                </div>
                                                <div class="ms-2">
                                                    <a href="/api/file-download.php?id=<?php echo $file['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($projectFiles) > 4): ?>
                                    <div class="text-center mt-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="showProjectFiles(<?php echo $projectId; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')">
                                            <i class="bi bi-files"></i> View All <?php echo count($projectFiles); ?> Files
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    -->
                    
                    <!-- Required Skills - HIDDEN
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">Required Skills</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            // Fetch project skills
                            $projectSkills = $projectObj->getProjectSkills($projectId);
                            ?>
                            <?php if (empty($projectSkills)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-tools text-muted" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-2">No skills have been linked to this project yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($projectSkills as $skill): ?>
                                        <div class="col-12 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($skill['name']); ?></h6>
                                                        <span class="badge bg-<?php echo $skill['importance_level'] === 'required' ? 'danger' : ($skill['importance_level'] === 'preferred' ? 'warning' : 'secondary'); ?>">
                                                            <?php echo ucfirst($skill['importance_level'] ?? ''); ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <p class="text-muted small mb-1">
                                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($skill['category']); ?>
                                                    </p>
                                                    
                                                    <?php if (!empty($skill['description'])): ?>
                                                        <p class="card-text small mb-2">
                                                            <?php echo htmlspecialchars($skill['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($skill['notes'])): ?>
                                                        <p class="text-muted small mb-0">
                                                            <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($skill['notes']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    -->
                    
                    <!-- Team Members - HIDDEN
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Team Members</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($members)): ?>
                                <p class="text-muted">No team members yet.</p>
                            <?php else: ?>
                                
                                    <?php foreach ($members as $member): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 40px; height: 40px;">
                                                    <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                                    <?php if ($member['user_id'] == $project['created_by']): ?>
                                                        <br><span class="badge bg-warning">Creator</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    -->
                </div>
            </div>


</main>
    </div>
</div>

<!-- File Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" id="uploadEntityType" name="entity_type" value="project">
                    <input type="hidden" id="uploadEntityId" name="entity_id" value="">
                    
                    <div class="mb-3">
                        <label for="uploadFile" class="form-label">Select File</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <input type="file" class="form-control" id="uploadFile" name="file" required style="display: none;">
                            <div class="upload-content">
                                <i class="bi bi-cloud-upload-fill" style="font-size: 3rem; color: var(--primary);"></i>
                                <p class="mt-2 mb-1"><strong>Click to upload</strong> or drag and drop</p>
                                <p class="text-muted small">All file types allowed except .exe and .msi</p>
                            </div>
                        </div>
                        <div id="selectedFile" class="mt-2" style="display: none;">
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="bi bi-file-earmark me-2"></i>
                                <span id="selectedFileName"></span>
                                <button type="button" class="btn-close ms-auto" onclick="clearSelectedFile()"></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="uploadDescription" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="uploadDescription" name="description" rows="3" placeholder="Brief description of this document..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadButton" onclick="uploadFile()">
                    <i class="bi bi-cloud-upload"></i> Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- File List Modal -->
<div class="modal fade" id="fileListModal" tabindex="-1" aria-labelledby="fileListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fileListModalLabel">Project Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="fileListContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading files...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="showUploadModal(getCurrentProjectId(), getCurrentProjectName())">
                    <i class="bi bi-cloud-upload"></i> Upload New File
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include file manager JavaScript -->
<script src="/assets/js/file-manager.js"></script>

<!-- Comment Styles -->
<style>
.comment {
    transition: all 0.2s ease;
}

.comment:hover {
    background-color: rgba(0, 0, 0, 0.02) !important;
}

.comment-actions button {
    transition: all 0.2s ease;
}

.comment-actions button:hover {
    transform: translateY(-1px);
}

.like-btn.text-primary {
    animation: heartBeat 0.3s ease;
}

@keyframes heartBeat {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.reply-form-container {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.replies-container {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.border-start.border-3 {
    border-color: #dee2e6 !important;
}

.comment.ms-4 {
    background-color: rgba(0, 0, 0, 0.02);
}
</style>

<script>
// Project-specific file management functions
let currentProjectId = <?php echo $projectId; ?>;
let currentProjectName = '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>';

function getCurrentProjectId() {
    return currentProjectId;
}

function getCurrentProjectName() {
    return currentProjectName;
}

function showUploadModal(projectId, projectName) {
    document.getElementById('uploadEntityId').value = projectId;
    document.getElementById('uploadModalLabel').textContent = 'Upload Document for ' + projectName;
    clearUploadForm();
    new bootstrap.Modal(document.getElementById('uploadModal')).show();
}

function showProjectFiles(projectId, projectName) {
    document.getElementById('fileListModalLabel').textContent = 'Files for ' + projectName;
    new bootstrap.Modal(document.getElementById('fileListModal')).show();
    loadProjectFiles(projectId);
}

function loadProjectFiles(projectId) {
    const content = document.getElementById('fileListContent');
    
    fetch(`/api/file-list.php?entity_type=project&entity_id=${projectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.files) {
                displayFileList(data.files);
            } else {
                content.innerHTML = '<div class="alert alert-warning">No files found or error loading files.</div>';
            }
        })
        .catch(error => {
            console.error('Error loading files:', error);
            content.innerHTML = '<div class="alert alert-danger">Error loading files. Please try again.</div>';
        });
}

function displayFileList(files) {
    const content = document.getElementById('fileListContent');
    
    if (files.length === 0) {
        content.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-file-earmark text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No files uploaded yet.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    files.forEach(file => {
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <i class="bi-file-earmark text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <h6 class="card-title mb-1 text-truncate" title="${file.original_filename}">
                                    ${file.original_filename}
                                </h6>
                                <p class="card-text small text-muted mb-1">
                                    ${formatFileSize(file.file_size)}
                                </p>
                                <p class="card-text small text-muted mb-2">
                                    Uploaded ${new Date(file.upload_date).toLocaleDateString()}
                                </p>
                                ${file.description ? `<p class="card-text small mb-2">${file.description}</p>` : ''}
                                <div class="d-flex gap-1">
                                    <a href="/api/file-download.php?id=${file.id}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFile(${file.id})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    content.innerHTML = html;
}

function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return Math.round(size * 100) / 100 + ' ' + units[unitIndex];
}

function deleteFile(fileId) {
    if (!confirm('Are you sure you want to delete this file?')) {
        return;
    }
    
    fetch('/api/file-upload.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ file_id: fileId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the file list
            loadProjectFiles(currentProjectId);
            // Refresh the page to update the artifacts section
            window.location.reload();
        } else {
            alert('Error deleting file: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting file. Please try again.');
    });
}

// File upload functions
function clearUploadForm() {
    document.getElementById('uploadFile').value = '';
    document.getElementById('uploadDescription').value = '';
    document.getElementById('selectedFile').style.display = 'none';
    document.getElementById('selectedFileName').textContent = '';
}

function clearSelectedFile() {
    clearUploadForm();
}

function uploadFile() {
    const fileInput = document.getElementById('uploadFile');
    const entityType = document.getElementById('uploadEntityType').value;
    const entityId = document.getElementById('uploadEntityId').value;
    const description = document.getElementById('uploadDescription').value;
    
    if (!fileInput.files.length) {
        alert('Please select a file to upload.');
        return;
    }
    
    const uploadButton = document.getElementById('uploadButton');
    const originalText = uploadButton.innerHTML;
    uploadButton.disabled = true;
    uploadButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading...';
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('entity_type', entityType);
    formData.append('entity_id', entityId);
    formData.append('description', description);
    
    fetch('/api/file-upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
            // Refresh page to show new file
            window.location.reload();
        } else {
            alert('Error uploading file: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error uploading file. Please try again.');
    })
    .finally(() => {
        uploadButton.disabled = false;
        uploadButton.innerHTML = originalText;
    });
}

function initializeFileUpload(uploadArea, fileInput) {
    // Click to select file
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Handle file selection
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const file = this.files[0];
            document.getElementById('selectedFileName').textContent = file.name + ' (' + formatFileSize(file.size) + ')';
            document.getElementById('selectedFile').style.display = 'block';
        }
    });
    
    // Drag and drop functionality
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            const file = files[0];
            document.getElementById('selectedFileName').textContent = file.name + ' (' + formatFileSize(file.size) + ')';
            document.getElementById('selectedFile').style.display = 'block';
        }
    });
}

// Initialize drag and drop
document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('uploadFile');
    
    if (fileUploadArea && fileInput) {
        initializeFileUpload(fileUploadArea, fileInput);
    }
});

// Feature modal functions
function showFeatureModal(projectId, projectName) {
    document.getElementById('feature-project-name').textContent = projectName;
    document.getElementById('feature-project-id').value = projectId;
    
    // Reset form
    document.getElementById('featureForm').reset();
    document.getElementById('feature-message').innerHTML = '';
    
    new bootstrap.Modal(document.getElementById('featureModal')).show();
}

async function submitFeature() {
    const title = document.getElementById('feature-title').value.trim();
    const description = document.getElementById('feature-description').value.trim();
    const priority = document.getElementById('feature-priority').value;
    const projectId = document.getElementById('feature-project-id').value;
    
    if (!title || !description) {
        document.getElementById('feature-message').innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Please fill in all required fields.
            </div>
        `;
        return;
    }
    
    const submitBtn = document.getElementById('submit-feature-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';
    
    try {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', description);
        formData.append('priority', priority);
        formData.append('project_id', projectId);
        formData.append('action', 'create');
        
        const response = await fetch('/api/feature-create.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('feature-message').innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Feature recommendation submitted successfully!
                </div>
            `;
            
            // Close modal and refresh page after success
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('featureModal')).hide();
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(result.error || 'Failed to submit feature recommendation');
        }
        
    } catch (error) {
        document.getElementById('feature-message').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> ${error.message}
            </div>
        `;
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

function showAllFeatures() {
    // For now, just reload the page - could implement a modal later
    alert('View all features functionality would open a modal with all project features');
}

// Voting functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle voting
    document.querySelectorAll('.vote-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const type = this.dataset.type;
            const id = this.dataset.id;
            const voteType = this.dataset.vote;
            
            // Check if this button is currently active (user clicking to remove vote)
            const isCurrentlyActive = 
                (voteType === 'up' && this.classList.contains('btn-success')) ||
                (voteType === 'down' && this.classList.contains('btn-danger'));
            
            // Determine action - if clicking active button, unvote; otherwise vote
            const action = isCurrentlyActive ? 'unvote' : 'vote';
            
            try {
                const response = await fetch('/api/vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        id: parseInt(id),
                        vote_type: voteType,
                        action: action
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update vote count display
                    const countElement = document.getElementById(`vote-count-${type}-${id}`);
                    if (countElement) {
                        countElement.textContent = result.vote_count;
                    }
                    
                    // Update button states
                    const upButton = this.parentElement.querySelector('[data-vote="up"]');
                    const downButton = this.parentElement.querySelector('[data-vote="down"]');
                    
                    // Reset button states
                    upButton.classList.remove('btn-success');
                    upButton.classList.add('btn-outline-success');
                    downButton.classList.remove('btn-danger');
                    downButton.classList.add('btn-outline-danger');
                    
                    // Highlight the active vote
                    if (result.user_vote === 'up') {
                        upButton.classList.remove('btn-outline-success');
                        upButton.classList.add('btn-success');
                    } else if (result.user_vote === 'down') {
                        downButton.classList.remove('btn-outline-danger');
                        downButton.classList.add('btn-danger');
                    }
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Voting error:', error);
                alert('Error processing vote. Please try again.');
            }
        });
    });
    
    // Load initial vote states
    async function loadVoteStates() {
        // Load project vote state
        try {
            const projectResponse = await fetch(`/api/vote.php?type=project&id=${currentProjectId}`);
            const projectResult = await projectResponse.json();
            
            if (projectResult.success) {
                // Update project vote count
                const projectCountElement = document.getElementById(`vote-count-project-${currentProjectId}`);
                if (projectCountElement) {
                    projectCountElement.textContent = projectResult.vote_count;
                }
                
                // Update project vote buttons
                const projectVoteButtons = document.querySelectorAll(`[data-type="project"][data-id="${currentProjectId}"]`);
                projectVoteButtons.forEach(button => {
                    if (button.dataset.vote === 'up' && projectResult.user_vote === 'up') {
                        button.classList.remove('btn-outline-success');
                        button.classList.add('btn-success');
                    } else if (button.dataset.vote === 'down' && projectResult.user_vote === 'down') {
                        button.classList.remove('btn-outline-danger');
                        button.classList.add('btn-danger');
                    }
                });
            }
        } catch (error) {
            console.error('Error loading project vote state:', error);
        }
        
        // Load feature vote states
        const featureButtons = document.querySelectorAll('.vote-btn[data-type="feature"]');
        const featureIds = new Set();
        
        featureButtons.forEach(button => {
            featureIds.add(button.dataset.id);
        });
        
        for (const featureId of featureIds) {
            try {
                const response = await fetch(`/api/vote.php?type=feature&id=${featureId}`);
                const result = await response.json();
                
                if (result.success) {
                    // Update vote count
                    const countElement = document.getElementById(`vote-count-feature-${featureId}`);
                    if (countElement) {
                        countElement.textContent = result.vote_count;
                    }
                    
                    // Update button states
                    const featureVoteButtons = document.querySelectorAll(`[data-type="feature"][data-id="${featureId}"]`);
                    featureVoteButtons.forEach(button => {
                        if (button.dataset.vote === 'up' && result.user_vote === 'up') {
                            button.classList.remove('btn-outline-success');
                            button.classList.add('btn-success');
                        } else if (button.dataset.vote === 'down' && result.user_vote === 'down') {
                            button.classList.remove('btn-outline-danger');
                            button.classList.add('btn-danger');
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading feature vote state:', error);
            }
        }
    }
    
    // Comments functionality
    const commentForm = document.getElementById('comment-form');
    const commentsList = document.getElementById('comments-list');
    const commentCount = document.getElementById('comment-count');
    
    // Load comments
    async function loadComments() {
        try {
            const response = await fetch(`/api/comments.php?commentable_type=project&commentable_id=${currentProjectId}`);
            const result = await response.json();
            
            if (result.success) {
                displayComments(result.comments);
                // Calculate total comments including replies
                let totalComments = result.comments.length;
                result.comments.forEach(comment => {
                    totalComments += parseInt(comment.reply_count) || 0;
                });
                commentCount.textContent = totalComments;
            } else {
                commentsList.innerHTML = '<div class="alert alert-danger">Error loading comments</div>';
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            commentsList.innerHTML = '<div class="alert alert-danger">Error loading comments</div>';
        }
    }
    
    // Display comments
    function displayComments(comments, parentElement = null, isReply = false) {
        if (!parentElement) parentElement = commentsList;
        
        if (comments.length === 0 && !isReply) {
            parentElement.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
            return;
        }
        
        let html = '';
        comments.forEach(comment => {
            const date = new Date(comment.created_at);
            const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const isLiked = comment.user_liked > 0;
            
            html += `
                <div class="comment mb-3 p-3 ${isReply ? 'ms-4 border-start border-3' : 'bg-light'} rounded" data-comment-id="${comment.id}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong>${comment.first_name} ${comment.last_name}</strong>
                            <small class="text-muted ms-2">${formattedDate}</small>
                            ${comment.edited ? '<small class="text-muted ms-1">(edited)</small>' : ''}
                        </div>
                        ${comment.user_id == <?php echo $currentUserId; ?> ? `
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="editComment(${comment.id}); return false;">Edit</a></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteComment(${comment.id}); return false;">Delete</a></li>
                                </ul>
                            </div>
                        ` : ''}
                    </div>
                    <div class="comment-content mb-2">${comment.content.replace(/\n/g, '<br>')}</div>
                    <div class="comment-actions d-flex align-items-center gap-3">
                        <button class="btn btn-sm btn-link text-decoration-none p-0 like-btn ${isLiked ? 'text-primary' : 'text-muted'}" 
                                onclick="toggleCommentLike(${comment.id}, this)" 
                                data-comment-id="${comment.id}">
                            <i class="bi ${isLiked ? 'bi-heart-fill' : 'bi-heart'}"></i>
                            <span class="like-count">${comment.like_count || 0}</span>
                        </button>
                        <button class="btn btn-sm btn-link text-decoration-none p-0 text-muted" 
                                onclick="showReplyForm(${comment.id})">
                            <i class="bi bi-reply"></i> Reply
                        </button>
                        ${comment.reply_count > 0 ? `
                            <button class="btn btn-sm btn-link text-decoration-none p-0 text-muted show-replies-btn" 
                                    onclick="toggleReplies(${comment.id}, this)"
                                    data-comment-id="${comment.id}">
                                <i class="bi bi-chat-left"></i> 
                                <span class="reply-count">${comment.reply_count}</span> ${comment.reply_count === 1 ? 'reply' : 'replies'}
                            </button>
                        ` : ''}
                    </div>
                    <div class="reply-form-container mt-3" id="reply-form-${comment.id}" style="display: none;">
                        <form onsubmit="submitReply(event, ${comment.id})">
                            <div class="input-group">
                                <input type="text" class="form-control form-control-sm" 
                                       placeholder="Write a reply..." 
                                       id="reply-content-${comment.id}" required>
                                <button class="btn btn-sm btn-primary" type="submit">
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="replies-container mt-3" id="replies-${comment.id}" style="display: none;"></div>
                </div>
            `;
        });
        
        if (isReply) {
            parentElement.innerHTML = html;
        } else {
            commentsList.innerHTML = html;
        }
    }
    
    // Submit comment
    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const content = document.getElementById('comment-content').value.trim();
            if (!content) return;
            
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Posting...';
            
            try {
                const response = await fetch('/api/comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        commentable_type: 'project',
                        commentable_id: currentProjectId,
                        content: content
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('comment-content').value = '';
                    loadComments(); // Reload comments
                } else {
                    alert('Error posting comment: ' + result.error);
                }
            } catch (error) {
                console.error('Error posting comment:', error);
                alert('Error posting comment. Please try again.');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-chat-left-text"></i> Post Comment';
            }
        });
    }
    
    // Load initial data
    loadVoteStates();
    loadComments();
});

// Comment management functions
async function deleteComment(commentId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    
    try {
        const response = await fetch('/api/comments.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: commentId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            loadComments(); // Reload comments
        } else {
            alert('Error deleting comment: ' + result.error);
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
        alert('Error deleting comment. Please try again.');
    }
}

function editComment(commentId) {
    // TODO: Implement inline editing
    alert('Edit functionality coming soon!');
}

// Toggle comment like
async function toggleCommentLike(commentId, button) {
    try {
        const response = await fetch('/api/comment-likes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                comment_id: commentId,
                action: 'toggle'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update button appearance
            const icon = button.querySelector('i');
            const countSpan = button.querySelector('.like-count');
            
            if (result.liked) {
                button.classList.remove('text-muted');
                button.classList.add('text-primary');
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
            } else {
                button.classList.remove('text-primary');
                button.classList.add('text-muted');
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
            }
            
            countSpan.textContent = result.like_count;
        } else {
            console.error('Error toggling like:', result.error);
        }
    } catch (error) {
        console.error('Error toggling like:', error);
    }
}

// Show reply form
function showReplyForm(commentId) {
    const replyForm = document.getElementById(`reply-form-${commentId}`);
    if (replyForm) {
        replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
        if (replyForm.style.display === 'block') {
            const input = document.getElementById(`reply-content-${commentId}`);
            if (input) input.focus();
        }
    }
}

// Submit reply
async function submitReply(event, parentCommentId) {
    event.preventDefault();
    
    const input = document.getElementById(`reply-content-${parentCommentId}`);
    const content = input.value.trim();
    
    if (!content) return;
    
    const submitButton = event.target.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    
    try {
        const response = await fetch('/api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                commentable_type: 'project',
                commentable_id: currentProjectId,
                parent_comment_id: parentCommentId,
                content: content
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            input.value = '';
            // Hide reply form
            document.getElementById(`reply-form-${parentCommentId}`).style.display = 'none';
            // Load replies
            await loadReplies(parentCommentId);
            // Update reply count
            updateReplyCount(parentCommentId, 1);
            // Update total comment count
            const currentCount = parseInt(commentCount.textContent) || 0;
            commentCount.textContent = currentCount + 1;
        } else {
            alert('Error posting reply: ' + result.error);
        }
    } catch (error) {
        console.error('Error posting reply:', error);
        alert('Error posting reply. Please try again.');
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="bi bi-send"></i>';
    }
}

// Toggle replies visibility
async function toggleReplies(commentId, button) {
    const repliesContainer = document.getElementById(`replies-${commentId}`);
    
    if (repliesContainer.style.display === 'none' || !repliesContainer.innerHTML) {
        // Load replies if not already loaded
        await loadReplies(commentId);
        repliesContainer.style.display = 'block';
    } else {
        // Toggle visibility
        repliesContainer.style.display = repliesContainer.style.display === 'none' ? 'block' : 'none';
    }
}

// Load replies for a comment
async function loadReplies(commentId) {
    const repliesContainer = document.getElementById(`replies-${commentId}`);
    
    try {
        const response = await fetch(`/api/comments.php?commentable_type=project&commentable_id=${currentProjectId}&parent_comment_id=${commentId}`);
        const result = await response.json();
        
        if (result.success) {
            displayComments(result.comments, repliesContainer, true);
            repliesContainer.style.display = 'block';
        } else {
            repliesContainer.innerHTML = '<div class="alert alert-danger">Error loading replies</div>';
        }
    } catch (error) {
        console.error('Error loading replies:', error);
        repliesContainer.innerHTML = '<div class="alert alert-danger">Error loading replies</div>';
    }
}

// Update reply count
function updateReplyCount(commentId, increment) {
    const button = document.querySelector(`.show-replies-btn[data-comment-id="${commentId}"]`);
    if (button) {
        const countSpan = button.querySelector('.reply-count');
        const currentCount = parseInt(countSpan.textContent) || 0;
        const newCount = currentCount + increment;
        countSpan.textContent = newCount;
        
        // Update text
        const replyText = newCount === 1 ? 'reply' : 'replies';
        button.innerHTML = `<i class="bi bi-chat-left"></i> <span class="reply-count">${newCount}</span> ${replyText}`;
        
        // Show button if it was hidden
        if (newCount > 0 && button.style.display === 'none') {
            button.style.display = 'inline-block';
        }
    }
}
</script>

<!-- Feature Recommendation Modal -->
<div class="modal fade" id="featureModal" tabindex="-1" aria-labelledby="featureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="featureModalLabel">
                    <i class="bi bi-lightbulb"></i> Recommend Feature
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i>
                    Recommending feature for: <strong><span id="feature-project-name">Project</span></strong>
                </div>
                
                <form id="featureForm">
                    <input type="hidden" id="feature-project-id" name="project_id">
                    
                    <div class="mb-3">
                        <label for="feature-title" class="form-label">Feature Title *</label>
                        <input type="text" class="form-control" id="feature-title" name="title" required
                               placeholder="Brief descriptive title for the feature">
                    </div>
                    
                    <div class="mb-3">
                        <label for="feature-description" class="form-label">Description *</label>
                        <textarea class="form-control" id="feature-description" name="description" rows="4" required
                                  placeholder="Detailed description of the feature, its benefits, and how it should work..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="feature-priority" class="form-label">Priority</label>
                        <select class="form-select" id="feature-priority" name="priority">
                            <option value="low">Low - Nice to have</option>
                            <option value="medium" selected>Medium - Would improve workflow</option>
                            <option value="high">High - Important for project success</option>
                            <option value="critical">Critical - Project depends on this</option>
                        </select>
                    </div>
                </form>
                
                <div id="feature-message"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submit-feature-btn" onclick="submitFeature()">
                    <i class="bi bi-lightbulb"></i> Submit Recommendation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal (Admin Only) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusChangeModalLabel">Change Project Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/project-detail?id=<?php echo $projectId; ?>">
                <input type="hidden" name="action" value="change_status">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Project Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="planning" <?php echo $project['status'] == 'planning' ? 'selected' : ''; ?>>Planning</option>
                            <option value="active" <?php echo $project['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $project['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="archived" <?php echo $project['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                        <div class="form-text">
                            <ul class="mb-0">
                                <li><strong>Planning:</strong> Project is being planned and organized</li>
                                <li><strong>Active:</strong> Project is actively being worked on</li>
                                <li><strong>Completed:</strong> Project work is finished</li>
                                <li><strong>Archived:</strong> Project is no longer active</li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if ($project['status'] == 'planning'): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This project is currently in planning mode. Only you and the project creator can see it. 
                        Changing status to "Active" will make it visible to all users.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>