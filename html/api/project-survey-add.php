<?php
/**
 * API endpoint to add a survey to a project
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Project.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['project_id']) || !isset($input['survey_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID and Survey ID are required']);
    exit;
}

$projectId = (int)$input['project_id'];
$surveyId = (int)$input['survey_id'];
$currentUserId = $_SESSION['user_id'];

// Check if user has permission (admin or project creator)
$userObj = new User();
$projectObj = new Project();

$project = $projectObj->findById($projectId);
if (!$project) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}

$isAdmin = $userObj->isAdmin($currentUserId);
$isCreator = $project['created_by'] == $currentUserId;

if (!$isAdmin && !$isCreator) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this project']);
    exit;
}

// Check if survey exists and is active
$db = getDB();
$stmt = $db->prepare("SELECT id FROM surveys WHERE id = ? AND type = 'project' AND is_active = 1");
$stmt->execute([$surveyId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Survey not found or not active']);
    exit;
}

// Check if this survey is already added to the project
$stmt = $db->prepare("SELECT id FROM project_surveys WHERE project_id = ? AND survey_id = ?");
$stmt->execute([$projectId, $surveyId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'This survey is already added to the project']);
    exit;
}

// Add the survey to the project
try {
    $stmt = $db->prepare("INSERT INTO project_surveys (project_id, survey_id) VALUES (?, ?)");
    $stmt->execute([$projectId, $surveyId]);
    
    echo json_encode(['success' => true, 'message' => 'Survey added to project successfully']);
} catch (PDOException $e) {
    error_log("Failed to add survey to project: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add survey to project']);
}
?>