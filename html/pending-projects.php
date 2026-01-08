<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/ProjectCategory.php';
require_once 'classes/User.php';
require_once 'classes/Task.php';

// Require login
requireLogin();

$page_title = 'Pending Projects';
$projectObj = new Project();
$categoryObj = new ProjectCategory();

// Get current user ID
$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Allow all users to create projects
$canCreateProjects = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if ($canCreateProjects) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course_code = trim($_POST['course_code'] ?? '');
        $git_repository_url = trim($_POST['git_repository_url'] ?? '');
        $project_category_id = (int)($_POST['project_category_id'] ?? 0);
        $slug = trim($_POST['slug'] ?? '');
        
        // Generate slug from name if not provided
        if (empty($slug) && !empty($name)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slug = trim($slug, '-');
        }
        
        if ($name) {
            // Check for duplicate slug if provided
            $slugError = false;
            if (!empty($slug)) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("SELECT id FROM projects WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ($stmt->fetch()) {
                        setFlashMessage('error', 'URL slug already exists. Please choose a different slug.');
                        $slugError = true;
                    }
                } catch (PDOException $e) {
                    setFlashMessage('error', 'Error checking slug uniqueness.');
                    $slugError = true;
                }
            }
            
            if (!$slugError) {
                // Create the project
                $result = $projectObj->create([
                    'name' => $name,
                    'description' => $description,
                    'course_code' => $course_code,
                    'git_repository_url' => $git_repository_url,
                    'project_category_id' => $project_category_id,
                    'slug' => $slug,
                    'created_by' => $currentUserId,
                    'community_id' => $currentCommunityId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_complete' => 0
                ]);
            } else {
                $result = false;
            }
            
            if ($result) {
                setFlashMessage('success', 'Project created successfully!');
                header('Location: /pending-projects');
                exit;
            } else {
                setFlashMessage('error', 'Failed to create project.');
            }
        }
    }
}

// Get pending projects (filtered by community)
$pendingProjects = $projectObj->findPendingProjects($currentUserId, $currentCommunityId);

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

addSkillIds($pendingProjects);

// Pagination settings
$projectsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalProjects = count($pendingProjects);
$totalPages = ceil($totalProjects / $projectsPerPage);
$offset = ($currentPage - 1) * $projectsPerPage;

// Slice the projects for current page
$projectsToDisplay = array_slice($pendingProjects, $offset, $projectsPerPage);

include 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div id="projects-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Pending Projects</h1>
        <div id="projects-toolbar" class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="project-categories" class="btn btn-outline-secondary">
                    <i class="bi bi-grid"></i> Categories
                </a>
                <a href="projects.php" class="btn btn-outline-secondary">
                    <i class="bi bi-list"></i> Active Projects
                </a>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                <i class="bi bi-plus-circle"></i> New Project
            </button>
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
    <h3 class="mb-3">Projects in Planning Stage</h3>
    
    <?php if (empty($projectsToDisplay)): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> No pending projects at this time.
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row" id="pendingProjectsList">
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

<!-- Create Project Modal -->
<div class="modal fade" id="createProjectModal" tabindex="-1" aria-labelledby="createProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createProjectModalLabel">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/pending-projects">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Project Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="course_code" class="form-label">Course Code</label>
                        <input type="text" class="form-control" id="course_code" name="course_code" placeholder="e.g., CS101">
                    </div>
                    <div class="mb-3">
                        <label for="slug" class="form-label">URL Slug <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control" id="slug" name="slug" placeholder="e.g., my-awesome-project (leave blank to auto-generate)">
                        <small class="text-muted">Custom URL for this project. Will be used as: /your-custom-url</small>
                    </div>
                    <div class="mb-3">
                        <label for="project_category_id" class="form-label">Category *</label>
                        <select class="form-select" id="project_category_id" name="project_category_id" required>
                            <option value="">Select a category</option>
                            <?php 
                            $categories = $categoryObj->findAll(true, $currentCommunityId);
                            foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="git_repository_url" class="form-label">Git Repository URL <span class="text-muted">(Optional)</span></label>
                        <input type="url" class="form-control" id="git_repository_url" name="git_repository_url" placeholder="https://github.com/username/repository.git">
                        <small class="form-text text-muted">You can add or change this later in project settings</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="submitProjectBtn" class="btn btn-primary" disabled>Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Skill filter functionality
    const skillFilter = document.getElementById('skillFilter');
    const projectCols = document.querySelectorAll('#pendingProjectsList > .col-lg-4');
    
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
                document.getElementById('pendingProjectsList').appendChild(alertDiv);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    });
    
    // Form validation for create project modal
    const form = document.querySelector('#createProjectModal form');
    const submitBtn = document.getElementById('submitProjectBtn');
    const nameField = document.getElementById('name');
    const categoryField = document.getElementById('project_category_id');
    
    function validateProjectForm() {
        const isValid = nameField.value.trim() !== '' && categoryField.value !== '';
        submitBtn.disabled = !isValid;
    }
    
    if (form) {
        nameField.addEventListener('input', validateProjectForm);
        categoryField.addEventListener('change', validateProjectForm);
        
        form.addEventListener('submit', function(e) {
            if (nameField.value.trim() === '' || categoryField.value === '') {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
        
        // Reset form when modal is closed
        document.getElementById('createProjectModal').addEventListener('hidden.bs.modal', function() {
            form.reset();
            validateProjectForm();
        });
    }
});
</script>

<!-- Voting functionality -->
<script src="/assets/js/voting.js"></script>

<?php include 'includes/footer.php'; ?>