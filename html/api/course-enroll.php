<?php
/**
 * Course Enrollment API
 * Handles course enrollment and unenrollment
 */

header('Content-Type: application/json');
require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();
    $currentUserId = getCurrentUserId();
    
    // Get request data
    $courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $action = $_POST['action'] ?? 'enroll';
    
    if (!$courseId) {
        echo json_encode(['success' => false, 'error' => 'Course ID is required']);
        exit;
    }
    
    // Verify course exists and is published
    $stmt = $db->prepare("SELECT id, title, status FROM courses WHERE id = ? AND status = 'published'");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo json_encode(['success' => false, 'error' => 'Course not found or not available']);
        exit;
    }
    
    if ($action === 'enroll') {
        // Check if user is already enrolled
        $stmt = $db->prepare("
            SELECT id FROM course_enrollments 
            WHERE course_id = ? AND user_id = ? AND status IN ('enrolled', 'in_progress', 'completed')
        ");
        $stmt->execute([$courseId, $currentUserId]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already enrolled in this course']);
            exit;
        }
        
        // Enroll user in the course
        $stmt = $db->prepare("
            INSERT INTO course_enrollments (
                user_id, course_id, status, enrollment_date, progress_percentage,
                last_accessed, time_spent_minutes
            ) VALUES (?, ?, 'enrolled', NOW(), 0.0, NOW(), 0)
        ");
        
        $result = $stmt->execute([$currentUserId, $courseId]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Successfully enrolled in ' . htmlspecialchars($course['title']),
                'action' => 'enrolled'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to enroll in course']);
        }
        
    } elseif ($action === 'unenroll') {
        // Remove enrollment (soft delete - keep progress data)
        $stmt = $db->prepare("
            UPDATE course_enrollments 
            SET status = 'unenrolled', unenrollment_date = NOW()
            WHERE course_id = ? AND user_id = ? AND status IN ('enrolled', 'in_progress')
        ");
        
        $result = $stmt->execute([$courseId, $currentUserId]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Successfully unenrolled from ' . htmlspecialchars($course['title']),
                'action' => 'unenrolled'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Not enrolled in this course or already completed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Course enrollment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Course enrollment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>