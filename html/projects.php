<?php
/**
 * Projects Page
 * 
 * Lists active projects, optionally filtered by category
 */

$page_title = 'Projects';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/ProjectCategory.php';
require_once 'classes/User.php';
require_once 'classes/Task.php';
require_once 'classes/Community.php';

// Require login
requireLogin();

$currentCommunityId = getCurrentCommunityId();

// Get current community name
$communityObj = new Community();
$currentCommunity = $communityObj->getById($currentCommunityId);
$currentCommunityName = $currentCommunity ? htmlspecialchars($currentCommunity['name']) : 'Community';

$projectObj = new Project();
$categoryObj = new ProjectCategory();

// Get category filter
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$selectedCategory = null;
if ($categoryId) {
    $selectedCategory = $categoryObj->findById($categoryId);
}

// Get all available skills for filter dropdown
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.name, s.category 
        FROM skills s
        JOIN project_skills ps ON s.id = ps.skill_id
        WHERE s.is_active = 1
        ORDER BY s.category, s.name
    ");
    $stmt->execute();
    $availableSkills = $stmt->fetchAll();
} catch (PDOException $e) {
    $availableSkills = [];
}

// Remove server-side form handling - now handled by HTMX endpoint

// Get current user ID
$currentUserId = getCurrentUserId();

// Get active projects (filtered by category if specified)
if ($categoryId) {
    $activeProjects = $projectObj->findByCategory($categoryId, null, $currentUserId); // null status = all statuses
} else {
    $activeProjects = $projectObj->findActiveProjects($currentUserId);
}

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

addSkillIds($activeProjects);

// Pagination settings
$projectsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalProjects = count($activeProjects);
$totalPages = ceil($totalProjects / $projectsPerPage);
$offset = ($currentPage - 1) * $projectsPerPage;

// Slice the projects for current page
$projectsToDisplay = array_slice($activeProjects, $offset, $projectsPerPage);

// Fetch additional data for paginated projects
foreach ($projectsToDisplay as &$project) {
    $project['skills'] = $projectObj->getProjectSkills($project['id']);
}

// Check if user can create projects - now all members can create projects
$userObj = new User();
$canCreateProjects = true; // All logged-in members can create projects

include 'includes/header.php';
?>

<!-- Main content -->
<main class="container-fluid px-4">
    <div id="projects-header" class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div>
            <h1 class="h2">
                <?php echo $currentCommunityName; ?> - Projects
                <?php if ($selectedCategory): ?>
                    <small class="text-muted">- <?php echo htmlspecialchars($selectedCategory['name']); ?></small>
                <?php endif; ?>
            </h1>
            <?php if ($selectedCategory && $selectedCategory['description']): ?>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($selectedCategory['description']); ?></p>
            <?php endif; ?>
        </div>
        <div id="projects-toolbar" class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="/project-categories" class="btn btn-outline-secondary">
                    <i class="bi bi-grid"></i> Categories
                </a>
                <?php if ($categoryId): ?>
                    <a href="/projects" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($canCreateProjects): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                    <i class="bi bi-plus-circle"></i> New Project
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Skill Filter with HTMX -->
    <div class="row mb-4" x-data="{ skillId: '' }">
        <div class="col-md-4">
            <label for="skillFilter" class="form-label">Filter by Required Skill:</label>
            <select class="form-select" 
                    id="skillFilter"
                    x-model="skillId"
                    hx-get="/htmx/filter-projects.php"
                    hx-trigger="change"
                    hx-target="#activeProjectsList"
                    hx-swap="innerHTML"
                    hx-include="[name='category']"
                    :hx-vals='JSON.stringify({skill: skillId})'>
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
            <input type="hidden" name="category" value="<?php echo $categoryId ?? ''; ?>">
        </div>
    </div>

    <!-- Projects List -->
    <?php if (empty($projectsToDisplay)): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle"></i> 
                    <?php if ($categoryId): ?>
                        No projects found in this category.
                    <?php else: ?>
                        No projects available at this time.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row" id="activeProjectsList" hx-get="/htmx/filter-projects.php?category=<?php echo $categoryId ?? ''; ?>" hx-trigger="load" hx-swap="innerHTML">
            <!-- Projects will be loaded via HTMX -->
            <div class="col-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading projects...</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- Create Project Modal -->
<?php if ($canCreateProjects): ?>
<div class="modal fade" id="createProjectModal" tabindex="-1" aria-labelledby="createProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createProjectModalLabel">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/htmx/create-project.php" 
                  hx-target="#create-project-messages" 
                  hx-swap="innerHTML"
                  x-data="createProjectForm()"
                  @submit="handleSubmit">
                <div class="modal-body">
                    <!-- Message area for HTMX responses -->
                    <div id="create-project-messages"></div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Project Name *</label>
                        <input type="text" class="form-control" id="name" name="name" x-model="name" @input="validateForm" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" x-model="description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="course_code" class="form-label">Course Code</label>
                        <input type="text" class="form-control" id="course_code" name="course_code" placeholder="e.g., CS101" x-model="courseCode">
                    </div>
                    <div class="mb-3">
                        <label for="slug" class="form-label">URL Slug <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control" id="slug" name="slug" placeholder="e.g., my-awesome-project (leave blank to auto-generate)" x-model="slug">
                        <small class="text-muted">Custom URL for this project. Will be used as: /your-custom-url</small>
                    </div>
                    <div class="mb-3">
                        <label for="project_category_id" class="form-label">Category *</label>
                        <select class="form-select" id="project_category_id" name="project_category_id" x-model="categoryId" @change="validateForm" required>
                            <option value="">Select a category</option>
                            <?php 
                            $categories = $categoryObj->findAll(true);
                            foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($categoryId == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="git_repository_url" class="form-label">Git Repository URL <span class="text-muted">(Optional)</span></label>
                        <input type="url" class="form-control" id="git_repository_url" name="git_repository_url" placeholder="https://github.com/username/repository.git" x-model="gitUrl">
                        <small class="form-text text-muted">You can add or change this later in project settings</small>
                    </div>
                    <div class="mb-3">
                        <label for="dev_system_url" class="form-label">Development System API URL <span class="text-muted">(Optional)</span></label>
                        <input type="url" class="form-control" id="dev_system_url" name="dev_system_url" placeholder="https://api.example.com/dev" x-model="devSystemUrl">
                        <small class="form-text text-muted">Development environment API URL for the project</small>
                    </div>
                    <div class="mb-3">
                        <label for="visibility" class="form-label">Project Visibility *</label>
                        <select class="form-select" id="visibility" name="visibility" x-model="visibility" required>
                            <option value="public">Public - Visible to all community members</option>
                            <option value="private">Private - Visible only to invited members</option>
                        </select>
                        <small class="form-text text-muted">Private projects are only visible to administrators, the project creator, and invited members</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" 
                            class="btn btn-primary" 
                            :disabled="!isFormValid || isSubmitting"
                            x-text="isSubmitting ? 'Creating...' : 'Create Project'">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function createProjectForm() {
    return {
        name: '',
        description: '',
        courseCode: '',
        slug: '',
        categoryId: '',
        gitUrl: '',
        devSystemUrl: '',
        visibility: 'public',
        isSubmitting: false,
        
        validateForm() {
            // Form validation is handled by Alpine.js bindings
        },
        
        get isFormValid() {
            return this.name.trim() !== '' && this.categoryId !== '';
        },
        
        handleSubmit(event) {
            if (!this.isFormValid) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                this.isSubmitting = true;
            }
        }
    }
}

// Reset form when modal is closed
document.addEventListener('DOMContentLoaded', function() {
    const createModal = document.getElementById('createProjectModal');
    if (createModal) {
        createModal.addEventListener('hidden.bs.modal', function() {
            // Reset Alpine.js data
            const form = this.querySelector('form');
            if (form && form._x_dataStack) {
                const data = Alpine.$data(form);
                if (data) {
                    data.name = '';
                    data.description = '';
                    data.courseCode = '';
                    data.slug = '';
                    data.categoryId = '';
                    data.gitUrl = '';
                    data.visibility = 'public';
                    data.isSubmitting = false;
                }
            }
            // Clear any messages
            const messages = document.getElementById('create-project-messages');
            if (messages) {
                messages.innerHTML = '';
            }
        });
    }
});
</script>

<!-- Voting functionality -->
<script src="/assets/js/voting.js"></script>

<?php include 'includes/footer.php'; ?>