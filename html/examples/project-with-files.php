<?php
/**
 * Example: Project Detail Page with File Management
 * 
 * This shows how to integrate the file manager component into a project detail page
 */

$page_title = 'Project Details';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/Project.php';

// Require login
requireLogin();

$projectId = (int)($_GET['id'] ?? 0);
if ($projectId <= 0) {
    header('Location: /projects.php');
    exit;
}

$projectObj = new Project();
$project = $projectObj->findById($projectId);

if (!$project) {
    header('Location: /projects.php');
    exit;
}

require_once 'includes/header.php';
?>

<div class="page-wrapper">
    <div class="main-container">
        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/courses.php">
                            <i class="bi bi-book"></i> Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/projects.php">
                            <i class="bi bi-folder"></i> Projects
                        </a>
                    </li>
                    <!-- ... other navigation items ... -->
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="content-wrapper px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo htmlspecialchars($project['name']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/projects.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Projects
                    </a>
                </div>
            </div>

            <!-- Project Information -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Project Details</h5>
                        </div>
                        <div class="card-body">
                            <p><?php echo htmlspecialchars($project['description'] ?? 'No description available'); ?></p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Course:</strong> <?php echo htmlspecialchars($project['course_code'] ?? 'N/A'); ?><br>
                                    <strong>Status:</strong> <span class="badge bg-primary"><?php echo ucfirst($project['status']); ?></span><br>
                                    <strong>Created:</strong> <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Team Size:</strong> <?php echo $project['team_size_limit']; ?> members<br>
                                    <strong>GitHub:</strong> 
                                    <?php if ($project['github_repo_url']): ?>
                                        <a href="<?php echo htmlspecialchars($project['github_repo_url']); ?>" target="_blank">View Repository</a>
                                    <?php else: ?>
                                        Not specified
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Project Stats</h5>
                        </div>
                        <div class="card-body">
                            <!-- Project stats here -->
                            <p class="text-muted">Quick project statistics and information.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Management Section -->
            <?php
            // Set variables for file manager component
            $entityType = 'project';
            $entityId = $project['id'];
            
            // Include the file manager component
            include 'includes/file-manager-component.php';
            ?>

        </main>
    </div>
</div>

<!-- Include file manager JavaScript -->
<script src="/assets/js/file-manager.js"></script>

<?php require_once 'includes/footer.php'; ?>