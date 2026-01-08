<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/SkillsDrill.php';

// Require login
requireLogin();

// Note: skills_drills table has lesson_id, not course_id - course info comes from lessons table

$pageTitle = 'Available Skills Drills';
require_once 'includes/header.php';

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

// Check if user wants to see all drills
$showAll = isset($_GET['show_all']) && $_GET['show_all'] === '1';

if ($showAll) {
    // Get all available drills
    $sql = "SELECT sd.*, c.title as course_title, l.title as lesson_title, l.course_id,
            (SELECT COUNT(*) FROM skills_drill_questions WHERE drill_id = sd.id) as question_count,
            ce.user_id as is_enrolled,
            lp.user_id as has_started_lesson
            FROM skills_drills sd
            JOIN lessons l ON sd.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.user_id = :user_id AND ce.status IN ('enrolled', 'in_progress')
            LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = :user_id2
            ORDER BY c.title, l.order_index";
} else {
    // Get only drills for lessons the user has started
    $sql = "SELECT sd.*, c.title as course_title, l.title as lesson_title, l.course_id,
            (SELECT COUNT(*) FROM skills_drill_questions WHERE drill_id = sd.id) as question_count,
            ce.user_id as is_enrolled,
            lp.user_id as has_started_lesson
            FROM skills_drills sd
            JOIN lessons l ON sd.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            JOIN course_enrollments ce ON c.id = ce.course_id AND ce.user_id = :user_id AND ce.status IN ('enrolled', 'in_progress')
            JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = :user_id2
            WHERE lp.status IN ('in_progress', 'completed')
            ORDER BY c.title, l.order_index";
}
        
$stmt = $db->prepare($sql);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindParam(':user_id2', $userId, PDO::PARAM_INT);
$stmt->execute();
$drills = $stmt->fetchAll();
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Available Skills Drills</h1>
        <div>
            <?php if ($showAll): ?>
                <a href="?show_all=0" class="btn btn-secondary">
                    <i class="bi bi-funnel"></i> Show My Lessons Only
                </a>
            <?php else: ?>
                <a href="?show_all=1" class="btn btn-outline-secondary">
                    <i class="bi bi-grid"></i> Show All Drills
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$showAll): ?>
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle"></i> Showing drills only for lessons you've started. 
            <a href="?show_all=1">Click here</a> to see all available drills.
        </div>
    <?php else: ?>
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle"></i> Showing all drills. Some may be for courses you haven't enrolled in yet.
        </div>
    <?php endif; ?>
    
    <?php if (empty($drills)): ?>
        <div class="alert alert-info">
            <?php if (!$showAll): ?>
                You haven't started any lessons with skills drills yet. Start a lesson to see available drills, or 
                <a href="?show_all=1">view all drills</a>.
            <?php else: ?>
                No skills drills are currently available. Please check back later.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($drills as $drill): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 <?= (!$drill['is_enrolled'] && $showAll) ? 'border-warning' : '' ?>">
                        <div class="card-header <?= (!$drill['is_enrolled'] && $showAll) ? 'bg-warning text-dark' : '' ?>">
                            <h6 class="mb-0"><?= htmlspecialchars($drill['course_title']) ?></h6>
                            <?php if (!$drill['is_enrolled'] && $showAll): ?>
                                <small class="text-muted">Not Enrolled</small>
                            <?php elseif (!$drill['has_started_lesson'] && $showAll): ?>
                                <small class="text-muted">Lesson Not Started</small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($drill['title']) ?></h5>
                            <p class="text-muted mb-2">Lesson: <?= htmlspecialchars($drill['lesson_title']) ?></p>
                            <?php if ($drill['description']): ?>
                                <p class="card-text"><?= htmlspecialchars($drill['description']) ?></p>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="badge bg-secondary"><?= $drill['question_count'] ?> questions</span>
                                <?php if ($drill['is_enrolled']): ?>
                                    <a href="/skills-drill-take.php?drill_id=<?= $drill['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-play-circle"></i> Start Drill
                                    </a>
                                <?php else: ?>
                                    <a href="/course-detail?id=<?= $drill['course_id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="bi bi-lock"></i> Enroll First
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

<?php require_once 'includes/footer.php'; ?>