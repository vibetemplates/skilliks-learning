<?php
/**
 * Test include paths
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Include Paths</h1>";

echo "<h2>Current Directory Structure:</h2>";
echo "<pre>";
echo "Current file: " . __FILE__ . "\n";
echo "Current dir: " . __DIR__ . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "</pre>";

echo "<h2>Checking config directory:</h2>";
$configDir = __DIR__ . '/config';
if (is_dir($configDir)) {
    echo "<p style='color:green'>✓ Config directory exists at: $configDir</p>";
    $files = scandir($configDir);
    echo "<p>Files in config/:</p><ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>✗ Config directory NOT found at: $configDir</p>";
    
    // Try to find where config actually is
    echo "<h3>Searching for config directory:</h3>";
    $searchPaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/config',
        dirname(__DIR__) . '/config',
        __DIR__ . '/../config',
        $_SERVER['DOCUMENT_ROOT'] . '/html/config',
        $_SERVER['DOCUMENT_ROOT'] . '/project-tracking-tool/html/config'
    ];
    
    foreach ($searchPaths as $path) {
        if (is_dir($path)) {
            echo "<p style='color:green'>Found config at: $path</p>";
        }
    }
}

echo "<h2>Testing includes with different methods:</h2>";

// Method 1: Direct include
echo "<h3>Method 1: Direct relative path</h3>";
if (file_exists('config/database.php')) {
    echo "<p style='color:green'>✓ Can find config/database.php with relative path</p>";
} else {
    echo "<p style='color:red'>✗ Cannot find config/database.php with relative path</p>";
}

// Method 2: Using __DIR__
echo "<h3>Method 2: Using __DIR__</h3>";
if (file_exists(__DIR__ . '/config/database.php')) {
    echo "<p style='color:green'>✓ Can find config/database.php with __DIR__</p>";
} else {
    echo "<p style='color:red'>✗ Cannot find config/database.php with __DIR__</p>";
}

// Show directory listing
echo "<h2>Directory Listing of current folder:</h2>";
echo "<pre>";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo $file . (is_dir(__DIR__ . '/' . $file) ? '/' : '') . "\n";
    }
}
echo "</pre>";

// Check if we're in a subdirectory
echo "<h2>URL Analysis:</h2>";
$scriptName = $_SERVER['SCRIPT_NAME'];
$requestUri = $_SERVER['REQUEST_URI'];
echo "<pre>";
echo "Script name: $scriptName\n";
echo "Request URI: $requestUri\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "</pre>";

// Suggestion
echo "<h2>Possible Solutions:</h2>";
echo "<ol>";
echo "<li>Make sure you're accessing the site from the correct URL path</li>";
echo "<li>Check if the application is in a subdirectory (e.g., /project-tracking-tool/html/)</li>";
echo "<li>Verify Apache DocumentRoot points to the html/ directory</li>";
echo "<li>Update config/constants.php with the correct APP_URL</li>";
echo "</ol>";
?>