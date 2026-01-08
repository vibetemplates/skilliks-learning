<?php
/**
 * API endpoint to reorder lessons
 */

header('Content-Type: application/json');

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Administrator privileges required.']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get the posted data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['course_id']) || !isset($input['lesson_orders'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$courseId = (int)$input['course_id'];
$lessonOrders = $input['lesson_orders'];

if (!$courseId || !is_array($lessonOrders)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid course ID or lesson orders']);
    exit;
}

try {
    $db = getDB();
    
    // Verify the course exists
    $stmt = $db->prepare("SELECT id FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Course not found']);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Update each lesson's order_index
    $updateStmt = $db->prepare("UPDATE lessons SET order_index = ?, updated_at = NOW() WHERE id = ? AND course_id = ?");
    
    foreach ($lessonOrders as $order => $lessonId) {
        $newOrderIndex = $order + 1; // Convert 0-based index to 1-based
        $lessonId = (int)$lessonId;
        
        if ($lessonId <= 0) {
            throw new Exception('Invalid lesson ID');
        }
        
        $result = $updateStmt->execute([$newOrderIndex, $lessonId, $courseId]);
        if (!$result) {
            throw new Exception('Failed to update lesson order');
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Lesson order updated successfully',
        'updated_count' => count($lessonOrders)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    error_log("Lesson reorder error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update lesson order: ' . $e->getMessage()
    ]);
}
?>