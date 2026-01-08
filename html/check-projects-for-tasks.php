<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Check existing projects
    $stmt = $db->prepare("SELECT id, name, description FROM projects ORDER BY id");
    $stmt->execute();
    $projects = $stmt->fetchAll();
    
    echo "<h2>Existing Projects:</h2>";
    if (empty($projects)) {
        echo "<p style='color: red;'>❌ No projects found - this is likely the issue!</p>";
        echo "<p>You need to create a project first before creating tasks.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Description</th></tr>";
        foreach ($projects as $project) {
            echo "<tr>";
            echo "<td>" . $project['id'] . "</td>";
            echo "<td>" . htmlspecialchars($project['name']) . "</td>";
            echo "<td>" . htmlspecialchars($project['description'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check if there are any tasks
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tasks");
    $stmt->execute();
    $taskCount = $stmt->fetch()['count'];
    
    echo "<h2>Existing Tasks:</h2>";
    echo "<p>Total tasks: $taskCount</p>";
    
    // Check for a default/personal project
    $stmt = $db->prepare("SELECT id, name FROM projects WHERE name LIKE '%personal%' OR name LIKE '%default%' OR name LIKE '%general%'");
    $stmt->execute();
    $defaultProjects = $stmt->fetchAll();
    
    if (!empty($defaultProjects)) {
        echo "<h2>Potential Default Projects:</h2>";
        foreach ($defaultProjects as $project) {
            echo "<p>ID: " . $project['id'] . " - " . htmlspecialchars($project['name']) . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
table { margin: 10px 0; }
th { background: #f0f0f0; padding: 8px; }
td { padding: 8px; }
</style>