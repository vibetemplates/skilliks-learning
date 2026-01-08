<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/User.php';
require_once 'classes/Task.php';

// Require login
requireLogin();

$page_title = 'My Projects';
$projectObj = new Project();

// Get current user ID
$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Get projects where user is a member (filtered by community)
$myProjects = $projectObj->findMyProjects($currentUserId, $currentCommunityId);

// Add skill IDs to each project for filtering
function addSkillIds(&$projects) {
    foreach ($projects as &$project) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT skill_id 
                FROM project_skills 
                WHERE project_id = ? AND importance_level = 'required'
            ");
            $stmt->execute([$project['id']]);
            $project['required_skill_ids'] = array_column($stmt->fetchAll(), 'skill_id');
        } catch (PDOException $e) {
            $project['required_skill_ids'] = [];
        }
    }
}

// Get all available skills for filter dropdown (filtered by community)
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.name, s.category 
        FROM skills s
        JOIN project_skills ps ON s.id = ps.skill_id
        JOIN projects p ON ps.project_id = p.id
        WHERE s.is_active = 1 AND p.community_id = ?
        ORDER BY s.category, s.name
    ");
    $stmt->execute([$currentCommunityId]);
    $availableSkills = $stmt->fetchAll();
} catch (PDOException $e) {
    $availableSkills = [];
}

addSkillIds($myProjects);

// Pagination settings
$projectsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalProjects = count($myProjects);
$totalPages = ceil($totalProjects / $projectsPerPage);
$offset = ($currentPage - 1) * $projectsPerPage;

// Slice the projects for current page
$projectsToDisplay = array_slice($myProjects, $offset, $projectsPerPage);

include 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div id="projects-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">My Projects</h1>
        <div id="projects-toolbar" class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="project-categories" class="btn btn-outline-secondary">
                    <i class="bi bi-grid"></i> Categories
                </a>
                <a href="projects.php" class="btn btn-outline-secondary">
                    <i class="bi bi-list"></i> All Projects
                </a>
            </div>
        </div>
    </div>

    <!-- Skill Filter -->
    <div class="row mb-4">
        <div class="col-md-4">
            <label for="skillFilter" class="form-label">Filter by Required Skill:</label>
            <select class="form-select" id="skillFilter">
                <option value="">All Skills</option>
                <?php 
                $currentCategory = '';
                foreach ($availableSkills as $skill): 
                    if ($skill['category'] !== $currentCategory):
                        if ($currentCategory !== ''): ?>
                            </optgroup>
                        <?php endif;
                        $currentCategory = $skill['category'];
                        ?>
                        <optgroup label="<?php echo htmlspecialchars($currentCategory); ?>">
                    <?php endif; ?>
                    <option value="<?php echo $skill['id']; ?>"><?php echo htmlspecialchars($skill['name']); ?></option>
                <?php endforeach; 
                if ($currentCategory !== ''): ?>
                    </optgroup>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <!-- Projects List -->
    <h3 class="mb-3">Projects I'm a Member Of</h3>
    
    <?php if (empty($projectsToDisplay)): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> You haven't joined any projects yet. 
                    <a href="projects.php" class="alert-link">Browse available projects</a> to get started.
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row" id="myProjectsList">
            <?php foreach ($projectsToDisplay as $project): ?>
                <div class="col-lg-4 mb-4">
                    <?php include 'includes/project-item.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="row">
                <div class="col-12">
                    <nav aria-label="Projects pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Skill filter functionality
    const skillFilter = document.getElementById('skillFilter');
    const projectCols = document.querySelectorAll('#myProjectsList > .col-lg-4');
    
    skillFilter.addEventListener('change', function() {
        const selectedSkillId = this.value;
        let visibleCount = 0;
        
        projectCols.forEach(function(col) {
            const projectItem = col.querySelector('.project-item');
            const requiredSkills = projectItem.dataset.requiredSkills ? 
                projectItem.dataset.requiredSkills.split(',') : [];
            
            if (selectedSkillId === '' || requiredSkills.includes(selectedSkillId)) {
                col.style.display = '';
                visibleCount++;
            } else {
                col.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        const noResultsMsg = document.getElementById('noFilterResults');
        if (visibleCount === 0 && selectedSkillId !== '') {
            if (!noResultsMsg) {
                const alertDiv = document.createElement('div');
                alertDiv.id = 'noFilterResults';
                alertDiv.className = 'col-12';
                alertDiv.innerHTML = '<div class="alert alert-info text-center mt-3"><i class="bi bi-info-circle"></i> No projects found with the selected skill requirement.</div>';
                document.getElementById('myProjectsList').appendChild(alertDiv);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    });
});
</script>

<!-- Voting functionality -->
<script src="/assets/js/voting.js"></script>

<?php include 'includes/footer.php'; ?>