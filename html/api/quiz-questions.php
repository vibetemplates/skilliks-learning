<?php
/**
 * API endpoint to load quiz questions and answers for a lesson
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Quiz.php';
require_once '../classes/Course.php';
require_once '../classes/User.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get lesson ID from query parameter
$lessonId = $_GET['lesson_id'] ?? null;

if (!$lessonId) {
    echo json_encode(['success' => false, 'message' => 'Missing lesson ID']);
    exit;
}

try {
    $db = getDB();
    $userId = $_SESSION['user_id'];
    $communityId = getCurrentCommunityId();
    
    // Verify lesson exists and user has access
    $stmt = $db->prepare("
        SELECT l.*, c.id as course_id, c.title as course_title, c.community_id
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.id = ? AND c.community_id = ?
    ");
    $stmt->execute([$lessonId, $communityId]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
        exit;
    }
    
    // Check if user is enrolled in the course
    $courseClass = new Course($db, $communityId);
    $userObj = new User();
    if (!$courseClass->isEnrolled($lesson['course_id'], $userId) && !$userObj->isAdmin($userId)) {
        echo json_encode(['success' => false, 'message' => 'You must be enrolled in this course to access the quiz']);
        exit;
    }
    
    // Get quiz for this lesson
    $quiz = new Quiz($db);
    $quizData = $quiz->getByLessonId($lessonId);
    
    if (!$quizData) {
        echo json_encode(['success' => false, 'message' => 'No quiz found for this lesson']);
        exit;
    }
    
    // Check if user can take the quiz
    if (!$quiz->canTakeQuiz($quizData['id'], $userId)) {
        // Get user's attempts to show limit message
        $attempts = $quiz->getUserAttempts($quizData['id'], $userId);
        echo json_encode([
            'success' => false, 
            'message' => 'You have reached the maximum number of attempts for this quiz',
            'max_attempts' => $quizData['max_attempts'],
            'attempts_used' => count($attempts)
        ]);
        exit;
    }
    
    // Get quiz questions with shuffling if enabled
    $questions = $quiz->getQuestions($quizData['id'], $quizData['shuffle_questions']);
    
    // Process questions for client
    $processedQuestions = [];
    foreach ($questions as $question) {
        $processedQuestion = [
            'id' => $question['id'],
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'points' => $question['points'],
            'hint' => $question['hint'],
            'answers' => []
        ];
        
        // Shuffle answers if enabled
        $answers = $question['answers'];
        if ($quizData['shuffle_answers'] && $question['question_type'] === 'multiple_choice') {
            shuffle($answers);
        }
        
        // Add answers without revealing which is correct
        foreach ($answers as $answer) {
            $processedQuestion['answers'][] = [
                'id' => $answer['id'],
                'text' => $answer['answer_text']
            ];
        }
        
        $processedQuestions[] = $processedQuestion;
    }
    
    // Get user's previous attempts
    $attempts = $quiz->getUserAttempts($quizData['id'], $userId);
    
    // Prepare response
    $response = [
        'success' => true,
        'quiz' => [
            'id' => $quizData['id'],
            'title' => $quizData['title'],
            'description' => $quizData['description'],
            'instructions' => $quizData['instructions'],
            'time_limit_minutes' => $quizData['time_limit_minutes'],
            'passing_score' => $quizData['passing_score'],
            'max_attempts' => $quizData['max_attempts'],
            'show_score_immediately' => $quizData['show_score_immediately'],
            'allow_review' => $quizData['allow_review'],
            'total_points' => $quizData['total_points']
        ],
        'questions' => $processedQuestions,
        'lesson' => [
            'id' => $lesson['id'],
            'title' => $lesson['title'],
            'course_id' => $lesson['course_id'],
            'course_title' => $lesson['course_title']
        ],
        'attempts_info' => [
            'attempts_used' => count($attempts),
            'max_attempts' => $quizData['max_attempts'] ?: 'Unlimited',
            'previous_attempts' => array_map(function($attempt) {
                return [
                    'attempt_number' => $attempt['attempt_number'],
                    'score' => $attempt['score_achieved'],
                    'passed' => $attempt['passed'],
                    'date' => $attempt['start_time']
                ];
            }, $attempts)
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Quiz questions API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading the quiz'
    ]);
}