<?php
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/SkillsDrill.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['session_id'])) {
        throw new Exception('Missing session ID');
    }
    
    $sessionId = intval($input['session_id']);
    
    // Verify session belongs to user
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM skills_drill_sessions WHERE id = ? AND user_id = ? AND status = 'in_progress'");
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Invalid or expired session');
    }
    
    // Complete session
    $skillsDrill = new SkillsDrill();
    $result = $skillsDrill->completeSession($sessionId);
    
    if (!$result) {
        throw new Exception('Failed to complete session');
    }
    
    // Clear session ID from PHP session
    unset($_SESSION['skills_drill_session_id']);
    
    echo json_encode([
        'success' => true,
        'session_id' => $sessionId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}