<?php
/**
 * Admin Course Creation Page
 * 
 * Allows administrators to create new courses
 */

$page_title = 'Create New Course';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    setFlashMessage('error', 'Access denied. Administrator privileges required.');
    header('Location: /courses');
    exit;
}

// Get all active programs for dropdown
try {
    $db = getDB();
    $communityId = getCurrentCommunityId();
    
    if ($communityId) {
        $programStmt = $db->prepare("SELECT id, name FROM programs WHERE is_active = 1 AND community_id = ? ORDER BY display_order, name");
        $programStmt->execute([$communityId]);
    } else {
        $programStmt = $db->prepare("SELECT id, name FROM programs WHERE is_active = 1 ORDER BY display_order, name");
        $programStmt->execute();
    }
    $programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Programs fetch error: " . $e->getMessage());
    $programs = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $difficulty_level = $_POST['difficulty_level'] ?? 'beginner';
    $duration_hours = (float)($_POST['duration_hours'] ?? 0);
    $prerequisites = trim($_POST['prerequisites'] ?? '');
    $learning_objectives = trim($_POST['learning_objectives'] ?? '');
    $status = $_POST['status'] ?? 'published';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $certificate_available = isset($_POST['certificate_available']) ? 1 : 0;
    $passing_score = (float)($_POST['passing_score'] ?? 70);
    $tags = trim($_POST['tags'] ?? '');
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    
    // Generate slug from title if not provided
    if (empty($slug) && !empty($title)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
    }
    
    $errors = [];
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Course title is required.';
    }
    if (empty($description)) {
        $errors[] = 'Course description is required.';
    }
    if (empty($program_id)) {
        $errors[] = 'Program selection is required.';
    }
    if ($duration_hours < 0) {
        $errors[] = 'Duration must be a positive number.';
    }
    if ($passing_score < 0 || $passing_score > 100) {
        $errors[] = 'Passing score must be between 0 and 100.';
    }
    
    // Check for duplicate course code if provided
    if (!empty($course_code)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ?");
            $stmt->execute([$course_code]);
            if ($stmt->fetch()) {
                $errors[] = 'Course code already exists. Please choose a different code.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking course code uniqueness.';
        }
    }
    
    // Check for duplicate slug if provided
    if (!empty($slug)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM courses WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $errors[] = 'URL slug already exists. Please choose a different slug.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking slug uniqueness.';
        }
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            $communityId = getCurrentCommunityId();
            
            if (!$communityId) {
                $errors[] = 'No community selected. Please select a community first.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO courses (community_id, title, description, short_description, course_code, category, 
                                       program_id, difficulty_level, duration_hours, prerequisites, learning_objectives, 
                                       status, featured, certificate_available, passing_score, tags, 
                                       thumbnail_url, slug, created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $result = $stmt->execute([
                    $communityId, $title, $description, $short_description, $course_code, $category,
                    $program_id, $difficulty_level, $duration_hours, $prerequisites, $learning_objectives,
                    $status, $featured, $certificate_available, $passing_score, $tags,
                    $thumbnail_url, $slug, $currentUserId
                ]);
                
                if ($result) {
                    $courseId = $db->lastInsertId();
                    setFlashMessage('success', 'Course created successfully!');
                    header('Location: /admin-course-edit.php?id=' . $courseId);
                    exit;
                } else {
                    $errors[] = 'Failed to create course. Please try again.';
                }
            }
        } catch (PDOException $e) {
            error_log("Course creation error: " . $e->getMessage());
            $errors[] = 'Database error occurred while creating course.';
        }
    }
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Create New Course</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/courses" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Courses
                    </a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Course Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/admin-course-create.php">
                                <!-- Basic Information -->
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="title" class="form-label">Course Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="course_code" class="form-label">Course Code</label>
                                        <input type="text" class="form-control" id="course_code" name="course_code" 
                                               value="<?php echo htmlspecialchars($_POST['course_code'] ?? ''); ?>" 
                                               placeholder="e.g., CS101">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="slug" class="form-label">URL Slug</label>
                                    <input type="text" class="form-control" id="slug" name="slug" 
                                           value="<?php echo htmlspecialchars($_POST['slug'] ?? ''); ?>" 
                                           placeholder="e.g., intro-to-python (leave blank to auto-generate)">
                                    <small class="text-muted">Custom URL for this course. Will be used as: /your-custom-url</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="short_description" class="form-label">Short Description</label>
                                    <textarea class="form-control" id="short_description" name="short_description" rows="2" 
                                              placeholder="Brief description shown in course cards"><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Full Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" 
                                              placeholder="Detailed course description" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Course Details -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="program_id" class="form-label">Program *</label>
                                        <select class="form-select" id="program_id" name="program_id" required>
                                            <option value="">Select a program</option>
                                            <?php foreach ($programs as $program): ?>
                                                <option value="<?php echo $program['id']; ?>" 
                                                        <?php echo (isset($_POST['program_id']) && $_POST['program_id'] == $program['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($program['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="category" class="form-label">Category</label>
                                        <input type="text" class="form-control" id="category" name="category" 
                                               value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>"
                                               placeholder="e.g., Programming, Business">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="difficulty_level" class="form-label">Difficulty Level</label>
                                        <select class="form-select" id="difficulty_level" name="difficulty_level">
                                            <option value="beginner" <?php echo ($_POST['difficulty_level'] ?? '') === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                            <option value="intermediate" <?php echo ($_POST['difficulty_level'] ?? '') === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                            <option value="advanced" <?php echo ($_POST['difficulty_level'] ?? '') === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                            <option value="expert" <?php echo ($_POST['difficulty_level'] ?? '') === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="duration_hours" class="form-label">Duration (Hours)</label>
                                        <input type="number" class="form-control" id="duration_hours" name="duration_hours" 
                                               value="<?php echo htmlspecialchars($_POST['duration_hours'] ?? ''); ?>"
                                               min="0" step="0.5" placeholder="0">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="thumbnail_url" class="form-label">Thumbnail URL</label>
                                    <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" 
                                           value="<?php echo htmlspecialchars($_POST['thumbnail_url'] ?? ''); ?>"
                                           placeholder="https://example.com/image.jpg">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="prerequisites" class="form-label">Prerequisites</label>
                                    <textarea class="form-control" id="prerequisites" name="prerequisites" rows="3" 
                                              placeholder="What students should know before taking this course"><?php echo htmlspecialchars($_POST['prerequisites'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="learning_objectives" class="form-label">Learning Objectives</label>
                                    <textarea class="form-control" id="learning_objectives" name="learning_objectives" rows="4" 
                                              placeholder="What students will learn in this course"><?php echo htmlspecialchars($_POST['learning_objectives'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tags" class="form-label">Tags</label>
                                    <input type="text" class="form-control" id="tags" name="tags" 
                                           value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>"
                                           placeholder="Comma-separated tags (e.g., javascript, web development, frontend)">
                                </div>
                                
                                <!-- Course Settings -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo ($_POST['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo ($_POST['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="passing_score" class="form-label">Passing Score (%)</label>
                                        <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                               value="<?php echo htmlspecialchars($_POST['passing_score'] ?? '70'); ?>"
                                               min="0" max="100">
                                    </div>
                                </div>
                                
                                <!-- Course Options -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                                   <?php echo isset($_POST['featured']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="featured">
                                                Featured Course
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="certificate_available" name="certificate_available"
                                                   <?php echo isset($_POST['certificate_available']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="certificate_available">
                                                Certificate Available
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create Course
                                    </button>
                                    <a href="/courses" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Help & Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Program</h6>
                                <p class="small text-muted">Select the program this course belongs to. Programs help organize courses into learning pathways.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Course Title</h6>
                                <p class="small text-muted">Choose a clear, descriptive title that accurately represents your course content.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Descriptions</h6>
                                <p class="small text-muted">Short description appears on course cards. Full description is shown on the course detail page.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Status</h6>
                                <p class="small text-muted">Draft courses are only visible to admins. Published courses are visible to all users.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Featured Courses</h6>
                                <p class="small text-muted">Featured courses appear at the top of the course list with a special badge.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Tags</h6>
                                <p class="small text-muted">Use relevant tags to help students find your course. Separate multiple tags with commas.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</main>

<?php require_once 'includes/footer.php'; ?>