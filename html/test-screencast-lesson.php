<?php
// Test adding a lesson with screencast URL and viewing it
require_once 'config/database.php';
require_once 'config/constants.php';

try {
    $db = getDB();
    
    // Insert a test lesson with screencast URL
    $stmt = $db->prepare("
        INSERT INTO lessons (course_id, title, description, video_url, content, status, created_by, order_index)
        VALUES (1, 'Test Screencast Lesson', 'This is a test lesson with a screencast video', 'https://app.screencast.com/vBpIOLgUBCkgP/e', 'Test content', 'published', 1, 1)
        ON DUPLICATE KEY UPDATE video_url = VALUES(video_url)
    ");
    $stmt->execute();
    
    echo "<h2>Test Lesson Added/Updated</h2>";
    
    // Get the lesson
    $stmt = $db->prepare("SELECT * FROM lessons WHERE title = 'Test Screencast Lesson'");
    $stmt->execute();
    $lesson = $stmt->fetch();
    
    if ($lesson) {
        echo "<h3>Lesson Details:</h3>";
        echo "ID: " . $lesson['id'] . "<br>";
        echo "Title: " . htmlspecialchars($lesson['title']) . "<br>";
        echo "Video URL: " . htmlspecialchars($lesson['video_url']) . "<br>";
        
        // Test the video parsing
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
        echo "<br><h3>Video Parsing Results:</h3>";
        echo "Type: " . $videoInfo['type'] . "<br>";
        echo "ID: " . ($videoInfo['id'] ?? 'None') . "<br>";
        echo "Embed URL: " . ($videoInfo['embed_url'] ?? 'None') . "<br>";
        
        if ($videoInfo['embed_url']) {
            echo "<br><h3>Embedded Video:</h3>";
            echo '<div style="width: 100%; max-width: 800px; position: relative; padding-bottom: 56.25%; height: 0; background: #000; border-radius: 8px; overflow: hidden;">';
            echo '<iframe src="' . htmlspecialchars($videoInfo['embed_url']) . '" ';
            echo 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" ';
            echo 'frameborder="0" scrolling="no" allowfullscreen></iframe>';
            echo '</div>';
        }
        
        echo "<br><br><a href='/course-detail.php?id=1&lesson=" . $lesson['id'] . "'>View Lesson in Course Detail</a>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>