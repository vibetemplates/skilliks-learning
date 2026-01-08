<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Get the existing lesson
    $stmt = $db->prepare("SELECT * FROM lessons WHERE id = 6");
    $stmt->execute();
    $lesson = $stmt->fetch();
    
    if ($lesson) {
        echo "<h2>Existing Lesson Details:</h2>";
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
            echo "<br><h3>Generated iframe HTML:</h3>";
            echo '<pre>' . htmlspecialchars('<iframe src="' . $videoInfo['embed_url'] . '" frameborder="0" scrolling="no" allowfullscreen></iframe>') . '</pre>';
            
            echo "<br><h3>Embedded Video Test:</h3>";
            echo '<div style="width: 100%; max-width: 800px; position: relative; padding-bottom: 56.25%; height: 0; background: #000; border-radius: 8px; overflow: hidden;">';
            echo '<iframe src="' . htmlspecialchars($videoInfo['embed_url']) . '" ';
            echo 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" ';
            echo 'frameborder="0" scrolling="no" allowfullscreen></iframe>';
            echo '</div>';
        }
        
        echo "<br><br><a href='/course-detail.php?id=4&lesson=6&debug=1'>View Lesson in Course Detail (with debug)</a>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>