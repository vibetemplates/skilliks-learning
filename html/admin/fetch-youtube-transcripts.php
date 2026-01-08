<?php
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/YouTubeTranscript.php';
require_once '../classes/YouTubeTranscriptWeb.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    setFlashMessage('error', 'Access denied. Administrator privileges required.');
    header('Location: /courses');
    exit();
}

$pageTitle = 'Fetch YouTube Transcripts';
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1>Fetch YouTube Transcripts</h1>
            <p>This tool will fetch transcripts for all lessons with YouTube videos.</p>
            
            <?php
            // Check OAuth status
            $tokenPath = '../config/tokens/youtube-token.json';
            $hasOAuth = file_exists($tokenPath);
            
            if (isset($_GET['oauth'])) {
                if ($_GET['oauth'] == 'success') {
                    echo '<div class="alert alert-success">YouTube OAuth authentication successful!</div>';
                } elseif ($_GET['oauth'] == 'error') {
                    echo '<div class="alert alert-danger">YouTube OAuth authentication failed. Please try again.</div>';
                }
            }
            ?>
            
            <div class="alert alert-<?= $hasOAuth ? 'success' : 'warning' ?> mb-3">
                <strong>OAuth Status:</strong> 
                <?php if ($hasOAuth): ?>
                    <i class="fas fa-check-circle"></i> Authenticated with YouTube
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle"></i> Not authenticated - 
                    <a href="/admin/youtube-oauth.php" class="alert-link">Click here to authenticate</a>
                <?php endif; ?>
            </div>
            
            <?php if (isset($_POST['fetch_transcripts'])): ?>
                <div class="alert alert-info">
                    <h4>Processing Transcripts...</h4>
                    <?php
                    try {
                        $pdo = getDB();
                        $transcriptFetcher = new YouTubeTranscript();
                        $webFetcher = new YouTubeTranscriptWeb();
                        
                        // Get all lessons with video URLs but no transcript
                        $stmt = $pdo->prepare("
                            SELECT l.id, l.title, l.video_url, c.title as course_title
                            FROM lessons l
                            JOIN courses c ON l.course_id = c.id
                            WHERE l.video_url IS NOT NULL 
                            AND l.video_url != ''
                            AND (l.video_transcript IS NULL OR l.video_transcript = '')
                            ORDER BY c.title, l.order_index
                        ");
                        $stmt->execute();
                        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($lessons)) {
                            echo "<p>No lessons found that need transcripts.</p>";
                        } else {
                            echo "<ul>";
                            $successCount = 0;
                            $errorCount = 0;
                            
                            foreach ($lessons as $lesson) {
                                echo "<li><strong>{$lesson['course_title']} - {$lesson['title']}</strong>: ";
                                
                                // Check if URL is a YouTube URL
                                if (strpos($lesson['video_url'], 'youtube.com') !== false || 
                                    strpos($lesson['video_url'], 'youtu.be') !== false) {
                                    
                                    // Try API method first
                                    $transcript = $transcriptFetcher->getTranscript($lesson['video_url']);
                                    
                                    // If API returns empty, try web scraping method
                                    if (!$transcript || strlen($transcript) == 0 || str_starts_with($transcript, 'Error') || str_starts_with($transcript, 'No captions')) {
                                        error_log("API method failed, trying web scraping for lesson " . $lesson['id']);
                                        $transcript = $webFetcher->getTranscript($lesson['video_url']);
                                    }
                                    
                                    if ($transcript && !str_starts_with($transcript, 'Error') && !str_starts_with($transcript, 'No captions') && strlen($transcript) > 0) {
                                        if ($transcriptFetcher->updateLessonTranscript($lesson['id'], $transcript)) {
                                            echo "<span class='text-success'>✓ Transcript fetched successfully (" . strlen($transcript) . " chars)</span>";
                                            $successCount++;
                                        } else {
                                            echo "<span class='text-danger'>✗ Failed to save transcript</span>";
                                            $errorCount++;
                                        }
                                    } else {
                                        if (strlen($transcript) == 0) {
                                            echo "<span class='text-warning'>⚠ Empty transcript returned - video may require manual caption download</span>";
                                        } else {
                                            echo "<span class='text-warning'>⚠ {$transcript}</span>";
                                        }
                                        $errorCount++;
                                    }
                                } else {
                                    echo "<span class='text-muted'>- Not a YouTube URL</span>";
                                }
                                
                                echo "</li>";
                                flush(); // Send output to browser immediately
                            }
                            echo "</ul>";
                            
                            echo "<div class='alert alert-success mt-3'>";
                            echo "<strong>Process Complete!</strong><br>";
                            echo "Successfully fetched: {$successCount} transcripts<br>";
                            echo "Errors: {$errorCount}";
                            echo "</div>";
                        }
                        
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                    ?>
                </div>
            <?php else: ?>
                <?php
                // Show summary of lessons
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_lessons,
                            SUM(CASE WHEN video_url IS NOT NULL AND video_url != '' THEN 1 ELSE 0 END) as with_video,
                            SUM(CASE WHEN video_transcript IS NOT NULL AND video_transcript != '' THEN 1 ELSE 0 END) as with_transcript,
                            SUM(CASE WHEN video_url IS NOT NULL AND video_url != '' 
                                AND (video_transcript IS NULL OR video_transcript = '') THEN 1 ELSE 0 END) as need_transcript
                        FROM lessons
                    ");
                    $stmt->execute();
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Lesson Statistics</h5>
                            <ul>
                                <li>Total lessons: <?= $stats['total_lessons'] ?></li>
                                <li>Lessons with videos: <?= $stats['with_video'] ?></li>
                                <li>Lessons with transcripts: <?= $stats['with_transcript'] ?></li>
                                <li><strong>Lessons needing transcripts: <?= $stats['need_transcript'] ?></strong></li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if ($stats['need_transcript'] > 0): ?>
                        <form method="POST">
                            <div class="alert alert-warning">
                                <strong>Note:</strong> This process will attempt to fetch transcripts from YouTube. 
                                Some videos may require OAuth authentication for transcript access.
                            </div>
                            <button type="submit" name="fetch_transcripts" class="btn btn-primary">
                                Fetch Transcripts for <?= $stats['need_transcript'] ?> Lessons
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            All lessons with videos already have transcripts!
                        </div>
                    <?php endif; ?>
                <?php
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error loading statistics: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="/admin-courses" class="btn btn-secondary">Back to Course Management</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>