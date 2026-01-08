<?php
/**
 * API endpoint to start a quiz attempt
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Quiz.php';
require_once '../classes/User.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$lessonId = $input['lesson_id'] ?? null;
$userId = $_SESSION['user_id'];

if (!$lessonId) {
    echo json_encode(['success' => false, 'message' => 'Missing lesson ID']);
    exit;
}

try {
    $db = getDB();
    $quiz = new Quiz($db);
    $userObj = new User();
    
    // Get lesson and quiz data
    $stmt = $db->prepare("
        SELECT l.*, c.id as course_id, q.id as quiz_id, q.max_attempts, q.time_limit_minutes
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        LEFT JOIN quizzes q ON l.id = q.lesson_id
        WHERE l.id = ?
    ");
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch();
    
    if (!$lesson || !$lesson['quiz_id']) {
        echo json_encode(['success' => false, 'message' => 'Quiz not found']);
        exit;
    }
    
    $quizId = $lesson['quiz_id'];
    
    // Verify enrollment
    $stmt = $db->prepare("
        SELECT * FROM course_enrollments 
        WHERE user_id = ? AND course_id = ? AND status IN ('enrolled', 'in_progress')
    ");
    $stmt->execute([$userId, $lesson['course_id']]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment && !$userObj->isAdmin($userId)) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        exit;
    }
    
    // Check if user can take the quiz
    if (!$quiz->canTakeQuiz($quizId, $userId)) {
        echo json_encode(['success' => false, 'message' => 'Maximum attempts reached for this quiz']);
        exit;
    }
    
    // Check for existing in-progress attempt
    $stmt = $db->prepare("
        SELECT * FROM quiz_attempts 
        WHERE quiz_id = ? AND user_id = ? AND status = 'in_progress'
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$quizId, $userId]);
    $existingAttempt = $stmt->fetch();
    
    if ($existingAttempt) {
        // Check if attempt has timed out
        if ($lesson['time_limit_minutes']) {
            $elapsedSeconds = time() - strtotime($existingAttempt['start_time']);
            $elapsedMinutes = $elapsedSeconds / 60;
            
            if ($elapsedMinutes > $lesson['time_limit_minutes']) {
                // Mark as timed out
                $stmt = $db->prepare("
                    UPDATE quiz_attempts
                    SET status = 'timed_out',
                        end_time = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$existingAttempt['id']]);
            } else {
                // Return existing attempt
                echo json_encode([
                    'success' => true,
                    'attempt_id' => $existingAttempt['id'],
                    'attempt_number' => $existingAttempt['attempt_number'],
                    'time_elapsed' => $elapsedSeconds,
                    'time_limit' => $lesson['time_limit_minutes'] ? $lesson['time_limit_minutes'] * 60 : null,
                    'resumed' => true
                ]);
                exit;
            }
        } else {
            // No time limit, return existing attempt
            echo json_encode([
                'success' => true,
                'attempt_id' => $existingAttempt['id'],
                'attempt_number' => $existingAttempt['attempt_number'],
                'time_elapsed' => time() - strtotime($existingAttempt['start_time']),
                'time_limit' => null,
                'resumed' => true
            ]);
            exit;
        }
    }
    
    // Get or create lesson progress record
    $stmt = $db->prepare("
        SELECT id FROM lesson_progress 
        WHERE user_id = ? AND lesson_id = ?
    ");
    $stmt->execute([$userId, $lessonId]);
    $progress = $stmt->fetch();
    
    if (!$progress) {
        // Create lesson progress record
        $stmt = $db->prepare("
            INSERT INTO lesson_progress 
            (user_id, lesson_id, course_id, status, started_at)
            VALUES (?, ?, ?, 'in_progress', NOW())
        ");
        $stmt->execute([$userId, $lessonId, $lesson['course_id']]);
        $lessonProgressId = $db->lastInsertId();
    } else {
        $lessonProgressId = $progress['id'];
        
        // Update status if needed
        $stmt = $db->prepare("
            UPDATE lesson_progress 
            SET status = 'in_progress' 
            WHERE id = ? AND status = 'not_started'
        ");
        $stmt->execute([$lessonProgressId]);
    }
    
    // Start new quiz attempt
    $attemptId = $quiz->startAttempt($quizId, $userId, $lessonProgressId);
    
    if (!$attemptId) {
        echo json_encode(['success' => false, 'message' => 'Failed to start quiz attempt']);
        exit;
    }
    
    // Get attempt details
    $stmt = $db->prepare("
        SELECT * FROM quiz_attempts WHERE id = ?
    ");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'attempt_id' => $attemptId,
        'attempt_number' => $attempt['attempt_number'],
        'time_elapsed' => 0,
        'time_limit' => $lesson['time_limit_minutes'] ? $lesson['time_limit_minutes'] * 60 : null,
        'resumed' => false
    ]);
    
} catch (Exception $e) {
    error_log("Quiz start error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while starting the quiz'
    ]);
}