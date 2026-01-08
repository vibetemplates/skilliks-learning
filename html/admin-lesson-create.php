<?php
/**
 * Admin Lesson Creation Page
 * 
 * Allows administrators to create new lessons for courses
 */

$page_title = 'Add New Lesson';
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

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
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
} catch (PDOException $e) {
    error_log("Course fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading course.');
    header('Location: /courses');
    exit;
}

// Get next sequence order
try {
    $stmt = $db->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM lessons WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $result = $stmt->fetch();
    $nextOrder = $result ? $result['next_order'] : 1;
} catch (PDOException $e) {
    error_log("Next order fetch error: " . $e->getMessage());
    $nextOrder = 1;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $order_index = (int)($_POST['sequence_order'] ?? $nextOrder);
    $status = $_POST['status'] ?? 'published';
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    
    $errors = [];
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Lesson title is required.';
    }
    if (empty($description)) {
        $errors[] = 'Lesson description is required.';
    }
    if ($duration_minutes < 0) {
        $errors[] = 'Duration must be a positive number.';
    }
    if ($order_index < 1) {
        $errors[] = 'Sequence order must be a positive number.';
    }
    
    // Validate video URL if provided
    if (!empty($video_url) && !filter_var($video_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Please enter a valid video URL.';
    }
    
    // Check for duplicate sequence order and auto-adjust if needed
    if ($order_index > 0) {
        try {
            $stmt = $db->prepare("SELECT id FROM lessons WHERE course_id = ? AND order_index = ?");
            $stmt->execute([$courseId, $order_index]);
            if ($stmt->fetch()) {
                // Find the next available order index
                $stmt = $db->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM lessons WHERE course_id = ?");
                $stmt->execute([$courseId]);
                $result = $stmt->fetch();
                $order_index = $result ? $result['next_order'] : 1;
                
                // Optional: Add a info message to let user know the order was adjusted
                setFlashMessage('info', 'Sequence order was automatically adjusted to ' . $order_index . ' to avoid duplicates.');
            }
        } catch (PDOException $e) {
            error_log("Sequence order check error: " . $e->getMessage());
            // Check if the lessons table exists
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $errors[] = 'The lessons table does not exist. Please contact your administrator.';
            } else {
                $errors[] = 'Error checking sequence order uniqueness: ' . $e->getMessage();
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO lessons (course_id, title, description, content, video_url, 
                                   duration_minutes, order_index, status, is_mandatory, 
                                   created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([
                $courseId, $title, $description, $content, $video_url,
                $duration_minutes, $order_index, $status, $is_free ? 0 : 1, $currentUserId
            ]);
            
            if ($result) {
                $lessonId = $db->lastInsertId();
                
                // Try to fetch YouTube transcript if video URL is provided
                if (!empty($video_url) && (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false)) {
                    require_once 'classes/YouTubeTranscript.php';
                    try {
                        $transcriptFetcher = new YouTubeTranscript();
                        $transcript = $transcriptFetcher->getTranscript($video_url);
                        if ($transcript && !str_starts_with($transcript, 'Error')) {
                            $transcriptFetcher->updateLessonTranscript($lessonId, $transcript);
                            setFlashMessage('success', 'Lesson created successfully with transcript!');
                        } else {
                            setFlashMessage('success', 'Lesson created successfully! (Transcript not available)');
                        }
                    } catch (Exception $e) {
                        error_log("Transcript fetch error: " . $e->getMessage());
                        setFlashMessage('success', 'Lesson created successfully!');
                    }
                } else {
                    setFlashMessage('success', 'Lesson created successfully!');
                }
                
                header('Location: /admin-course-edit.php?id=' . $courseId);
                exit;
            } else {
                $errors[] = 'Failed to create lesson. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Lesson creation error: " . $e->getMessage());
            $errors[] = 'Database error occurred while creating lesson.';
        }
    }
}

// Get existing lessons for reference
try {
    $stmt = $db->prepare("SELECT id, title, order_index FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
    $stmt->execute([$courseId]);
    $existingLessons = $stmt->fetchAll();
} catch (PDOException $e) {
    $existingLessons = [];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Add New Lesson</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/admin-course-edit.php?id=<?php echo $courseId; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Course
                    </a>
                </div>
            </div>

            <!-- Course Info -->
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i>
                <strong>Course:</strong> <?php echo htmlspecialchars($course['title'] ?? ''); ?>
                <?php if ($course['course_code']): ?>
                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($course['course_code'] ?? ''); ?></span>
                <?php endif; ?>
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
                            <h5 class="card-title mb-0">Lesson Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/admin-lesson-create.php?course_id=<?php echo $courseId; ?>">
                                <!-- Basic Information -->
                                <div class="mb-3">
                                    <label for="title" class="form-label">Lesson Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Brief description of what this lesson covers" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="content" class="form-label">Lesson Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="8" 
                                              placeholder="Detailed lesson content, instructions, or text material"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Media & Resources -->
                                <div class="mb-3">
                                    <label for="video_url" class="form-label">Video URL</label>
                                    <input type="url" class="form-control" id="video_url" name="video_url" 
                                           value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>"
                                           placeholder="https://www.youtube.com/watch?v=... or https://app.screencast.com/...">
                                    <div class="form-text">Optional video URL for this lesson (YouTube, Screencast.com, or direct video link)</div>
                                </div>
                                
                                <!-- Lesson Settings -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="duration_minutes" class="form-label">Duration (Minutes)</label>
                                        <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" 
                                               value="<?php echo htmlspecialchars($_POST['duration_minutes'] ?? ''); ?>"
                                               min="0" placeholder="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sequence_order" class="form-label">Sequence Order</label>
                                        <input type="number" class="form-control" id="sequence_order" name="sequence_order" 
                                               value="<?php echo htmlspecialchars($_POST['sequence_order'] ?? $nextOrder); ?>"
                                               min="1" required>
                                        <div class="form-text">Order in which this lesson appears in the course</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo ($_POST['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo ($_POST['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Lesson Options -->
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_free" name="is_free"
                                               <?php echo isset($_POST['is_free']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_free">
                                            Free Preview Lesson
                                        </label>
                                        <div class="form-text">Allow non-enrolled users to view this lesson as a preview</div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create Lesson
                                    </button>
                                    <a href="/admin-course-edit.php?id=<?php echo $courseId; ?>" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Note:</strong> After creating the lesson, you can add YouTube videos using the "Manage Videos" button in the lesson edit page.
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Existing Lessons -->
                    <?php if (!empty($existingLessons)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Existing Lessons</h5>
                        </div>
                        <div class="card-body">
                            <div class="lesson-list">
                                <?php foreach ($existingLessons as $lesson): ?>
                                    <div class="lesson-item d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                            <small class="text-muted">Order: <?php echo $lesson['order_index']; ?></small>
                                        </div>
                                        <a href="/admin-lesson-edit.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Help & Tips -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Help & Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Sequence Order</h6>
                                <p class="small text-muted">Lessons are displayed in sequence order. Use whole numbers (1, 2, 3...) to organize your lessons.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Video URLs</h6>
                                <p class="small text-muted">Support for YouTube, Screencast.com, and direct video file URLs. Leave empty if no video is needed.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Content</h6>
                                <p class="small text-muted">Use the content field for text-based lesson material, instructions, or supplementary information.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Free Preview</h6>
                                <p class="small text-muted">Mark lessons as free preview to allow non-enrolled users to sample your course content.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Publishing</h6>
                                <p class="small text-muted">Only published lessons are visible to students. Draft lessons are visible only to administrators.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</main>

<style>
.lesson-item {
    transition: background-color 0.2s ease;
}

.lesson-item:hover {
    background-color: #f8f9fa;
}

.lesson-title {
    font-weight: 500;
    color: #212529;
}

.lesson-list .lesson-item:last-child {
    border-bottom: none !important;
}
</style>

<?php require_once 'includes/footer.php'; ?>