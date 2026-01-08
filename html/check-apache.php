<?php
echo "<h1>Apache Configuration Check</h1>";

// Check if mod_rewrite is loaded
echo "<h2>1. Checking mod_rewrite:</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p style='color: green;'>✓ mod_rewrite is enabled</p>";
    } else {
        echo "<p style='color: red;'>✗ mod_rewrite is NOT enabled</p>";
    }
} else {
    echo "<p style='color: orange;'>Cannot check Apache modules directly</p>";
}

// Test if .htaccess is working
echo "<h2>2. Current Request:</h2>";
echo "<p>REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "</p>";

// Check .htaccess
echo "<h2>3. .htaccess Status:</h2>";
if (file_exists('.htaccess')) {
    echo "<p style='color: green;'>✓ .htaccess exists</p>";
} else {
    echo "<p style='color: red;'>✗ .htaccess not found</p>";
}

echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
EOF < /dev/null
