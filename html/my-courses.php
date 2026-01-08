<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Course.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentUserRole = getCurrentUserRole();
$userObj = new User();
$isAdmin = $userObj->isAdmin($currentUserId);

// Get database connection
$db = getDB();

// Fetch all enrolled courses grouped by program
$courses_query = "
    SELECT 
        c.*,
        p.name as program_name,
        ce.enrollment_date,
        ce.status as enrollment_status,
        ce.progress_percentage,
        ce.completion_date,
        COUNT(DISTINCT l.id) as total_lessons,
        COUNT(DISTINCT lp.lesson_id) as completed_lessons
    FROM course_enrollments ce
    INNER JOIN courses c ON ce.course_id = c.id
    INNER JOIN programs p ON c.program_id = p.id
    LEFT JOIN lessons l ON l.course_id = c.id AND l.status = 'published'
    LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ce.user_id AND lp.status = 'completed'
    WHERE ce.user_id = ?
    AND ce.status IN ('enrolled', 'in_progress', 'completed')
    AND c.status = 'published'
    GROUP BY c.id, ce.id
    ORDER BY p.display_order, p.name, c.order_index, c.title
";
$courses_stmt = $db->prepare($courses_query);
$courses_stmt->execute([$currentUserId]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group courses by program
$coursesByProgram = [];
foreach ($courses as $course) {
    $programName = $course['program_name'];
    if (!isset($coursesByProgram[$programName])) {
        $coursesByProgram[$programName] = [];
    }
    $coursesByProgram[$programName][] = $course;
}

$page_title = "My Courses";
require_once 'includes/header.php';
?>

<style>
.progress {
    height: 8px;
}
.course-status-badge {
    font-size: 0.75rem;
}
</style>

<main class="container-fluid px-4" style="padding-top: 40px;">
    <div id="my-courses-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">My Courses</h1>
        <div id="my-courses-toolbar" class="btn-toolbar mb-2 mb-md-0">
            <a href="/programs" class="btn btn-outline-secondary me-2">
                <i class="bi bi-grid"></i> All Programs
            </a>
            <a href="/my-programs" class="btn btn-outline-primary">
                <i class="bi bi-person-circle"></i> My Programs
            </a>
        </div>
    </div>

    <?php if (empty($courses)): ?>
        <div class="row" id="no-courses-row">
            <div class="col-12">
                <div class="alert alert-info" id="no-courses-alert">
                    <i class="bi bi-info-circle"></i> You are not enrolled in any courses yet.
                    <a href="/programs" class="alert-link">Browse programs</a> to find courses to enroll in.
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Display all courses in a single grid -->
        <div class="row">
            <?php foreach ($courses as $course): ?>
                <div class="col-md-6 col-lg-4 mb-4" id="my-course-card-<?php echo $course['id']; ?>">
                            <div class="card h-100 course-card">
                                <?php if ($course['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" class="card-img-top" style="height: 180px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                                        <i class="bi bi-book text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body d-flex flex-column" id="my-course-body-<?php echo $course['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="card-title mb-1" id="my-course-title-<?php echo $course['id']; ?>">
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                <i class="bi bi-folder2"></i> <?php echo htmlspecialchars($course['program_name']); ?>
                                            </small>
                                        </div>
                                        <?php
                                        $statusBadge = '';
                                        $statusClass = '';
                                        switch ($course['enrollment_status']) {
                                            case 'completed':
                                                $statusBadge = 'Completed';
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'in_progress':
                                                $statusBadge = 'In Progress';
                                                $statusClass = 'bg-primary';
                                                break;
                                            default:
                                                $statusBadge = 'Enrolled';
                                                $statusClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> course-status-badge"><?php echo $statusBadge; ?></span>
                                    </div>
                                    
                                    <p class="card-text text-muted small flex-grow-1" id="my-course-desc-<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['short_description'] ?? substr($course['description'] ?? '', 0, 100) . '...'); ?>
                                    </p>
                                    
                                    <!-- Progress bar -->
                                    <?php if ($course['total_lessons'] > 0): ?>
                                        <?php $progress = round(($course['completed_lessons'] / $course['total_lessons']) * 100); ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <small class="text-muted">Progress</small>
                                                <small class="text-muted"><?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> lessons</small>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%;" 
                                                     aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $progress; ?>%
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="small text-muted mb-3">
                                        <i class="bi bi-calendar3"></i> Enrolled: <?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?>
                                        <?php if ($course['completion_date']): ?>
                                            <br><i class="bi bi-check-circle text-success"></i> Completed: <?php echo date('M d, Y', strtotime($course['completion_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <a href="/course-detail?id=<?php echo $course['id']; ?>" 
                                           class="btn btn-primary btn-sm w-100" 
                                           id="my-course-btn-<?php echo $course['id']; ?>">
                                            <i class="bi bi-play-circle"></i> Continue Course
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>