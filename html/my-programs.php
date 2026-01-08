<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();
$userObj = new User();
$isAdmin = $userObj->isAdmin($currentUserId);

$db = getDB();

// Fetch programs where user is enrolled in at least one course in the current community
$programs_query = "
    SELECT DISTINCT p.* 
    FROM programs p
    INNER JOIN courses c ON p.id = c.program_id
    INNER JOIN course_enrollments ce ON c.id = ce.course_id
    WHERE ce.user_id = ? 
    AND ce.status IN ('enrolled', 'in_progress', 'completed')
    AND p.is_active = 1
    AND p.community_id = ?
    AND c.community_id = ?
    AND c.status = 'published'
    ORDER BY p.display_order, p.name
";
$programs_stmt = $db->prepare($programs_query);
$programs_stmt->execute([$currentUserId, $currentCommunityId, $currentCommunityId]);
$programs = $programs_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "My Programs";
require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4" style="padding-top: 40px;">
        <div id="my-programs-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">My Programs</h1>
            <div id="my-programs-toolbar" class="btn-toolbar mb-2 mb-md-0">
                <a href="/programs" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> All Programs
                </a>
                <a href="/my-courses" class="btn btn-outline-primary">
                    <i class="bi bi-book"></i> My Courses
                </a>
            </div>
        </div>

        <div class="row" id="my-programs-grid">
            <?php foreach ($programs as $program): ?>
                <div class="col-md-6 col-lg-3 mb-4" id="my-program-card-<?php echo $program['id']; ?>">
                    <div class="card h-100 course-card">
                        <?php if ($program['thumbnail_url']): ?>
                            <img src="<?php echo htmlspecialchars($program['thumbnail_url']); ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="bi bi-mortarboard text-muted" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column" id="my-program-body-<?php echo $program['id']; ?>">
                            <h5 class="card-title" id="my-program-title-<?php echo $program['id']; ?>">
                                <?php echo htmlspecialchars($program['name']); ?>
                            </h5>
                            <p class="card-text flex-grow-1" id="my-program-desc-<?php echo $program['id']; ?>">
                                <?php echo htmlspecialchars($program['short_description'] ?? $program['description'] ?? 'Explore courses in this program.'); ?>
                            </p>
                            
                            <?php 
                            // Get enrolled course count for this program
                            $stmt = $db->prepare("
                                SELECT COUNT(DISTINCT c.id) as enrolled_count,
                                       COUNT(DISTINCT CASE WHEN ce.status = 'completed' THEN c.id END) as completed_count
                                FROM courses c
                                INNER JOIN course_enrollments ce ON c.id = ce.course_id
                                WHERE c.program_id = ? 
                                AND ce.user_id = ?
                                AND ce.status IN ('enrolled', 'in_progress', 'completed')
                                AND c.status = 'published'
                            ");
                            $stmt->execute([$program['id'], $currentUserId]);
                            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="small text-muted mb-2">
                                <i class="bi bi-book-half"></i> <?php echo $counts['enrolled_count']; ?> enrolled
                                <?php if ($counts['completed_count'] > 0): ?>
                                    <span class="text-success">
                                        • <i class="bi bi-check-circle"></i> <?php echo $counts['completed_count']; ?> completed
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-auto">
                                <a href="courses?program_id=<?php echo $program['id']; ?>" 
                                   class="btn btn-primary btn-sm" 
                                   id="my-program-btn-<?php echo $program['id']; ?>">
                                    <i class="bi bi-arrow-right-circle"></i> View My Courses
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($programs)): ?>
            <div class="row" id="no-programs-row">
                <div class="col-12">
                    <div class="alert alert-info" id="no-programs-alert">
                        <i class="bi bi-info-circle"></i> You are not enrolled in any programs yet.
                        <a href="/programs" class="alert-link">Browse all programs</a> to get started.
                    </div>
                </div>
            </div>
        <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>