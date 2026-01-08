<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Task.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();

echo "<h2>Task Creation Fix Test</h2>";

try {
    $db = getDB();
    $taskObj = new Task();
    
    // Test data with empty due_date (this was causing the issue)
    $testData = [
        'title' => 'Test Task Fix',
        'description' => 'Testing the due_date fix',
        'project_id' => 1, // Assuming project ID 1 exists
        'created_by' => $currentUserId,
        'type' => 'task',
        'priority' => 'medium',
        'due_date' => '' // Empty string that should be converted to null
    ];
    
    echo "<h3>Testing with empty due_date:</h3>";
    echo "<pre>" . print_r($testData, true) . "</pre>";
    
    $taskId = $taskObj->create($testData);
    
    if ($taskId) {
        echo "<p style='color: green;'>✓ Task created successfully! ID: $taskId</p>";
        
        // Verify the task was created correctly
        $task = $taskObj->findById($taskId);
        echo "<h3>Created Task Details:</h3>";
        echo "<pre>" . print_r($task, true) . "</pre>";
        
        // Clean up - delete the test task
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        echo "<p>✓ Test task cleaned up</p>";
    } else {
        echo "<p style='color: red;'>❌ Task creation failed</p>";
        echo "<p>Error: " . ($taskObj->lastError ?? 'Unknown error') . "</p>";
    }
    
    // Test with valid date
    $testData2 = [
        'title' => 'Test Task Fix 2',
        'description' => 'Testing with valid date',
        'project_id' => 1,
        'created_by' => $currentUserId,
        'type' => 'task',
        'priority' => 'medium',
        'due_date' => '2025-12-31'
    ];
    
    echo "<h3>Testing with valid due_date:</h3>";
    echo "<pre>" . print_r($testData2, true) . "</pre>";
    
    $taskId2 = $taskObj->create($testData2);
    
    if ($taskId2) {
        echo "<p style='color: green;'>✓ Task created successfully! ID: $taskId2</p>";
        
        // Verify the task was created correctly
        $task2 = $taskObj->findById($taskId2);
        echo "<h3>Created Task Details:</h3>";
        echo "<pre>" . print_r($task2, true) . "</pre>";
        
        // Clean up - delete the test task
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId2]);
        echo "<p>✓ Test task cleaned up</p>";
    } else {
        echo "<p style='color: red;'>❌ Task creation failed</p>";
        echo "<p>Error: " . ($taskObj->lastError ?? 'Unknown error') . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>