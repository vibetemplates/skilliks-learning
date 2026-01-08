<?php
/**
 * Recommended Courses Page
 * 
 * Shows personalized course recommendations based on survey responses
 */

$page_title = 'Recommended Courses';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/SurveyNarrative.php';
require_once 'classes/Course.php';
require_once 'classes/CourseRecommendation.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Initialize classes
$surveyNarrative = new SurveyNarrative();
$course = new Course(null, $currentCommunityId);
$courseRecommendation = new CourseRecommendation();

// Generate narrative from survey responses
$narrative = $surveyNarrative->generateNarrative($currentUserId);
$topInterests = $surveyNarrative->getTopInterests($currentUserId);

// Get all available courses with their lessons (filtered by community)
$allCourses = $course->getAllWithDetails($currentCommunityId);

// Get active recommendations from database
$recommendations = $courseRecommendation->getActiveRecommendations($currentUserId);

// Separate recommendations by type
$beginnerRecommendations = [];
$interestBasedRecommendations = [];
$otherRecommendations = [];

foreach ($recommendations as $rec) {
    if ($rec['recommendation_type'] === 'beginner') {
        $beginnerRecommendations[] = $rec;
    } elseif ($rec['recommendation_type'] === 'interest_based') {
        $interestBasedRecommendations[] = $rec;
    } else {
        $otherRecommendations[] = $rec;
    }
}

// For now, we'll show the narrative and manually curated recommendations
// In the next step, we'll integrate with an LLM API for smart recommendations

require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h2 mb-4">
                <i class="bi bi-mortarboard-fill me-2"></i>Your Recommended Learning Path
            </h1>
            
            <?php if (empty($narrative) || $narrative === "No survey responses found for this user."): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Complete the Skills Survey First</strong>
                    <p class="mb-0">To get personalized course recommendations, please complete our skills survey first.</p>
                    <a href="survey" class="btn btn-primary mt-3">Take Skills Survey</a>
                </div>
            <?php else: ?>
                
                <!-- Top Interests -->
                <?php if (!empty($topInterests)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Your Top Learning Interests</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($topInterests as $interest): ?>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($interest); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Survey Narrative (Hidden in production, shown for debugging) -->
                <div class="card mb-4 d-none" id="survey-narrative">
                    <div class="card-body">
                        <h5 class="card-title">Your Profile Analysis</h5>
                        <pre class="small"><?php echo htmlspecialchars($narrative); ?></pre>
                    </div>
                </div>
                
                <!-- Recommended Courses -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Recommended Courses for You</h5>
                        
                        <!-- Beginner Courses Section -->
                        <?php if (!empty($beginnerRecommendations)): ?>
                        <h6 class="text-muted mb-3">Start Here - Foundation Courses</h6>
                        <div class="row mb-4">
                            <?php foreach ($beginnerRecommendations as $rec): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <?php if ($rec['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($rec['thumbnail_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($rec['course_title']); ?>"
                                         style="height: 150px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($rec['course_title']); ?></h6>
                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars($rec['reason'] ?? ''); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="bi bi-collection me-1"></i>
                                                <?php echo $rec['lesson_count'] ?? 0; ?> lessons
                                            </small>
                                            <a href="course-detail?id=<?php echo $rec['course_id']; ?>" 
                                               class="btn btn-sm btn-primary">View Course</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Interest-based Recommendations -->
                        <?php if (!empty($interestBasedRecommendations)): ?>
                        <h6 class="text-muted mb-3">Based on Your Interests</h6>
                        <div class="row mb-4">
                            <?php foreach ($interestBasedRecommendations as $rec): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <?php if ($rec['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($rec['thumbnail_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($rec['course_title']); ?>"
                                         style="height: 150px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($rec['course_title']); ?></h6>
                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars($rec['reason'] ?? ''); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="bi bi-collection me-1"></i>
                                                <?php echo $rec['lesson_count'] ?? 0; ?> lessons
                                            </small>
                                            <a href="course-detail?id=<?php echo $rec['course_id']; ?>" 
                                               class="btn btn-sm btn-primary">View Course</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Other Recommendations -->
                        <?php if (!empty($otherRecommendations)): ?>
                        <h6 class="text-muted mb-3">Additional Recommendations</h6>
                        <div class="row mb-4">
                            <?php foreach ($otherRecommendations as $rec): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <?php if ($rec['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($rec['thumbnail_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($rec['course_title']); ?>"
                                         style="height: 150px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($rec['course_title']); ?></h6>
                                        <p class="card-text small text-muted">
                                            <?php echo htmlspecialchars($rec['reason'] ?? ''); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="bi bi-collection me-1"></i>
                                                <?php echo $rec['lesson_count'] ?? 0; ?> lessons
                                            </small>
                                            <a href="course-detail?id=<?php echo $rec['course_id']; ?>" 
                                               class="btn btn-sm btn-primary">View Course</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- All Courses Link -->
                        <div class="text-center mt-4">
                            <a href="courses.php" class="btn btn-outline-primary">
                                Browse All Courses
                                <i class="bi bi-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Learning Path Suggestion -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Your Suggested Learning Path</h5>
                        <ol class="mb-0">
                            <li class="mb-2">Start with <strong>AI Fundamentals</strong> to build a solid foundation</li>
                            <li class="mb-2">Move on to <strong>Prompt Engineering</strong> based on your interest</li>
                            <li class="mb-2">Explore <strong>Project Management for AI</strong> aligned with your career goals</li>
                            <li class="mb-2">Practice with hands-on projects in your areas of interest</li>
                        </ol>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Debug toggle for developers -->
<?php if (isset($_GET['debug'])): ?>
<script>
document.getElementById('survey-narrative').classList.remove('d-none');
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>