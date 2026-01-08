<?php
/**
 * Project Edit Page
 * 
 * Allows admins and project creators to edit project details
 */

$page_title = 'Edit Project';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Project.php';
require_once 'classes/ProjectCategory.php';

// Require login
requireLogin();

$userObj = new User();
$projectObj = new Project();
$currentUserId = getCurrentUserId();

// Get project ID
$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    setFlashMessage('error', 'Invalid project ID.');
    header('Location: /projects.php');
    exit;
}

// Get project details
$project = $projectObj->findById($projectId);
if (!$project) {
    setFlashMessage('error', 'Project not found.');
    header('Location: /projects.php');
    exit;
}

// Check if user is admin or project creator
$isAdmin = $userObj->isAdmin($currentUserId);
$isCreator = $project['created_by'] == $currentUserId;

if (!$isAdmin && !$isCreator) {
    setFlashMessage('error', 'You do not have permission to edit this project.');
    header('Location: /projects.php');
    exit;
}

// Get project members for manager selection
$members = $projectObj->getMembers($projectId);

// Get project categories for the community
$categoryObj = new ProjectCategory();
$categories = $categoryObj->getAllByCommunity($project['community_id']);

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Double-check permissions for delete
    $canDelete = false;
    if ($isAdmin) {
        $canDelete = true;
    } elseif ($isCreator && $project['status'] !== 'active') {
        $canDelete = true;
    }
    
    if (!$canDelete) {
        setFlashMessage('error', 'You do not have permission to delete this project.');
        header("Location: /project-detail?id={$projectId}");
        exit;
    }
    
    // Delete the project
    $result = $projectObj->delete($projectId);
    
    if ($result) {
        setFlashMessage('success', 'Project deleted successfully.');
        header('Location: /projects.php');
        exit;
    } else {
        setFlashMessage('error', 'Failed to delete project. Please try again.');
        header("Location: /project-edit?id={$projectId}");
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $course_code = $_POST['course_code'] ?? '';
    $git_repository_url = $_POST['git_repository_url'] ?? '';
    $dev_system_url = $_POST['dev_system_url'] ?? '';
    $test_url = $_POST['test_url'] ?? '';
    $skilliks_api_key = $_POST['skilliks_api_key'] ?? '';
    $skilliks_system_url = $_POST['skilliks_system_url'] ?? '';
    $skilliks_agent_api = $_POST['skilliks_agent_api'] ?? '';
    $video_url = $_POST['video_url'] ?? '';
    $video_embed_code = $_POST['video_embed_code'] ?? '';
    $project_manager_id = $_POST['project_manager_id'] ?? null;
    $thumbnail_url = $_POST['thumbnail_url'] ?? '';
    $slug = trim($_POST['slug'] ?? '');
    
    // Generate slug from name if not provided
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
    }
    
    // Validate required fields
    if (empty($name)) {
        setFlashMessage('error', 'Project name is required.');
    } else {
        // Check for duplicate slug if provided and changed
        $slugError = false;
        if (!empty($slug) && $slug !== ($project['slug'] ?? '')) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM projects WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $projectId]);
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
            // Update project
            $data = [
                'name' => $name,
                'description' => $description,
                'course_code' => $course_code,
                'team_size_limit' => 10, // Keep default value for DB
                'thumbnail_url' => $thumbnail_url,
                'git_repository_url' => $git_repository_url,
                'dev_system_url' => $dev_system_url,
                'test_url' => $test_url,
                'skilliks_api_key' => $skilliks_api_key,
                'skilliks_system_url' => $skilliks_system_url,
                'skilliks_agent_api' => $skilliks_agent_api,
                'video_url' => $video_url,
                'video_embed_code' => $video_embed_code,
                'project_manager_id' => $project_manager_id ? intval($project_manager_id) : null,
                'slug' => $slug,
                'project_category_id' => (!empty($_POST['project_category_id']) ? intval($_POST['project_category_id']) : null)
            ];
            
            $result = $projectObj->update($projectId, $data);
            
            if ($result) {
                setFlashMessage('success', 'Project updated successfully!');
                header("Location: /project-detail?id={$projectId}");
                exit;
            } else {
                setFlashMessage('error', 'Failed to update project. Please try again.');
            }
        }
    }
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Project</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/projects.php">Projects</a></li>
                <li class="breadcrumb-item"><a href="/project-detail?id=<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs" id="projectEditTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
                <i class="bi bi-info-circle"></i> Project Details
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab" aria-controls="courses" aria-selected="false">
                <i class="bi bi-book"></i> Recommended Courses
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="skills-tab" data-bs-toggle="tab" data-bs-target="#skills" type="button" role="tab" aria-controls="skills" aria-selected="false">
                <i class="bi bi-tools"></i> Required Skills
            </button>
        </li>
    </ul>

    <!-- Tab content -->
    <div class="tab-content" id="projectEditTabContent">
        <!-- Project Details Tab -->
        <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Project Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Project Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($project['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="slug" class="form-label">URL Slug</label>
                                    <input type="text" class="form-control" id="slug" name="slug" 
                                           value="<?php echo htmlspecialchars($project['slug'] ?? ''); ?>" 
                                           placeholder="e.g., my-awesome-project (leave blank to auto-generate)">
                                    <small class="text-muted">Custom URL for this project. Will be used as: /<?php echo htmlspecialchars($project['slug'] ?? 'your-custom-url'); ?></small>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="project_category_id" class="form-label">Category</label>
                                    <select class="form-control" id="project_category_id" name="project_category_id">
                                        <option value="">-- No Category --</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo ($project['project_category_id'] ?? null) == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select a category for this project</small>
                                </div>

                                <div class="mb-3">
                                    <label for="course_code" class="form-label">Course Code</label>
                                    <input type="text" class="form-control" id="course_code" name="course_code" 
                                           value="<?php echo htmlspecialchars($project['course_code'] ?? ''); ?>"
                                           placeholder="e.g., CS101">
                                </div>

                                <div class="mb-3">
                                    <label for="project_manager_id" class="form-label">Project Manager</label>
                                    <select class="form-control" id="project_manager_id" name="project_manager_id">
                                        <option value="">-- No Project Manager --</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?php echo $member['user_id']; ?>" 
                                                    <?php echo ($project['project_manager_id'] ?? null) == $member['user_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                <?php echo $member['completion_status'] === 'completed' ? ' (Completed)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select a project member to be the project manager</small>
                                </div>

                                <div class="mb-3">
                                    <label for="thumbnail_url" class="form-label">Thumbnail URL</label>
                                    <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" 
                                           value="<?php echo htmlspecialchars($project['thumbnail_url'] ?? ''); ?>"
                                           placeholder="https://example.com/image.jpg">
                                    <small class="text-muted">Optional: URL to an image that represents this project</small>
                                </div>

                                <div class="mb-3">
                                    <label for="git_repository_url" class="form-label">Git Repository URL</label>
                                    <input type="url" class="form-control" id="git_repository_url" name="git_repository_url" 
                                           value="<?php echo htmlspecialchars($project['git_repository_url'] ?? ''); ?>"
                                           placeholder="https://github.com/username/repository">
                                    <small class="text-muted">Optional: Link to the project's Git repository</small>
                                </div>

                                <div class="mb-3">
                                    <label for="test_url" class="form-label">Test/Run Project URL</label>
                                    <input type="url" class="form-control" id="test_url" name="test_url" 
                                           value="<?php echo htmlspecialchars($project['test_url'] ?? ''); ?>"
                                           placeholder="https://app.example.com">
                                    <small class="text-muted">Optional: URL where the project can be tested or run</small>
                                </div>

                                <div class="mb-3">
                                    <label for="dev_system_url" class="form-label">Claude Agent URL</label>
                                    <input type="url" class="form-control" id="dev_system_url" name="dev_system_url" 
                                           value="<?php echo htmlspecialchars($project['dev_system_url'] ?? ''); ?>"
                                           placeholder="https://api.example.com/dev">
                                    <small class="text-muted">Optional: Development environment API URL for Claude Code</small>
                                </div>

                                <div class="mb-3">
                                    <label for="skilliks_api_key" class="form-label">Skilliks API Key (Claude Code)</label>
                                    <input type="text" class="form-control" id="skilliks_api_key" name="skilliks_api_key" 
                                           value="<?php echo htmlspecialchars($project['skilliks_api_key'] ?? ''); ?>"
                                           placeholder="Enter your Skilliks API key">
                                    <small class="text-muted">Optional: API key for sending prompts to Skilliks servers via Claude Code</small>
                                </div>
                                <div class="mb-3">
                                    <label for="skilliks_system_url" class="form-label">Skilliks Agent URL</label>
                                    <input type="url" class="form-control" id="skilliks_system_url" name="skilliks_system_url" 
                                           value="<?php echo htmlspecialchars($project['skilliks_system_url'] ?? ''); ?>"
                                           placeholder="https://api.skilliks.com/coder">
                                    <small class="text-muted">Optional: URL for Skilliks Coder development system</small>
                                </div>
                                <div class="mb-3">
                                    <label for="skilliks_agent_api" class="form-label">Skilliks Agent API Key</label>
                                    <input type="text" class="form-control" id="skilliks_agent_api" name="skilliks_agent_api" 
                                           value="<?php echo htmlspecialchars($project['skilliks_agent_api'] ?? ''); ?>"
                                           placeholder="Enter your Skilliks Agent API key">
                                    <small class="text-muted">Optional: API key for Skilliks Coder agent</small>
                                </div>

                                <div class="mb-3">
                                    <label for="video_url" class="form-label">Introduction Video URL</label>
                                    <input type="url" class="form-control" id="video_url" name="video_url" 
                                           value="<?php echo htmlspecialchars($project['video_url'] ?? ''); ?>"
                                           placeholder="https://youtube.com/watch?v=... or https://screencast.com/...">
                                    <small class="text-muted">Optional: YouTube, Vimeo, or Screencast.com video URL</small>
                                </div>

                                <div class="mb-3">
                                    <label for="video_embed_code" class="form-label">Custom Video Embed Code</label>
                                    <textarea class="form-control" id="video_embed_code" name="video_embed_code" rows="3"
                                              placeholder='<iframe src="..." width="560" height="315"></iframe>'><?php echo htmlspecialchars($project['video_embed_code'] ?? ''); ?></textarea>
                                    <small class="text-muted">Optional: Custom embed code (takes precedence over URL)</small>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="/project-detail?id=<?php echo $projectId; ?>" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Project Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Created by:</strong> <?php echo htmlspecialchars($project['creator_first_name'] . ' ' . $project['creator_last_name']); ?></p>
                            <p><strong>Created on:</strong> <?php echo date('M d, Y', strtotime($project['created_at'])); ?></p>
                            <p><strong>Last updated:</strong> <?php echo date('M d, Y g:i A', strtotime($project['updated_at'])); ?></p>
                            <p><strong>Current members:</strong> <?php echo $project['member_count']; ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-<?php echo $project['status'] == 'active' ? 'success' : ($project['status'] == 'planning' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($project['status']); ?></span></p>
                            
                            <?php 
                            // Determine if user can delete this project
                            $canDelete = false;
                            if ($isAdmin) {
                                // Admins can delete any project
                                $canDelete = true;
                            } elseif ($isCreator && $project['status'] !== 'active') {
                                // Creators can delete their own projects only if not active
                                $canDelete = true;
                            }
                            ?>
                            
                            <hr>
                            <h6 class="mb-3">Project Configuration</h6>
                            <div class="mb-3">
                                <a href="/project-survey?project_id=<?php echo $projectId; ?>&survey_type=general" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-clipboard-check"></i> Take General Project Survey
                                </a>
                                <small class="text-muted d-block mt-1">Configure basic project settings and preferences</small>
                            </div>
                            
                            <hr>
                            <h6 class="mb-3">Project Surveys</h6>
                            <?php
                            // Get project surveys
                            $db = getDB();
                            $stmt = $db->prepare("
                                SELECT ps.*, s.name as survey_name, s.description as survey_description, s.sub_type,
                                       (SELECT COUNT(DISTINCT user_id) FROM survey_responses WHERE survey_id = ps.survey_id) as response_count
                                FROM project_surveys ps
                                JOIN surveys s ON ps.survey_id = s.id
                                WHERE ps.project_id = ?
                                ORDER BY ps.created_at DESC
                            ");
                            $stmt->execute([$projectId]);
                            $projectSurveys = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Get available survey templates
                            $stmt = $db->prepare("
                                SELECT id, name, description 
                                FROM surveys 
                                WHERE type = 'project' AND is_active = 1
                                AND community_id = ?
                                AND id NOT IN (SELECT survey_id FROM project_surveys WHERE project_id = ?)
                                ORDER BY name
                            ");
                            $stmt->execute([$project['community_id'], $projectId]);
                            $availableSurveys = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <?php if (!empty($projectSurveys)): ?>
                                <div class="list-group mb-3">
                                    <?php foreach ($projectSurveys as $ps): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($ps['survey_name']); ?></h6>
                                                    <?php if ($ps['survey_description']): ?>
                                                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($ps['survey_description']); ?></p>
                                                    <?php endif; ?>
                                                    <small class="text-muted">
                                                        <?php echo $ps['response_count']; ?> response(s)
                                                        <?php if ($ps['generated_at']): ?>
                                                            • AI recommendations generated
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <a href="/project-survey?project_id=<?php echo $projectId; ?>&survey_id=<?php echo $ps['survey_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Take Survey
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No surveys added to this project yet.</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($availableSurveys)): ?>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSurveyModal">
                                        <i class="bi bi-plus-circle"></i> Add Survey to Project
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($canDelete): ?>
                                <hr>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal">
                                        <i class="bi bi-trash"></i> Delete Project
                                    </button>
                                </div>
                                <?php if (!$isAdmin && $project['status'] === 'planning'): ?>
                                    <small class="text-muted mt-2 d-block">Note: You can only delete projects in planning status.</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommended Courses Tab -->
        <div class="tab-pane fade" id="courses" role="tabpanel" aria-labelledby="courses-tab">
            <div class="mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Manage Recommended Courses</h5>
                    </div>
                    <div class="card-body" id="courses-content">
                        <!-- Courses management will be loaded here -->
                        <p class="text-muted">Loading courses...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Required Skills Tab -->
        <div class="tab-pane fade" id="skills" role="tabpanel" aria-labelledby="skills-tab">
            <div class="mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Manage Required Skills</h5>
                    </div>
                    <div class="card-body" id="skills-content">
                        <!-- Skills management will be loaded here -->
                        <p class="text-muted">Loading skills...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectId = <?php echo json_encode($projectId); ?>;
    
    // Load courses when courses tab is shown
    document.getElementById('courses-tab').addEventListener('shown.bs.tab', function() {
        loadCourses();
    });
    
    // Load skills when skills tab is shown
    document.getElementById('skills-tab').addEventListener('shown.bs.tab', function() {
        loadSkills();
    });
    
    function loadCourses() {
        const coursesContent = document.getElementById('courses-content');
        coursesContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        fetch(`/api/project-courses?project_id=${projectId}`)
            .then(response => response.text())
            .then(html => {
                coursesContent.innerHTML = html;
                initializeCourseHandlers();
            })
            .catch(error => {
                coursesContent.innerHTML = '<div class="alert alert-danger">Failed to load courses. Please try again.</div>';
                console.error('Error loading courses:', error);
            });
    }
    
    function loadSkills() {
        const skillsContent = document.getElementById('skills-content');
        skillsContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        fetch(`/api/project-skills.php?project_id=${projectId}`)
            .then(response => response.text())
            .then(html => {
                skillsContent.innerHTML = html;
                initializeSkillHandlers();
            })
            .catch(error => {
                skillsContent.innerHTML = '<div class="alert alert-danger">Failed to load skills. Please try again.</div>';
                console.error('Error loading skills:', error);
            });
    }
    
    function initializeCourseHandlers() {
        // Handle course addition
        const addCourseBtn = document.getElementById('add-course-btn');
        if (addCourseBtn) {
            addCourseBtn.addEventListener('click', function() {
                const courseId = document.getElementById('course-select').value;
                const assignmentType = document.getElementById('assignment-type').value;
                const notes = document.getElementById('course-notes').value;
                
                if (!courseId) {
                    alert('Please select a course');
                    return;
                }
                
                fetch(`/api/project-courses?project_id=${projectId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        course_id: courseId,
                        assignment_type: assignmentType,
                        notes: notes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadCourses(); // Reload the courses list
                    } else {
                        alert(data.message || 'Failed to add course');
                    }
                })
                .catch(error => {
                    console.error('Error adding course:', error);
                    alert('Failed to add course');
                });
            });
        }
        
        // Handle course removal
        document.querySelectorAll('.remove-course-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Are you sure you want to remove this course?')) {
                    const courseId = this.dataset.courseId;
                    
                    fetch(`/api/project-courses?project_id=${projectId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            course_id: courseId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadCourses(); // Reload the courses list
                        } else {
                            alert(data.message || 'Failed to remove course');
                        }
                    })
                    .catch(error => {
                        console.error('Error removing course:', error);
                        alert('Failed to remove course');
                    });
                }
            });
        });
    }
    
    function initializeSkillHandlers() {
        // Handle skill addition
        const addSkillBtn = document.getElementById('add-skill-btn');
        if (addSkillBtn) {
            addSkillBtn.addEventListener('click', function() {
                const skillId = document.getElementById('skill-select').value;
                const importance = document.getElementById('skill-importance').value;
                
                if (!skillId) {
                    alert('Please select a skill');
                    return;
                }
                
                fetch(`/api/project-skills.php?project_id=${projectId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        skill_id: skillId,
                        importance: importance
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadSkills(); // Reload the skills list
                    } else {
                        alert(data.message || 'Failed to add skill');
                    }
                })
                .catch(error => {
                    console.error('Error adding skill:', error);
                    alert('Failed to add skill');
                });
            });
        }
        
        // Handle skill removal
        document.querySelectorAll('.remove-skill-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Are you sure you want to remove this skill?')) {
                    const skillId = this.dataset.skillId;
                    
                    fetch(`/api/project-skills.php?project_id=${projectId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            skill_id: skillId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadSkills(); // Reload the skills list
                        } else {
                            alert(data.message || 'Failed to remove skill');
                        }
                    })
                    .catch(error => {
                        console.error('Error removing skill:', error);
                        alert('Failed to remove skill');
                    });
                }
            });
        });
    }
});
</script>

<!-- Delete Project Modal -->
<div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProjectModalLabel">Confirm Project Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> This action cannot be undone!
                </div>
                <p>Are you sure you want to delete the project "<strong><?php echo htmlspecialchars($project['name']); ?></strong>"?</p>
                <p>This will permanently delete:</p>
                <ul>
                    <li>All project details and settings</li>
                    <li>All project members and their assignments</li>
                    <li>All tasks associated with the project</li>
                    <li>All features and recommendations</li>
                    <li>All comments and votes</li>
                    <li>All uploaded files and artifacts</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/project-edit?id=<?php echo $projectId; ?>" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Project
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Survey Modal -->
<div class="modal fade" id="addSurveyModal" tabindex="-1" aria-labelledby="addSurveyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSurveyModalLabel">Add Survey to Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addSurveyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="survey_id" class="form-label">Select Survey Template</label>
                        <select class="form-select" id="survey_id" name="survey_id" required>
                            <option value="">Choose a survey...</option>
                            <?php foreach ($availableSurveys as $survey): ?>
                                <option value="<?php echo $survey['id']; ?>">
                                    <?php echo htmlspecialchars($survey['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php foreach ($availableSurveys as $survey): ?>
                            <?php if ($survey['description']): ?>
                                <div class="form-text survey-description d-none" data-survey-id="<?php echo $survey['id']; ?>">
                                    <?php echo htmlspecialchars($survey['description']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Survey</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle survey selection to show description
document.getElementById('survey_id').addEventListener('change', function() {
    // Hide all descriptions
    document.querySelectorAll('.survey-description').forEach(el => el.classList.add('d-none'));
    
    // Show selected survey description
    if (this.value) {
        const description = document.querySelector(`.survey-description[data-survey-id="${this.value}"]`);
        if (description) {
            description.classList.remove('d-none');
        }
    }
});

// Handle add survey form submission
document.getElementById('addSurveyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const surveyId = document.getElementById('survey_id').value;
    if (!surveyId) return;
    
    // Submit the form via AJAX
    fetch('/api/project-survey-add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            project_id: <?php echo json_encode($projectId); ?>,
            survey_id: surveyId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('addSurveyModal'));
            modal.hide();
            location.reload();
        } else {
            alert(data.message || 'Failed to add survey to project');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add survey to project');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>