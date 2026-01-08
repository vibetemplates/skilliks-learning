<?php
/**
 * Course Detail Page
 * 
 * Shows course details with lesson menu on the left
 */

$page_title = 'Course Details';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';
require_once 'classes/Lesson.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if user is admin
$userObj = new User();
$isAdmin = $userObj->isAdmin($currentUserId);

if (!$courseId) {
    header('Location: /courses');
    exit;
}

// Get course details
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id AND status IN ('enrolled', 'in_progress', 'completed')) as enrollment_count
        FROM courses c
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: /courses');
        exit;
    }
} catch (PDOException $e) {
    error_log("Course detail query error: " . $e->getMessage());
    header('Location: /courses');
    exit;
}

// Check if user is enrolled
$isEnrolled = false;
$userProgress = null;
try {
    $stmt = $db->prepare("
        SELECT * FROM course_enrollments 
        WHERE course_id = ? AND user_id = ? AND status IN ('enrolled', 'in_progress', 'completed')
    ");
    $stmt->execute([$courseId, $currentUserId]);
    $userProgress = $stmt->fetch();
    $isEnrolled = ($userProgress !== false);
    
} catch (PDOException $e) {
    error_log("Enrollment check error: " . $e->getMessage());
}

// Get course lessons (published for regular users, all for admins)
$lessons = [];
try {
    // Admins can see all lessons (including drafts), regular users only see published lessons
    $statusFilter = $isAdmin ? "l.status IN ('draft', 'published')" : "l.status = 'published'";
    
    $stmt = $db->prepare("
        SELECT l.*, 
               lp.status as progress_status, lp.completed_at, lp.time_spent_minutes
        FROM lessons l
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
        WHERE l.course_id = ? AND $statusFilter
        ORDER BY l.order_index ASC
    ");
    $stmt->execute([$currentUserId, $courseId]);
    $lessons = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Lessons query error: " . $e->getMessage());
}

// Get current lesson (first lesson by default, or from URL parameter)
$currentLessonId = isset($_GET['lesson']) ? (int)$_GET['lesson'] : ($lessons[0]['id'] ?? 0);
$currentLesson = null;
foreach ($lessons as $lesson) {
    if ($lesson['id'] == $currentLessonId) {
        $currentLesson = $lesson;
        break;
    }
}

// If no current lesson found, use first lesson
if (!$currentLesson && !empty($lessons)) {
    $currentLesson = $lessons[0];
    $currentLessonId = $currentLesson['id'];
}

// Get lesson videos if we have a current lesson
$lessonVideos = [];
if ($currentLessonId > 0) {
    try {
        // Create lesson_videos table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS `lesson_videos` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `lesson_id` int(11) NOT NULL,
              `title` varchar(255) NOT NULL,
              `description` text,
              `youtube_url` varchar(500) NOT NULL,
              `youtube_id` varchar(50) NOT NULL,
              `duration_seconds` int(11) DEFAULT 0,
              `order_index` int(11) NOT NULL DEFAULT 0,
              `is_active` tinyint(1) DEFAULT 1,
              `created_by` int unsigned NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_lesson_id` (`lesson_id`),
              KEY `idx_order_index` (`order_index`),
              KEY `idx_is_active` (`is_active`),
              KEY `idx_created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Get videos for current lesson
        $stmt = $db->prepare("
            SELECT * FROM lesson_videos 
            WHERE lesson_id = ? AND is_active = 1 
            ORDER BY order_index ASC
        ");
        $stmt->execute([$currentLessonId]);
        $lessonVideos = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Lesson videos query error: " . $e->getMessage());
    }
}

// Function to extract YouTube video ID from URL
function extractYouTubeId($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    }
    return false;
}

// Function to extract Screencast.com video ID from URL
function extractScreencastId($url) {
    // Handle various screencast.com URL formats
    $patterns = [
        '/screencast\.com\/t\/([a-zA-Z0-9-_]+)/i',
        '/screencast\.com\/media\/([a-zA-Z0-9-_]+)/i',
        '/screencast\.com\/embed\/([a-zA-Z0-9-_]+)/i',
        '/app\.screencast\.com\/([a-zA-Z0-9-_]+)\/e/i',  // Handle embed format first
        '/app\.screencast\.com\/([a-zA-Z0-9-_]+)\/?$/i'   // Handle regular format
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return false;
}

// Function to determine video type and get embed info
function getVideoEmbedInfo($url) {
    $result = [
        'type' => 'unknown',
        'id' => null,
        'embed_url' => null,
        'thumbnail_url' => null
    ];
    
    // Check for YouTube
    $youtubeId = extractYouTubeId($url);
    if ($youtubeId) {
        $result['type'] = 'youtube';
        $result['id'] = $youtubeId;
        $result['embed_url'] = "https://www.youtube.com/embed/{$youtubeId}";
        $result['thumbnail_url'] = "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg";
        return $result;
    }
    
    // Check for Screencast.com
    $screencastId = extractScreencastId($url);
    if ($screencastId) {
        $result['type'] = 'screencast';
        $result['id'] = $screencastId;
        $result['embed_url'] = "https://app.screencast.com/{$screencastId}/e";
        $result['thumbnail_url'] = null; // Screencast.com doesn't provide direct thumbnail URLs
        return $result;
    }
    
    return $result;
}

require_once 'includes/header.php';
?>

<div class="course-detail-wrapper">
    <div class="course-container">
        <!-- Left Sidebar - Course Lessons -->
        <div class="course-sidebar">
            <div class="course-header">
                <div class="course-title-section">
                    <h4 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h4>
                    <div class="course-meta">
                        <?php if ($course['course_code']): ?>
                            <span class="course-code"><?php echo htmlspecialchars($course['course_code'] ?? ''); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($isEnrolled && $userProgress): ?>
                    <div class="course-progress">
                        <div class="progress-info">
                            <span class="progress-text"><?php echo number_format($userProgress['progress_percentage'], 0); ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $userProgress['progress_percentage']; ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="course-navigation">
                <nav class="lesson-menu">
                    <?php if (empty($lessons)): ?>
                        <div class="no-lessons">
                            <i class="bi bi-info-circle"></i>
                            <span>No lessons available</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lessons as $index => $lesson): ?>
                            <a href="/course-detail?id=<?php echo $courseId; ?>&lesson=<?php echo $lesson['id']; ?>" 
                               class="lesson-item <?php echo $lesson['id'] == $currentLessonId ? 'active' : ''; ?>">
                                <div class="lesson-status">
                                    <?php if ($lesson['lesson_type'] === 'quiz'): ?>
                                        <?php if ($lesson['progress_status'] === 'completed'): ?>
                                            <i class="bi bi-patch-question-fill text-success"></i>
                                        <?php elseif ($lesson['progress_status'] === 'in_progress'): ?>
                                            <i class="bi bi-patch-question-fill text-warning"></i>
                                        <?php else: ?>
                                            <i class="bi bi-patch-question text-muted"></i>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($lesson['progress_status'] === 'completed'): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php elseif ($lesson['progress_status'] === 'in_progress'): ?>
                                            <i class="bi bi-play-circle-fill text-warning"></i>
                                        <?php else: ?>
                                            <i class="bi bi-circle text-muted"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="lesson-content">
                                    <div class="lesson-title">
                                        <?php echo htmlspecialchars($lesson['title']); ?>
                                        <?php if ($lesson['status'] === 'draft' && $isAdmin): ?>
                                            <span class="badge bg-warning ms-1" style="font-size: 0.6rem;">Draft</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($lesson['duration_minutes']): ?>
                                        <div class="lesson-duration"><?php echo $lesson['duration_minutes']; ?> min</div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="course-content">
            <?php if (!$isEnrolled): ?>
                <!-- Enrollment Required -->
                <div class="enrollment-required">
                    <div class="enrollment-card">
                        <div class="enrollment-header">
                            <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                            <div class="course-badges">
                                <?php if ($course['course_code']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($course['course_code'] ?? ''); ?></span>
                                <?php endif; ?>
                                <span class="badge bg-info"><?php echo ucfirst($course['difficulty_level']); ?></span>
                                <?php if ($course['featured']): ?>
                                    <span class="badge bg-warning"><i class="bi bi-star-fill"></i> Featured</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="enrollment-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? ''); ?></p>
                            
                            <div class="course-details">
                                <div class="detail-item">
                                    <i class="bi bi-clock"></i>
                                    <span><?php echo $course['duration_hours']; ?> hours</span>
                                </div>
                                <div class="detail-item">
                                    <i class="bi bi-people"></i>
                                    <span><?php echo $course['enrollment_count']; ?> students</span>
                                </div>
                                <?php if ($course['first_name'] && $course['last_name']): ?>
                                    <div class="detail-item">
                                        <i class="bi bi-person"></i>
                                        <span><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($course['prerequisites']): ?>
                                <div class="prerequisites">
                                    <h6><i class="bi bi-list-check"></i> Prerequisites</h6>
                                    <p><?php echo htmlspecialchars($course['prerequisites'] ?? ''); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($course['learning_objectives']): ?>
                                <div class="learning-objectives">
                                    <h6><i class="bi bi-target"></i> What You'll Learn</h6>
                                    <p><?php echo htmlspecialchars($course['learning_objectives'] ?? ''); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="enrollment-footer">
                            <button type="button" class="btn btn-primary btn-lg" onclick="enrollInCourse(<?php echo $courseId; ?>)">
                                <i class="bi bi-plus-circle"></i> Enroll in Course
                            </button>
                            <a href="/courses" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Courses
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Course Content for Enrolled Users -->
                <div class="lesson-content">
                    <?php if ($currentLesson): ?>
                        <div class="lesson-header">
                            <div class="lesson-title-section">
                                <h1><?php echo htmlspecialchars($currentLesson['title']); ?></h1>
                                <div class="lesson-meta">
                                    <?php if ($currentLesson['duration_minutes']): ?>
                                        <span class="duration"><i class="bi bi-clock"></i> <?php echo $currentLesson['duration_minutes']; ?> minutes</span>
                                    <?php endif; ?>
                                    <?php if ($currentLesson['progress_status'] === 'completed'): ?>
                                        <span class="status completed"><i class="bi bi-check-circle-fill"></i> Completed</span>
                                    <?php elseif ($currentLesson['progress_status'] === 'in_progress'): ?>
                                        <span class="status in-progress"><i class="bi bi-play-circle-fill"></i> In Progress</span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                // Display lesson skills
                                $lessonObj = new Lesson();
                                $lessonSkills = $lessonObj->getLessonSkills($currentLesson['id']);
                                if (!empty($lessonSkills)):
                                ?>
                                <div class="lesson-skills mt-2">
                                    <strong>Skills:</strong>
                                    <?php foreach ($lessonSkills as $skill): ?>
                                        <span class="badge bg-<?php echo $skill['is_required'] ? 'primary' : 'secondary'; ?> me-1">
                                            <?php echo htmlspecialchars($skill['name']); ?>
                                            <small>(<?php echo ucfirst($skill['skill_level']); ?>)</small>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="lesson-actions">
                                <?php 
                                // Debug: uncomment to see progress status
                                // echo "<!-- Progress Status: " . ($currentLesson['progress_status'] ?? 'null') . " -->";
                                ?>
                                <?php if (!isset($currentLesson['progress_status']) || $currentLesson['progress_status'] === null): ?>
                                    <!-- Lesson not started yet -->
                                    <button type="button" class="btn btn-primary btn-sm" id="lesson-start-btn" onclick="startLesson(<?php echo $currentLesson['id']; ?>)">
                                        <i class="bi bi-play-circle-fill"></i> Start Lesson
                                    </button>
                                <?php elseif ($currentLesson['progress_status'] === 'completed'): ?>
                                    <button type="button" class="btn btn-success btn-sm" id="lesson-complete-btn" onclick="toggleLessonCompletion(<?php echo $currentLesson['id']; ?>, 'incomplete')">
                                        <i class="bi bi-check-circle-fill"></i> Completed
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="lesson-complete-btn" onclick="toggleLessonCompletion(<?php echo $currentLesson['id']; ?>, 'complete')">
                                        <i class="bi bi-check-circle"></i> Mark Complete
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-success btn-sm" id="lesson-quiz-btn">
                                    <i class="bi bi-patch-question"></i> Lesson Quiz
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" id="skills-drill-btn">
                                    <i class="bi bi-lightning"></i> Skills Drill
                                </button>
                            </div>
                        </div>

                        <div class="lesson-body">
                            <?php if ($currentLesson['lesson_type'] === 'quiz'): ?>
                                <!-- Quiz Lesson -->
                                <div class="quiz-lesson">
                                    <?php
                                    // Get quiz details from new tables
                                    require_once 'classes/Quiz.php';
                                    $quiz = new Quiz($db);
                                    $quizData = $quiz->getByLessonId($currentLesson['id']);
                                    
                                    if ($quizData) {
                                        // Get user's quiz attempts
                                        $attempts = $quiz->getUserAttempts($quizData['id'], $userId);
                                        $lastAttempt = $quiz->getLatestAttempt($quizData['id'], $userId);
                                        $canTakeQuiz = $quiz->canTakeQuiz($quizData['id'], $userId);
                                        
                                        // Get quiz questions count
                                        $questions = $quiz->getQuestions($quizData['id']);
                                        $questionCount = count($questions);
                                    } else {
                                        // Fallback for lessons that haven't been migrated yet
                                        $quizData = json_decode($currentLesson['quiz_data'], true) ?: [];
                                        $lastAttempt = null;
                                        $canTakeQuiz = true;
                                        $questionCount = count($quizData['questions'] ?? []);
                                        $attempts = [];
                                    }
                                    ?>
                                    
                                    <div class="card">
                                        <div class="card-body text-center py-5">
                                            <i class="bi bi-question-circle display-1 text-primary mb-3"></i>
                                            <h3>Quiz: <?php echo htmlspecialchars($quizData['title'] ?? $currentLesson['title']); ?></h3>
                                            
                                            <?php if ($lastAttempt && $lastAttempt['status'] == 'completed'): ?>
                                                <div class="mt-4">
                                                    <h4 class="<?php echo $lastAttempt['passed'] ? 'text-success' : 'text-danger'; ?>">
                                                        Your Best Score: <?php echo number_format($lastAttempt['score_achieved'], 1); ?>%
                                                    </h4>
                                                    <p class="text-muted">Attempts: <?php echo count($attempts); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="quiz-info mt-4">
                                                <div class="row justify-content-center">
                                                    <div class="col-md-3">
                                                        <div class="text-muted">Questions</div>
                                                        <div class="h5"><?php echo $questionCount; ?></div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="text-muted">Passing Score</div>
                                                        <div class="h5"><?php echo $quizData['passing_score'] ?? 70; ?>%</div>
                                                    </div>
                                                    <?php if ($quizData['time_limit_minutes'] ?? 0): ?>
                                                    <div class="col-md-3">
                                                        <div class="text-muted">Time Limit</div>
                                                        <div class="h5"><?php echo $quizData['time_limit_minutes']; ?> min</div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4">
                                                <?php if ($canTakeQuiz): ?>
                                                    <a href="quiz-take.php?lesson_id=<?php echo $currentLesson['id']; ?>" class="btn btn-primary btn-lg">
                                                        <i class="bi bi-play-circle"></i> 
                                                        <?php echo !empty($attempts) ? 'Retake Quiz' : 'Start Quiz'; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-lg" disabled>
                                                        Maximum Attempts Reached
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($lastAttempt && $lastAttempt['status'] == 'completed'): ?>
                                                    <a href="quiz-results.php?attempt_id=<?php echo $lastAttempt['id']; ?>" 
                                                       class="btn btn-outline-primary btn-lg ms-2">
                                                        <i class="bi bi-bar-chart"></i> View Last Results
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($currentLesson['description']): ?>
                                        <div class="content-section mt-4">
                                            <h3>About This Quiz</h3>
                                            <p><?php echo nl2br(htmlspecialchars($currentLesson['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($quizData['instructions'])): ?>
                                        <div class="content-section mt-4">
                                            <h3>Instructions</h3>
                                            <p><?php echo nl2br(htmlspecialchars($quizData['instructions'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (!empty($lessonVideos)): ?>
                                <div class="lesson-videos">
                                    <?php foreach ($lessonVideos as $video): ?>
                                        <div class="lesson-video mb-4">
                                            <div class="video-header">
                                                <h4><?php echo htmlspecialchars($video['title']); ?></h4>
                                                <?php if ($video['description']): ?>
                                                    <p class="text-muted"><?php echo htmlspecialchars($video['description']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="video-container">
                                                <div class="responsive-video">
                                                    <?php 
                                                    $videoInfo = getVideoEmbedInfo($video['youtube_url']);
                                                    ?>
                                                    <iframe src="<?php echo htmlspecialchars($videoInfo['embed_url'] ?: "https://www.youtube.com/embed/{$video['youtube_id']}"); ?>" 
                                                            frameborder="0" 
                                                            <?php if ($videoInfo['type'] === 'youtube'): ?>
                                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                            <?php elseif ($videoInfo['type'] === 'screencast'): ?>
                                                                scrolling="no"
                                                            <?php else: ?>
                                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                            <?php endif; ?>
                                                            allowfullscreen></iframe>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($currentLesson['video_url']): ?>
                                <div class="lesson-video">
                                    <?php 
                                    $videoInfo = getVideoEmbedInfo($currentLesson['video_url']);
                                    // Debug info - remove after testing
                                    if (isset($_GET['debug'])) {
                                        echo "<div class='alert alert-info'>";
                                        echo "Video URL: " . htmlspecialchars($currentLesson['video_url']) . "<br>";
                                        echo "Video Type: " . $videoInfo['type'] . "<br>";
                                        echo "Video ID: " . ($videoInfo['id'] ?? 'None') . "<br>";
                                        echo "Embed URL: " . ($videoInfo['embed_url'] ?? 'None') . "<br>";
                                        echo "</div>";
                                    }
                                    if ($videoInfo['type'] !== 'unknown' && $videoInfo['embed_url']): ?>
                                        <div class="video-header">
                                            <h4>Video: <?php echo htmlspecialchars($currentLesson['title']); ?></h4>
                                        </div>
                                        <div class="video-container">
                                            <div class="responsive-video">
                                                <iframe src="<?php echo htmlspecialchars($videoInfo['embed_url']); ?>" 
                                                        frameborder="0" 
                                                        <?php if ($videoInfo['type'] === 'youtube'): ?>
                                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                        <?php elseif ($videoInfo['type'] === 'screencast'): ?>
                                                            scrolling="no"
                                                        <?php else: ?>
                                                            allow="fullscreen"
                                                        <?php endif; ?>
                                                        allowfullscreen></iframe>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="video-container">
                                            <div class="video-placeholder">
                                                <i class="bi bi-play-circle"></i>
                                                <p>Video: <?php echo htmlspecialchars($currentLesson['title']); ?></p>
                                                <small class="text-muted">Video URL: <?php echo htmlspecialchars($currentLesson['video_url'] ?? ''); ?></small>
                                                <div class="text-muted mt-2">
                                                    <small>Note: Only YouTube and Screencast.com videos can be embedded. Other video types will show as links.</small>
                                                    <br>
                                                    <a href="<?php echo htmlspecialchars($currentLesson['video_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm mt-2">
                                                        <i class="bi bi-play-circle"></i> Open Video
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="lesson-description">
                                <?php if ($currentLesson['description']): ?>
                                    <div class="content-section">
                                        <h3>About This Lesson</h3>
                                        <p><?php echo nl2br(htmlspecialchars($currentLesson['description'] ?? '')); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($currentLesson['content']): ?>
                                    <div class="content-section">
                                        <h3>Lesson Content</h3>
                                        <div class="lesson-content-body">
                                            <?php echo nl2br(htmlspecialchars($currentLesson['content'] ?? '')); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                            </div>
                        </div>

                        <div class="lesson-navigation-footer">
                            <?php
                            // Find current lesson index
                            $currentIndex = 0;
                            foreach ($lessons as $index => $lesson) {
                                if ($lesson['id'] == $currentLessonId) {
                                    $currentIndex = $index;
                                    break;
                                }
                            }
                            ?>
                            
                            <div class="nav-buttons">
                                <?php if ($currentIndex > 0): ?>
                                    <a href="/course-detail?id=<?php echo $courseId; ?>&lesson=<?php echo $lessons[$currentIndex - 1]['id']; ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-left"></i> Previous Lesson
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($currentIndex < count($lessons) - 1): ?>
                                    <a href="/course-detail?id=<?php echo $courseId; ?>&lesson=<?php echo $lessons[$currentIndex + 1]['id']; ?>" 
                                       class="btn btn-primary">
                                        Next Lesson <i class="bi bi-arrow-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-content">
                            <div class="text-center py-5">
                                <i class="bi bi-info-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                                <h3 class="mt-3">No lesson selected</h3>
                                <p class="text-muted">Please select a lesson from the sidebar to continue learning.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.course-detail-wrapper {
    min-height: 100vh;
    background-color: #f8f9fa;
}

.course-container {
    display: flex;
    height: 100vh;
}

.course-sidebar {
    width: 350px;
    background-color: #fff;
    border-right: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    /* Hide scrollbar for Chrome, Safari and Opera */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* Internet Explorer 10+ */
}

/* Hide scrollbar for Chrome, Safari and Opera */
.course-sidebar::-webkit-scrollbar {
    display: none;
}

.course-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.course-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: #212529;
}

.course-code {
    font-size: 0.85rem;
    color: #6c757d;
    background-color: #f8f9fa;
    padding: 2px 8px;
    border-radius: 4px;
}

.course-progress {
    margin-top: 12px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}

.progress-text {
    font-size: 0.9rem;
    font-weight: 500;
    color: #495057;
}

.progress-bar {
    height: 6px;
    background-color: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background-color: #28a745;
    transition: width 0.3s ease;
}

.course-navigation {
    flex: 1;
    overflow-y: auto;
}

.lesson-menu {
    padding: 0;
}

.lesson-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    text-decoration: none;
    color: #495057;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.2s ease;
}

.lesson-item:hover {
    background-color: #f8f9fa;
    color: #495057;
    text-decoration: none;
}

.lesson-item.active {
    background-color: #fff3cd;
    border-left: 3px solid #ffc107;
}

.lesson-status {
    margin-right: 12px;
    font-size: 1.1rem;
}

.lesson-content {
    flex: 1;
}

.lesson-title {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 2px;
}

.lesson-duration {
    font-size: 0.8rem;
    color: #6c757d;
}

.no-lessons {
    padding: 20px;
    text-align: center;
    color: #6c757d;
}

.course-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    background-color: #fff;
    /* Hide scrollbar for Chrome, Safari and Opera */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* Internet Explorer 10+ */
}

/* Hide scrollbar for Chrome, Safari and Opera */
.course-content::-webkit-scrollbar {
    display: none;
}

.enrollment-required {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    padding: 40px;
}

.enrollment-card {
    max-width: 600px;
    width: 100%;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.enrollment-header {
    padding: 30px 30px 20px;
    border-bottom: 1px solid #dee2e6;
}

.enrollment-header h1 {
    font-size: 1.8rem;
    margin-bottom: 12px;
    color: #212529;
}

.course-badges .badge {
    margin-right: 6px;
}

.enrollment-body {
    padding: 30px;
}

.course-description {
    font-size: 1.1rem;
    line-height: 1.6;
    color: #495057;
    margin-bottom: 25px;
}

.course-details {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 25px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6c757d;
    font-size: 0.9rem;
}

.prerequisites, .learning-objectives {
    margin-bottom: 20px;
}

.prerequisites h6, .learning-objectives h6 {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #495057;
    margin-bottom: 10px;
}

.enrollment-footer {
    padding: 20px 30px;
    background-color: #f8f9fa;
    display: flex;
    gap: 12px;
    justify-content: center;
}

.lesson-content .lesson-header {
    padding: 30px 40px 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.lesson-title-section h1 {
    font-size: 1.6rem;
    margin-bottom: 10px;
    color: #212529;
}

.lesson-meta {
    display: flex;
    gap: 15px;
    align-items: center;
}

.lesson-meta .duration {
    color: #6c757d;
    font-size: 0.9rem;
}

.lesson-meta .status {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
    font-weight: 500;
}

.status.completed {
    color: #28a745;
}

.status.in-progress {
    color: #ffc107;
}

.lesson-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.lesson-actions .btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.lesson-actions .btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

.lesson-body {
    padding: 30px 40px;
}

.lesson-video {
    margin-bottom: 30px;
}

.video-header h4 {
    margin-bottom: 10px;
    color: #212529;
}

.video-header p {
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.video-container {
    position: relative;
    background-color: #000;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 16/9;
}

.responsive-video {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 56.25%; /* 16:9 aspect ratio */
    background-color: #000;
    border-radius: 8px;
    overflow: hidden;
}

.responsive-video iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 8px;
}

.video-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #fff;
    text-align: center;
}

.video-placeholder i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.8;
}

.content-section {
    margin-bottom: 30px;
}

.content-section h3 {
    font-size: 1.3rem;
    margin-bottom: 15px;
    color: #212529;
}

.lesson-content-body {
    line-height: 1.7;
    color: #495057;
}

.lesson-navigation-footer {
    padding: 20px 40px;
    border-top: 1px solid #dee2e6;
    background-color: #f8f9fa;
}

.nav-buttons {
    display: flex;
    justify-content: space-between;
}

.no-content {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    color: #6c757d;
}

@media (max-width: 768px) {
    .course-container {
        flex-direction: column;
        height: auto;
    }
    
    .course-sidebar {
        width: 100%;
        height: auto;
        max-height: 300px;
    }
    
    .course-content {
        min-height: 70vh;
    }
}
</style>

<script>
async function enrollInCourse(courseId) {
    try {
        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('action', 'enroll');
        
        const response = await fetch('/api/course-enroll.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Refresh the page to show the course content
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        console.error('Enrollment error:', error);
        alert('An error occurred while enrolling in the course. Please try again.');
    }
}

async function startLesson(lessonId) {
    const button = document.getElementById('lesson-start-btn');
    const originalContent = button.innerHTML;
    
    // Provide immediate visual feedback
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Starting...';
    
    try {
        const response = await fetch('/api/lesson-progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                lesson_id: lessonId,
                action: 'start'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Change button to Mark Complete button
            button.className = 'btn btn-outline-primary btn-sm';
            button.innerHTML = '<i class="bi bi-check-circle"></i> Mark Complete';
            button.id = 'lesson-complete-btn';
            button.onclick = function() { toggleLessonCompletion(lessonId, 'complete'); };
            button.disabled = false;
            
            // Update the status display if it exists
            const statusElement = document.querySelector('.lesson-meta .status');
            if (statusElement) {
                statusElement.className = 'status in-progress';
                statusElement.innerHTML = '<i class="bi bi-play-circle-fill"></i> In Progress';
            } else {
                // Add in-progress status if it doesn't exist
                const lessonMeta = document.querySelector('.lesson-meta');
                if (lessonMeta) {
                    const newStatus = document.createElement('span');
                    newStatus.className = 'status in-progress';
                    newStatus.innerHTML = '<i class="bi bi-play-circle-fill"></i> In Progress';
                    lessonMeta.appendChild(newStatus);
                }
            }
        } else {
            // Restore original state on error
            button.innerHTML = originalContent;
            button.disabled = false;
            if (result.message) {
                alert(result.message);
            }
        }
    } catch (error) {
        console.error('Error starting lesson:', error);
        button.innerHTML = originalContent;
        button.disabled = false;
        alert('An error occurred while starting the lesson. Please try again.');
    }
}

async function toggleLessonCompletion(lessonId, action) {
    const button = document.getElementById('lesson-complete-btn');
    const originalContent = button.innerHTML;
    
    // Provide immediate visual feedback
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';
    
    try {
        const response = await fetch('/api/lesson-progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                lesson_id: lessonId,
                action: action
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update button immediately without page refresh
            if (action === 'complete') {
                button.className = 'btn btn-success btn-sm';
                button.innerHTML = '<i class="bi bi-check-circle-fill"></i> Completed';
                button.onclick = function() { toggleLessonCompletion(lessonId, 'incomplete'); };
            } else {
                button.className = 'btn btn-outline-primary btn-sm';
                button.innerHTML = '<i class="bi bi-check-circle"></i> Mark Complete';
                button.onclick = function() { toggleLessonCompletion(lessonId, 'complete'); };
            }
            button.disabled = false;
            
            // Optionally refresh the page to update sidebar and progress bar
            // Commenting out for smoother UX - uncomment if full page update is needed
            // window.location.reload();
        } else {
            // Restore original state on error
            button.innerHTML = originalContent;
            button.disabled = false;
            
            console.error('Error:', result.message);
            // Only show alert for actual errors (e.g., quiz lessons that need completion)
            if (result.message && result.message.includes('quiz')) {
                alert(result.message);
            }
        }
    } catch (error) {
        // Restore original state on error
        button.innerHTML = originalContent;
        button.disabled = false;
        
        console.error('Lesson completion error:', error);
        alert('An error occurred while updating lesson status. Please try again.');
    }
}

// Pre-load skills drill data
<?php
$drillExists = false;
$drillId = null;
if ($currentLessonId > 0) {
    require_once 'classes/SkillsDrill.php';
    $skillsDrill = new SkillsDrill();
    $drill = $skillsDrill->getByLessonId($currentLessonId);
    if ($drill) {
        $drillExists = true;
        $drillId = $drill['id'];
    }
}
?>

// Add event listeners when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Lesson Quiz button handler
    const lessonQuizBtn = document.getElementById('lesson-quiz-btn');
    if (lessonQuizBtn) {
        lessonQuizBtn.addEventListener('click', function() {
            const currentLessonId = <?php echo intval($currentLessonId); ?>;
            window.location.href = '/quiz-take.php?lesson_id=' + currentLessonId;
        });
    }
    
    // Skills Drill button handler
    const skillsDrillBtn = document.getElementById('skills-drill-btn');
    if (skillsDrillBtn) {
        skillsDrillBtn.addEventListener('click', function() {
            const drillExists = <?php echo $drillExists ? 'true' : 'false'; ?>;
            const drillId = <?php echo $drillId ? intval($drillId) : 'null'; ?>;
            const hasTranscript = <?php echo ($currentLessonId > 0 && $currentLesson && isset($currentLesson['video_transcript']) && $currentLesson['video_transcript']) ? 'true' : 'false'; ?>;
            
            if (drillExists && drillId) {
                window.location.href = '/skills-drill-take.php?drill_id=' + drillId;
            } else {
                let message = 'Skills drill not available for this lesson.';
                if (hasTranscript) {
                    message += '\n\nThis lesson has a transcript but no skills drill has been generated yet. An administrator needs to generate the skills drill from the admin panel.';
                } else {
                    message += '\n\nThis lesson needs a video transcript before a skills drill can be created.';
                }
                alert(message);
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>