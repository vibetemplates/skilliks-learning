<?php
/**
 * HTMX endpoint for dashboard statistics
 * Returns updated stats cards HTML
 */

require_once '../includes/session.php';
require_once '../config/database.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

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
        $surveyCompletion = $completion ? $completion['completion_percentage'] . '%' : 'Start';
    } catch (PDOException $e) {
        $surveyCompletion = 'Start';
    }
    
    $stats = [
        'projects' => $projectCount,
        'tasks' => $taskCount,
        'features' => $featureCount,
        'completed' => $completedCount,
        'courses' => $courseCount,
        'recommended' => $recommendedCount,
        'survey' => $surveyCompletion
    ];
} catch (PDOException $e) {
    error_log("Dashboard stats query error: " . $e->getMessage());
    $stats = [
        'projects' => 0,
        'tasks' => 0,
        'features' => 0,
        'completed' => 0,
        'courses' => 0,
        'recommended' => 0,
        'survey' => 'Start'
    ];
}
?>

<!-- Stats cards HTML fragment -->
<div id="stats-card-projects" class="col-xl-3 col-md-6 mb-4">
    <a href="/project-categories" class="text-decoration-none">
        <div id="projects-card" class="card border-left-success shadow h-100 py-2" style="background-color: #f8f9fa;">
            <div id="projects-card-body" class="card-body">
                <div id="projects-card-content" class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Projects</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['projects']; ?></div>
                        <small class="text-muted">Active projects</small>
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
        <div id="courses-card" class="card border-left-primary shadow h-100 py-2" style="background-color: #f8f9fa;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Courses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['courses']; ?></div>
                        <small class="text-muted">Enrolled courses</small>
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
        <div id="learning-plan-card" class="card border-left-warning shadow h-100 py-2" style="background-color: #f8f9fa;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Learning Plan</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['recommended'] ?? 0; ?></div>
                        <small class="text-muted">Recommended courses</small>
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
        <div id="survey-card" class="card border-left-info shadow h-100 py-2" style="background-color: #f8f9fa;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Skills Survey</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['survey']; ?></div>
                        <small class="text-muted">Complete your profile</small>
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