<?php
/**
 * API endpoint to save quiz data using normalized database tables
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/User.php';
require_once '../classes/Quiz.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is an admin
$userObj = new User();
$userId = getCurrentUserId();
if (!isLoggedIn() || !$userObj->isAdmin($userId)) {
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
$quizData = $input['quiz_data'] ?? null;

if (!$lessonId || !$quizData) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db = getDB();
    $quiz = new Quiz($db);
    
    // Verify lesson exists and user has permission
    $stmt = $db->prepare("
        SELECT l.*, c.community_id 
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
        exit;
    }
    
    // Validate quiz data structure
    if (!isset($quizData['title']) || !isset($quizData['questions']) || !is_array($quizData['questions'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid quiz data structure']);
        exit;
    }
    
    $db->beginTransaction();
    
    try {
        // Check if quiz already exists for this lesson
        $existingQuiz = $quiz->getByLessonId($lessonId);
        
        if ($existingQuiz) {
            // Update existing quiz
            $quizId = $existingQuiz['id'];
            $quiz->update($quizId, [
                'title' => $quizData['title'],
                'description' => $quizData['description'] ?? null,
                'instructions' => $quizData['instructions'] ?? null,
                'passing_score' => $quizData['passing_score'] ?? 70,
                'max_attempts' => $quizData['max_attempts'] ?? 3,
                'time_limit_minutes' => $quizData['time_limit'] ?? null,
                'shuffle_questions' => $quizData['shuffle_questions'] ?? 0,
                'shuffle_answers' => $quizData['shuffle_answers'] ?? 0,
                'show_correct_answers' => $quizData['show_correct_answers'] ?? 1,
                'show_score_immediately' => $quizData['show_score_immediately'] ?? 1,
                'allow_review' => $quizData['allow_review'] ?? 1
            ]);
            
            // Delete existing questions (they will be recreated)
            $stmt = $db->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
        } else {
            // Create new quiz
            $quizId = $quiz->create($lessonId, [
                'title' => $quizData['title'],
                'description' => $quizData['description'] ?? null,
                'instructions' => $quizData['instructions'] ?? null,
                'passing_score' => $quizData['passing_score'] ?? 70,
                'max_attempts' => $quizData['max_attempts'] ?? 3,
                'time_limit' => $quizData['time_limit'] ?? null,
                'shuffle_questions' => $quizData['shuffle_questions'] ?? 0,
                'shuffle_answers' => $quizData['shuffle_answers'] ?? 0,
                'show_correct_answers' => $quizData['show_correct_answers'] ?? 1,
                'show_score_immediately' => $quizData['show_score_immediately'] ?? 1,
                'allow_review' => $quizData['allow_review'] ?? 1
            ]);
        }
        
        if (!$quizId) {
            throw new Exception('Failed to create/update quiz');
        }
        
        // Add questions
        foreach ($quizData['questions'] as $index => $questionData) {
            $questionId = $quiz->addQuestion($quizId, [
                'question_text' => $questionData['text'] ?? $questionData['question'],
                'question_type' => $questionData['type'] ?? 'multiple_choice',
                'explanation' => $questionData['explanation'] ?? null,
                'hint' => $questionData['hint'] ?? null,
                'points' => $questionData['points'] ?? 1,
                'order_index' => $index,
                'is_required' => 1
            ]);
            
            if (!$questionId) {
                throw new Exception('Failed to add question');
            }
            
            // Add answers based on question type
            if ($questionData['type'] === 'multiple_choice' && isset($questionData['options'])) {
                foreach ($questionData['options'] as $optIndex => $option) {
                    $quiz->addAnswerOption($questionId, [
                        'answer_text' => $option,
                        'is_correct' => ($questionData['correct_answer'] == $optIndex) ? 1 : 0,
                        'order_index' => $optIndex
                    ]);
                }
            } elseif ($questionData['type'] === 'true_false') {
                $correctAnswer = $questionData['correct_answer'] ?? 0;
                $quiz->addAnswerOption($questionId, [
                    'answer_text' => 'True',
                    'is_correct' => ($correctAnswer == 0) ? 1 : 0,
                    'order_index' => 0
                ]);
                $quiz->addAnswerOption($questionId, [
                    'answer_text' => 'False',
                    'is_correct' => ($correctAnswer == 1) ? 1 : 0,
                    'order_index' => 1
                ]);
            }
        }
        
        // Update lesson type to quiz
        $stmt = $db->prepare("
            UPDATE lessons 
            SET lesson_type = 'quiz',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$lessonId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz saved successfully',
            'quiz_id' => $quizId,
            'quiz_data' => $quizData
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Quiz save error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving the quiz: ' . $e->getMessage()
    ]);
}