<?php
/**
 * API endpoint to update lesson progress
 * Handles marking lessons as complete and tracking progress
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$lessonId = $input['lesson_id'] ?? null;
$action = $input['action'] ?? 'complete'; // complete, incomplete, start, update
$userId = $_SESSION['user_id'];

if (!$lessonId) {
    echo json_encode(['success' => false, 'message' => 'Missing lesson ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get lesson and course details
    $stmt = $db->prepare("
        SELECT l.*, c.id as course_id
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lessonId]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
        exit;
    }
    
    // Verify enrollment
    $stmt = $db->prepare("
        SELECT * FROM course_enrollments 
        WHERE user_id = ? AND course_id = ? AND status IN ('enrolled', 'in_progress')
    ");
    $stmt->execute([$userId, $lesson['course_id']]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment && !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        exit;
    }
    
    // Check existing progress
    $stmt = $db->prepare("
        SELECT * FROM lesson_progress 
        WHERE user_id = ? AND lesson_id = ?
    ");
    $stmt->execute([$userId, $lessonId]);
    $progress = $stmt->fetch();
    
    if ($action === 'complete') {
        // Don't allow marking quiz lessons as complete without taking the quiz
        if ($lesson['lesson_type'] === 'quiz') {
            if (!$progress || !$progress['score']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Please complete the quiz to mark this lesson as complete'
                ]);
                exit;
            }
        }
        
        if ($progress) {
            // Update existing progress
            $stmt = $db->prepare("
                UPDATE lesson_progress 
                SET status = 'completed',
                    progress_percentage = 100,
                    completed_at = CASE WHEN completed_at IS NULL THEN NOW() ELSE completed_at END,
                    last_accessed = NOW()
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$userId, $lessonId]);
        } else {
            // Create new progress record
            $stmt = $db->prepare("
                INSERT INTO lesson_progress 
                (user_id, lesson_id, course_id, status, progress_percentage, 
                 started_at, completed_at, last_accessed)
                VALUES (?, ?, ?, 'completed', 100, NOW(), NOW(), NOW())
            ");
            $stmt->execute([$userId, $lessonId, $lesson['course_id']]);
        }
        
        // Log analytics event
        $stmt = $db->prepare("
            INSERT INTO learning_analytics 
            (user_id, course_id, lesson_id, action_type, created_at)
            VALUES (?, ?, ?, 'lesson_completed', NOW())
        ");
        $stmt->execute([$userId, $lesson['course_id'], $lessonId]);
        
    } elseif ($action === 'incomplete') {
        // Mark lesson as incomplete (revert completion)
        if ($progress) {
            $stmt = $db->prepare("
                UPDATE lesson_progress 
                SET status = 'in_progress',
                    progress_percentage = 50,
                    completed_at = NULL,
                    last_accessed = NOW()
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$userId, $lessonId]);
        } else {
            // Create new progress record as in_progress
            $stmt = $db->prepare("
                INSERT INTO lesson_progress 
                (user_id, lesson_id, course_id, status, progress_percentage, 
                 started_at, last_accessed)
                VALUES (?, ?, ?, 'in_progress', 50, NOW(), NOW())
            ");
            $stmt->execute([$userId, $lessonId, $lesson['course_id']]);
        }
        
    } elseif ($action === 'start') {
        if (!$progress) {
            // Create new progress record
            $stmt = $db->prepare("
                INSERT INTO lesson_progress 
                (user_id, lesson_id, course_id, status, progress_percentage, 
                 started_at, last_accessed)
                VALUES (?, ?, ?, 'in_progress', 0, NOW(), NOW())
            ");
            $stmt->execute([$userId, $lessonId, $lesson['course_id']]);
        } else {
            // Update last accessed
            $stmt = $db->prepare("
                UPDATE lesson_progress 
                SET last_accessed_at = NOW()
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$userId, $lessonId]);
        }
    }
    
    // Calculate and update course progress
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT l.id) as total_lessons,
            COUNT(DISTINCT lp.lesson_id) as completed_lessons
        FROM lessons l
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id 
            AND lp.user_id = ? AND lp.status = 'completed'
        WHERE l.course_id = ? AND l.status = 'published'
    ");
    $stmt->execute([$userId, $lesson['course_id']]);
    $courseStats = $stmt->fetch();
    
    $courseProgress = $courseStats['total_lessons'] > 0 
        ? round(($courseStats['completed_lessons'] / $courseStats['total_lessons']) * 100, 2) 
        : 0;
    
    // Update course enrollment progress
    $stmt = $db->prepare("
        UPDATE course_enrollments 
        SET progress_percentage = ?,
            status = CASE 
                WHEN ? >= 100 THEN 'completed'
                WHEN ? > 0 THEN 'in_progress'
                ELSE status
            END,
            completion_date = CASE 
                WHEN ? >= 100 AND completion_date IS NULL THEN NOW()
                ELSE completion_date
            END,
            last_accessed = NOW()
        WHERE user_id = ? AND course_id = ?
    ");
    $stmt->execute([
        $courseProgress,
        $courseProgress,
        $courseProgress,
        $courseProgress,
        $userId,
        $lesson['course_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Progress updated successfully',
        'lesson_progress' => [
            'status' => $action === 'complete' ? 'completed' : 'in_progress',
            'progress_percentage' => $action === 'complete' ? 100 : ($action === 'incomplete' ? 50 : 0)
        ],
        'course_progress' => [
            'total_lessons' => $courseStats['total_lessons'],
            'completed_lessons' => $courseStats['completed_lessons'],
            'progress_percentage' => $courseProgress
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Lesson progress error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating progress'
    ]);
}