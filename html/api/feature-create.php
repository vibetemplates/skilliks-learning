<?php
/**
 * Feature Creation API
 * 
 * Handles feature recommendation creation for projects
 */

require_once dirname(dirname(__FILE__)) . '/includes/session.php';
require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(dirname(__FILE__)) . '/config/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$currentUserId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        $projectId = (int)($_POST['project_id'] ?? 0);
        
        // Validate input
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Feature title is required.']);
            exit;
        }
        
        if (empty($description)) {
            echo json_encode(['success' => false, 'error' => 'Feature description is required.']);
            exit;
        }
        
        if ($projectId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid project ID.']);
            exit;
        }
        
        if (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
            $priority = 'medium';
        }
        
        // Verify project exists and get its community_id
        $db = getDB();
        $stmt = $db->prepare("SELECT id, community_id FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
        if (!$project) {
            echo json_encode(['success' => false, 'error' => 'Project not found.']);
            exit;
        }
        $communityId = $project['community_id'];
        
        // Ensure community_id is valid
        if (empty($communityId) || $communityId == 0) {
            // Use default community if none set
            $stmt = $db->prepare("SELECT id FROM communities WHERE slug = 'default' LIMIT 1");
            $stmt->execute();
            $defaultCommunity = $stmt->fetch();
            $communityId = $defaultCommunity ? $defaultCommunity['id'] : 1;
        }
        
        // Log values for debugging
        error_log("Feature creation - Project ID: $projectId, Community ID: $communityId");
        
        // Create feature
        $stmt = $db->prepare("
            INSERT INTO features (community_id, title, description, priority, project_id, submitted_by, status, category) 
            VALUES (?, ?, ?, ?, ?, ?, 'proposed', 'feature')
        ");
        
        $result = $stmt->execute([
            $communityId,
            $title,
            $description,
            $priority,
            $projectId,
            $currentUserId
        ]);
        
        if ($result) {
            $featureId = $db->lastInsertId();
            echo json_encode([
                'success' => true,
                'feature_id' => $featureId,
                'message' => 'Feature recommendation submitted successfully!'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create feature recommendation.']);
        }
        
    } catch (PDOException $e) {
        error_log("Feature creation error: " . $e->getMessage());
        
        // Check if it's a foreign key constraint error
        if (strpos($e->getMessage(), '1452') !== false) {
            echo json_encode(['success' => false, 'error' => 'Invalid community reference. Please try again or contact support.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error submitting feature. Please try again.']);
        }
    } catch (Exception $e) {
        error_log("Feature creation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred while creating the feature recommendation.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>