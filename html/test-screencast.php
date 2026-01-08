<!DOCTYPE html>
<html>
<head>
    <title>Screencast Test</title>
    <style>
        .video-container {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
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
    </style>
</head>
<body>
    <h1>Screencast.com Video Test</h1>
    
    <div class="video-container">
        <h2>Test 1: Direct iframe (from your example)</h2>
        <iframe scrolling='no' frameborder='0' style='width: 100%; height: 400px; border:0;' src='https://app.screencast.com/vBpIOLgUBCkgP/e' allowfullscreen></iframe>
    </div>
    
    <div class="video-container">
        <h2>Test 2: Responsive container</h2>
        <div class="responsive-video">
            <iframe src="https://app.screencast.com/vBpIOLgUBCkgP/e" 
                    frameborder="0" 
                    scrolling="no"
                    allowfullscreen></iframe>
        </div>
    </div>
    
    <div class="video-container">
        <h2>Test 3: URL parsing test</h2>
        <?php
        // Test the URL parsing
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
        
        $testUrl = 'https://app.screencast.com/vBpIOLgUBCkgP/e';
        $screencastId = extractScreencastId($testUrl);
        
        echo "Test URL: " . htmlspecialchars($testUrl) . "<br>";
        echo "Extracted ID: " . ($screencastId ? $screencastId : 'FAILED') . "<br>";
        
        if ($screencastId) {
            $embedUrl = "https://app.screencast.com/{$screencastId}/e";
            echo "Generated embed URL: " . htmlspecialchars($embedUrl) . "<br>";
            
            echo "<div class='responsive-video'>";
            echo "<iframe src='{$embedUrl}' frameborder='0' scrolling='no' allowfullscreen></iframe>";
            echo "</div>";
        }
        ?>
    </div>
</body>
</html>