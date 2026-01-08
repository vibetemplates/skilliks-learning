<?php
/**
 * Courses Page
 * 
 * Lists all courses with enrollment functionality
 */

$page_title = 'Courses';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Course.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();
$userObj = new User();
$isAdmin = $userObj->isAdmin($currentUserId);

// Get current community name
$communityObj = new Community();
$currentCommunity = $communityObj->getById($currentCommunityId);
$currentCommunityName = $currentCommunity ? htmlspecialchars($currentCommunity['name']) : 'Community';

// Get program_id from URL, default to 1
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 1;

// Fetch program information
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM programs WHERE id = ? AND is_active = 1");
    $stmt->execute([$program_id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$program) {
        // If program not found, default to program_id = 1
        $program_id = 1;
        $stmt = $db->prepare("SELECT * FROM programs WHERE id = ? AND is_active = 1");
        $stmt->execute([$program_id]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Program fetch error: " . $e->getMessage());
    $program = null;
}

// Handle course creation (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!$isAdmin) {
        setFlashMessage('error', 'Only administrators can create courses.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $course_code = trim($_POST['course_code'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty_level = $_POST['difficulty_level'] ?? 'beginner';
        $duration_hours = (float)($_POST['duration_hours'] ?? 0);
        $prerequisites = trim($_POST['prerequisites'] ?? '');
        $learning_objectives = trim($_POST['learning_objectives'] ?? '');
        $status = $_POST['status'] ?? 'published';
        $featured = isset($_POST['featured']) ? 1 : 0;
        $certificate_available = isset($_POST['certificate_available']) ? 1 : 0;
        $passing_score = (float)($_POST['passing_score'] ?? 70);
        $tags = trim($_POST['tags'] ?? '');
        
        if (empty($title)) {
            setFlashMessage('error', 'Course title is required.');
        } elseif (empty($description)) {
            setFlashMessage('error', 'Course description is required.');
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    INSERT INTO courses (title, description, short_description, course_code, category, 
                                       difficulty_level, duration_hours, prerequisites, learning_objectives, 
                                       status, featured, certificate_available, passing_score, tags, 
                                       created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $result = $stmt->execute([
                    $title, $description, $short_description, $course_code, $category,
                    $difficulty_level, $duration_hours, $prerequisites, $learning_objectives,
                    $status, $featured, $certificate_available, $passing_score, $tags,
                    $currentUserId
                ]);
                
                if ($result) {
                    $courseId = $db->lastInsertId();
                    setFlashMessage('success', 'Course created successfully!');
                    
                    // If published, refresh the page to show the new course
                    if ($status === 'published') {
                        header('Location: /courses');
                        exit;
                    }
                } else {
                    setFlashMessage('error', 'Failed to create course.');
                }
            } catch (PDOException $e) {
                error_log("Course creation error: " . $e->getMessage());
                setFlashMessage('error', 'Database error occurred while creating course.');
            }
        }
    }
    
    header('Location: /courses');
    exit;
}

// Get available courses (published courses for regular users, all courses for admins)
try {
    $db = getDB();
    
    // Admins can see all courses (including drafts), regular users only see published courses
    $statusCondition = $isAdmin ? "c.status IN ('draft', 'published')" : "c.status = 'published'";
    $whereClause = "WHERE $statusCondition AND c.program_id = ?";
    
    $stmt = $db->prepare("
        SELECT c.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status IN ('enrolled', 'in_progress', 'completed')) as enrollment_count
        FROM courses c
        LEFT JOIN users u ON c.created_by = u.id
        $whereClause
        ORDER BY c.featured DESC, c.order_index ASC, c.created_at DESC
    ");
    $stmt->execute([$program_id]);
    $availableCourses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Available courses query error: " . $e->getMessage());
    $availableCourses = [];
}

// Get user's enrolled courses with progress
try {
    $db = getDB();
    
    // Admins can see all lessons (including drafts), regular users only see published lessons
    $lessonStatusFilter = $isAdmin ? "status IN ('draft', 'published')" : "status = 'published'";
    
    $stmt = $db->prepare("
        SELECT c.*, u.first_name, u.last_name,
               ce.enrollment_date, ce.completion_date, ce.status as enrollment_status,
               ce.progress_percentage, ce.final_score, ce.last_accessed, ce.time_spent_minutes,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id AND $lessonStatusFilter) as total_lessons,
               (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.course_id = c.id AND lp.user_id = ? AND lp.status = 'completed') as completed_lessons
        FROM courses c
        LEFT JOIN users u ON c.created_by = u.id
        JOIN course_enrollments ce ON c.id = ce.course_id
        WHERE ce.user_id = ? AND ce.status IN ('enrolled', 'in_progress', 'completed')
        ORDER BY 
            CASE ce.status 
                WHEN 'in_progress' THEN 1
                WHEN 'enrolled' THEN 2
                WHEN 'completed' THEN 3
            END,
            ce.last_accessed DESC
    ");
    $stmt->execute([$currentUserId, $currentUserId]);
    $userCourses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("User courses query error: " . $e->getMessage());
    $userCourses = [];
}

// Keep track of enrolled course IDs but don't filter them out
$enrolledCourseIds = array_column($userCourses, 'id');

// Fetch skills for each course
$courseObj = new Course();
foreach ($availableCourses as &$course) {
    $course['skills'] = $courseObj->getCourseSkills($course['id']);
}
foreach ($userCourses as &$course) {
    $course['skills'] = $courseObj->getCourseSkills($course['id']);
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
            <div id="courses-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $currentCommunityName; ?> - Courses<?php echo $program ? ' - ' . htmlspecialchars($program['name']) : ''; ?></h1>
                <div id="courses-toolbar" class="btn-toolbar mb-2 mb-md-0">
                    <a href="/programs" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Programs
                    </a>
                    <?php if ($isAdmin): ?>
                    <a href="/admin-course-create.php" class="btn btn-primary me-2">
                        <i class="bi bi-plus-circle"></i> New Course
                    </a>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Course Tabs -->
            <ul class="nav nav-tabs mb-3" id="courseTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="available-courses-tab" data-bs-toggle="tab" data-bs-target="#available-courses" type="button" role="tab">
                        <i class="bi bi-book-half"></i> All Courses
                        <span class="badge bg-success ms-1"><?php echo count($availableCourses); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="my-courses-tab" data-bs-toggle="tab" data-bs-target="#my-courses" type="button" role="tab">
                        <i class="bi bi-person-circle"></i> My Courses
                        <span class="badge bg-primary ms-1"><?php echo count($userCourses); ?></span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content with Two Column Layout -->
            <div id="courses-main-row" class="row">
                <!-- Main Course Content Column -->
                <div id="courses-content-column" class="col-lg-12">
                    <div id="courseTabContent" class="tab-content">
                        <!-- All Courses Tab -->
                        <div id="available-courses" class="tab-pane fade show active" role="tabpanel">
                    <?php if (empty($availableCourses)): ?>
                        <div id="no-courses-alert" class="alert alert-info fade-in">
                            <i class="bi bi-info-circle"></i>
                            No courses available at the moment. Check back later for new courses.
                        </div>
                    <?php else: ?>
                        <div id="available-courses-grid" class="row">
                            <?php foreach ($availableCourses as $course): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div id="course-card-<?php echo $course['id']; ?>" class="card h-100 course-card">
                                        <?php if ($course['featured']): ?>
                                            <div class="badge bg-warning position-absolute" style="top: 10px; right: 10px; z-index: 1;">
                                                <i class="bi bi-star-fill"></i> Featured
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($course['id'], $enrolledCourseIds)): ?>
                                            <div class="badge bg-success position-absolute" style="top: 10px; left: 10px; z-index: 1;">
                                                <i class="bi bi-check-circle-fill"></i> Enrolled
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($course['thumbnail_url']): ?>
                                            <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                                <i class="bi bi-book text-muted" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body d-flex flex-column">
                                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                            <p class="card-text flex-grow-1"><?php echo htmlspecialchars($course['short_description'] ?? $course['description'] ?? 'No description available'); ?></p>
                                            
                                            <div class="mb-2">
                                                <?php if ($course['course_code']): ?>
                                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                                <?php endif; ?>
                                                <span class="badge bg-info"><?php echo ucfirst($course['difficulty_level']); ?></span>
                                                <?php if ($course['status'] === 'draft' && $isAdmin): ?>
                                                    <span class="badge bg-warning me-1">Draft</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="small text-muted mb-2">
                                                <i class="bi bi-clock"></i> <?php echo $course['duration_hours']; ?> hours
                                                • <i class="bi bi-people"></i> <?php echo $course['enrollment_count']; ?> enrolled
                                                <?php if ($course['first_name'] && $course['last_name']): ?>
                                                    <br><i class="bi bi-person"></i> <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($course['skills'])): ?>
                                                <div class="mb-2">
                                                    <strong class="text-muted small">Skills Covered:</strong><br>
                                                    <?php foreach ($course['skills'] as $skill): ?>
                                                        <span class="badge bg-<?php echo $skill['skill_level'] == 'advanced' ? 'danger' : ($skill['skill_level'] == 'intermediate' ? 'warning' : 'success'); ?> me-1 mb-1" style="font-size: 0.75rem;">
                                                            <?php echo htmlspecialchars($skill['name']); ?>
                                                            <small>(<?php echo substr($skill['skill_level'], 0, 3); ?>)</small>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-auto">
                                                <a href="/course-detail?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-eye"></i> View Course
                                                </a>
                                                <?php if ($course['status'] === 'published' && !in_array($course['id'], $enrolledCourseIds)): ?>
                                                    <button type="button" class="btn btn-outline-success btn-sm ms-1" onclick="enrollInCourse(<?php echo $course['id']; ?>)">
                                                        <i class="bi bi-plus-circle"></i> Enroll
                                                    </button>
                                                <?php elseif ($course['status'] === 'draft'): ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" disabled>
                                                        <i class="bi bi-lock"></i> Draft
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($isAdmin): ?>
                                                    <a href="/admin-course-edit.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary btn-sm ms-1">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- My Courses Tab -->
                <div class="tab-pane fade" id="my-courses" role="tabpanel">
                    <?php if (empty($userCourses)): ?>
                        <div id="no-enrolled-courses-alert" class="alert alert-info fade-in">
                            <i class="bi bi-info-circle"></i>
                            You haven't enrolled in any courses yet. Browse available courses to get started.
                        </div>
                    <?php else: ?>
                        <div id="my-courses-list" class="list-group">
                            <?php foreach ($userCourses as $course): ?>
                                <div id="my-course-item-<?php echo $course['id']; ?>" class="list-group-item mb-3 border-primary">
                                    <div class="row">
                                        <!-- Course Thumbnail -->
                                        <div class="col-md-2">
                                            <?php if (isset($course['thumbnail_url']) && $course['thumbnail_url']): ?>
                                                <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" class="img-fluid rounded" style="width: 100%; height: 120px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 100%; height: 120px;">
                                                    <i class="bi bi-book text-muted" style="font-size: 2.5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Course Information (Middle) -->
                                        <div class="col-md-5">
                                            <h5 class="mb-2">
                                                <i class="bi bi-book-fill text-primary me-2"></i>
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </h5>
                                            <p class="card-text"><?php echo htmlspecialchars($course['short_description'] ?? $course['description'] ?? 'No description available'); ?></p>
                                            <div class="mb-2">
                                                <?php if ($course['course_code']): ?>
                                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                                <?php endif; ?>
                                                <?php if (isset($course['enrollment_status'])): ?>
                                                <span class="badge bg-<?php echo $course['enrollment_status'] === 'completed' ? 'success' : ($course['enrollment_status'] === 'in_progress' ? 'warning' : 'primary'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $course['enrollment_status'])); ?>
                                                </span>
                                                <?php endif; ?>
                                                <span class="badge bg-info"><?php echo ucfirst($course['difficulty_level']); ?></span>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i> <?php echo $course['duration_hours']; ?> hours
                                                <?php if (isset($course['time_spent_minutes']) && $course['time_spent_minutes'] > 0): ?>
                                                    • <i class="bi bi-person-check"></i> <?php echo number_format($course['time_spent_minutes'] / 60, 1); ?> hours spent
                                                <?php endif; ?>
                                            </small>
                                            <?php if (!empty($course['skills'])): ?>
                                                <div class="mt-2 mb-2">
                                                    <strong class="text-muted small">Skills Covered:</strong><br>
                                                    <?php foreach ($course['skills'] as $skill): ?>
                                                        <span class="badge bg-<?php echo $skill['skill_level'] == 'advanced' ? 'danger' : ($skill['skill_level'] == 'intermediate' ? 'warning' : 'success'); ?> me-1 mb-1" style="font-size: 0.75rem;">
                                                            <?php echo htmlspecialchars($skill['name']); ?>
                                                            <small>(<?php echo substr($skill['skill_level'], 0, 3); ?>)</small>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <a href="/course-detail?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-play-circle"></i> Continue Learning
                                                </a>
                                                <?php if (isset($course['enrollment_status']) && $course['enrollment_status'] === 'completed'): ?>
                                                    <button type="button" class="btn btn-outline-success btn-sm ms-1" disabled>
                                                        <i class="bi bi-check-circle"></i> Completed
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($isAdmin): ?>
                                                    <a href="/admin-course-edit.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-secondary btn-sm ms-1">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Progress Information (Right Side) -->
                                        <div class="col-md-5">
                                            <div class="border-start ps-3">
                                                <h6 class="text-muted mb-2">
                                                    <i class="bi bi-graph-up"></i> Progress
                                                </h6>
                                                <div class="progress mb-2" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo isset($course['progress_percentage']) ? $course['progress_percentage'] : 0; ?>%"></div>
                                                </div>
                                                <div class="small text-muted mb-2">
                                                    <?php echo number_format(isset($course['progress_percentage']) ? $course['progress_percentage'] : 0, 1); ?>% complete
                                                    (<?php echo isset($course['completed_lessons']) ? $course['completed_lessons'] : 0; ?>/<?php echo isset($course['total_lessons']) ? $course['total_lessons'] : 0; ?> lessons)
                                                </div>
                                                <?php if (isset($course['final_score']) && $course['final_score']): ?>
                                                    <div class="small text-muted mb-2">
                                                        <i class="bi bi-trophy"></i> Score: <?php echo number_format($course['final_score'], 1); ?>%
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (isset($course['last_accessed']) && $course['last_accessed']): ?>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-clock-history"></i> Last accessed: <?php echo date('M j, g:i A', strtotime($course['last_accessed'])); ?>
                                                    </div>
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
        </div>
                
                <!-- Custom Learning Plan Column -->
                <!-- Commented out for later implementation
                <div id="learning-plan-sidebar" class="col-lg-3">
                    <div id="learning-plan-card" class="card sticky-top" style="top: 20px;">
                        <div id="learning-plan-header" class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-compass"></i> My Learning Plan
                            </h5>
                        </div>
                        <div id="learning-plan-body" class="card-body">
                            <p class="small text-muted">Your personalized learning path based on your survey responses and selected projects.</p>
                            
                            <div id="survey-status-section" class="mb-3">
                                <h6 class="fw-bold">Survey Status</h6>
                                <div id="survey-status-alert" class="alert alert-info py-2 small">
                                    <i class="bi bi-clipboard-check"></i> Complete your learning survey to get personalized recommendations
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="fw-bold">Selected Projects</h6>
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item px-0 py-2">
                                        <small class="text-muted">No projects selected yet</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="fw-bold">Recommended For You</h6>
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item px-0 py-2">
                                        <small class="text-muted">Complete your survey to see recommendations</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="fw-bold">Learning Progress</h6>
                                <div class="progress mb-2" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted">0% Complete</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil-square"></i> Take Survey
                                </button>
                                <button class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-folder"></i> Browse Projects
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                -->
            </div>
</main>


<script>
async function enrollInCourse(courseId) {
    try {
        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('action', 'enroll');
        
        const response = await fetch('/api/course-enroll.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Refresh the page to update the course lists without alert
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Enrollment error:', error);
        alert('An error occurred while enrolling in the course. Please try again.');
    }
}

async function unenrollFromCourse(courseId) {
    if (!confirm('Are you sure you want to unenroll from this course? Your progress will be saved.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('action', 'unenroll');
        
        const response = await fetch('/api/course-enroll.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            // Refresh the page to update the course lists
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Unenrollment error:', error);
        alert('An error occurred while unenrolling from the course. Please try again.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>