<?php
/**
 * Database Initialization Script
 * 
 * This script creates the database and runs the schema.sql file
 * Run this script once to set up the database
 */

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 8889, // MAMP MySQL port
    'username' => 'root',
    'password' => 'root', // MAMP default root password
    'charset' => 'utf8mb4'
];

// Application database details
$dbName = 'project_tracker';
$appUser = 'students';
$appPassword = '#ClaudeCode123#';

try {
    // Connect to MySQL server without specifying a database
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server successfully.\n";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$dbName' created or already exists.\n";
    
    // Use the database
    $pdo->exec("USE `$dbName`");
    
    // Read and execute the schema file
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }
    
    $schema = file_get_contents($schemaFile);
    
    // Remove comments and split by delimiter
    $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);
    $schema = preg_replace('/--.*$/m', '', $schema);
    
    // Split by DELIMITER if exists, otherwise by semicolon
    if (strpos($schema, 'DELIMITER') !== false) {
        // Handle DELIMITER statements
        $statements = preg_split('/DELIMITER\s+/i', $schema);
        foreach ($statements as $i => $statement) {
            if ($i == 0) {
                // First part before DELIMITER
                $queries = array_filter(explode(';', $statement), 'trim');
                foreach ($queries as $query) {
                    if (trim($query)) {
                        $pdo->exec($query);
                    }
                }
            } else {
                // Parts after DELIMITER
                $parts = preg_split('/\$\$/', $statement, 2);
                if (count($parts) == 2) {
                    $delimiter_query = trim($parts[0]);
                    if ($delimiter_query) {
                        $pdo->exec($delimiter_query);
                    }
                    // Process remaining queries after delimiter change
                    $remaining = trim($parts[1]);
                    if ($remaining) {
                        $queries = array_filter(explode(';', $remaining), 'trim');
                        foreach ($queries as $query) {
                            if (trim($query)) {
                                $pdo->exec($query);
                            }
                        }
                    }
                }
            }
        }
    } else {
        // No DELIMITER statements, split by semicolon
        $queries = array_filter(explode(';', $schema), 'trim');
        $count = 0;
        foreach ($queries as $query) {
            if (trim($query)) {
                try {
                    $pdo->exec($query);
                    $count++;
                } catch (PDOException $e) {
                    // Skip if table/index already exists
                    if ($e->getCode() != '42S01' && $e->getCode() != '42000') {
                        throw $e;
                    }
                }
            }
        }
    }
    
    echo "Schema executed successfully.\n";
    
    // Create application user and grant privileges
    try {
        // Drop user if exists (for re-running the script)
        $pdo->exec("DROP USER IF EXISTS '$appUser'@'localhost'");
        
        // Create user
        $pdo->exec("CREATE USER '$appUser'@'localhost' IDENTIFIED BY '$appPassword'");
        
        // Grant privileges
        $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$appUser'@'localhost'");
        $pdo->exec("FLUSH PRIVILEGES");
        
        echo "Application user '$appUser' created with full privileges on '$dbName'.\n";
    } catch (PDOException $e) {
        echo "Note: Could not create application user (may already exist): " . $e->getMessage() . "\n";
    }
    
    // Verify tables were created
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nCreated tables:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }
    
    echo "\nDatabase initialization completed successfully!\n";
    echo "You can now connect to the database using:\n";
    echo "  Host: {$config['host']}\n";
    echo "  Port: {$config['port']}\n";
    echo "  Database: $dbName\n";
    echo "  Username: $appUser\n";
    echo "  Password: $appPassword\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}