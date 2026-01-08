<?php
/**
 * Learning Plan Page
 * 
 * Displays personalized learning recommendations based on survey responses
 */

$page_title = 'Learning Plan';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/LearningPlan.php';
require_once 'classes/Survey.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Initialize classes
$learningPlan = new LearningPlan();
$survey = new Survey();

// Get learning plan data
$recommendedProjects = $learningPlan->getRecommendedProjects($currentUserId, $currentCommunityId);
$recommendedCourses = $learningPlan->getRecommendedCourses($currentUserId, $currentCommunityId);
$skillAssessments = $learningPlan->getSkillAssessments($currentUserId, $currentCommunityId);
$stats = $learningPlan->getLearningPlanStats($currentUserId, $currentCommunityId);
$lastGenerated = $learningPlan->getLastGenerationDate($currentUserId, $currentCommunityId);

// Check if user has completed survey
$skillsSurvey = $survey->getSurveyByCommunity($currentCommunityId, 'skills');
$surveyCompletion = null;
$hasCompletedSurvey = false;

if ($skillsSurvey) {
    $surveyCompletion = $survey->getCompletionStatus($skillsSurvey['id'], $currentUserId);
    $hasCompletedSurvey = $surveyCompletion && $surveyCompletion['completion_percentage'] == 100;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if ($action === 'update_status' && in_array($type, ['project', 'course']) && $id && $status) {
        if ($learningPlan->updateRecommendationStatus($type, $id, $currentUserId, $status)) {
            setFlashMessage('success', ucfirst($type) . ' status updated successfully!');
        } else {
            setFlashMessage('error', 'Failed to update ' . $type . ' status.');
        }
        header('Location: learning-plan');
        exit;
    }
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">My Learning Plan</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if ($lastGenerated): ?>
            <small class="text-muted me-3">
                <i class="bi bi-clock-history"></i> Last updated: <?php echo timeAgo($lastGenerated); ?>
            </small>
            <?php endif; ?>
            <?php if (!$hasCompletedSurvey): ?>
            <a href="survey" class="btn btn-primary">
                <i class="bi bi-clipboard-check"></i> Complete Survey
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$hasCompletedSurvey): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Complete your survey!</strong> Your personalized learning plan will be generated based on your survey responses.
        <a href="survey" class="alert-link">Take the survey now</a> to get customized recommendations.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Projects</h5>
                    <div class="d-flex justify-content-around">
                        <div>
                            <h3 class="text-warning"><?php echo $stats['pending_projects']; ?></h3>
                            <small class="text-muted">Recommended</small>
                        </div>
                        <div>
                            <h3 class="text-primary"><?php echo $stats['enrolled_projects']; ?></h3>
                            <small class="text-muted">Active</small>
                        </div>
                        <div>
                            <h3 class="text-success"><?php echo $stats['completed_projects']; ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Courses</h5>
                    <div class="d-flex justify-content-around">
                        <div>
                            <h3 class="text-warning"><?php echo $stats['pending_courses']; ?></h3>
                            <small class="text-muted">Recommended</small>
                        </div>
                        <div>
                            <h3 class="text-primary"><?php echo $stats['enrolled_courses']; ?></h3>
                            <small class="text-muted">Active</small>
                        </div>
                        <div>
                            <h3 class="text-success"><?php echo $stats['completed_courses']; ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Skills</h5>
                    <div class="d-flex justify-content-center align-items-center">
                        <div>
                            <h3 class="text-info"><?php echo $stats['total_skills']; ?></h3>
                            <small class="text-muted">Assessed Skills</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Learning Plan Content -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs mb-3" id="learningPlanTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="projects-tab" data-bs-toggle="tab" data-bs-target="#projects" type="button" role="tab">
                        <i class="bi bi-folder"></i> Projects
                        <?php if ($stats['pending_projects'] > 0): ?>
                        <span class="badge bg-warning ms-1"><?php echo $stats['pending_projects']; ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab">
                        <i class="bi bi-book"></i> Courses
                        <?php if ($stats['pending_courses'] > 0): ?>
                        <span class="badge bg-warning ms-1"><?php echo $stats['pending_courses']; ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="assessments-tab" data-bs-toggle="tab" data-bs-target="#assessments" type="button" role="tab">
                        <i class="bi bi-graph-up"></i> Skill Assessments
                        <span class="badge bg-info ms-1"><?php echo $stats['total_skills']; ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="learningPlanContent">
                <!-- Projects Tab -->
                <div class="tab-pane fade show active" id="projects" role="tabpanel">
                    <?php if (empty($recommendedProjects)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-folder display-1 text-muted"></i>
                        <h3 class="mt-3">No Project Recommendations Yet</h3>
                        <p class="text-muted">Complete your survey to get personalized project recommendations.</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($recommendedProjects as $project): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 <?php echo $project['status'] === 'completed' ? 'bg-light' : ''; ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $project['priority'] === 'high' ? 'danger' : ($project['priority'] === 'medium' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($project['priority']); ?> Priority
                                    </span>
                                    <span class="badge bg-<?php echo $project['status'] === 'enrolled' ? 'primary' : ($project['status'] === 'completed' ? 'success' : 'secondary'); ?>">
                                        <?php echo ucfirst($project['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="project-detail?id=<?php echo $project['project_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($project['project_name']); ?>
                                        </a>
                                    </h5>
                                    <p class="card-text small text-muted">
                                        <?php echo htmlspecialchars(substr($project['project_description'], 0, 100)); ?>...
                                    </p>
                                    <?php if ($project['recommendation_reason']): ?>
                                    <p class="card-text">
                                        <small class="text-info">
                                            <i class="bi bi-lightbulb"></i> <?php echo htmlspecialchars($project['recommendation_reason']); ?>
                                        </small>
                                    </p>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-people"></i> <?php echo $project['member_count']; ?> members
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-check2-square"></i> <?php echo $project['task_count']; ?> tasks
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="type" value="project">
                                        <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                        <?php if ($project['status'] === 'pending'): ?>
                                        <button type="submit" name="status" value="enrolled" class="btn btn-sm btn-primary flex-fill">
                                            <i class="bi bi-plus-circle"></i> Join Project
                                        </button>
                                        <button type="submit" name="status" value="dismissed" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-x"></i>
                                        </button>
                                        <?php elseif ($project['status'] === 'enrolled'): ?>
                                        <a href="project-detail?id=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-success flex-fill">
                                            <i class="bi bi-arrow-right"></i> View Project
                                        </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Courses Tab -->
                <div class="tab-pane fade" id="courses" role="tabpanel">
                    <?php if (empty($recommendedCourses)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-book display-1 text-muted"></i>
                        <h3 class="mt-3">No Course Recommendations Yet</h3>
                        <p class="text-muted">Complete your survey to get personalized course recommendations.</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($recommendedCourses as $course): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 <?php echo $course['status'] === 'completed' ? 'bg-light' : ''; ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $course['priority'] === 'high' ? 'danger' : ($course['priority'] === 'medium' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($course['priority']); ?> Priority
                                    </span>
                                    <span class="badge bg-<?php echo $course['status'] === 'enrolled' ? 'primary' : ($course['status'] === 'completed' ? 'success' : 'secondary'); ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="course-detail?id=<?php echo $course['course_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($course['course_title']); ?>
                                        </a>
                                    </h5>
                                    <p class="card-text small text-muted">
                                        <?php echo htmlspecialchars(substr($course['course_description'], 0, 100)); ?>...
                                    </p>
                                    <?php if ($course['recommendation_reason']): ?>
                                    <p class="card-text">
                                        <small class="text-info">
                                            <i class="bi bi-lightbulb"></i> <?php echo htmlspecialchars($course['recommendation_reason']); ?>
                                        </small>
                                    </p>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-bar-chart"></i> <?php echo ucfirst($course['difficulty_level']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> <?php echo $course['estimated_hours']; ?>h
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-collection"></i> <?php echo $course['lesson_count']; ?> lessons
                                        </small>
                                    </div>
                                    <?php if ($course['user_enrolled_at']): ?>
                                    <div class="progress mt-3" style="height: 10px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $course['user_progress']; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo $course['user_progress']; ?>% complete</small>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="type" value="course">
                                        <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
                                        <?php if ($course['status'] === 'pending'): ?>
                                        <button type="submit" name="status" value="enrolled" class="btn btn-sm btn-primary flex-fill">
                                            <i class="bi bi-plus-circle"></i> Enroll
                                        </button>
                                        <button type="submit" name="status" value="dismissed" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-x"></i>
                                        </button>
                                        <?php elseif ($course['status'] === 'enrolled' || $course['user_enrolled_at']): ?>
                                        <a href="course-detail?id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-success flex-fill">
                                            <i class="bi bi-play-circle"></i> Continue Learning
                                        </a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Assessments Tab -->
                <div class="tab-pane fade" id="assessments" role="tabpanel">
                    <?php if (empty($skillAssessments)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-graph-up display-1 text-muted"></i>
                        <h3 class="mt-3">No Skill Assessments Yet</h3>
                        <p class="text-muted">Complete your survey to get a personalized skill assessment.</p>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($skillAssessments as $skill): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($skill['skill_name']); ?></h5>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Current Level</span>
                                            <span class="badge bg-<?php echo $skill['current_level'] === 'expert' ? 'success' : ($skill['current_level'] === 'advanced' ? 'info' : ($skill['current_level'] === 'intermediate' ? 'warning' : 'secondary')); ?>">
                                                <?php echo ucfirst($skill['current_level']); ?>
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 20px;">
                                            <?php 
                                            $levelPercentages = ['beginner' => 25, 'intermediate' => 50, 'advanced' => 75, 'expert' => 100];
                                            $currentPercentage = $levelPercentages[$skill['current_level']] ?? 0;
                                            ?>
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $currentPercentage; ?>%">
                                                <?php echo $currentPercentage; ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($skill['target_level'] && $skill['target_level'] !== $skill['current_level']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">Target: <strong><?php echo ucfirst($skill['target_level']); ?></strong></small>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($skill['improvement_areas']): ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted">Areas for Improvement:</h6>
                                        <small><?php echo htmlspecialchars($skill['improvement_areas']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($skill['recommended_resources']): ?>
                                    <div>
                                        <h6 class="text-muted">Recommended Resources:</h6>
                                        <?php 
                                        $resources = json_decode($skill['recommended_resources'], true);
                                        if (is_array($resources)):
                                            foreach ($resources as $resource):
                                        ?>
                                        <a href="<?php echo htmlspecialchars($resource['url'] ?? '#'); ?>" class="btn btn-sm btn-outline-primary me-1 mb-1" target="_blank">
                                            <i class="bi bi-link-45deg"></i> <?php echo htmlspecialchars($resource['title'] ?? 'Resource'); ?>
                                        </a>
                                        <?php 
                                            endforeach;
                                        endif;
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> Last assessed: <?php echo timeAgo($skill['last_assessed_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>