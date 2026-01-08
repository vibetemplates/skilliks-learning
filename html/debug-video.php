<?php
// Debug video URL parsing
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// Test the functions
function extractScreencastId($url) {
    // Handle various screencast.com URL formats
    $patterns = [
        '/screencast\.com\/t\/([a-zA-Z0-9-_]+)/i',
        '/screencast\.com\/media\/([a-zA-Z0-9-_]+)/i',
        '/screencast\.com\/embed\/([a-zA-Z0-9-_]+)/i',
        '/app\.screencast\.com\/([a-zA-Z0-9-_]+)/i',
        '/app\.screencast\.com\/([a-zA-Z0-9-_]+)\/e/i'  // Handle embed format
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
    
    // Check for Screencast.com
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

// Test URLs
$testUrls = [
    'https://app.screencast.com/vBpIOLgUBCkgP/e',
    'https://app.screencast.com/vBpIOLgUBCkgP',
    'https://screencast.com/t/vBpIOLgUBCkgP',
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
];

// Test the specific patterns
$testUrl = 'https://app.screencast.com/vBpIOLgUBCkgP/e';
$patterns = [
    '/screencast\.com\/t\/([a-zA-Z0-9-_]+)/i',
    '/screencast\.com\/media\/([a-zA-Z0-9-_]+)/i',
    '/screencast\.com\/embed\/([a-zA-Z0-9-_]+)/i',
    '/app\.screencast\.com\/([a-zA-Z0-9-_]+)\/e/i',
    '/app\.screencast\.com\/([a-zA-Z0-9-_]+)\/?$/i'
];

echo "<h2>Pattern Testing for: " . htmlspecialchars($testUrl) . "</h2>";
foreach ($patterns as $i => $pattern) {
    $matches = [];
    if (preg_match($pattern, $testUrl, $matches)) {
        echo "Pattern " . ($i + 1) . ": MATCH - ID: " . $matches[1] . "<br>";
    } else {
        echo "Pattern " . ($i + 1) . ": NO MATCH<br>";
    }
}
echo "<hr>";

echo "<h2>Video URL Debugging</h2>";

foreach ($testUrls as $url) {
    echo "<h3>Testing: " . htmlspecialchars($url) . "</h3>";
    
    $screencastId = extractScreencastId($url);
    echo "Screencast ID: " . ($screencastId ? $screencastId : 'Not found') . "<br>";
    
    $videoInfo = getVideoEmbedInfo($url);
    echo "Video Type: " . $videoInfo['type'] . "<br>";
    echo "Video ID: " . ($videoInfo['id'] ?? 'None') . "<br>";
    echo "Embed URL: " . ($videoInfo['embed_url'] ?? 'None') . "<br>";
    
    if ($videoInfo['embed_url']) {
        echo "Iframe: <br>";
        echo htmlspecialchars("<iframe src='{$videoInfo['embed_url']}' frameborder='0' scrolling='no' allowfullscreen></iframe>");
    }
    
    echo "<hr>";
}
?>