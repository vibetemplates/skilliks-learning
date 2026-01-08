<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';
require_once 'classes/ProjectCategory.php';

requireLogin();

$searchTerm = trim($_GET['q'] ?? '');

// If search term is empty, we'll show all active projects
if (strlen($searchTerm) > 0 && strlen($searchTerm) < 3) {
    header('Location: /project-categories.php');
    exit;
}

$page_title = empty($searchTerm) ? 'All Active Projects' : 'Search Results - ' . htmlspecialchars($searchTerm);

// Debug mode - temporarily show all projects to verify data exists
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';

try {
    $db = getDB();
    
    if ($debugMode) {
        // First, let's see if there are ANY projects at all
        $checkSql = "SELECT id, name, status, description FROM projects LIMIT 10";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute();
        $allProjects = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<!-- DEBUG: Total projects in database (first 10): " . json_encode($allProjects) . " -->\n";
        
        // Check specifically for projects with 'gemini' in the name
        $geminiSql = "SELECT id, name, status FROM projects WHERE name LIKE '%gemini%' OR name LIKE '%Gemini%' OR name LIKE '%GEMINI%'";
        $geminiStmt = $db->prepare($geminiSql);
        $geminiStmt->execute();
        $geminiProjects = $geminiStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<!-- DEBUG: Projects with 'gemini' in name: " . json_encode($geminiProjects) . " -->\n";
    }
    
    // Build query based on whether we have a search term
    if (empty($searchTerm)) {
        // Show all active projects
        $sql = "
            SELECT DISTINCT p.id, p.name, p.description, p.status, p.thumbnail_url,
                   pc.name as category_name, pc.id as category_id,
                   COUNT(DISTINCT pm.user_id) as member_count,
                   u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM projects p
            LEFT JOIN project_categories pc ON p.project_category_id = pc.id
            LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY p.name ASC
            LIMIT 100
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
    } else {
        // Search projects by name and skills
        $sql = "
            SELECT DISTINCT p.id, p.name, p.description, p.status, p.thumbnail_url,
                   pc.name as category_name, pc.id as category_id,
                   COUNT(DISTINCT pm.user_id) as member_count,
                   u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM projects p
            LEFT JOIN project_categories pc ON p.project_category_id = pc.id
            LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.status = 'approved'
            LEFT JOIN users u ON p.created_by = u.id
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
        
        $stmt = $db->prepare($sql);
        $searchParam = '%' . $searchTerm . '%';
        
        if ($debugMode) {
            echo "<!-- DEBUG: Search param: " . htmlspecialchars($searchParam) . " -->\n";
        }
        
        // Bind the same value to multiple parameters
        $stmt->bindParam(':search1', $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(':search2', $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(':search3', $searchParam, PDO::PARAM_STR);
        $stmt->execute();
    }
    
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($debugMode) {
        echo "<!-- DEBUG: Found " . count($projects) . " projects -->\n";
    }
    
    // For each project, get skills
    foreach ($projects as &$project) {
        // Get all skills for this project
        $skillSql = "
            SELECT s.name, s.category, ps.importance_level
            FROM project_skills ps
            JOIN skills s ON ps.skill_id = s.id
            WHERE ps.project_id = :project_id
            ORDER BY ps.importance_level DESC, s.name ASC
        ";
        
        $skillStmt = $db->prepare($skillSql);
        $skillStmt->bindParam(':project_id', $project['id'], PDO::PARAM_INT);
        $skillStmt->execute();
        
        $project['skills'] = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check which skills match the search term
        $project['matching_skills'] = [];
        foreach ($project['skills'] as $skill) {
            if (stripos($skill['name'], $searchTerm) !== false) {
                $project['matching_skills'][] = $skill['name'];
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Project search error: ' . $e->getMessage());
    $projects = [];
}

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Search Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 id="search-results-heading"><?php echo empty($searchTerm) ? 'All Active Projects' : 'Search Results'; ?></h1>
                    <?php if (!empty($searchTerm)): ?>
                        <p class="text-muted mb-0">
                            Searching for: <strong><?php echo htmlspecialchars($searchTerm); ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Showing all active projects
                        </p>
                    <?php endif; ?>
                    <p class="text-muted">
                        Found <?php echo count($projects); ?> project<?php echo count($projects) !== 1 ? 's' : ''; ?>
                    </p>
                </div>
                <div>
                    <a href="/project-categories.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Categories
                    </a>
                </div>
            </div>
            
            <!-- Search Form -->
            <div class="card mb-4" id="search-card">
                <div class="card-body">
                    <form action="/search-results.php" method="GET" id="searchForm">
                        <div class="row">
                            <div class="col-md-10">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           name="q"
                                           id="searchInput" 
                                           placeholder="Search projects by name or skills... (leave blank to show all)" 
                                           value="<?php echo htmlspecialchars($searchTerm); ?>"
                                           autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100" id="searchBtn">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Results -->
            <?php if (empty($projects)): ?>
                <div class="alert alert-info" id="no-results-alert">
                    <i class="bi bi-info-circle"></i> No projects found matching your search criteria.
                    <br><br>
                    <strong>Search tips:</strong>
                    <ul class="mb-0">
                        <li>Try different keywords</li>
                        <li>Search for specific skills like "Python", "JavaScript", etc.</li>
                        <li>Use partial words to find more matches</li>
                    </ul>
                    <?php if ($debugMode): ?>
                        <hr>
                        <strong>Debug Mode:</strong> Add &debug=1 to URL to see debug info in HTML comments.
                        <br>View page source to see debug output.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row" id="search-results-grid">
                    <?php foreach ($projects as $project): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm project-result-card" id="project-<?php echo $project['id']; ?>">
                                <?php if ($project['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($project['thumbnail_url']); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($project['name']); ?>"
                                         style="height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px;">
                                        <i class="bi bi-diagram-3 text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($project['name']); ?></h5>
                                    
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-folder"></i> 
                                        <a href="/projects.php?category=<?php echo $project['category_id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($project['category_name']); ?>
                                        </a>
                                    </p>
                                    
                                    <?php if ($project['description']): ?>
                                        <p class="card-text text-truncate">
                                            <?php echo htmlspecialchars($project['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($project['matching_skills'])): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Matching Skills:</small><br>
                                            <?php foreach ($project['matching_skills'] as $skill): ?>
                                                <span class="badge bg-warning text-dark me-1">
                                                    <?php echo htmlspecialchars($skill); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($project['skills']) && count($project['skills']) > count($project['matching_skills'])): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">Other Skills:</small><br>
                                            <?php 
                                            $otherSkills = array_slice($project['skills'], 0, 5);
                                            foreach ($otherSkills as $skill): 
                                                if (!in_array($skill['name'], $project['matching_skills'])): 
                                            ?>
                                                <span class="badge bg-secondary me-1">
                                                    <?php echo htmlspecialchars($skill['name']); ?>
                                                </span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                            <?php if (count($project['skills']) > 5): ?>
                                                <span class="text-muted">+<?php echo count($project['skills']) - 5; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div>
                                            <span class="badge bg-secondary">
                                                <?php echo $project['member_count']; ?> 
                                                member<?php echo $project['member_count'] !== 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                        <a href="/project-detail?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            View Project <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>