<?php
/**
 * API endpoint to submit quiz answers using normalized database tables
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
$attemptId = $input['attempt_id'] ?? null;
$answers = $input['answers'] ?? [];
$timeTaken = $input['time_taken'] ?? 0;
$userId = $_SESSION['user_id'];

if (!$lessonId || !$attemptId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $db = getDB();
    $quiz = new Quiz($db);
    
    // Get lesson and quiz data
    $stmt = $db->prepare("
        SELECT l.*, c.id as course_id, q.id as quiz_id
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
    
    $userObj = new User();
    if (!$enrollment && !$userObj->isAdmin($userId)) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        exit;
    }
    
    // Verify the attempt belongs to this user and is in progress
    $stmt = $db->prepare("
        SELECT * FROM quiz_attempts 
        WHERE id = ? AND user_id = ? AND quiz_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$attemptId, $userId, $quizId]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired quiz attempt']);
        exit;
    }
    
    // Get lesson progress ID if exists
    $stmt = $db->prepare("
        SELECT id FROM lesson_progress 
        WHERE user_id = ? AND lesson_id = ?
    ");
    $stmt->execute([$userId, $lessonId]);
    $progress = $stmt->fetch();
    $lessonProgressId = $progress ? $progress['id'] : null;
    
    // Update attempt with lesson progress ID if needed
    if ($lessonProgressId && !$attempt['lesson_progress_id']) {
        $stmt = $db->prepare("
            UPDATE quiz_attempts 
            SET lesson_progress_id = ? 
            WHERE id = ?
        ");
        $stmt->execute([$lessonProgressId, $attemptId]);
    }
    
    $db->beginTransaction();
    
    try {
        // Submit all responses
        foreach ($answers as $questionId => $answer) {
            if (is_array($answer)) {
                // Multiple choice with ID
                $quiz->submitResponse($attemptId, $questionId, [
                    'answer_option_id' => $answer['option_id'] ?? null,
                    'answer_text' => $answer['text'] ?? null,
                    'time_spent' => $answer['time_spent'] ?? 0
                ]);
            } else {
                // Simple answer (short answer or option ID)
                if (is_numeric($answer)) {
                    $quiz->submitResponse($attemptId, $questionId, [
                        'answer_option_id' => $answer,
                        'time_spent' => 0
                    ]);
                } else {
                    $quiz->submitResponse($attemptId, $questionId, [
                        'answer_text' => $answer,
                        'time_spent' => 0
                    ]);
                }
            }
        }
        
        // Complete and score the attempt
        $result = $quiz->completeAttempt($attemptId);
        
        if (!$result) {
            throw new Exception('Failed to complete quiz attempt');
        }
        
        // Update course enrollment progress if needed
        if ($result['passed']) {
            // Update course progress
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_lessons,
                       SUM(CASE WHEN lp.status = 'completed' THEN 1 ELSE 0 END) as completed_lessons
                FROM lessons l
                LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
                WHERE l.course_id = ? AND l.status = 'published'
            ");
            $stmt->execute([$userId, $lesson['course_id']]);
            $courseProgress = $stmt->fetch();
            
            $progressPercentage = $courseProgress['total_lessons'] > 0 
                ? round(($courseProgress['completed_lessons'] / $courseProgress['total_lessons']) * 100, 2) 
                : 0;
            
            $stmt = $db->prepare("
                UPDATE course_enrollments 
                SET progress_percentage = ?,
                    status = CASE WHEN ? >= 100 THEN 'completed' ELSE 'in_progress' END,
                    completion_date = CASE WHEN ? >= 100 AND completion_date IS NULL THEN NOW() ELSE completion_date END
                WHERE user_id = ? AND course_id = ?
            ");
            $stmt->execute([
                $progressPercentage,
                $progressPercentage,
                $progressPercentage,
                $userId,
                $lesson['course_id']
            ]);
        }
        
        // Log analytics event
        $stmt = $db->prepare("
            INSERT INTO learning_analytics 
            (user_id, course_id, lesson_id, action_type, action_data, created_at)
            VALUES (?, ?, ?, 'quiz_attempt', ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $lesson['course_id'],
            $lessonId,
            json_encode([
                'attempt_number' => $attempt['attempt_number'],
                'score' => $result['score'],
                'time_taken' => $timeTaken,
                'questions_answered' => count($answers),
                'points_earned' => $result['points_earned'],
                'total_points' => $result['total_points'],
                'passed' => $result['passed']
            ])
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz submitted successfully',
            'score' => $result['score'],
            'earned_points' => $result['points_earned'],
            'total_points' => $result['total_points'],
            'attempt_id' => $attemptId,
            'passed' => $result['passed']
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Quiz submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while submitting the quiz'
    ]);
}