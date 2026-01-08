<?php
/**
 * Admin Lesson Videos Management Page
 * 
 * Allows administrators to manage YouTube videos for a specific lesson
 */

$page_title = 'Lesson Videos';
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

$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
if (!$lessonId) {
    setFlashMessage('error', 'Lesson ID is required.');
    header('Location: /courses');
    exit;
}

// Get lesson and course details
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT l.*, c.title as course_title, c.id as course_id
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
} catch (PDOException $e) {
    error_log("Lesson fetch error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading lesson.');
    header('Location: /courses');
    exit;
}

// Create lesson_videos table if it doesn't exist
try {
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
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_video') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        $order_index = (int)($_POST['order_index'] ?? 0);
        
        $errors = [];
        
        // Validation
        if (empty($title)) {
            $errors[] = 'Video title is required.';
        }
        if (empty($youtube_url)) {
            $errors[] = 'Video URL is required.';
        } else {
            $videoInfo = getVideoEmbedInfo($youtube_url);
            if ($videoInfo['type'] === 'unknown') {
                $errors[] = 'Please enter a valid YouTube or Screencast.com URL.';
            }
        }
        
        if (empty($errors)) {
            // Get next order index if not specified
            if ($order_index <= 0) {
                try {
                    $stmt = $db->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM lesson_videos WHERE lesson_id = ? AND is_active = 1");
                    $stmt->execute([$lessonId]);
                    $result = $stmt->fetch();
                    $order_index = $result ? $result['next_order'] : 1;
                } catch (PDOException $e) {
                    $order_index = 1;
                }
            }
            
            try {
                $stmt = $db->prepare("
                    INSERT INTO lesson_videos (lesson_id, title, description, youtube_url, youtube_id, order_index, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([$lessonId, $title, $description, $youtube_url, $videoInfo['id'], $order_index, $currentUserId]);
                
                if ($result) {
                    setFlashMessage('success', 'Video added successfully!');
                } else {
                    setFlashMessage('error', 'Failed to add video.');
                }
            } catch (PDOException $e) {
                error_log("Video creation error: " . $e->getMessage());
                setFlashMessage('error', 'Database error occurred while adding video.');
            }
        } else {
            setFlashMessage('error', implode('<br>', $errors));
        }
    }
    
    elseif ($action === 'delete_video') {
        $videoId = (int)($_POST['video_id'] ?? 0);
        if ($videoId > 0) {
            try {
                $stmt = $db->prepare("UPDATE lesson_videos SET is_active = 0 WHERE id = ? AND lesson_id = ?");
                $result = $stmt->execute([$videoId, $lessonId]);
                
                if ($result) {
                    setFlashMessage('success', 'Video deleted successfully!');
                } else {
                    setFlashMessage('error', 'Failed to delete video.');
                }
            } catch (PDOException $e) {
                error_log("Video deletion error: " . $e->getMessage());
                setFlashMessage('error', 'Database error occurred while deleting video.');
            }
        }
    }
    
    elseif ($action === 'reorder_videos') {
        $videoIds = $_POST['video_ids'] ?? [];
        if (!empty($videoIds)) {
            try {
                $db->beginTransaction();
                
                foreach ($videoIds as $index => $videoId) {
                    $stmt = $db->prepare("UPDATE lesson_videos SET order_index = ? WHERE id = ? AND lesson_id = ?");
                    $stmt->execute([$index + 1, (int)$videoId, $lessonId]);
                }
                
                $db->commit();
                setFlashMessage('success', 'Video order updated successfully!');
            } catch (PDOException $e) {
                $db->rollback();
                error_log("Video reorder error: " . $e->getMessage());
                setFlashMessage('error', 'Database error occurred while reordering videos.');
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: /admin-lesson-videos.php?lesson_id=' . $lessonId);
    exit;
}

// Get existing videos for this lesson
try {
    $stmt = $db->prepare("
        SELECT * FROM lesson_videos 
        WHERE lesson_id = ? AND is_active = 1 
        ORDER BY order_index ASC
    ");
    $stmt->execute([$lessonId]);
    $videos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Videos fetch error: " . $e->getMessage());
    $videos = [];
}

require_once 'includes/header.php';
?>

<main class="container-fluid px-4 py-3">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Lesson Videos</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/admin-lesson-edit.php?id=<?php echo $lessonId; ?>" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Lesson
                    </a>
                    <a href="/admin-course-edit.php?id=<?php echo $lesson['course_id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Course
                    </a>
                </div>
            </div>

            <!-- Lesson Info -->
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle"></i>
                <strong>Lesson:</strong> <?php echo htmlspecialchars($lesson['title']); ?>
                <br>
                <strong>Course:</strong> <?php echo htmlspecialchars($lesson['course_title']); ?>
            </div>

            <?php if (getFlashMessage('success')): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    <?php echo getFlashMessage('success'); ?>
                </div>
            <?php endif; ?>

            <?php if (getFlashMessage('error')): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php echo getFlashMessage('error'); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Add New Video Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add New Video</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/admin-lesson-videos.php?lesson_id=<?php echo $lessonId; ?>">
                                <input type="hidden" name="action" value="add_video">
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Video Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="youtube_url" class="form-label">Video URL *</label>
                                    <input type="url" class="form-control" id="youtube_url" name="youtube_url" 
                                           placeholder="https://www.youtube.com/watch?v=... or https://app.screencast.com/..." required>
                                    <div class="form-text">Paste the full YouTube or Screencast.com URL here</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Brief description of what this video covers"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="order_index" class="form-label">Order Position</label>
                                    <input type="number" class="form-control" id="order_index" name="order_index" 
                                           value="<?php echo count($videos) + 1; ?>" min="1">
                                    <div class="form-text">Position in the video sequence (leave blank to add at the end)</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Add Video
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Existing Videos -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Lesson Videos (<?php echo count($videos); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($videos)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-camera-video text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3">No videos added yet</h5>
                                    <p class="text-muted">Add your first YouTube video using the form above.</p>
                                </div>
                            <?php else: ?>
                                <div id="video-list">
                                    <?php foreach ($videos as $video): ?>
                                        <div class="video-item card mb-3" data-video-id="<?php echo $video['id']; ?>">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="video-thumbnail">
                                                            <?php 
                                                            $videoInfo = getVideoEmbedInfo($video['youtube_url']);
                                                            if ($videoInfo['type'] === 'youtube' && $videoInfo['thumbnail_url']): ?>
                                                                <img src="<?php echo $videoInfo['thumbnail_url']; ?>" 
                                                                     class="img-fluid rounded" alt="Video thumbnail"
                                                                     onerror="this.src='https://img.youtube.com/vi/<?php echo $video['youtube_id']; ?>/hqdefault.jpg'">
                                                            <?php else: ?>
                                                                <div class="video-placeholder-thumb bg-light d-flex align-items-center justify-content-center rounded" style="height: 120px;">
                                                                    <i class="bi bi-play-circle text-muted" style="font-size: 2rem;"></i>
                                                                    <div class="ms-2">
                                                                        <small class="text-muted">
                                                                            <?php echo $videoInfo['type'] === 'screencast' ? 'Screencast.com' : 'Video'; ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="card-title"><?php echo htmlspecialchars($video['title']); ?></h6>
                                                                <p class="card-text small text-muted"><?php echo htmlspecialchars($video['description']); ?></p>
                                                                <div class="small text-muted">
                                                                    <i class="bi bi-youtube"></i> 
                                                                    <a href="<?php echo htmlspecialchars($video['youtube_url']); ?>" target="_blank" class="text-muted">
                                                                        View on YouTube
                                                                    </a>
                                                                    <span class="ms-2">Order: <?php echo $video['order_index']; ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="btn-group">
                                                                <button class="btn btn-sm btn-outline-danger" 
                                                                        onclick="deleteVideo(<?php echo $video['id']; ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Help & Tips -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Help & Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Supported Video URLs</h6>
                                <p class="small text-muted">You can paste any of these URL formats:
                                    <br><strong>YouTube:</strong>
                                    <br>• https://www.youtube.com/watch?v=VIDEO_ID
                                    <br>• https://youtu.be/VIDEO_ID
                                    <br>• https://www.youtube.com/embed/VIDEO_ID
                                    <br><strong>Screencast.com:</strong>
                                    <br>• https://screencast.com/t/VIDEO_ID
                                    <br>• https://screencast.com/media/VIDEO_ID
                                    <br>• https://app.screencast.com/VIDEO_ID
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Video Order</h6>
                                <p class="small text-muted">Videos are displayed in the order you specify. Lower numbers appear first.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><i class="bi bi-lightbulb"></i> Video Titles</h6>
                                <p class="small text-muted">Use descriptive titles that help students understand what each video covers.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</main>

<script>
function deleteVideo(videoId) {
    if (confirm('Are you sure you want to delete this video?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin-lesson-videos.php?lesson_id=<?php echo $lessonId; ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_video';
        
        const videoIdInput = document.createElement('input');
        videoIdInput.type = 'hidden';
        videoIdInput.name = 'video_id';
        videoIdInput.value = videoId;
        
        form.appendChild(actionInput);
        form.appendChild(videoIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.video-thumbnail img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.video-item {
    border: 1px solid #dee2e6;
    transition: box-shadow 0.2s ease;
}

.video-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.sortable-placeholder {
    background-color: #f8f9fa;
    border: 2px dashed #dee2e6;
    margin: 10px 0;
    height: 100px;
}
</style>

<?php require_once 'includes/footer.php'; ?>