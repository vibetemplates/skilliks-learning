<?php
/**
 * Admin Lesson Edit Page
 * 
 * Allows administrators to edit existing lessons
 */

$page_title = 'Edit Lesson';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Lesson.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    setFlashMessage('error', 'Access denied. Administrator privileges required.');
    header('Location: /courses');
    exit;
}

$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$lessonId) {
    setFlashMessage('error', 'Lesson ID is required.');
    header('Location: /courses');
    exit;
}

// Get lesson and course details
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT l.*, c.title as course_title, c.course_code 
        FROM lessons l 
        JOIN courses c ON l.course_id = c.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        setFlashMessage('error', 'Lesson not found.');
        header('Location: /courses');
        exit;
    }
    
    $courseId = $lesson['course_id'];
} catch (PDOException $e) {
    error_log("Lesson fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading lesson.');
    header('Location: /courses');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $order_index = (int)($_POST['sequence_order'] ?? 1);
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
    
    // Check for duplicate sequence order and auto-adjust if needed (excluding current lesson)
    if ($order_index != $lesson['order_index'] && $order_index > 0) {
        try {
            $stmt = $db->prepare("SELECT id FROM lessons WHERE course_id = ? AND order_index = ? AND id != ?");
            $stmt->execute([$courseId, $order_index, $lessonId]);
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
                UPDATE lessons SET 
                    title = ?, description = ?, content = ?, video_url = ?, 
                    duration_minutes = ?, order_index = ?, status = ?, is_mandatory = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $title, $description, $content, $video_url,
                $duration_minutes, $order_index, $status, $is_free ? 0 : 1, $lessonId
            ]);
            
            if ($result) {
                // Update local lesson data
                $lesson['title'] = $title;
                $lesson['description'] = $description;
                $lesson['content'] = $content;
                $lesson['video_url'] = $video_url;
                $lesson['duration_minutes'] = $duration_minutes;
                $lesson['order_index'] = $order_index;
                $lesson['status'] = $status;
                $lesson['is_mandatory'] = $is_free ? 0 : 1;
                
                // Try to fetch YouTube transcript if video URL changed
                if (!empty($video_url) && $video_url != $_POST['original_video_url'] && 
                    (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false)) {
                    require_once 'classes/YouTubeTranscript.php';
                    try {
                        $transcriptFetcher = new YouTubeTranscript();
                        $transcript = $transcriptFetcher->getTranscript($video_url);
                        if ($transcript && !str_starts_with($transcript, 'Error')) {
                            $transcriptFetcher->updateLessonTranscript($lessonId, $transcript);
                            setFlashMessage('success', 'Lesson updated successfully with new transcript!');
                        } else {
                            setFlashMessage('success', 'Lesson updated successfully! (Transcript not available)');
                        }
                    } catch (Exception $e) {
                        error_log("Transcript fetch error: " . $e->getMessage());
                        setFlashMessage('success', 'Lesson updated successfully!');
                    }
                } else {
                    setFlashMessage('success', 'Lesson updated successfully!');
                }
                
                // Update lesson skills
                if ($result) {
                    $selectedSkills = [];
                    if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                        foreach ($_POST['skills'] as $skillId => $skillData) {
                            if (isset($skillData['selected']) && $skillData['selected'] == '1') {
                                $selectedSkills[] = [
                                    'skill_id' => (int)$skillId,
                                    'skill_level' => $skillData['level'] ?? 'beginner',
                                    'is_required' => (isset($skillData['required']) && $skillData['required'] == '1') ? true : false
                                ];
                            }
                        }
                    }
                    
                    $lessonObj = new Lesson();
                    $lessonObj->updateLessonSkills($lessonId, $selectedSkills);
                }
            } else {
                $errors[] = 'Failed to update lesson. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Lesson update error: " . $e->getMessage());
            $errors[] = 'Database error occurred while updating lesson.';
        }
    }
}

// Get lesson statistics
try {
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM lesson_progress WHERE lesson_id = ?) as view_count,
            (SELECT COUNT(*) FROM lesson_progress WHERE lesson_id = ? AND status = 'completed') as completion_count,
            (SELECT AVG(time_spent_seconds) FROM lesson_progress WHERE lesson_id = ? AND time_spent_seconds > 0) as avg_time_spent
    ");
    $stmt->execute([$lessonId, $lessonId, $lessonId]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Lesson stats error: " . $e->getMessage());
    $stats = ['view_count' => 0, 'completion_count' => 0, 'avg_time_spent' => null];
}

// Get other lessons in the course
try {
    $stmt = $db->prepare("SELECT id, title, order_index FROM lessons WHERE course_id = ? AND id != ? ORDER BY order_index ASC");
    $stmt->execute([$courseId, $lessonId]);
    $otherLessons = $stmt->fetchAll();
} catch (PDOException $e) {
    $otherLessons = [];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Lesson: <?php echo htmlspecialchars($lesson['title']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/admin-lesson-videos.php?lesson_id=<?php echo $lessonId; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-camera-video"></i> Manage Videos
                    </a>
                    <a href="/admin-quiz-builder.php?lesson_id=<?php echo $lessonId; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-question-circle"></i> Quiz Builder
                    </a>
                    <a href="/course-detail?id=<?php echo $courseId; ?>&lesson=<?php echo $lessonId; ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-eye"></i> View Lesson
                    </a>
                    <a href="/admin-course-edit.php?id=<?php echo $courseId; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Course
                    </a>
                </div>
            </div>

            <!-- Course Info -->
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i>
                <strong>Course:</strong> <?php echo htmlspecialchars($lesson['course_title'] ?? ''); ?>
                <?php if ($lesson['course_code']): ?>
                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($lesson['course_code'] ?? ''); ?></span>
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
                            <form method="POST" action="/admin-lesson-edit.php?id=<?php echo $lessonId; ?>">
                                <!-- Basic Information -->
                                <div class="mb-3">
                                    <label for="title" class="form-label">Lesson Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Brief description of what this lesson covers" required><?php echo htmlspecialchars($lesson['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="content" class="form-label">Lesson Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="8" 
                                              placeholder="Detailed lesson content, instructions, or text material"><?php echo htmlspecialchars($lesson['content'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Media & Resources -->
                                <div class="mb-3">
                                    <label for="video_url" class="form-label">Video URL</label>
                                    <input type="url" class="form-control" id="video_url" name="video_url" 
                                           value="<?php echo htmlspecialchars($lesson['video_url'] ?? ''); ?>"
                                           placeholder="https://example.com/video.mp4">
                                    <input type="hidden" name="original_video_url" value="<?php echo htmlspecialchars($lesson['video_url'] ?? ''); ?>">
                                    <div class="form-text">Optional video URL for this lesson (YouTube, Vimeo, or direct video link)</div>
                                    <?php if (!empty($lesson['video_transcript'])): ?>
                                        <div class="form-text text-success"><i class="bi bi-check-circle"></i> Transcript available</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Lesson Settings -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="duration_minutes" class="form-label">Duration (Minutes)</label>
                                        <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" 
                                               value="<?php echo $lesson['duration_minutes']; ?>"
                                               min="0" placeholder="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sequence_order" class="form-label">Sequence Order</label>
                                        <input type="number" class="form-control" id="sequence_order" name="sequence_order" 
                                               value="<?php echo $lesson['order_index']; ?>"
                                               min="1" required>
                                        <div class="form-text">Order in which this lesson appears in the course</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo $lesson['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo $lesson['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Lesson Options -->
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_free" name="is_free"
                                               <?php echo !$lesson['is_mandatory'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_free">
                                            Free Preview Lesson
                                        </label>
                                        <div class="form-text">Allow non-enrolled users to view this lesson as a preview</div>
                                    </div>
                                </div>
                                
                                <!-- Skills Section -->
                                <div class="mb-4" id="skills-section">
                                    <h6 class="border-bottom pb-2 mb-3">Skills Taught in This Lesson</h6>
                                    <div id="selected-skills">
                                        <?php
                                        // Get current lesson skills
                                        $lessonObj = new Lesson();
                                        $currentSkills = $lessonObj->getLessonSkills($lessonId);
                                        $allSkills = $lessonObj->getAllSkills();
                                        
                                        // Group skills by category
                                        $skillsByCategory = [];
                                        foreach ($allSkills as $skill) {
                                            $skillsByCategory[$skill['category']][] = $skill;
                                        }
                                        
                                        // Create a map of current skills for easy lookup
                                        $currentSkillsMap = [];
                                        foreach ($currentSkills as $skill) {
                                            $currentSkillsMap[$skill['id']] = $skill;
                                        }
                                        ?>
                                        
                                        <?php foreach ($skillsByCategory as $category => $skills): ?>
                                            <div class="mb-3">
                                                <h6 class="text-muted small"><?php echo htmlspecialchars($category); ?></h6>
                                                <div class="row g-2">
                                                    <?php foreach ($skills as $skill): ?>
                                                        <?php $isChecked = isset($currentSkillsMap[$skill['id']]); ?>
                                                        <?php $currentSkill = $isChecked ? $currentSkillsMap[$skill['id']] : null; ?>
                                                        <div class="col-md-6">
                                                            <div class="border rounded p-2 skill-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input skill-checkbox" 
                                                                           type="checkbox" 
                                                                           id="skill_<?php echo $skill['id']; ?>" 
                                                                           name="skills[<?php echo $skill['id']; ?>][selected]"
                                                                           value="1"
                                                                           <?php echo $isChecked ? 'checked' : ''; ?>
                                                                           onchange="toggleSkillOptions(<?php echo $skill['id']; ?>)">
                                                                    <label class="form-check-label" for="skill_<?php echo $skill['id']; ?>">
                                                                        <?php echo htmlspecialchars($skill['name']); ?>
                                                                    </label>
                                                                </div>
                                                                <div class="skill-options mt-2" id="skill_options_<?php echo $skill['id']; ?>" 
                                                                     style="<?php echo $isChecked ? '' : 'display:none;'; ?>">
                                                                    <div class="row g-2">
                                                                        <div class="col-8">
                                                                            <select class="form-select form-select-sm" 
                                                                                    name="skills[<?php echo $skill['id']; ?>][level]">
                                                                                <option value="beginner" <?php echo ($currentSkill && $currentSkill['skill_level'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                                                                <option value="intermediate" <?php echo ($currentSkill && $currentSkill['skill_level'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                                                                <option value="advanced" <?php echo ($currentSkill && $currentSkill['skill_level'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-4">
                                                                            <div class="form-check">
                                                                                <input class="form-check-input" 
                                                                                       type="checkbox" 
                                                                                       id="skill_required_<?php echo $skill['id']; ?>" 
                                                                                       name="skills[<?php echo $skill['id']; ?>][required]"
                                                                                       value="1"
                                                                                       <?php echo ($currentSkill && $currentSkill['is_required']) ? 'checked' : ''; ?>>
                                                                                <label class="form-check-label small" for="skill_required_<?php echo $skill['id']; ?>">
                                                                                    Required
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Update Lesson
                                    </button>
                                    <a href="/admin-course-edit.php?id=<?php echo $courseId; ?>" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                                        <i class="bi bi-trash"></i> Delete Lesson
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Lesson Statistics -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Lesson Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border-end">
                                        <div class="fs-4 fw-bold text-primary"><?php echo $stats['view_count']; ?></div>
                                        <div class="small text-muted">Views</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <div class="fs-4 fw-bold text-success"><?php echo $stats['completion_count']; ?></div>
                                        <div class="small text-muted">Completed</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="fs-4 fw-bold text-info">
                                        <?php echo $stats['avg_time_spent'] ? number_format($stats['avg_time_spent'] / 60, 1) . 'm' : 'N/A'; ?>
                                    </div>
                                    <div class="small text-muted">Avg Time</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lesson Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Lesson Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?php echo $lesson['status'] === 'published' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($lesson['status']); ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong>Order:</strong> #<?php echo $lesson['order_index']; ?>
                            </div>
                            <div class="mb-3">
                                <strong>Duration:</strong> <?php echo $lesson['duration_minutes']; ?> minutes
                            </div>
                            <div class="mb-3">
                                <strong>Created:</strong> <?php echo date('M j, Y', strtotime($lesson['created_at'])); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Updated:</strong> <?php echo date('M j, Y', strtotime($lesson['updated_at'])); ?>
                            </div>
                            <?php if (!$lesson['is_mandatory']): ?>
                                <div class="mb-3">
                                    <span class="badge bg-warning">
                                        <i class="bi bi-unlock"></i> Free Preview
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Other Lessons -->
                    <?php if (!empty($otherLessons)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Other Lessons</h5>
                        </div>
                        <div class="card-body">
                            <div class="lesson-list">
                                <?php foreach ($otherLessons as $otherLesson): ?>
                                    <div class="lesson-item d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <div class="lesson-title"><?php echo htmlspecialchars($otherLesson['title']); ?></div>
                                            <small class="text-muted">Order: <?php echo $otherLesson['order_index']; ?></small>
                                        </div>
                                        <a href="/admin-lesson-edit.php?id=<?php echo $otherLesson['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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

.skill-item {
    background-color: #f8f9fa;
    transition: background-color 0.2s;
}

.skill-item:hover {
    background-color: #e9ecef;
}

.skill-checkbox:checked ~ .form-check-label {
    font-weight: 500;
}

.skill-options {
    margin-left: 1.5rem;
}
</style>

<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this lesson? This action cannot be undone.')) {
        // Create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin-lesson-delete.php';
        
        const lessonIdInput = document.createElement('input');
        lessonIdInput.type = 'hidden';
        lessonIdInput.name = 'lesson_id';
        lessonIdInput.value = <?php echo $lessonId; ?>;
        
        const courseIdInput = document.createElement('input');
        courseIdInput.type = 'hidden';
        courseIdInput.name = 'course_id';
        courseIdInput.value = <?php echo $courseId; ?>;
        
        form.appendChild(lessonIdInput);
        form.appendChild(courseIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleSkillOptions(skillId) {
    const checkbox = document.getElementById('skill_' + skillId);
    const options = document.getElementById('skill_options_' + skillId);
    
    if (checkbox.checked) {
        options.style.display = 'block';
    } else {
        options.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>