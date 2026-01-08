<?php
/**
 * Test recommendation generation with partial survey responses
 */

require_once 'html/config/database.php';
require_once 'html/config/functions.php';
require_once 'html/classes/SurveyNarrative.php';
require_once 'html/classes/Course.php';
require_once 'html/classes/CourseRecommendation.php';

// Test scenarios
$testScenarios = [
    ['user_id' => 99999, 'name' => 'User with no responses'],
    ['user_id' => 5, 'name' => 'User 5 with many responses'],
    ['user_id' => 1, 'name' => 'User 1 - check response count']
];

echo "Testing Recommendation Generation with Partial Responses\n";
echo str_repeat('=', 60) . "\n\n";

$db = getDB();

foreach ($testScenarios as $scenario) {
    $userId = $scenario['user_id'];
    
    echo "Testing: {$scenario['name']} (ID: $userId)\n";
    echo str_repeat('-', 40) . "\n";
    
    // Check response count
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT sr.question_id) as response_count,
               COUNT(DISTINCT sq.id) as total_questions,
               ROUND((COUNT(DISTINCT sr.question_id) * 100.0 / NULLIF(COUNT(DISTINCT sq.id), 0)), 2) as completion_percentage
        FROM survey_questions sq
        JOIN survey_sections ss ON sq.section_id = ss.id
        JOIN surveys s ON ss.survey_id = s.id
        LEFT JOIN survey_responses sr ON sq.id = sr.question_id AND sr.user_id = ?
        WHERE s.type = 'skills'
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    echo "Survey completion: {$stats['response_count']}/{$stats['total_questions']} questions ({$stats['completion_percentage']}%)\n\n";
    
    // Generate narrative
    $surveyNarrative = new SurveyNarrative();
    $narrative = $surveyNarrative->generateNarrative($userId);
    
    echo "Narrative (first 300 chars):\n";
    echo substr($narrative, 0, 300) . "...\n\n";
    
    // Get top interests
    $topInterests = $surveyNarrative->getTopInterests($userId);
    echo "Top interests: " . implode(', ', $topInterests) . "\n\n";
    
    // Generate recommendations
    $courseRecommendation = new CourseRecommendation($db);
    $result = $courseRecommendation->generateAndStoreRecommendations($userId);
    
    echo "Recommendations generated:\n";
    echo "  - Total: {$result['total_generated']}\n";
    echo "  - Stored: {$result['total_stored']}\n";
    echo "  - Beginner: {$result['beginner_courses']}\n";
    echo "  - Interest-based: {$result['interest_based']}\n\n";
    
    // Show top 3 recommendations
    $recommendations = $courseRecommendation->getActiveRecommendations($userId, 3);
    echo "Top 3 recommendations:\n";
    foreach ($recommendations as $rec) {
        echo "  - {$rec['title']} (Type: {$rec['recommendation_type']}, Score: {$rec['score']})\n";
        echo "    Reason: {$rec['reason']}\n";
    }
    
    echo "\n" . str_repeat('-', 40) . "\n\n";
}

echo "Test completed successfully!\n";