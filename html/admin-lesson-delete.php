<?php
/**
 * Admin Lesson Delete Handler
 * 
 * Handles lesson deletion requests
 */

require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login and admin role
requireLogin();
$currentUserId = getCurrentUserId();
$userObj = new User();
if (!$userObj->isAdmin($currentUserId)) {
    setFlashMessage('error', 'Access denied. Administrator privileges required.');
    header('Location: /courses');
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Invalid request method.');
    header('Location: /courses');
    exit;
}

$lessonId = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
$courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;

if (!$lessonId || !$courseId) {
    setFlashMessage('error', 'Lesson ID and Course ID are required.');
    header('Location: /courses');
    exit;
}

try {
    $db = getDB();
    
    // Verify lesson exists and belongs to the course
    $stmt = $db->prepare("SELECT id FROM lessons WHERE id = ? AND course_id = ?");
    $stmt->execute([$lessonId, $courseId]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        setFlashMessage('error', 'Lesson not found or does not belong to this course.');
        header('Location: /admin-course-edit.php?id=' . $courseId);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Delete lesson progress records first (foreign key constraint)
    $stmt = $db->prepare("DELETE FROM lesson_progress WHERE lesson_id = ?");
    $stmt->execute([$lessonId]);
    
    // Delete the lesson
    $stmt = $db->prepare("DELETE FROM lessons WHERE id = ?");
    $result = $stmt->execute([$lessonId]);
    
    if ($result) {
        // Commit transaction
        $db->commit();
        setFlashMessage('success', 'Lesson deleted successfully.');
    } else {
        // Rollback transaction
        $db->rollback();
        setFlashMessage('error', 'Failed to delete lesson.');
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollback();
    }
    error_log("Lesson deletion error: " . $e->getMessage());
    setFlashMessage('error', 'Database error occurred while deleting lesson.');
}

// Redirect back to course edit page
header('Location: /admin-course-edit.php?id=' . $courseId);
exit;
?>