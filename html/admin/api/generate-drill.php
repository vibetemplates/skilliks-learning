<?php
require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';
require_once '../../classes/SkillsDrill.php';
require_once '../../classes/OpenAISkillsDrillGenerator.php';
require_once '../../classes/User.php';

// Set JSON content type
header('Content-Type: application/json');

// Require admin
requireLogin();
$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$lessonId = $input['lesson_id'] ?? null;

if (!$lessonId) {
    echo json_encode(['success' => false, 'error' => 'Lesson ID required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get lesson details
    $sql = "SELECT l.*, c.title as course_title 
            FROM lessons l
            JOIN courses c ON l.course_id = c.id
            WHERE l.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        throw new Exception('Lesson not found');
    }
    
    if (empty($lesson['video_transcript'])) {
        throw new Exception('Lesson has no transcript');
    }
    
    // Check if drill already exists
    $skillsDrill = new SkillsDrill();
    $existingDrill = $skillsDrill->getByLessonId($lessonId);
    
    if ($existingDrill) {
        throw new Exception('Skills drill already exists for this lesson');
    }
    
    // Generate drill questions
    $generator = new OpenAISkillsDrillGenerator();
    $questions = $generator->generateDrillFromTranscript($lesson['video_transcript'], $lesson['title']);
    
    if (empty($questions)) {
        throw new Exception('No questions generated');
    }
    
    // Create drill data
    $drillData = [
        'lesson_id' => $lesson['id'],
        'title' => "Skills Practice: " . $lesson['title'],
        'description' => "Practice your skills from the lesson: " . $lesson['title'],
        'instructions' => "Answer the questions to test your understanding. You can attempt each question multiple times.",
        'min_questions_per_session' => 10,
        'max_questions_per_session' => min(20, count($questions)),
        'shuffle_questions' => 1,
        'shuffle_answers' => 1,
        'created_by' => $_SESSION['user_id']
    ];
    
    // Create the drill
    $drillId = $skillsDrill->create($drillData);
    
    if (!$drillId) {
        throw new Exception('Failed to create drill');
    }
    
    // Add questions to the drill
    foreach ($questions as $questionIndex => $question) {
        $questionData = [
            'drill_id' => $drillId,
            'question_text' => $question['question_text'],
            'difficulty_level' => $question['difficulty_level'] ?? 'medium',
            'hint' => $question['hint'] ?? null,
            'explanation' => $question['explanation'] ?? null
        ];
        
        $questionId = $skillsDrill->addQuestion($questionData);
        
        if ($questionId) {
            // Add answer options
            foreach ($question['options'] as $optionIndex => $option) {
                $optionData = [
                    'question_id' => $questionId,
                    'answer_text' => $option['answer_text'],
                    'is_correct' => $option['is_correct'] ? 1 : 0,
                    'feedback' => $option['feedback'] ?? null,
                    'order_index' => $optionIndex + 1
                ];
                
                $skillsDrill->addAnswerOption($optionData);
            }
        }
    }
    
    // Update session progress if tracking
    if (isset($_SESSION['generation_progress'])) {
        $_SESSION['generation_progress']++;
    }
    
    echo json_encode([
        'success' => true,
        'drill_id' => $drillId,
        'question_count' => count($questions)
    ]);
    
} catch (Exception $e) {
    error_log('Skills drill generation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}