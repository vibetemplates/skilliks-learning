<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();
$userObj = new User();
$isAdmin = $userObj->isAdmin($currentUserId);

$db = getDB();

// Get current community name
$communityObj = new Community();
$currentCommunity = $communityObj->getById($currentCommunityId);
$currentCommunityName = $currentCommunity ? htmlspecialchars($currentCommunity['name']) : 'Community';

// Fetch all active programs for the current community
$programs_query = "SELECT * FROM programs WHERE is_active = 1 AND community_id = ? ORDER BY display_order, name";
$programs_stmt = $db->prepare($programs_query);
$programs_stmt->execute([$currentCommunityId]);
$programs = $programs_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Programs";
require_once 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4" style="padding-top: 40px;">
        <div id="programs-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><?php echo $currentCommunityName; ?> - Programs</h1>
            <div id="programs-toolbar" class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="/my-programs" class="btn btn-outline-primary">
                        <i class="bi bi-person-circle"></i> My Programs
                    </a>
                    <a href="/my-courses" class="btn btn-outline-primary">
                        <i class="bi bi-book"></i> My Courses
                    </a>
                </div>
                <?php if ($isAdmin): ?>
                <a href="/admin-program-create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> New Program
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Skill search section -->
        <div id="skill-search-section" class="mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title" id="skill-search-title">Find Lessons by Skill</h5>
                    <form action="lessons-by-skill.php" method="GET" id="skill-search-form">
                        <div class="row">
                            <div class="col-md-8">
                                <select name="skill_id" class="form-select" id="skill-select" required>
                                    <option value="">Type or select a skill...</option>
                                    <?php
                                    // Fetch all active skills
                                    $skills_query = "SELECT id, name, category FROM skills WHERE is_active = 1 ORDER BY category, name";
                                    $skills_stmt = $db->prepare($skills_query);
                                    $skills_stmt->execute();
                                    $skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    $current_category = '';
                                    foreach ($skills as $skill) {
                                        if ($skill['category'] != $current_category) {
                                            if ($current_category != '') {
                                                echo '</optgroup>';
                                            }
                                            echo '<optgroup label="' . htmlspecialchars($skill['category']) . '">';
                                            $current_category = $skill['category'];
                                        }
                                        echo '<option value="' . $skill['id'] . '" data-search="' . htmlspecialchars(strtolower($skill['name'] . ' ' . $skill['category'])) . '">' . htmlspecialchars($skill['name']) . '</option>';
                                    }
                                    if ($current_category != '') {
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary" id="skill-search-btn">
                                    <i class="bi bi-search"></i> Search Lessons
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row" id="programs-grid">
            <?php foreach ($programs as $program): ?>
                <div class="col-md-6 col-lg-3 mb-4" id="program-card-<?php echo $program['id']; ?>">
                    <div class="card h-100 course-card">
                        <?php if ($program['thumbnail_url']): ?>
                            <img src="<?php echo htmlspecialchars($program['thumbnail_url']); ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="bi bi-mortarboard text-muted" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column" id="program-body-<?php echo $program['id']; ?>">
                            <h5 class="card-title" id="program-title-<?php echo $program['id']; ?>">
                                <?php echo htmlspecialchars($program['name']); ?>
                            </h5>
                            <p class="card-text flex-grow-1" id="program-desc-<?php echo $program['id']; ?>">
                                <?php echo htmlspecialchars($program['short_description'] ?? $program['description'] ?? 'Explore courses in this program.'); ?>
                            </p>
                            
                            <?php 
                            // Get course count for this program
                            $stmt = $db->prepare("SELECT COUNT(*) as course_count FROM courses WHERE program_id = ? AND status = 'published'");
                            $stmt->execute([$program['id']]);
                            $course_count = $stmt->fetch(PDO::FETCH_ASSOC)['course_count'];
                            ?>
                            
                            <div class="small text-muted mb-2">
                                <i class="bi bi-book"></i> <?php echo $course_count; ?> <?php echo $course_count == 1 ? 'course' : 'courses'; ?> available
                            </div>
                            
                            <div class="mt-auto">
                                <a href="courses?program_id=<?php echo $program['id']; ?>" 
                                   class="btn btn-primary btn-sm" 
                                   id="program-btn-<?php echo $program['id']; ?>">
                                    <i class="bi bi-arrow-right-circle"></i> View Courses
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="/admin-program-edit.php?id=<?php echo $program['id']; ?>" 
                                       class="btn btn-outline-secondary btn-sm ms-1"
                                       id="program-edit-btn-<?php echo $program['id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                <?php endif; ?>
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
                        <i class="bi bi-info-circle"></i> No programs are currently available.
                    </div>
                </div>
            </div>
        <?php endif; ?>
</main>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize Select2 on the skill dropdown
    $('#skill-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Type or select a skill...',
        allowClear: true,
        width: '100%',
        matcher: function(params, data) {
            // If there are no search terms, return all of the data
            if ($.trim(params.term) === '') {
                return data;
            }

            // Do not display the item if there is no 'text' property
            if (typeof data.text === 'undefined') {
                return null;
            }

            // Search in both the text and the data-search attribute
            var searchData = $(data.element).data('search') || '';
            var searchText = data.text.toLowerCase() + ' ' + searchData;
            
            // Check if the text contains the search term
            if (searchText.indexOf(params.term.toLowerCase()) > -1) {
                return data;
            }

            // Return `null` if the term should not be displayed
            return null;
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>