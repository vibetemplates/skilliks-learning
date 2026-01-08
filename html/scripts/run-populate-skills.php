<?php
/**
 * Run Script to Populate Lesson Skills
 * 
 * This script runs the lesson skills population in test mode first,
 * then allows you to proceed with actual population
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/scripts/populate-lesson-skills.php';

// Set execution time limit for long-running script
set_time_limit(300); // 5 minutes

echo "\n=== Running Lesson Skills Population Script ===\n\n";

try {
    $populator = new LessonSkillsPopulator();
    
    // Show current statistics
    echo "Current State:\n";
    echo "==============\n";
    $populator->showStatistics();
    
    // Test mode - show what would happen for a few lessons
    echo "\n\nTest Mode - Analyzing first 5 lessons:\n";
    echo "=====================================\n";
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT id, title, description, 
               SUBSTRING(video_transcript, 1, 500) as transcript_preview,
               LENGTH(video_transcript) as transcript_length
        FROM lessons 
        WHERE status = 'published'
        AND (video_transcript IS NOT NULL AND LENGTH(video_transcript) > 0)
        ORDER BY id
        LIMIT 5
    ");
    
    $testLessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($testLessons as $lesson) {
        echo "\nLesson: {$lesson['title']} (ID: {$lesson['id']})\n";
        echo "Transcript length: {$lesson['transcript_length']} characters\n";
        
        // Get full lesson for analysis
        $fullStmt = $db->prepare("SELECT * FROM lessons WHERE id = ?");
        $fullStmt->execute([$lesson['id']]);
        $fullLesson = $fullStmt->fetch(PDO::FETCH_ASSOC);
        
        $detectedSkills = $populator->extractSkillsFromLesson($fullLesson);
        
        if (empty($detectedSkills)) {
            echo "  → No skills detected\n";
        } else {
            echo "  → Detected skills:\n";
            foreach ($detectedSkills as $skillId => $skillLevel) {
                $skillStmt = $db->prepare("SELECT name FROM skills WHERE id = ?");
                $skillStmt->execute([$skillId]);
                $skillName = $skillStmt->fetchColumn();
                echo "     • {$skillName} (Level: {$skillLevel})\n";
            }
        }
    }
    
    // Ask user to proceed
    echo "\n\nDo you want to proceed with populating ALL lesson skills? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    
    if (trim($line) === 'yes') {
        echo "\nStarting full population process...\n";
        echo "=================================\n\n";
        
        $populator->populateAllLessons();
        
        echo "\n\nFinal Statistics:\n";
        echo "=================\n";
        $populator->showStatistics();
        
        // Show some examples of populated skills
        echo "\n\nExample populated lessons:\n";
        echo "==========================\n";
        
        $exampleStmt = $db->query("
            SELECT l.title, s.name as skill_name, ls.skill_level
            FROM lesson_skills ls
            JOIN lessons l ON ls.lesson_id = l.id
            JOIN skills s ON ls.skill_id = s.id
            ORDER BY ls.added_at DESC
            LIMIT 10
        ");
        
        $examples = $exampleStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($examples as $example) {
            echo "• {$example['title']} → {$example['skill_name']} ({$example['skill_level']})\n";
        }
        
    } else {
        echo "\nPopulation cancelled.\n";
    }
    
    fclose($handle);
    
} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";