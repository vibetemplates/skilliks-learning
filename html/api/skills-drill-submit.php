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
    $rawInput = file_get_contents('php://input');
    error_log('Skills drill submit raw input: ' . $rawInput);
    
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($input['session_id']) || !isset($input['question_id']) || !isset($input['answer_option_id'])) {
        throw new Exception('Missing required parameters');
    }
    
    $sessionId = intval($input['session_id']);
    $questionId = intval($input['question_id']);
    $answerOptionId = intval($input['answer_option_id']);
    
    // Verify session belongs to user
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM skills_drill_sessions WHERE id = ? AND user_id = ? AND status = 'in_progress'");
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Invalid or expired session');
    }
    
    // Submit answer
    $skillsDrill = new SkillsDrill();
    $result = $skillsDrill->submitAnswer($sessionId, $questionId, $answerOptionId);
    
    error_log('Skills drill submit result: ' . json_encode($result));
    
    if ($result === false) {
        throw new Exception('Failed to submit answer');
    }
    
    $response = [
        'success' => true,
        'attempt_number' => $result['attempt_number'],
        'is_correct' => $result['is_correct'],
        'points_earned' => $result['points_earned']
    ];
    
    error_log('Skills drill API response: ' . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Skills drill submit error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}