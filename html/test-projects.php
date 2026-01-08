<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// Require login
requireLogin();

// Only allow admins to view this test page
if (!isCurrentUserAdmin()) {
    header('Location: /dashboard');
    exit;
}

$db = getDB();

// Test 1: Count all projects
$stmt = $db->query("SELECT COUNT(*) as total FROM projects");
$totalProjects = $stmt->fetch()['total'];

// Test 2: Get first 10 projects
$stmt = $db->query("SELECT id, name, status, description FROM projects LIMIT 10");
$first10Projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Test 3: Search for 'gemini' case-insensitive
$stmt = $db->prepare("SELECT id, name, status FROM projects WHERE LOWER(name) LIKE LOWER('%gemini%')");
$stmt->execute();
$geminiProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Test 4: Get all project statuses
$stmt = $db->query("SELECT DISTINCT status FROM projects");
$statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Test 5: Check active and pending projects
$stmt = $db->query("SELECT COUNT(*) as total FROM projects WHERE status IN ('active', 'pending')");
$activeAndPending = $stmt->fetch()['total'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Projects Database</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin: 30px 0; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Project Database Test</h1>
    
    <div class="section">
        <h2>Summary</h2>
        <ul>
            <li>Total projects in database: <strong><?php echo $totalProjects; ?></strong></li>
            <li>Active and pending projects: <strong><?php echo $activeAndPending; ?></strong></li>
            <li>Projects with 'gemini' in name: <strong><?php echo count($geminiProjects); ?></strong></li>
            <li>Distinct statuses: <strong><?php echo implode(', ', $statuses); ?></strong></li>
        </ul>
    </div>
    
    <div class="section">
        <h2>First 10 Projects</h2>
        <?php if (empty($first10Projects)): ?>
            <p>No projects found in database!</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Description</th>
                </tr>
                <?php foreach ($first10Projects as $project): ?>
                    <tr>
                        <td><?php echo $project['id']; ?></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo $project['status']; ?></td>
                        <td><?php echo htmlspecialchars(substr($project['description'] ?? '', 0, 100)); ?>...</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Projects with 'gemini' in name</h2>
        <?php if (empty($geminiProjects)): ?>
            <p>No projects found with 'gemini' in the name!</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($geminiProjects as $project): ?>
                    <tr>
                        <td><?php echo $project['id']; ?></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo $project['status']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Raw SQL Test</h2>
        <p>Testing the exact query from search-results.php:</p>
        <?php
        $searchTerm = 'gemini';
        $sql = "
            SELECT DISTINCT p.id, p.name, p.description, p.status, p.thumbnail_url,
                   pc.name as category_name, pc.id as category_id,
                   COUNT(DISTINCT pm.user_id) as member_count
            FROM projects p
            LEFT JOIN project_categories pc ON p.project_category_id = pc.id
            LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
            WHERE (p.status = 'active' OR p.status = 'pending')
            AND (
                p.name LIKE :search1 COLLATE utf8mb4_general_ci OR
                p.description LIKE :search2 COLLATE utf8mb4_general_ci OR
                p.id IN (
                    SELECT DISTINCT ps.project_id 
                    FROM project_skills ps
                    JOIN skills s ON ps.skill_id = s.id
                    WHERE s.name LIKE :search3 COLLATE utf8mb4_general_ci
                )
            )
            GROUP BY p.id
            ORDER BY p.name ASC
            LIMIT 100
        ";
        
        try {
            $stmt = $db->prepare($sql);
            $searchParam = '%' . $searchTerm . '%';
            $stmt->bindParam(':search1', $searchParam, PDO::PARAM_STR);
            $stmt->bindParam(':search2', $searchParam, PDO::PARAM_STR);
            $stmt->bindParam(':search3', $searchParam, PDO::PARAM_STR);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p>Search parameter: " . htmlspecialchars($searchParam) . "</p>";
            echo "<p>Results found: " . count($results) . "</p>";
            
            if (!empty($results)) {
                echo "<pre>" . htmlspecialchars(print_r($results, true)) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <a href="/project-categories.php">Back to Project Categories</a>
    </div>
</body>
</html>