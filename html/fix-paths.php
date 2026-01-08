<?php
/**
 * Path fixing utility
 * This helps identify and fix path issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define APP_ROOT with the correct path
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Path Issues</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; }
        .code { background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; }
    </style>
</head>
<body>
    <h1>Path Configuration Checker</h1>
    
    <h2>1. Current Environment</h2>
    <div class="code">
        Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?><br>
        Current Directory: <?php echo __DIR__; ?><br>
        Script Name: <?php echo $_SERVER['SCRIPT_NAME']; ?><br>
        Access URL: <?php echo "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?>
    </div>
    
    <h2>2. Path Analysis</h2>
    <?php
    // Check if we're in a subdirectory
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $pathParts = explode('/', trim($scriptPath, '/'));
    
    if (count($pathParts) > 1) {
        echo '<p class="warning">⚠️ Application appears to be in a subdirectory: /' . implode('/', array_slice($pathParts, 0, -1)) . '/</p>';
        $appBasePath = '/' . implode('/', array_slice($pathParts, 0, -1)) . '/';
    } else {
        echo '<p class="success">✓ Application appears to be in document root</p>';
        $appBasePath = '/';
    }
    ?>
    
    <h2>3. Testing File Access</h2>
    <?php
    $testFiles = [
        'config/database.php' => 'Database configuration',
        'config/constants.php' => 'Application constants',
        'config/functions.php' => 'Helper functions',
        'includes/session.php' => 'Session handler',
        'includes/header.php' => 'Header template'
    ];
    
    $allGood = true;
    foreach ($testFiles as $file => $desc) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            echo '<p class="success">✓ ' . $desc . ' (' . $file . ')</p>';
        } else {
            echo '<p class="error">✗ ' . $desc . ' (' . $file . ') - NOT FOUND</p>';
            $allGood = false;
        }
    }
    ?>
    
    <h2>4. Recommended Configuration</h2>
    <?php if ($allGood): ?>
        <p class="success">✓ All required files found!</p>
        
        <h3>Update your config/constants.php with:</h3>
        <div class="code">
            define('APP_URL', '<?php echo "http://$_SERVER[HTTP_HOST]$appBasePath"; ?>');
        </div>
        
        <h3>Your Apache VirtualHost should look like:</h3>
        <div class="code">
&lt;VirtualHost *:80&gt;<br>
    ServerName <?php echo $_SERVER['HTTP_HOST']; ?><br>
    DocumentRoot <?php echo __DIR__; ?><br>
    <br>
    &lt;Directory <?php echo __DIR__; ?>&gt;<br>
        Options -Indexes +FollowSymLinks<br>
        AllowOverride All<br>
        Require all granted<br>
    &lt;/Directory&gt;<br>
&lt;/VirtualHost&gt;
        </div>
        
        <h3>Test Links:</h3>
        <ul>
            <li><a href="<?php echo $appBasePath; ?>index.php">Home Page (index.php)</a></li>
            <li><a href="<?php echo $appBasePath; ?>login">Login Page</a></li>
            <li><a href="<?php echo $appBasePath; ?>register">Register Page</a></li>
        </ul>
    <?php else: ?>
        <p class="error">✗ Some files are missing. Please check your installation.</p>
        
        <h3>Possible issues:</h3>
        <ul>
            <li>Files not uploaded completely</li>
            <li>Wrong directory structure</li>
            <li>Permissions issue (though less likely if PHP is running)</li>
        </ul>
    <?php endif; ?>
    
    <h2>5. Quick Database Test</h2>
    <?php
    if (file_exists(__DIR__ . '/config/database.php')) {
        try {
            require_once __DIR__ . '/config/database.php';
            $db = getDB();
            echo '<p class="success">✓ Database connection successful!</p>';
        } catch (Exception $e) {
            echo '<p class="error">✗ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p>Make sure to update config/database.php with your local database settings.</p>';
        }
    }
    ?>
</body>
</html>