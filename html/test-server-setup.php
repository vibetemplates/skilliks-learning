<?php
/**
 * Server Setup Test Script
 * 
 * This file tests your server configuration and database connection
 * IMPORTANT: Delete this file after testing for security reasons!
 */

// Start output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Setup Test - SkillikS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        table td:first-child {
            font-weight: bold;
            width: 200px;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .delete-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-top: 30px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 SkillikS Server Setup Test</h1>
        
        <div class="delete-warning">
            ⚠️ SECURITY WARNING: Delete this file immediately after testing!
        </div>

        <div class="test-section">
            <h2>1. PHP Configuration</h2>
            <table>
                <tr>
                    <td>PHP Version:</td>
                    <td>
                        <?php 
                        $phpVersion = phpversion();
                        $phpVersionOk = version_compare($phpVersion, '8.2.0', '>=');
                        echo $phpVersion;
                        echo $phpVersionOk ? ' <span class="success">✓ OK</span>' : ' <span class="error">✗ Requires PHP 8.2+</span>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>Server Software:</td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                </tr>
                <tr>
                    <td>Document Root:</td>
                    <td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td>
                </tr>
                <tr>
                    <td>Current Directory:</td>
                    <td><?php echo __DIR__; ?></td>
                </tr>
            </table>
        </div>

        <div class="test-section">
            <h2>2. Required PHP Extensions</h2>
            <table>
                <?php
                $requiredExtensions = [
                    'PDO' => 'Database connectivity',
                    'pdo_mysql' => 'MySQL database driver',
                    'mbstring' => 'Multibyte string support',
                    'json' => 'JSON support',
                    'session' => 'Session management',
                    'fileinfo' => 'File upload handling',
                    'openssl' => 'HTTPS/SSL support',
                    'curl' => 'External API calls'
                ];
                
                foreach ($requiredExtensions as $ext => $description) {
                    $loaded = extension_loaded($ext);
                    echo "<tr>";
                    echo "<td>$ext:</td>";
                    echo "<td>$description - ";
                    echo $loaded ? '<span class="success">✓ Installed</span>' : '<span class="error">✗ Not installed</span>';
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>

        <div class="test-section">
            <h2>3. File System Checks</h2>
            <?php
            // Check if config files exist
            $configFiles = [
                'config/database.php' => 'Database configuration',
                'config/constants.php' => 'Application constants',
                'config/functions.php' => 'Helper functions'
            ];
            
            echo "<table>";
            foreach ($configFiles as $file => $description) {
                $exists = file_exists(__DIR__ . '/' . $file);
                echo "<tr>";
                echo "<td>$file:</td>";
                echo "<td>$description - ";
                echo $exists ? '<span class="success">✓ Found</span>' : '<span class="error">✗ Not found</span>';
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check uploads directory
            $uploadsDir = __DIR__ . '/uploads';
            $uploadsExists = is_dir($uploadsDir);
            $uploadsWritable = $uploadsExists && is_writable($uploadsDir);
            ?>
            
            <h3>Uploads Directory:</h3>
            <table>
                <tr>
                    <td>Directory exists:</td>
                    <td><?php echo $uploadsExists ? '<span class="success">✓ Yes</span>' : '<span class="error">✗ No - needs to be created</span>'; ?></td>
                </tr>
                <tr>
                    <td>Is writable:</td>
                    <td><?php echo $uploadsWritable ? '<span class="success">✓ Yes</span>' : '<span class="warning">✗ No - needs write permissions</span>'; ?></td>
                </tr>
            </table>
        </div>

        <div class="test-section">
            <h2>4. Database Connection Test</h2>
            <?php
            // Try to load database configuration
            $dbConfigFile = __DIR__ . '/config/database.php';
            if (file_exists($dbConfigFile)) {
                // Define APP_ROOT if not defined
                if (!defined('APP_ROOT')) {
                    define('APP_ROOT', __DIR__);
                }
                
                // Include the database config
                include_once $dbConfigFile;
                
                if (isset($db_config)) {
                    echo "<table>";
                    echo "<tr><td>Database Host:</td><td>" . htmlspecialchars($db_config['host']) . "</td></tr>";
                    echo "<tr><td>Database Port:</td><td>" . htmlspecialchars($db_config['port']) . "</td></tr>";
                    echo "<tr><td>Database Name:</td><td>" . htmlspecialchars($db_config['dbname']) . "</td></tr>";
                    echo "<tr><td>Database User:</td><td>" . htmlspecialchars($db_config['username']) . "</td></tr>";
                    echo "</table>";
                    
                    // Try to connect
                    echo "<h3>Connection Test:</h3>";
                    
                    // First, let's test basic connectivity without database selection
                    echo "<h4>Step 1: Testing MySQL Server Connection</h4>";
                    try {
                        $testDsn = "mysql:host={$db_config['host']};port={$db_config['port']};charset={$db_config['charset']}";
                        $testPdo = new PDO($testDsn, $db_config['username'], $db_config['password'], $db_config['options']);
                        echo '<p class="success">✓ Connected to MySQL server successfully!</p>';
                        
                        // Get MySQL version
                        $stmt = $testPdo->query("SELECT VERSION() as version");
                        $result = $stmt->fetch();
                        echo "<div class='info'>MySQL Version: " . $result['version'] . "</div>";
                        
                        // Check if database exists
                        echo "<h4>Step 2: Checking Database</h4>";
                        $stmt = $testPdo->query("SHOW DATABASES LIKE '{$db_config['dbname']}'");
                        $dbExists = $stmt->fetch();
                        
                        if ($dbExists) {
                            echo '<p class="success">✓ Database \'' . htmlspecialchars($db_config['dbname']) . '\' exists</p>';
                        } else {
                            echo '<p class="error">✗ Database \'' . htmlspecialchars($db_config['dbname']) . '\' does not exist</p>';
                            echo '<div class="info">Create it with: CREATE DATABASE `' . htmlspecialchars($db_config['dbname']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</div>';
                        }
                        
                        // Check user privileges
                        echo "<h4>Step 3: Checking User Privileges</h4>";
                        $stmt = $testPdo->query("SHOW GRANTS FOR CURRENT_USER()");
                        $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        echo "<div class='info' style='max-height: 150px; overflow-y: auto;'>";
                        echo "<strong>User Grants:</strong><br>";
                        foreach ($grants as $grant) {
                            echo "- " . htmlspecialchars($grant) . "<br>";
                        }
                        echo "</div>";
                        
                        $testPdo = null; // Close test connection
                        
                    } catch (PDOException $e) {
                        echo '<p class="error">✗ Cannot connect to MySQL server!</p>';
                        echo '<div class="info">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        
                        // Parse error for more specific help
                        $errorMsg = $e->getMessage();
                        echo "<h4>Specific Issues Detected:</h4>";
                        echo "<ul>";
                        
                        if (strpos($errorMsg, 'Access denied') !== false) {
                            echo "<li><strong>Authentication Issue:</strong> Username or password is incorrect</li>";
                            echo "<li>Current user: <code>" . htmlspecialchars($db_config['username']) . "</code></li>";
                            echo "<li>Make sure the password in config/database.php is correct</li>";
                        } elseif (strpos($errorMsg, 'getaddrinfo failed') !== false || strpos($errorMsg, 'php_network_getaddresses') !== false) {
                            echo "<li><strong>Host Resolution Issue:</strong> Cannot resolve host <code>" . htmlspecialchars($db_config['host']) . "</code></li>";
                            echo "<li>Try using IP address instead (e.g., 127.0.0.1 for localhost)</li>";
                        } elseif (strpos($errorMsg, 'Connection refused') !== false) {
                            echo "<li><strong>Connection Issue:</strong> MySQL is not running or not accepting connections</li>";
                            echo "<li>Port specified: <code>" . htmlspecialchars($db_config['port']) . "</code></li>";
                            echo "<li>Check if MySQL is running on this port</li>";
                            echo "<li>Common ports: 3306 (default), 3307, 8889 (MAMP)</li>";
                        } elseif (strpos($errorMsg, 'Connection timed out') !== false) {
                            echo "<li><strong>Network Issue:</strong> Connection timed out</li>";
                            echo "<li>Check firewall settings</li>";
                            echo "<li>Verify MySQL is configured to accept remote connections</li>";
                        }
                        
                        echo "</ul>";
                    }
                    
                    // Now try full connection with database
                    if (isset($dbExists) && $dbExists) {
                        echo "<h4>Step 4: Full Database Connection Test</h4>";
                        try {
                            $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
                            $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
                            
                            echo '<p class="success">✓ Full database connection successful!</p>';
                            
                            // Check if tables exist
                            echo "<h4>Step 5: Database Tables</h4>";
                            $stmt = $pdo->query("SHOW TABLES");
                            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            if (count($tables) > 0) {
                                echo '<p class="success">✓ Found ' . count($tables) . ' tables in the database</p>';
                                
                                // Check for core tables
                                $coreTables = ['users', 'projects', 'tasks', 'features', 'communities'];
                                $missingTables = [];
                                foreach ($coreTables as $coreTable) {
                                    if (!in_array($coreTable, $tables)) {
                                        $missingTables[] = $coreTable;
                                    }
                                }
                                
                                if (empty($missingTables)) {
                                    echo '<p class="success">✓ All core tables are present</p>';
                                } else {
                                    echo '<p class="warning">⚠ Missing core tables: ' . implode(', ', $missingTables) . '</p>';
                                    echo '<div class="info">Run the database migrations to create missing tables</div>';
                                }
                                
                                echo "<details>";
                                echo "<summary>Show all tables</summary>";
                                echo "<div class='info' style='max-height: 200px; overflow-y: auto;'>";
                                foreach ($tables as $table) {
                                    echo "- $table<br>";
                                }
                                echo "</div>";
                                echo "</details>";
                            } else {
                                echo '<p class="warning">⚠ No tables found - you need to run the database migrations</p>';
                                echo '<div class="info">';
                                echo 'Run these SQL files in order:<br>';
                                echo '1. database/schema.sql<br>';
                                echo '2. html/config/education_schema.sql<br>';
                                echo '3. html/config/file_uploads_schema.sql<br>';
                                echo '4. All files in html/migrations/ folder';
                                echo '</div>';
                            }
                            
                        } catch (PDOException $e) {
                            echo '<p class="error">✗ Database connection failed!</p>';
                            echo '<div class="info">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                    
                    // Additional debugging information
                    echo "<h4>Debug Information:</h4>";
                    echo "<div class='info'>";
                    echo "<strong>Connection String (DSN):</strong><br>";
                    echo "<code>mysql:host=" . htmlspecialchars($db_config['host']) . ";port=" . htmlspecialchars($db_config['port']) . ";dbname=" . htmlspecialchars($db_config['dbname']) . ";charset=" . htmlspecialchars($db_config['charset']) . "</code><br><br>";
                    echo "<strong>PHP MySQL Driver:</strong> " . (extension_loaded('pdo_mysql') ? 'Loaded' : 'Not loaded') . "<br>";
                    echo "<strong>PDO Driver:</strong> " . (extension_loaded('PDO') ? 'Loaded' : 'Not loaded') . "<br>";
                    if (extension_loaded('PDO')) {
                        echo "<strong>Available PDO Drivers:</strong> " . implode(', ', PDO::getAvailableDrivers()) . "<br>";
                    }
                    echo "</div>";
                } else {
                    echo '<p class="error">✗ Database configuration not found in database.php</p>';
                }
            } else {
                echo '<p class="error">✗ Database configuration file not found at: ' . htmlspecialchars($dbConfigFile) . '</p>';
            }
            ?>
        </div>

        <div class="test-section">
            <h2>5. URL Configuration Test</h2>
            <?php
            // Try to load constants
            $constantsFile = __DIR__ . '/config/constants.php';
            if (file_exists($constantsFile)) {
                include_once $constantsFile;
                
                if (defined('APP_URL')) {
                    echo "<table>";
                    echo "<tr><td>APP_URL:</td><td>" . htmlspecialchars(APP_URL) . "</td></tr>";
                    echo "<tr><td>Current URL:</td><td>" . htmlspecialchars("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}") . "</td></tr>";
                    echo "</table>";
                    
                    // Check if APP_URL matches current domain
                    $currentDomain = "https://{$_SERVER['HTTP_HOST']}/";
                    if (rtrim(APP_URL, '/') . '/' === rtrim($currentDomain, '/') . '/') {
                        echo '<p class="success">✓ APP_URL matches current domain</p>';
                    } else {
                        echo '<p class="warning">⚠ APP_URL does not match current domain - update constants.php</p>';
                    }
                } else {
                    echo '<p class="error">✗ APP_URL not defined in constants.php</p>';
                }
            } else {
                echo '<p class="error">✗ Constants file not found</p>';
            }
            ?>
        </div>

        <div class="test-section">
            <h2>6. Security Checklist</h2>
            <table>
                <tr>
                    <td>HTTPS Enabled:</td>
                    <td><?php echo (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '<span class="success">✓ Yes</span>' : '<span class="warning">⚠ No - SSL recommended</span>'; ?></td>
                </tr>
                <tr>
                    <td>Display Errors:</td>
                    <td><?php echo ini_get('display_errors') ? '<span class="warning">⚠ On - should be off in production</span>' : '<span class="success">✓ Off</span>'; ?></td>
                </tr>
                <tr>
                    <td>Error Reporting:</td>
                    <td><?php echo error_reporting() === E_ALL ? '<span class="warning">⚠ Full - consider reducing in production</span>' : '<span class="success">✓ Reduced</span>'; ?></td>
                </tr>
            </table>
        </div>

        <div class="test-section">
            <h2>7. Manual Database Connection Test</h2>
            <p>If the automatic test above fails, you can try a manual connection with custom settings:</p>
            
            <form method="post" action="">
                <table>
                    <tr>
                        <td><label for="test_host">Host:</label></td>
                        <td><input type="text" id="test_host" name="test_host" value="<?php echo isset($_POST['test_host']) ? htmlspecialchars($_POST['test_host']) : '127.0.0.1'; ?>" style="width: 300px;"></td>
                    </tr>
                    <tr>
                        <td><label for="test_port">Port:</label></td>
                        <td><input type="text" id="test_port" name="test_port" value="<?php echo isset($_POST['test_port']) ? htmlspecialchars($_POST['test_port']) : '3306'; ?>" style="width: 100px;"></td>
                    </tr>
                    <tr>
                        <td><label for="test_user">Username:</label></td>
                        <td><input type="text" id="test_user" name="test_user" value="<?php echo isset($_POST['test_user']) ? htmlspecialchars($_POST['test_user']) : 'project_tracker'; ?>" style="width: 300px;"></td>
                    </tr>
                    <tr>
                        <td><label for="test_pass">Password:</label></td>
                        <td><input type="password" id="test_pass" name="test_pass" value="<?php echo isset($_POST['test_pass']) ? htmlspecialchars($_POST['test_pass']) : ''; ?>" style="width: 300px;"></td>
                    </tr>
                    <tr>
                        <td><label for="test_db">Database:</label></td>
                        <td><input type="text" id="test_db" name="test_db" value="<?php echo isset($_POST['test_db']) ? htmlspecialchars($_POST['test_db']) : 'project_tracker'; ?>" style="width: 300px;"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><button type="submit" name="test_connection" style="padding: 8px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Test Connection</button></td>
                    </tr>
                </table>
            </form>
            
            <?php
            if (isset($_POST['test_connection'])) {
                echo "<h3>Manual Test Results:</h3>";
                
                $test_host = $_POST['test_host'];
                $test_port = $_POST['test_port'];
                $test_user = $_POST['test_user'];
                $test_pass = $_POST['test_pass'];
                $test_db = $_POST['test_db'];
                
                // Test without database first
                try {
                    echo "<p>Testing connection to MySQL server...</p>";
                    $testDsn = "mysql:host=$test_host;port=$test_port;charset=utf8mb4";
                    $testPdo = new PDO($testDsn, $test_user, $test_pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    
                    echo '<p class="success">✓ Connected to MySQL server!</p>';
                    
                    // Check if database exists
                    $stmt = $testPdo->query("SHOW DATABASES LIKE '$test_db'");
                    if ($stmt->fetch()) {
                        echo '<p class="success">✓ Database \'' . htmlspecialchars($test_db) . '\' exists</p>';
                        
                        // Try full connection
                        $fullDsn = "mysql:host=$test_host;port=$test_port;dbname=$test_db;charset=utf8mb4";
                        $fullPdo = new PDO($fullDsn, $test_user, $test_pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]);
                        echo '<p class="success">✓ Full database connection successful!</p>';
                        
                        // Show what to put in config/database.php
                        echo '<div class="info">';
                        echo '<strong>Update your config/database.php with these settings:</strong><br><br>';
                        echo '<pre>';
                        echo htmlspecialchars("'host' => '$test_host',\n");
                        echo htmlspecialchars("'port' => $test_port,\n");
                        echo htmlspecialchars("'dbname' => '$test_db',\n");
                        echo htmlspecialchars("'username' => '$test_user',\n");
                        echo htmlspecialchars("'password' => '$test_pass',");
                        echo '</pre>';
                        echo '</div>';
                        
                    } else {
                        echo '<p class="error">✗ Database \'' . htmlspecialchars($test_db) . '\' does not exist</p>';
                        echo '<div class="info">Create it with:<br><code>CREATE DATABASE `' . htmlspecialchars($test_db) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code></div>';
                    }
                    
                } catch (PDOException $e) {
                    echo '<p class="error">✗ Connection failed!</p>';
                    echo '<div class="info">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    
                    // Common port suggestions
                    echo '<div class="info">';
                    echo '<strong>Common MySQL ports to try:</strong><br>';
                    echo '• 3306 (default MySQL)<br>';
                    echo '• 3307 (alternative MySQL)<br>';
                    echo '• 8889 (MAMP default)<br>';
                    echo '• 33060 (MySQL X Protocol)<br>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <div class="delete-warning" style="margin-bottom: 0;">
            🗑️ REMEMBER: Delete this test file (test-server-setup.php) after testing!
        </div>
    </div>
</body>
</html>