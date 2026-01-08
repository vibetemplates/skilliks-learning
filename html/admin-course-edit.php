<?php
/**
 * Admin Course Edit Page
 * 
 * Allows administrators to edit existing courses
 */

$page_title = 'Edit Course';
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

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$courseId) {
    setFlashMessage('error', 'Course ID is required.');
    header('Location: /courses');
    exit;
}

// Get course details
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    if (!$course) {
        setFlashMessage('error', 'Course not found.');
        header('Location: /courses');
        exit;
    }
    
    // Get all active programs for dropdown
    $programStmt = $db->prepare("SELECT id, name FROM programs WHERE is_active = 1 ORDER BY display_order, name");
    $programStmt->execute();
    $programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Course fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading course.');
    header('Location: /courses');
    exit;
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
    $status = $_POST['status'] ?? 'draft';
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
    
    // Check for duplicate course code if changed
    if (!empty($course_code) && $course_code !== $course['course_code']) {
        try {
            $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ?");
            $stmt->execute([$course_code, $courseId]);
            if ($stmt->fetch()) {
                $errors[] = 'Course code already exists. Please choose a different code.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking course code uniqueness.';
        }
    }
    
    // Check for duplicate slug if provided and changed
    if (!empty($slug) && $slug !== ($course['slug'] ?? '')) {
        try {
            $stmt = $db->prepare("SELECT id FROM courses WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $courseId]);
            if ($stmt->fetch()) {
                $errors[] = 'URL slug already exists. Please choose a different slug.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking slug uniqueness.';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE courses SET 
                    title = ?, description = ?, short_description = ?, course_code = ?, 
                    category = ?, program_id = ?, difficulty_level = ?, duration_hours = ?, prerequisites = ?, 
                    learning_objectives = ?, status = ?, featured = ?, certificate_available = ?, 
                    passing_score = ?, tags = ?, thumbnail_url = ?, slug = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $title, $description, $short_description, $course_code, $category,
                $program_id, $difficulty_level, $duration_hours, $prerequisites, $learning_objectives,
                $status, $featured, $certificate_available, $passing_score, $tags,
                $thumbnail_url, $slug, $courseId
            ]);
            
            if ($result) {
                // Update local course data
                $course['title'] = $title;
                $course['description'] = $description;
                $course['short_description'] = $short_description;
                $course['course_code'] = $course_code;
                $course['category'] = $category;
                $course['difficulty_level'] = $difficulty_level;
                $course['duration_hours'] = $duration_hours;
                $course['prerequisites'] = $prerequisites;
                $course['learning_objectives'] = $learning_objectives;
                $course['status'] = $status;
                $course['featured'] = $featured;
                $course['certificate_available'] = $certificate_available;
                $course['passing_score'] = $passing_score;
                $course['tags'] = $tags;
                $course['thumbnail_url'] = $thumbnail_url;
                $course['slug'] = $slug;
                
                setFlashMessage('success', 'Course updated successfully!');
            } else {
                $errors[] = 'Failed to update course. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Course update error: " . $e->getMessage());
            $errors[] = 'Database error occurred while updating course.';
        }
    }
}

// Get course statistics
try {
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM course_enrollments WHERE course_id = ? AND status IN ('enrolled', 'in_progress', 'completed')) as enrollment_count,
            (SELECT COUNT(*) FROM lessons WHERE course_id = ?) as lesson_count,
            (SELECT AVG(final_score) FROM course_enrollments WHERE course_id = ? AND final_score IS NOT NULL) as avg_score
    ");
    $stmt->execute([$courseId, $courseId, $courseId]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Course stats error: " . $e->getMessage());
    $stats = ['enrollment_count' => 0, 'lesson_count' => 0, 'avg_score' => null];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Course: <?php echo htmlspecialchars($course['title']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/course-detail?id=<?php echo $courseId; ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-eye"></i> View Course
                    </a>
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
                            <form method="POST" action="/admin-course-edit.php?id=<?php echo $courseId; ?>">
                                <!-- Basic Information -->
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="title" class="form-label">Course Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($course['title']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="course_code" class="form-label">Course Code</label>
                                        <input type="text" class="form-control" id="course_code" name="course_code" 
                                               value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>" 
                                               placeholder="e.g., CS101">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="slug" class="form-label">URL Slug</label>
                                    <input type="text" class="form-control" id="slug" name="slug" 
                                           value="<?php echo htmlspecialchars($course['slug'] ?? ''); ?>" 
                                           placeholder="e.g., intro-to-python (leave blank to auto-generate)">
                                    <small class="text-muted">Custom URL for this course. Will be used as: /<?php echo htmlspecialchars($course['slug'] ?? 'your-custom-url'); ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="short_description" class="form-label">Short Description</label>
                                    <textarea class="form-control" id="short_description" name="short_description" rows="2" 
                                              placeholder="Brief description shown in course cards"><?php echo htmlspecialchars($course['short_description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Full Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" 
                                              placeholder="Detailed course description" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                                </div>
                                
                                <!-- Course Details -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="program_id" class="form-label">Program *</label>
                                        <select class="form-select" id="program_id" name="program_id" required>
                                            <option value="">Select a program</option>
                                            <?php foreach ($programs as $program): ?>
                                                <option value="<?php echo $program['id']; ?>" 
                                                        <?php echo ($course['program_id'] == $program['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($program['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="category" class="form-label">Category</label>
                                        <input type="text" class="form-control" id="category" name="category" 
                                               value="<?php echo htmlspecialchars($course['category'] ?? ''); ?>"
                                               placeholder="e.g., Programming, Business">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="difficulty_level" class="form-label">Difficulty Level</label>
                                        <select class="form-select" id="difficulty_level" name="difficulty_level">
                                            <option value="beginner" <?php echo $course['difficulty_level'] === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                            <option value="intermediate" <?php echo $course['difficulty_level'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                            <option value="advanced" <?php echo $course['difficulty_level'] === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                            <option value="expert" <?php echo $course['difficulty_level'] === 'expert' ? 'selected' : ''; ?>>Expert</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="duration_hours" class="form-label">Duration (Hours)</label>
                                        <input type="number" class="form-control" id="duration_hours" name="duration_hours" 
                                               value="<?php echo $course['duration_hours']; ?>"
                                               min="0" step="0.5" placeholder="0">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="thumbnail_url" class="form-label">Thumbnail URL</label>
                                    <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" 
                                           value="<?php echo htmlspecialchars($course['thumbnail_url'] ?? ''); ?>"
                                           placeholder="https://example.com/image.jpg">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="prerequisites" class="form-label">Prerequisites</label>
                                    <textarea class="form-control" id="prerequisites" name="prerequisites" rows="3" 
                                              placeholder="What students should know before taking this course"><?php echo htmlspecialchars($course['prerequisites'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="learning_objectives" class="form-label">Learning Objectives</label>
                                    <textarea class="form-control" id="learning_objectives" name="learning_objectives" rows="4" 
                                              placeholder="What students will learn in this course"><?php echo htmlspecialchars($course['learning_objectives'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tags" class="form-label">Tags</label>
                                    <input type="text" class="form-control" id="tags" name="tags" 
                                           value="<?php echo htmlspecialchars($course['tags'] ?? ''); ?>"
                                           placeholder="Comma-separated tags (e.g., javascript, web development, frontend)">
                                </div>
                                
                                <!-- Course Settings -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo $course['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo $course['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="passing_score" class="form-label">Passing Score (%)</label>
                                        <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                               value="<?php echo $course['passing_score']; ?>"
                                               min="0" max="100">
                                    </div>
                                </div>
                                
                                <!-- Course Options -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                                   <?php echo $course['featured'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="featured">
                                                Featured Course
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="certificate_available" name="certificate_available"
                                                   <?php echo $course['certificate_available'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="certificate_available">
                                                Certificate Available
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Update Course
                                    </button>
                                    <a href="/courses" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Lessons Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Course Lessons</h5>
                                <a href="/admin-lesson-create.php?course_id=<?php echo $courseId; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-circle"></i> Add Lesson
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get course lessons
                            try {
                                $stmt = $db->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
                                $stmt->execute([$courseId]);
                                $lessons = $stmt->fetchAll();
                            } catch (PDOException $e) {
                                error_log("Lessons fetch error: " . $e->getMessage());
                                $lessons = [];
                            }
                            ?>
                            
                            <?php if (empty($lessons)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-collection" style="font-size: 2rem; opacity: 0.3;"></i>
                                    <p class="mt-2">No lessons created yet</p>
                                    <a href="/admin-lesson-create.php?course_id=<?php echo $courseId; ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create First Lesson
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="80">Order</th>
                                                <th>Title</th>
                                                <th width="100">Duration</th>
                                                <th width="120">Status</th>
                                                <th width="120">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sortable-lessons">
                                            <?php foreach ($lessons as $lesson): ?>
                                                <tr data-lesson-id="<?php echo $lesson['id']; ?>" class="sortable-lesson">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-grip-vertical text-muted me-2 drag-handle" style="cursor: move;" title="Drag to reorder"></i>
                                                            <span class="badge bg-secondary"><?php echo $lesson['order_index']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($lesson['description'], 0, 60)); ?><?php echo strlen($lesson['description']) > 60 ? '...' : ''; ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($lesson['duration_minutes'] > 0): ?>
                                                            <span class="text-muted"><?php echo $lesson['duration_minutes']; ?> min</span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $lesson['status'] === 'published' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($lesson['status']); ?>
                                                        </span>
                                                        <?php if (!$lesson['is_mandatory']): ?>
                                                            <span class="badge bg-warning ms-1">
                                                                <i class="bi bi-unlock"></i> Free
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="/course-detail?id=<?php echo $courseId; ?>&lesson=<?php echo $lesson['id']; ?>" 
                                                               class="btn btn-outline-primary" title="View Lesson">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="/admin-lesson-edit.php?id=<?php echo $lesson['id']; ?>" 
                                                               class="btn btn-outline-secondary" title="Edit Lesson">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3 text-muted small">
                                    <i class="bi bi-info-circle"></i>
                                    <?php echo count($lessons); ?> lesson(s) • 
                                    <?php echo array_sum(array_column($lessons, 'duration_minutes')); ?> total minutes
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Course Statistics -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Course Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border-end">
                                        <div class="fs-4 fw-bold text-primary"><?php echo $stats['enrollment_count']; ?></div>
                                        <div class="small text-muted">Students</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <div class="fs-4 fw-bold text-info"><?php echo $stats['lesson_count']; ?></div>
                                        <div class="small text-muted">Lessons</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="fs-4 fw-bold text-success">
                                        <?php echo $stats['avg_score'] ? number_format($stats['avg_score'], 1) . '%' : 'N/A'; ?>
                                    </div>
                                    <div class="small text-muted">Avg Score</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Course Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Course Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?php echo $course['status'] === 'published' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong>Created:</strong> <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Updated:</strong> <?php echo date('M j, Y', strtotime($course['updated_at'])); ?>
                            </div>
                            <?php if ($course['featured']): ?>
                                <div class="mb-3">
                                    <span class="badge bg-warning">
                                        <i class="bi bi-star-fill"></i> Featured
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Help & Tips -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Help & Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Publishing</h6>
                                <p class="small text-muted">Only published courses are visible to students. Draft courses are visible only to administrators.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Course Code</h6>
                                <p class="small text-muted">Course codes must be unique. They help identify and organize courses.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Lessons</h6>
                                <p class="small text-muted">Add lessons to structure your course content. Students progress through lessons sequentially.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</main>

<!-- Include SortableJS from CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sortableElement = document.getElementById('sortable-lessons');
    
    if (sortableElement) {
        const sortable = new Sortable(sortableElement, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                // Get the new order of lessons
                const lessonOrders = [];
                const rows = sortableElement.querySelectorAll('tr[data-lesson-id]');
                
                rows.forEach(function(row, index) {
                    const lessonId = row.getAttribute('data-lesson-id');
                    lessonOrders.push(parseInt(lessonId));
                    
                    // Update the order badge display
                    const orderBadge = row.querySelector('.badge');
                    if (orderBadge) {
                        orderBadge.textContent = index + 1;
                    }
                });
                
                // Send the new order to the server
                updateLessonOrder(lessonOrders);
            }
        });
    }
});

function updateLessonOrder(lessonOrders) {
    const courseId = <?php echo $courseId; ?>;
    
    // Show loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'alert alert-info mt-3';
    loadingIndicator.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating lesson order...';
    
    const lessonsCard = document.querySelector('.card:last-child .card-body');
    if (lessonsCard) {
        lessonsCard.appendChild(loadingIndicator);
    }
    
    fetch('/api/lesson-reorder.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            course_id: courseId,
            lesson_orders: lessonOrders
        })
    })
    .then(response => response.json())
    .then(data => {
        // Remove loading indicator
        if (loadingIndicator.parentNode) {
            loadingIndicator.remove();
        }
        
        if (data.success) {
            // Show success message
            showTemporaryMessage('Lesson order updated successfully!', 'success');
        } else {
            // Show error message and reload page to revert changes
            showTemporaryMessage('Failed to update lesson order: ' + data.error, 'danger');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    })
    .catch(error => {
        // Remove loading indicator
        if (loadingIndicator.parentNode) {
            loadingIndicator.remove();
        }
        
        console.error('Error:', error);
        showTemporaryMessage('Network error occurred while updating lesson order.', 'danger');
        
        // Reload page to revert changes
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    });
}

function showTemporaryMessage(message, type) {
    const messageEl = document.createElement('div');
    messageEl.className = `alert alert-${type} mt-3`;
    messageEl.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}`;
    
    const lessonsCard = document.querySelector('.card:last-child .card-body');
    if (lessonsCard) {
        lessonsCard.appendChild(messageEl);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (messageEl.parentNode) {
                messageEl.remove();
            }
        }, 3000);
    }
}
</script>

<style>
/* Drag and drop styles */
.sortable-ghost {
    opacity: 0.5;
    background-color: #f8f9fa;
}

.sortable-chosen {
    background-color: #e3f2fd;
}

.sortable-drag {
    background-color: #ffffff;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.sortable-lesson {
    transition: background-color 0.2s ease;
}

.sortable-lesson:hover {
    background-color: #f8f9fa;
}

.drag-handle {
    opacity: 0.5;
    transition: opacity 0.2s ease;
}

.drag-handle:hover {
    opacity: 1;
}

/* Prevent text selection during drag */
.sortable-lessons {
    user-select: none;
}
</style>

<?php require_once 'includes/footer.php'; ?>