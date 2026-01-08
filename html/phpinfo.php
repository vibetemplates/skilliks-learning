<?php
// Check loaded Apache modules
if (function_exists('apache_get_modules')) {
    echo "<h2>Apache Modules:</h2>";
    $modules = apache_get_modules();
    echo "<p>mod_rewrite: " . (in_array('mod_rewrite', $modules) ? 'ENABLED' : 'DISABLED') . "</p>";
} else {
    echo "<p>Running as FastCGI/FPM - cannot check Apache modules directly</p>";
}

// Check environment variable from .htaccess
echo "<h2>Environment Check:</h2>";
echo "<p>HTACCESS_WORKS: " . (getenv('HTACCESS_WORKS') ? 'YES' : 'NO') . "</p>";

// Show all info
phpinfo();