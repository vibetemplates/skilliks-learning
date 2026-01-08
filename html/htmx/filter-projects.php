<?php
/**
 * HTMX Filter Projects Endpoint
 * Returns filtered project list based on skill or category
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';
require_once '../classes/ProjectCategory.php';

// Require login
requireLogin();

$projectObj = new Project();
$currentUserId = getCurrentUserId();

// Get filter parameters
$skillId = isset($_GET['skill']) ? (int)$_GET['skill'] : null;
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$projectsPerPage = 10;

// Get projects based on filters
if ($categoryId) {
    $activeProjects = $projectObj->findByCategory($categoryId, null, $currentUserId);
} else {
    $activeProjects = $projectObj->findActiveProjects($currentUserId);
}

// Filter by skill if specified
if ($skillId) {
    $filteredProjects = [];
    foreach ($activeProjects as $project) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM project_skills 
                WHERE project_id = ? AND skill_id = ? AND importance_level = 'required'
            ");
            $stmt->execute([$project['id'], $skillId]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $filteredProjects[] = $project;
            }
        } catch (PDOException $e) {
            continue;
        }
    }
    $activeProjects = $filteredProjects;
}

// Add skill IDs to each project
foreach ($activeProjects as &$project) {
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

// Pagination
$totalProjects = count($activeProjects);
$totalPages = ceil($totalProjects / $projectsPerPage);
$offset = ($page - 1) * $projectsPerPage;
$projectsToDisplay = array_slice($activeProjects, $offset, $projectsPerPage);

// Fetch additional data for paginated projects
foreach ($projectsToDisplay as &$project) {
    $project['skills'] = $projectObj->getProjectSkills($project['id']);
}

// Output the filtered project list
if (empty($projectsToDisplay)): ?>
    <div class="col-12">
        <div class="alert alert-info text-center">
            <i class="bi bi-info-circle"></i> 
            No projects found with the selected filters.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($projectsToDisplay as $project): ?>
        <div class="col-lg-4 mb-4">
            <?php include '../includes/project-item.php'; ?>
        </div>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="col-12">
            <nav aria-label="Projects pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="#"
                           hx-get="/htmx/filter-projects.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                           hx-target="#activeProjectsList"
                           hx-swap="innerHTML">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="#"
                               hx-get="/htmx/filter-projects.php?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                               hx-target="#activeProjectsList"
                               hx-swap="innerHTML"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="#"
                           hx-get="/htmx/filter-projects.php?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                           hx-target="#activeProjectsList"
                           hx-swap="innerHTML">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
<?php endif;