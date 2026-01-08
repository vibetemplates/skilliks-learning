<?php
// Test course detail page directly
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

$courseId = 4;
$lessonId = 6;

try {
    $db = getDB();
    
    // Get lesson details
    $stmt = $db->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch();
    
    if ($lesson) {
        echo "<h2>Lesson: " . htmlspecialchars($lesson['title']) . "</h2>";
        echo "<p><strong>Video URL:</strong> " . htmlspecialchars($lesson['video_url']) . "</p>";
        
        // Test the video parsing functions from course-detail.php
        function extractScreencastId($url) {
            $patterns = [
                '/screencast\.com\/t\/([a-zA-Z0-9-_]+)/i',
                '/screencast\.com\/media\/([a-zA-Z0-9-_]+)/i',
                '/screencast\.com\/embed\/([a-zA-Z0-9-_]+)/i',
                '/app\.screencast\.com\/([a-zA-Z0-9-_]+)\/e/i',
                '/app\.screencast\.com\/([a-zA-Z0-9-_]+)\/?$/i'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $url, $matches)) {
                    return $matches[1];
                }
            }
            return false;
        }
        
        function getVideoEmbedInfo($url) {
            $result = [
                'type' => 'unknown',
                'id' => null,
                'embed_url' => null,
                'thumbnail_url' => null
            ];
            
            $screencastId = extractScreencastId($url);
            if ($screencastId) {
                $result['type'] = 'screencast';
                $result['id'] = $screencastId;
                $result['embed_url'] = "https://app.screencast.com/{$screencastId}/e";
                $result['thumbnail_url'] = null;
                return $result;
            }
            
            return $result;
        }
        
        $videoInfo = getVideoEmbedInfo($lesson['video_url']);
        
        echo "<h3>Debug Info:</h3>";
        echo "Video Type: " . $videoInfo['type'] . "<br>";
        echo "Video ID: " . ($videoInfo['id'] ?? 'None') . "<br>";
        echo "Embed URL: " . ($videoInfo['embed_url'] ?? 'None') . "<br>";
        
        echo "<h3>Video Display Logic:</h3>";
        if ($videoInfo['type'] !== 'unknown' && $videoInfo['embed_url']) {
            echo "<p>✓ Video should display (type: " . $videoInfo['type'] . ")</p>";
            echo "<h4>Generated Video HTML:</h4>";
            
            echo '<div class="lesson-video">';
            echo '<div class="video-header">';
            echo '<h4>Video: ' . htmlspecialchars($lesson['title']) . '</h4>';
            echo '<div class="video-meta">';
            echo '<span class="badge bg-info">';
            echo ($videoInfo['type'] === 'youtube' ? 'YouTube' : 'Screencast.com');
            echo '</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="video-container">';
            echo '<div class="responsive-video">';
            echo '<iframe src="' . htmlspecialchars($videoInfo['embed_url']) . '" ';
            echo 'frameborder="0" ';
            if ($videoInfo['type'] === 'youtube') {
                echo 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ';
            } elseif ($videoInfo['type'] === 'screencast') {
                echo 'scrolling="no" ';
            }
            echo 'allowfullscreen></iframe>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } else {
            echo "<p>✗ Video would show placeholder (type: " . $videoInfo['type'] . ")</p>";
        }
        
        echo "<br><br><a href='/course-detail.php?id=$courseId&lesson=$lessonId&debug=1' target='_blank'>Open in Course Detail Page</a>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
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
    padding-bottom: 56.25%;
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

.badge {
    display: inline-block;
    padding: 0.25em 0.4em;
    font-size: 0.75em;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.bg-info {
    background-color: #0dcaf0 !important;
    color: #000 !important;
}
</style>