<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Display Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .video-container {
            position: relative;
            background-color: #000;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
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
        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 0.75em;
            font-weight: 700;
            background-color: #0dcaf0;
            color: #000;
            border-radius: 0.25rem;
            margin: 5px 0;
        }
        .alert {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <h1>Screencast.com Video Display Test</h1>
    
    <?php
    // Test the actual lesson from the database
    require_once 'config/database.php';
    
    try {
        $db = getDB();
        
        // Get the lesson
        $stmt = $db->prepare("SELECT * FROM lessons WHERE id = 6");
        $stmt->execute();
        $lesson = $stmt->fetch();
        
        if ($lesson) {
            echo "<h2>Lesson: " . htmlspecialchars($lesson['title']) . "</h2>";
            echo "<p><strong>Original Video URL:</strong> " . htmlspecialchars($lesson['video_url']) . "</p>";
            
            // Video parsing functions
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
            
            echo '<div class="alert">';
            echo '<strong>Debug Info:</strong><br>';
            echo 'Video Type: ' . $videoInfo['type'] . '<br>';
            echo 'Video ID: ' . ($videoInfo['id'] ?? 'None') . '<br>';
            echo 'Embed URL: ' . ($videoInfo['embed_url'] ?? 'None') . '<br>';
            echo '</div>';
            
            if ($videoInfo['type'] !== 'unknown' && $videoInfo['embed_url']) {
                echo '<div class="video-header">';
                echo '<h3>Video: ' . htmlspecialchars($lesson['title']) . '</h3>';
                echo '<span class="badge">Screencast.com</span>';
                echo '</div>';
                
                echo '<div class="video-container">';
                echo '<div class="responsive-video">';
                echo '<iframe src="' . htmlspecialchars($videoInfo['embed_url']) . '" ';
                echo 'frameborder="0" scrolling="no" allowfullscreen></iframe>';
                echo '</div>';
                echo '</div>';
                
                echo '<p><strong>✓ Video should be displayed above</strong></p>';
            } else {
                echo '<p><strong>✗ Video cannot be displayed - unsupported format</strong></p>';
            }
            
        } else {
            echo "<p>Lesson not found.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }
    ?>
    
    <hr>
    
    <h2>Manual Test</h2>
    <p>Direct iframe test with the exact URL format:</p>
    <div class="video-container">
        <div class="responsive-video">
            <iframe src="https://app.screencast.com/vBpIOLgUBCkgP/e" 
                    frameborder="0" 
                    scrolling="no" 
                    allowfullscreen></iframe>
        </div>
    </div>
    <p><strong>✓ Manual test video should be displayed above</strong></p>
    
    <hr>
    
    <p><a href="/course-detail.php?id=4&lesson=6&debug=1">View in actual course detail page</a></p>
</body>
</html>