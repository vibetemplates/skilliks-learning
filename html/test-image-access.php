<?php
// Test direct image access
$avatarFile = 'avatar_1_1752619473.png';
$avatarPath = dirname(__FILE__) . '/uploads/avatars/' . $avatarFile;

echo "<h2>Avatar Access Test</h2>";
echo "<p>Testing avatar file: " . htmlspecialchars($avatarFile) . "</p>";

// Check file existence
if (file_exists($avatarPath)) {
    echo "<p style='color: green;'>✓ File exists on server</p>";
    echo "<p>File size: " . filesize($avatarPath) . " bytes</p>";
    echo "<p>File permissions: " . decoct(fileperms($avatarPath) & 0777) . "</p>";
    
    // Get image info
    $imageInfo = getimagesize($avatarPath);
    if ($imageInfo) {
        echo "<p>Image dimensions: " . $imageInfo[0] . "x" . $imageInfo[1] . "</p>";
        echo "<p>MIME type: " . $imageInfo['mime'] . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ File not found on server</p>";
}

// Test web access
$webUrl = '/uploads/avatars/' . $avatarFile;
echo "<h3>Web Access Test</h3>";
echo "<p>URL: " . htmlspecialchars($webUrl) . "</p>";
echo "<img src='" . htmlspecialchars($webUrl) . "' alt='Avatar test' style='max-width: 200px; border: 1px solid #ccc;'>";

// Test with full URL
$fullUrl = 'http://' . $_SERVER['HTTP_HOST'] . $webUrl;
echo "<h3>Full URL Test</h3>";
echo "<p>Full URL: " . htmlspecialchars($fullUrl) . "</p>";
echo "<img src='" . htmlspecialchars($fullUrl) . "' alt='Avatar test full URL' style='max-width: 200px; border: 1px solid #ccc;'>";

// Check server configuration
echo "<h3>Server Configuration</h3>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
?>