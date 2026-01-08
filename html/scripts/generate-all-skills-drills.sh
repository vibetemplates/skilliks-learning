#!/bin/bash

# Script to generate skills drills for all lessons that don't have them yet
# This script should be run from the command line with appropriate permissions

# Set the base directory
BASE_DIR="/var/www/html"
cd "$BASE_DIR"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Skills Drill Generation Script${NC}"
echo "================================="
echo ""

# Check if user ID is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Please provide a user ID as the first argument${NC}"
    echo "Usage: $0 <user_id>"
    echo "Example: $0 1"
    exit 1
fi

USER_ID=$1

# Create a PHP script to process the drills
cat > /tmp/generate_drills.php << 'EOF'
<?php
require_once '/var/www/html/config/database.php';
require_once '/var/www/html/classes/SkillsDrill.php';
require_once '/var/www/html/classes/OpenAISkillsDrillGenerator.php';

$userId = $argv[1] ?? 1;

$db = Database::getInstance()->getConnection();

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
echo "Found {$totalLessons} lessons without skills drills\n\n";

if ($totalLessons === 0) {
    echo "All lessons with transcripts already have skills drills!\n";
    exit(0);
}

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
        
        // Add a small delay to avoid rate limiting
        sleep(2);
        
    } catch (Exception $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n\n";
        $errors[] = [
            'lesson' => $lesson['title'],
            'error' => $e->getMessage()
        ];
        $failureCount++;
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
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- {$error['lesson']}: {$error['error']}\n";
    }
}
EOF

# Run the PHP script
echo -e "${YELLOW}Starting skills drill generation...${NC}"
echo ""

php /tmp/generate_drills.php "$USER_ID"

# Clean up
rm /tmp/generate_drills.php

echo ""
echo -e "${GREEN}Script completed!${NC}"