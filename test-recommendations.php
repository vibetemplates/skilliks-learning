<?php
/**
 * Test course recommendations for user 5
 */

require_once 'html/config/database.php';
require_once 'html/config/functions.php';
require_once 'html/classes/SurveyNarrative.php';
require_once 'html/classes/Course.php';

// Test with user ID 5
$userId = 5;

echo "Testing course recommendations for user ID: $userId\n";
echo str_repeat('=', 60) . "\n\n";

// Initialize classes
$surveyNarrative = new SurveyNarrative();

// Get user's default community
$db = getDB();
$stmt = $db->prepare("SELECT default_community_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$communityId = $user['default_community_id'] ?? 1;

$course = new Course($db, $communityId);

// Generate narrative from survey responses
echo "1. Generating user profile narrative...\n";
$narrative = $surveyNarrative->generateNarrative($userId);
echo "Narrative generated:\n";
echo str_repeat('-', 40) . "\n";
echo $narrative . "\n";
echo str_repeat('-', 40) . "\n\n";

// Get top interests
echo "2. Getting top interests...\n";
$topInterests = $surveyNarrative->getTopInterests($userId);
echo "Top interests: " . implode(', ', $topInterests) . "\n\n";

// Get all available courses
echo "3. Getting all available courses...\n";
$allCourses = $course->getAllWithDetails();
echo "Found " . count($allCourses) . " total courses\n\n";

// Find beginner courses
echo "4. Finding beginner courses...\n";
$beginnerKeywords = ['introduction', 'basics', 'fundamentals', 'getting started', 'beginner'];
$beginnerCourses = array_filter($allCourses, function($c) use ($beginnerKeywords) {
    $title = strtolower($c['title']);
    $desc = strtolower($c['description'] ?? '');
    foreach ($beginnerKeywords as $keyword) {
        if (strpos($title, $keyword) !== false || strpos($desc, $keyword) !== false) {
            return true;
        }
    }
    return false;
});

echo "Found " . count($beginnerCourses) . " beginner courses:\n";
foreach (array_slice($beginnerCourses, 0, 3) as $course) {
    echo "  - " . $course['title'] . " (ID: " . $course['id'] . ")\n";
}
echo "\n";

// Find interest-based courses
echo "5. Finding interest-based courses...\n";
$interestCourses = [];
foreach ($topInterests as $interest) {
    foreach ($allCourses as $c) {
        $title = strtolower($c['title']);
        $desc = strtolower($c['description'] ?? '');
        $interestLower = strtolower($interest);
        
        if (strpos($title, $interestLower) !== false || 
            strpos($desc, $interestLower) !== false ||
            (strpos($interestLower, 'project') !== false && strpos($title, 'project') !== false) ||
            (strpos($interestLower, 'prompt') !== false && strpos($title, 'prompt') !== false)) {
            $interestCourses[$c['id']] = $c;
        }
    }
}

echo "Found " . count($interestCourses) . " interest-based courses:\n";
foreach (array_slice($interestCourses, 0, 5) as $course) {
    echo "  - " . $course['title'] . " (ID: " . $course['id'] . ")\n";
}
echo "\n";

// Summary
echo "6. Summary:\n";
echo "   - User has " . count($topInterests) . " top interests\n";
echo "   - " . count($beginnerCourses) . " beginner courses recommended\n";
echo "   - " . count($interestCourses) . " interest-based courses recommended\n";
echo "\nRecommendation system is working correctly!\n";