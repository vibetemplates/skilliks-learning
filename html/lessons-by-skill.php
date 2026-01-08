<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$userObj = new User();
$isAdmin = $userObj->isAdmin($currentUserId);

$db = getDB();

// Get skill_id from GET parameters
$skill_id = isset($_GET['skill_id']) ? intval($_GET['skill_id']) : 0;

if (!$skill_id) {
    header('Location: programs.php');
    exit;
}

// Fetch skill details
$skill_query = "SELECT * FROM skills WHERE id = ? AND is_active = 1";
$skill_stmt = $db->prepare($skill_query);
$skill_stmt->execute([$skill_id]);
$skill = $skill_stmt->fetch(PDO::FETCH_ASSOC);

if (!$skill) {
    header('Location: programs.php');
    exit;
}

// Fetch lessons that teach this skill
$lessons_query = "
    SELECT 
        l.*,
        c.title as course_name,
        c.id as course_id,
        p.name as program_name,
        p.id as program_id,
        ls.skill_level,
        ls.is_required,
        ce.user_id as is_enrolled
    FROM lessons l
    INNER JOIN lesson_skills ls ON l.id = ls.lesson_id
    INNER JOIN courses c ON l.course_id = c.id
    LEFT JOIN programs p ON c.program_id = p.id
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id 
        AND ce.user_id = ? 
        AND ce.status IN ('enrolled', 'in_progress', 'completed')
    WHERE ls.skill_id = ? 
    AND c.status = 'published'
    AND l.status = 'published'
    ORDER BY p.name, c.title, l.order_index
";

$lessons_stmt = $db->prepare($lessons_query);
$lessons_stmt->execute([$currentUserId, $skill_id]);
$lessons = $lessons_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Lessons for " . htmlspecialchars($skill['name']);
require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4" style="padding-top: 40px;">
    <div id="lessons-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div>
            <h1 class="h2">Lessons for <?php echo htmlspecialchars($skill['name']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($skill['description'] ?? ''); ?></p>
        </div>
        <div id="lessons-toolbar" class="btn-toolbar mb-2 mb-md-0">
            <a href="programs.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Programs
            </a>
        </div>
    </div>

    <?php if (empty($lessons)): ?>
        <div class="alert alert-info" id="no-lessons-alert">
            <i class="bi bi-info-circle"></i> No lessons found for this skill.
        </div>
    <?php else: ?>
        <div class="row" id="lessons-grid">
            <?php 
            $current_program = '';
            $current_course = '';
            foreach ($lessons as $lesson): 
                $is_enrolled = !empty($lesson['is_enrolled']);
                
                // Show program header when it changes
                if ($lesson['program_name'] != $current_program) {
                    $current_program = $lesson['program_name'];
                    $current_course = ''; // Reset course when program changes
                    ?>
                    <div class="col-12 mt-4" id="program-header-<?php echo $lesson['program_id']; ?>">
                        <h3 class="text-primary"><?php echo htmlspecialchars($current_program ?: 'No Program'); ?></h3>
                        <hr>
                    </div>
                    <?php
                }
                
                // Show course header when it changes
                if ($lesson['course_name'] != $current_course) {
                    $current_course = $lesson['course_name'];
                    ?>
                    <div class="col-12 mt-3 mb-2" id="course-header-<?php echo $lesson['course_id']; ?>">
                        <h4 class="text-secondary">
                            <i class="bi bi-book"></i> <?php echo htmlspecialchars($current_course); ?>
                            <?php if (!$is_enrolled): ?>
                                <a href="course-detail?id=<?php echo $lesson['course_id']; ?>" class="btn btn-sm btn-outline-primary ms-2">
                                    <i class="bi bi-info-circle"></i> View Course
                                </a>
                            <?php endif; ?>
                        </h4>
                    </div>
                    <?php
                }
            ?>
                <div class="col-md-6 col-lg-4 mb-3" id="lesson-card-<?php echo $lesson['id']; ?>">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title" id="lesson-title-<?php echo $lesson['id']; ?>">
                                <?php echo htmlspecialchars($lesson['title']); ?>
                            </h5>
                            <p class="card-text" id="lesson-desc-<?php echo $lesson['id']; ?>">
                                <?php echo htmlspecialchars($lesson['description'] ?? 'No description available'); ?>
                            </p>
                            <div class="mb-2">
                                <span class="badge bg-info" id="skill-level-<?php echo $lesson['id']; ?>">
                                    <?php echo ucfirst($lesson['skill_level']); ?> Level
                                </span>
                                <?php if ($lesson['is_required']): ?>
                                    <span class="badge bg-warning" id="skill-required-<?php echo $lesson['id']; ?>">
                                        Required for Skill
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-auto">
                                <?php if ($is_enrolled): ?>
                                    <a href="course-detail?id=<?php echo $lesson['course_id']; ?>&lesson=<?php echo $lesson['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-play-circle"></i> Go to Lesson
                                    </a>
                                <?php else: ?>
                                    <a href="course-detail?id=<?php echo $lesson['course_id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-lock"></i> Enroll in Course
                                    </a>
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