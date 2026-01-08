<?php
/**
 * HTMX endpoint for course progress widget
 * Returns updated course progress HTML
 */

require_once '../includes/session.php';
require_once '../config/database.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();

try {
    $db = getDB();
    
    // Fetch enrolled courses with lesson progress and quiz scores
    $stmt = $db->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            l.id as lesson_id,
            l.title as lesson_title,
            l.lesson_type,
            lp.status as lesson_status,
            lp.completed_at,
            lp.started_at,
            (SELECT COUNT(*) FROM quiz_attempts qa 
             JOIN quizzes q ON qa.quiz_id = q.id 
             WHERE q.lesson_id = l.id 
             AND qa.user_id = ce.user_id
             AND qa.status = 'completed') as quiz_attempt_count
        FROM course_enrollments ce
        INNER JOIN courses c ON ce.course_id = c.id
        INNER JOIN lessons l ON l.course_id = c.id
        LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ce.user_id
        WHERE ce.user_id = ? 
        AND ce.status IN ('enrolled', 'in_progress', 'completed')
        AND c.status = 'published'
        AND l.status = 'published'
        AND (
            lp.id IS NOT NULL  -- Lesson has been started
            OR EXISTS (        -- OR quiz has been attempted
                SELECT 1 FROM quiz_attempts qa2
                JOIN quizzes q2 ON qa2.quiz_id = q2.id
                WHERE q2.lesson_id = l.id
                AND qa2.user_id = ce.user_id
                AND qa2.status = 'completed'
            )
        )
        ORDER BY c.title, l.order_index
    ");
    $stmt->execute([$currentUserId]);
    $courseProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($courseProgress)) {
        // Check if user is enrolled but hasn't started any lessons
        $enrollmentCheck = $db->prepare("
            SELECT COUNT(*) as enrolled_count
            FROM course_enrollments ce
            WHERE ce.user_id = ? 
            AND ce.status IN ('enrolled', 'in_progress', 'completed')
        ");
        $enrollmentCheck->execute([$currentUserId]);
        $enrolledCount = $enrollmentCheck->fetch()['enrolled_count'];
        
        if ($enrolledCount > 0) {
            echo '<p class="text-muted mb-0">You are enrolled in courses but haven\'t started any lessons yet.</p>';
            echo '<a href="/my-courses" class="btn btn-primary btn-sm mt-3"><i class="bi bi-play-circle me-2"></i>View My Courses</a>';
        } else {
            echo '<p class="text-muted mb-0">You are not enrolled in any courses yet.</p>';
            echo '<a href="/programs" class="btn btn-primary btn-sm mt-3"><i class="bi bi-plus-circle me-2"></i>Browse Courses</a>';
        }
    } else {
        $currentCourseId = null;
        foreach ($courseProgress as $progress) {
            // Start new course section
            if ($currentCourseId !== $progress['course_id']) {
                if ($currentCourseId !== null) {
                    echo '</ul></div>'; // Close previous course
                }
                $currentCourseId = $progress['course_id'];
                echo '<div class="course-section mb-3">';
                echo '<h6 class="fw-bold mb-2">' . htmlspecialchars($progress['course_title']) . '</h6>';
                echo '<ul class="list-unstyled ms-3">';
            }
            
            // Display lesson with quiz score if applicable
            echo '<li class="mb-2">';
            echo '<div class="d-flex justify-content-between align-items-center">';
            echo '<div>';
            
            // Lesson status icon
            if ($progress['lesson_status'] === 'completed') {
                echo '<i class="bi bi-check-circle-fill text-success me-2"></i>';
            } else {
                echo '<i class="bi bi-dot text-muted me-2"></i>';
            }
            
            echo '<span>' . htmlspecialchars($progress['lesson_title']) . '</span>';
            echo '</div>';
            
            // Check if this lesson has a quiz and display score
            echo '<div class="text-end">';
            
            // Try to fetch quiz score for any lesson that has a quiz
            try {
                // First check if a quiz exists for this lesson
                $quizCheckStmt = $db->prepare("SELECT id FROM quizzes WHERE lesson_id = ? LIMIT 1");
                $quizCheckStmt->execute([$progress['lesson_id']]);
                $quizExists = $quizCheckStmt->fetch();
                
                if ($quizExists) {
                    // Fetch quiz score
                    $scoreStmt = $db->prepare("
                        SELECT 
                            qa.score_achieved,
                            qa.points_earned,
                            qa.total_points,
                            COUNT(qr.id) as total_questions,
                            SUM(CASE WHEN qr.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                        FROM quiz_attempts qa
                        JOIN quizzes q ON qa.quiz_id = q.id
                        LEFT JOIN quiz_responses qr ON qr.attempt_id = qa.id
                        WHERE qa.user_id = ? 
                        AND q.lesson_id = ? 
                        AND qa.status = 'completed'
                        GROUP BY qa.id
                        ORDER BY qa.end_time DESC 
                        LIMIT 1
                    ");
                    $scoreStmt->execute([$currentUserId, $progress['lesson_id']]);
                    $quizData = $scoreStmt->fetch();
                    
                    if ($quizData && $quizData['total_questions'] > 0) {
                        // Use correct_answers if available, otherwise calculate from score_achieved
                        $correctAnswers = $quizData['correct_answers'] ?? round($quizData['score_achieved'] * $quizData['total_questions'] / 100);
                        $percentage = $quizData['score_achieved'] ?? round(($correctAnswers / $quizData['total_questions']) * 100);
                        $scoreClass = $percentage >= 70 ? 'text-success' : ($percentage >= 50 ? 'text-warning' : 'text-danger');
                        echo '<small class="' . $scoreClass . ' fw-bold">';
                        echo $correctAnswers . '/' . $quizData['total_questions'] . ' (' . $percentage . '%)';
                        echo '</small>';
                    } else {
                        echo '<small class="text-muted">Not attempted</small>';
                    }
                }
            } catch (PDOException $e) {
                // Silently fail if tables don't exist
            }
            
            echo '</div>';
            
            echo '</div>';
            echo '</li>';
        }
        
        // Close last course
        if ($currentCourseId !== null) {
            echo '</ul></div>';
        }
    }
} catch (PDOException $e) {
    error_log("Course progress query error: " . $e->getMessage());
    echo '<p class="text-danger">Unable to load course progress.</p>';
}
?>