<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Check existing courses
    $stmt = $db->prepare("SELECT id, title FROM courses ORDER BY id");
    $stmt->execute();
    $courses = $stmt->fetchAll();
    
    echo "<h2>Existing Courses:</h2>";
    if (empty($courses)) {
        echo "No courses found.<br>";
    } else {
        foreach ($courses as $course) {
            echo "ID: " . $course['id'] . " - " . htmlspecialchars($course['title']) . "<br>";
        }
    }
    
    // Check existing lessons
    $stmt = $db->prepare("SELECT id, title, video_url FROM lessons ORDER BY id");
    $stmt->execute();
    $lessons = $stmt->fetchAll();
    
    echo "<h2>Existing Lessons:</h2>";
    if (empty($lessons)) {
        echo "No lessons found.<br>";
    } else {
        foreach ($lessons as $lesson) {
            echo "ID: " . $lesson['id'] . " - " . htmlspecialchars($lesson['title']) . " - " . htmlspecialchars($lesson['video_url'] ?? 'No video') . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>