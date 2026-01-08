<?php
/**
 * Test storing course recommendations for user 5
 */

require_once 'html/config/database.php';
require_once 'html/config/functions.php';
require_once 'html/classes/SurveyNarrative.php';
require_once 'html/classes/Course.php';
require_once 'html/classes/CourseRecommendation.php';

// Test with user ID 5
$userId = 5;

echo "Testing course recommendation storage for user ID: $userId\n";
echo str_repeat('=', 60) . "\n\n";

// Get database connection
$db = getDB();

// Initialize recommendation class
$courseRecommendation = new CourseRecommendation($db);

// Generate and store recommendations
echo "1. Generating and storing recommendations...\n";
$result = $courseRecommendation->generateAndStoreRecommendations($userId);

echo "Results:\n";
echo "  - Total generated: " . $result['total_generated'] . "\n";
echo "  - Total stored: " . $result['total_stored'] . "\n";
echo "  - Beginner courses: " . $result['beginner_courses'] . "\n";
echo "  - Interest-based: " . $result['interest_based'] . "\n\n";

// Retrieve stored recommendations
echo "2. Retrieving stored recommendations...\n";
$recommendations = $courseRecommendation->getActiveRecommendations($userId, 20);

echo "Found " . count($recommendations) . " active recommendations:\n\n";

foreach ($recommendations as $rec) {
    echo "Course: " . $rec['title'] . "\n";
    echo "  - Type: " . $rec['recommendation_type'] . "\n";
    echo "  - Score: " . $rec['score'] . "\n";
    echo "  - Reason: " . $rec['reason'] . "\n";
    echo "  - Lessons: " . $rec['lesson_count'] . "\n";
    echo "  - Enrolled: " . $rec['enrolled_count'] . "\n";
    echo "  - Generated: " . $rec['generated_at'] . "\n";
    echo "\n";
}

// Get user statistics
echo "3. User recommendation statistics:\n";
$stats = $courseRecommendation->getUserStats($userId);
print_r($stats);

echo "\nRecommendation storage test completed successfully!\n";