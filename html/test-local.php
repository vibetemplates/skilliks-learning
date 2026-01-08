<?php
/**
 * Local debugging script
 * Tests the application step by step
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Local Debug Test</title>";
echo "<style>body { font-family: Arial; margin: 20px; } .success { color: green; } .error { color: red; } .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }</style>";
echo "</head><body>";
echo "<h1>SkillikS Local Debug Test</h1>";

// Test 1: Basic PHP
echo "<h2>Test 1: Basic PHP</h2>";
echo '<p class="success">✓ PHP is working (version ' . phpversion() . ')</p>';

// Test 2: Can we access files?
echo "<h2>Test 2: File Access</h2>";
$testFiles = [
    'config/constants.php',
    'config/database.php',
    'config/functions.php'
];

foreach ($testFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo '<p class="success">✓ Found: ' . $file . '</p>';
    } else {
        echo '<p class="error">✗ Missing: ' . $file . '</p>';
    }
}

// Test 3: Try including constants without session
echo "<h2>Test 3: Include Constants</h2>";
try {
    // Define APP_ROOT if not defined
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', __DIR__);
    }
    
    $constantsFile = __DIR__ . '/config/constants.php';
    if (file_exists($constantsFile)) {
        include_once $constantsFile;
        echo '<p class="success">✓ Constants loaded successfully</p>';
        echo '<div class="info">APP_URL: ' . (defined('APP_URL') ? APP_URL : 'Not defined') . '</div>';
    } else {
        echo '<p class="error">✗ Constants file not found</p>';
    }
} catch (Exception $e) {
    echo '<p class="error">✗ Error loading constants: ' . $e->getMessage() . '</p>';
}

// Test 4: Database configuration (without connecting)
echo "<h2>Test 4: Database Configuration</h2>";
try {
    $dbConfigFile = __DIR__ . '/config/database.php';
    if (file_exists($dbConfigFile)) {
        // Save current error reporting
        $oldError = error_reporting();
        error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        
        include_once $dbConfigFile;
        
        error_reporting($oldError);
        
        if (isset($db_config)) {
            echo '<p class="success">✓ Database config loaded</p>';
            echo '<div class="info">';
            echo 'Host: ' . $db_config['host'] . '<br>';
            echo 'Port: ' . $db_config['port'] . '<br>';
            echo 'Database: ' . $db_config['dbname'];
            echo '</div>';
        } else {
            echo '<p class="error">✗ Database config not set properly</p>';
        }
    } else {
        echo '<p class="error">✗ Database config file not found</p>';
    }
} catch (Exception $e) {
    echo '<p class="error">✗ Error loading database config: ' . $e->getMessage() . '</p>';
}

// Test 5: Check what happens with index.php
echo "<h2>Test 5: Index.php Analysis</h2>";
$indexFile = __DIR__ . '/index.php';
if (file_exists($indexFile)) {
    echo '<p class="success">✓ index.php exists</p>';
    
    // Read first few lines to see what it's trying to do
    $lines = file($indexFile, FILE_IGNORE_NEW_LINES);
    echo '<div class="info"><strong>First 10 lines of index.php:</strong><br><pre>';
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]) . "\n";
    }
    echo '</pre></div>';
} else {
    echo '<p class="error">✗ index.php not found</p>';
}

// Test 6: Session directory
echo "<h2>Test 6: Session Configuration</h2>";
$sessionPath = session_save_path();
if (empty($sessionPath)) {
    $sessionPath = sys_get_temp_dir();
}
echo '<div class="info">';
echo 'Session save path: ' . $sessionPath . '<br>';
echo 'Is writable: ' . (is_writable($sessionPath) ? 'Yes' : 'No') . '<br>';
echo '</div>';

// Test 7: Common issues
echo "<h2>Test 7: Common Issues Check</h2>";
echo '<div class="info">';
echo 'Short open tags: ' . (ini_get('short_open_tag') ? 'Enabled' : 'Disabled') . '<br>';
echo 'Magic quotes: ' . (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() ? 'Enabled' : 'Disabled') . '<br>';
echo 'Register globals: ' . (ini_get('register_globals') ? 'Enabled' : 'Disabled') . '<br>';
echo 'Display errors: ' . (ini_get('display_errors') ? 'Enabled' : 'Disabled') . '<br>';
echo 'Error reporting: ' . error_reporting() . '<br>';
echo 'Memory limit: ' . ini_get('memory_limit') . '<br>';
echo '</div>';

// Test 8: URL configuration
echo "<h2>Test 8: URL Configuration</h2>";
echo '<div class="info">';
echo 'Current URL: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]<br>";
echo 'Document root: ' . $_SERVER['DOCUMENT_ROOT'] . '<br>';
echo 'Script name: ' . $_SERVER['SCRIPT_NAME'] . '<br>';
echo '</div>';

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>If all tests pass above, try accessing: <a href='index.php'>index.php directly</a></li>";
echo "<li>Check if sessions are causing issues by accessing: <a href='login'>login</a></li>";
echo "<li>Try disabling .htaccess temporarily by renaming it</li>";
echo "</ol>";

echo "</body></html>";
?>