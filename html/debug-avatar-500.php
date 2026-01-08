<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Avatar 500 Error Debug</h1>";

// Test 1: Direct file access
echo "<h2>Test 1: Direct File Access</h2>";
$test_files = [
    'avatar_1_1752619473.png',
    'test.png',
    'test.txt'
];

foreach ($test_files as $file) {
    $path = dirname(__FILE__) . '/uploads/avatars/' . $file;
    echo "<p>Testing: $file</p>";
    echo "<ul>";
    echo "<li>Path: $path</li>";
    echo "<li>Exists: " . (file_exists($path) ? 'Yes' : 'No') . "</li>";
    if (file_exists($path)) {
        echo "<li>Readable: " . (is_readable($path) ? 'Yes' : 'No') . "</li>";
        echo "<li>Size: " . filesize($path) . " bytes</li>";
        echo "<li>Permissions: " . decoct(fileperms($path) & 0777) . "</li>";
    }
    echo "</ul>";
    
    // Try different URLs
    echo "<p>URL Tests:</p>";
    echo "<ol>";
    echo "<li>Direct URL: <a href='/uploads/avatars/$file' target='_blank'>/uploads/avatars/$file</a></li>";
    echo "<li>Serve Script: <a href='/serve-avatar.php?file=$file' target='_blank'>/serve-avatar.php?file=$file</a></li>";
    echo "</ol>";
    
    // Try to display
    echo "<p>Display attempts:</p>";
    echo "<div style='display: flex; gap: 20px;'>";
    echo "<div>";
    echo "<p>Direct URL:</p>";
    echo "<img src='/uploads/avatars/$file' style='max-width: 100px; border: 1px solid #ccc;' alt='Direct'>";
    echo "</div>";
    echo "<div>";
    echo "<p>Serve Script:</p>";
    echo "<img src='/serve-avatar.php?file=$file' style='max-width: 100px; border: 1px solid #ccc;' alt='Script'>";
    echo "</div>";
    echo "</div>";
    echo "<hr>";
}

// Test 2: Check .htaccess files
echo "<h2>Test 2: .htaccess Files</h2>";
$htaccess_locations = [
    '/uploads/.htaccess',
    '/uploads/.htaccess.bak',
    '/uploads/avatars/.htaccess',
    '/uploads/avatars/.htaccess.bak'
];

foreach ($htaccess_locations as $htaccess) {
    $full_path = dirname(__FILE__) . $htaccess;
    echo "<p>$htaccess: " . (file_exists($full_path) ? 'EXISTS' : 'Not found') . "</p>";
}

// Test 3: Server Information
echo "<h2>Test 3: Server Information</h2>";
echo "<ul>";
echo "<li>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li>Script Path: " . __FILE__ . "</li>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>SAPI: " . php_sapi_name() . "</li>";
echo "</ul>";

// Test 4: Apache Modules
echo "<h2>Test 4: Apache Modules (if available)</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<p>Loaded modules: " . implode(', ', $modules) . "</p>";
} else {
    echo "<p>apache_get_modules() not available</p>";
}

// Test 5: Create and serve a test image inline
echo "<h2>Test 5: Inline Base64 Test Image</h2>";
$test_image_data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
echo "<p>If you can see a red pixel below, images can be displayed:</p>";
echo "<img src='data:image/png;base64,$test_image_data' style='width: 50px; height: 50px; border: 1px solid #000;' alt='Test'>";

// Test 6: Error log check
echo "<h2>Test 6: PHP Error Reporting</h2>";
echo "<p>Error reporting level: " . error_reporting() . "</p>";
echo "<p>Display errors: " . ini_get('display_errors') . "</p>";
echo "<p>Log errors: " . ini_get('log_errors') . "</p>";
echo "<p>Error log: " . ini_get('error_log') . "</p>";
?>