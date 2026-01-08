<?php
/**
 * Debug Error Script
 * This file helps diagnose 500 Internal Server Errors
 * DELETE THIS FILE AFTER DEBUGGING!
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug 500 Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Debug 500 Internal Server Error</h1>
    
    <h2>1. PHP Error Reporting</h2>
    <?php
    echo "<p>Error reporting is now enabled. If there are PHP errors, they should appear below:</p>";
    
    // Test basic PHP
    echo '<p class="success">✓ Basic PHP is working</p>';
    ?>
    
    <h2>2. Common Issues Check</h2>
    <?php
    // Check if session can start
    echo "<h3>Session Test:</h3>";
    try {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
            echo '<p class="success">✓ Sessions can start</p>';
        } else {
            echo '<p class="info">Session already started</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ Session error: ' . $e->getMessage() . '</p>';
    }
    
    // Check file permissions
    echo "<h3>File Permissions:</h3>";
    $checkDirs = [
        'config' => 'Configuration directory',
        'uploads' => 'Uploads directory',
        'includes' => 'Includes directory',
        'classes' => 'Classes directory'
    ];
    
    foreach ($checkDirs as $dir => $desc) {
        $path = __DIR__ . '/' . $dir;
        if (is_dir($path)) {
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $readable = is_readable($path) ? '✓ Readable' : '✗ Not readable';
            echo "<p>$desc: $readable (permissions: $perms)</p>";
        } else {
            echo "<p class='error'>$desc: ✗ Directory not found</p>";
        }
    }
    ?>
    
    <h2>3. Test File Includes</h2>
    <?php
    // Test including critical files
    $testFiles = [
        'config/constants.php' => 'Constants file',
        'config/database.php' => 'Database config',
        'config/functions.php' => 'Functions file',
        'includes/session.php' => 'Session handler'
    ];
    
    foreach ($testFiles as $file => $desc) {
        echo "<h3>Testing $desc:</h3>";
        $fullPath = __DIR__ . '/' . $file;
        
        if (file_exists($fullPath)) {
            try {
                // Use output buffering to catch any output
                ob_start();
                $errorsBefore = error_get_last();
                
                // Don't actually include session.php as it might redirect
                if ($file === 'includes/session.php') {
                    echo '<p class="info">Skipping session.php (may cause redirect)</p>';
                } else {
                    include_once $fullPath;
                }
                
                $output = ob_get_clean();
                $errorsAfter = error_get_last();
                
                if ($errorsBefore !== $errorsAfter) {
                    echo '<p class="error">✗ Error including file: ' . htmlspecialchars($errorsAfter['message']) . '</p>';
                } else {
                    echo '<p class="success">✓ File included successfully</p>';
                    if (!empty($output)) {
                        echo '<div class="info">Output: ' . htmlspecialchars($output) . '</div>';
                    }
                }
            } catch (Exception $e) {
                ob_end_clean();
                echo '<p class="error">✗ Exception: ' . htmlspecialchars($e->getMessage()) . '</p>';
            } catch (ParseError $e) {
                ob_end_clean();
                echo '<p class="error">✗ Parse Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            } catch (Error $e) {
                ob_end_clean();
                echo '<p class="error">✗ Fatal Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        } else {
            echo '<p class="error">✗ File not found: ' . htmlspecialchars($file) . '</p>';
        }
    }
    ?>
    
    <h2>4. Check .htaccess Issues</h2>
    <?php
    $htaccessPath = __DIR__ . '/.htaccess';
    if (file_exists($htaccessPath)) {
        echo '<p class="info">.htaccess file exists</p>';
        echo '<p>Common .htaccess issues that cause 500 errors:</p>';
        echo '<ul>';
        echo '<li>Invalid RewriteRule syntax</li>';
        echo '<li>Trying to use modules that aren\'t enabled (like mod_rewrite)</li>';
        echo '<li>Invalid Options directives</li>';
        echo '</ul>';
        echo '<p>Try temporarily renaming .htaccess to .htaccess.bak to see if the error goes away.</p>';
    } else {
        echo '<p>.htaccess file not found</p>';
    }
    ?>
    
    <h2>5. PHP Info</h2>
    <p>Key PHP settings:</p>
    <div class="info">
        PHP Version: <?php echo phpversion(); ?><br>
        Memory Limit: <?php echo ini_get('memory_limit'); ?><br>
        Max Execution Time: <?php echo ini_get('max_execution_time'); ?><br>
        Error Log: <?php echo ini_get('error_log') ?: 'Not set'; ?><br>
        Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?><br>
        Script Filename: <?php echo $_SERVER['SCRIPT_FILENAME']; ?>
    </div>
    
    <h2>6. Recent PHP Errors</h2>
    <?php
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog) && is_readable($errorLog)) {
        $lines = file($errorLog);
        $recentErrors = array_slice($lines, -10); // Last 10 lines
        if (!empty($recentErrors)) {
            echo '<pre>' . htmlspecialchars(implode('', $recentErrors)) . '</pre>';
        } else {
            echo '<p>No recent errors in log file</p>';
        }
    } else {
        echo '<p>Cannot read error log. Check your server\'s error log for details.</p>';
    }
    ?>
    
    <h2>7. Test Simple Page</h2>
    <p>Try accessing this simple test page to isolate the issue:</p>
    <p><a href="test-simple.php">test-simple.php</a> (we'll create it next)</p>
    
    <div style="background: #ffffcc; padding: 10px; margin-top: 20px;">
        <strong>⚠️ Security Warning:</strong> Delete this file (debug-error.php) after debugging!
    </div>
</body>
</html>