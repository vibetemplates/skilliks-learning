<?php
/**
 * Script to generate skills drills for all lessons that don't have them yet
 * Run from command line: php generate-missing-drills.php [user_id]
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/classes/SkillsDrill.php';
require_once dirname(__DIR__) . '/classes/OpenAISkillsDrillGenerator.php';

// Get user ID from command line or default to 1
$userId = $argv[1] ?? 1;

$db = Database::getInstance()->getConnection();

// First, let's check the current status
echo "\n=== SKILLS DRILL STATUS ===\n";

$stmt = $db->query("SELECT COUNT(*) as total FROM lessons WHERE video_transcript IS NOT NULL AND video_transcript != ''");
$result = $stmt->fetch();
$totalWithTranscripts = $result['total'];

$stmt = $db->query("SELECT COUNT(DISTINCT lesson_id) as total FROM skills_drills");
$result = $stmt->fetch();
$totalWithDrills = $result['total'];

echo "Lessons with transcripts: {$totalWithTranscripts}\n";
echo "Lessons with skills drills: {$totalWithDrills}\n";
echo "Lessons needing drills: " . ($totalWithTranscripts - $totalWithDrills) . "\n\n";

// Get all lessons with transcripts but without skills drills
$sql = "SELECT l.*, c.title as course_title 
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.video_transcript IS NOT NULL 
        AND l.video_transcript != ''
        AND NOT EXISTS (
            SELECT 1 FROM skills_drills sd 
            WHERE sd.lesson_id = l.id
        )
        ORDER BY c.id, l.order_index";

$stmt = $db->prepare($sql);
$stmt->execute();
$lessons = $stmt->fetchAll();

$totalLessons = count($lessons);

if ($totalLessons === 0) {
    echo "All lessons with transcripts already have skills drills!\n";
    exit(0);
}

echo "Found {$totalLessons} lessons without skills drills\n";
echo "Do you want to generate drills for all of them? (y/N): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) != 'y') {
    echo "Cancelled.\n";
    exit(0);
}
fclose($handle);

echo "\nStarting generation...\n\n";

$skillsDrill = new SkillsDrill();
$generator = new OpenAISkillsDrillGenerator();

$successCount = 0;
$failureCount = 0;
$errors = [];

foreach ($lessons as $index => $lesson) {
    $current = $index + 1;
    echo "[{$current}/{$totalLessons}] Processing: {$lesson['course_title']} - {$lesson['title']}\n";
    
    try {
        // Generate drill questions
        echo "  - Generating questions from transcript...\n";
        $questions = $generator->generateDrillFromTranscript($lesson['video_transcript'], $lesson['title']);
        
        if (empty($questions)) {
            throw new Exception("No questions generated");
        }
        
        echo "  - Generated " . count($questions) . " questions\n";
        
        // Create drill data
        $drillData = [
            'lesson_id' => $lesson['id'],
            'course_id' => $lesson['course_id'],
            'title' => "Skills Practice: " . $lesson['title'],
            'description' => "Practice your skills from the lesson: " . $lesson['title'],
            'instructions' => "Answer the questions to test your understanding. You can attempt each question multiple times.",
            'min_questions_per_session' => 10,
            'max_questions_per_session' => min(20, count($questions)),
            'shuffle_questions' => 1,
            'shuffle_answers' => 1,
            'created_by' => $userId
        ];
        
        // Create the drill
        echo "  - Creating skills drill...\n";
        $drillId = $skillsDrill->create($drillData);
        
        if (!$drillId) {
            throw new Exception("Failed to create drill");
        }
        
        // Add questions to the drill
        echo "  - Adding questions to drill...\n";
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
        
        echo "  ✓ Successfully created skills drill (ID: {$drillId})\n\n";
        $successCount++;
        
        // Add a delay to avoid rate limiting (3 seconds between API calls)
        if ($current < $totalLessons) {
            echo "  Waiting 3 seconds before next lesson...\n\n";
            sleep(3);
        }
        
    } catch (Exception $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n\n";
        $errors[] = [
            'lesson' => $lesson['title'],
            'course' => $lesson['course_title'],
            'error' => $e->getMessage()
        ];
        $failureCount++;
        
        // Add delay even on error
        if ($current < $totalLessons) {
            sleep(2);
        }
    }
}

echo "\n";
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Total lessons processed: {$totalLessons}\n";
echo "Successful: {$successCount}\n";
echo "Failed: {$failureCount}\n";

if (!empty($errors)) {
    echo "\nFailed lessons:\n";
    foreach ($errors as $error) {
        echo "- [{$error['course']}] {$error['lesson']}: {$error['error']}\n";
    }
}

echo "\n";
?>