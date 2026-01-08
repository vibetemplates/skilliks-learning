<?php
/**
 * HTMX Create Project Endpoint
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Project.php';

// Log that we received a request
error_log("create-project.php: Received " . $_SERVER['REQUEST_METHOD'] . " request");
error_log("POST data: " . print_r($_POST, true));

// Require login
requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$projectObj = new Project();

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$course_code = trim($_POST['course_code'] ?? '');
$git_repository_url = trim($_POST['git_repository_url'] ?? '');
$dev_system_url = trim($_POST['dev_system_url'] ?? '');
$project_category_id = (int)($_POST['project_category_id'] ?? 0);
$slug = trim($_POST['slug'] ?? '');
$visibility = trim($_POST['visibility'] ?? 'public');

// Generate slug from name if not provided
if (empty($slug) && !empty($name)) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    $slug = trim($slug, '-');
}

// Validation
if (empty($name)) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'Project name is required.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    exit;
}

if (empty($project_category_id)) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'Project category is required.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    exit;
}

// Check for duplicate slug if provided
if (!empty($slug)) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM projects WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
            echo 'URL slug already exists. Please choose a different slug.';
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            exit;
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
        echo 'Error checking slug uniqueness.';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        exit;
    }
}

$data = [
    'name' => $name,
    'description' => $description,
    'course_code' => $course_code,
    'team_size_limit' => 10,
    'git_repository_url' => $git_repository_url,
    'dev_system_url' => $dev_system_url,
    'project_category_id' => $project_category_id,
    'slug' => $slug,
    'visibility' => $visibility,
    'created_by' => getCurrentUserId()
];

try {
    $projectId = $projectObj->create($data);
    if ($projectId) {
        setFlashMessage('success', 'Project created successfully!');
        
        // Check if this is an HTMX request
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            // Use HX-Redirect for HTMX navigation
            header('HX-Redirect: /project-detail?id=' . $projectId);
        } else {
            // Regular redirect for standard form submission
            header('Location: /project-detail.php?id=' . $projectId);
        }
        exit;
    } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
        echo 'Failed to create project.';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'Failed to create project: ' . htmlspecialchars($e->getMessage());
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    error_log("Project creation exception: " . $e->getMessage());
}