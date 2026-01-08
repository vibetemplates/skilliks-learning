<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();

echo "<h2>Task Creation Debug</h2>";

try {
    $db = getDB();
    
    // Check database connection
    echo "<h3>✓ Database Connection: OK</h3>";
    
    // Check projects table
    $stmt = $db->prepare("SELECT id, name FROM projects LIMIT 5");
    $stmt->execute();
    $projects = $stmt->fetchAll();
    
    echo "<h3>Available Projects:</h3>";
    if (empty($projects)) {
        echo "<p style='color: red;'>❌ No projects found - this might be the issue!</p>";
    } else {
        foreach ($projects as $project) {
            echo "<p>✓ Project ID: " . $project['id'] . " - " . htmlspecialchars($project['name']) . "</p>";
        }
    }
    
    // Check users table
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch();
    
    echo "<h3>Current User:</h3>";
    if ($user) {
        echo "<p>✓ User ID: " . $user['id'] . " - " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Current user not found - this might be the issue!</p>";
    }
    
    // Test task creation with minimal data
    echo "<h3>Testing Task Creation:</h3>";
    
    if (!empty($projects)) {
        $testData = [
            'title' => 'Test Task Debug',
            'description' => 'This is a test task for debugging',
            'project_id' => $projects[0]['id'], // Use first available project
            'created_by' => $currentUserId,
            'type' => 'task',
            'priority' => 'medium'
        ];
        
        echo "<p><strong>Test data:</strong></p>";
        echo "<pre>" . print_r($testData, true) . "</pre>";
        
        // Test the SQL statement directly
        try {
            $stmt = $db->prepare("
                INSERT INTO tasks (title, description, project_id, assignee_id, reporter_id, priority, type, status, due_date, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'todo', ?, NOW())
            ");
            
            $params = [
                $testData['title'],
                $testData['description'] ?? null,
                $testData['project_id'],
                $testData['assigned_to'] ?? null,
                $testData['created_by'],
                $testData['priority'] ?? 'medium',
                $testData['type'] ?? 'task',
                $testData['due_date'] ?? null
            ];
            
            echo "<p><strong>SQL Parameters:</strong></p>";
            echo "<pre>" . print_r($params, true) . "</pre>";
            
            $result = $stmt->execute($params);
            
            if ($result) {
                $taskId = $db->lastInsertId();
                echo "<p style='color: green;'>✓ Task created successfully! ID: $taskId</p>";
                
                // Clean up - delete the test task
                $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->execute([$taskId]);
                echo "<p>✓ Test task cleaned up</p>";
            } else {
                $errorInfo = $stmt->errorInfo();
                echo "<p style='color: red;'>❌ Task creation failed:</p>";
                echo "<pre>SQL State: " . $errorInfo[0] . "</pre>";
                echo "<pre>Driver Code: " . $errorInfo[1] . "</pre>";
                echo "<pre>Message: " . $errorInfo[2] . "</pre>";
            }
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ PDO Exception: " . $e->getMessage() . "</p>";
            echo "<pre>Code: " . $e->getCode() . "</pre>";
        }
    } else {
        echo "<p style='color: red;'>❌ Cannot test task creation - no projects available</p>";
    }
    
    // Check table structure
    echo "<h3>Tasks Table Structure:</h3>";
    $stmt = $db->prepare("DESCRIBE tasks");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
table { margin: 10px 0; }
th { background: #f0f0f0; padding: 8px; }
td { padding: 8px; }
</style>