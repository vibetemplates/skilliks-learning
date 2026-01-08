<?php
/**
 * Check if .htaccess is causing issues
 * DELETE THIS FILE AFTER TESTING!
 */

// Simple PHP file with no dependencies
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>.htaccess Check</title>
</head>
<body>
    <h1>.htaccess Diagnostic</h1>
    
    <?php
    $htaccess = __DIR__ . '/.htaccess';
    
    if (file_exists($htaccess)) {
        echo "<h2>.htaccess Contents:</h2>";
        echo "<pre>" . htmlspecialchars(file_get_contents($htaccess)) . "</pre>";
        
        echo "<h2>Apache Modules (if available):</h2>";
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            echo "<ul>";
            foreach (['mod_rewrite', 'mod_headers', 'mod_deflate', 'mod_expires'] as $mod) {
                if (in_array($mod, $modules)) {
                    echo "<li>✓ $mod is loaded</li>";
                } else {
                    echo "<li>✗ $mod is NOT loaded</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p>Cannot check Apache modules (apache_get_modules not available)</p>";
        }
        
        echo "<h2>To Test:</h2>";
        echo "<ol>";
        echo "<li>Temporarily rename .htaccess to .htaccess.bak</li>";
        echo "<li>Try accessing your site again</li>";
        echo "<li>If it works, the issue is in .htaccess</li>";
        echo "</ol>";
    } else {
        echo "<p>No .htaccess file found</p>";
    }
    ?>
    
    <p><strong>Common .htaccess issues that cause 500 errors:</strong></p>
    <ul>
        <li>RewriteEngine On when mod_rewrite is not enabled</li>
        <li>Invalid RewriteRule syntax</li>
        <li>Options -Indexes when not allowed by server config</li>
        <li>php_value directives when PHP runs as CGI/FastCGI</li>
    </ul>
</body>
</html>