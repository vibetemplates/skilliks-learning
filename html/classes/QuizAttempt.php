<?php
/**
 * QuizAttempt Class
 * 
 * Handles quiz attempt operations and analytics
 */

class QuizAttempt {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: getDB();
    }
    
    /**
     * Get attempt by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT qa.*, 
                       q.title as quiz_title,
                       q.passing_score,
                       q.allow_review,
                       q.show_correct_answers,
                       l.title as lesson_title,
                       l.course_id,
                       u.first_name,
                       u.last_name
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN lessons l ON q.lesson_id = l.id
                LEFT JOIN users u ON qa.user_id = u.id
                WHERE qa.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("QuizAttempt getById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get quiz analytics for a course
     */
    public function getCourseAnalytics($courseId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    l.id as lesson_id,
                    l.title as lesson_title,
                    q.id as quiz_id,
                    q.title as quiz_title,
                    COUNT(DISTINCT qa.user_id) as total_students,
                    COUNT(qa.id) as total_attempts,
                    AVG(qa.score_achieved) as avg_score,
                    MIN(qa.score_achieved) as min_score,
                    MAX(qa.score_achieved) as max_score,
                    SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as passed_count,
                    AVG(qa.time_spent_seconds) as avg_time_seconds
                FROM lessons l
                JOIN quizzes q ON l.id = q.lesson_id
                LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.status = 'completed'
                WHERE l.course_id = ? AND l.lesson_type = 'quiz'
                GROUP BY l.id, q.id
                ORDER BY l.order_index
            ");
            $stmt->execute([$courseId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("QuizAttempt getCourseAnalytics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get student quiz performance for a course
     */
    public function getStudentPerformance($courseId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    l.id as lesson_id,
                    l.title as lesson_title,
                    q.id as quiz_id,
                    q.title as quiz_title,
                    q.max_attempts,
                    COUNT(qa.id) as attempts_made,
                    MAX(qa.score_achieved) as best_score,
                    MAX(qa.passed) as has_passed,
                    MAX(qa.end_time) as last_attempt_date
                FROM lessons l
                JOIN quizzes q ON l.id = q.lesson_id
                LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id 
                    AND qa.user_id = ? 
                    AND qa.status = 'completed'
                WHERE l.course_id = ? AND l.lesson_type = 'quiz'
                GROUP BY l.id, q.id
                ORDER BY l.order_index
            ");
            $stmt->execute([$userId, $courseId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("QuizAttempt getStudentPerformance error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get detailed question analytics for a quiz
     */
    public function getQuestionAnalytics($quizId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    qq.id as question_id,
                    qq.question_text,
                    qq.question_type,
                    qq.points,
                    COUNT(DISTINCT qr.attempt_id) as times_answered,
                    SUM(CASE WHEN qr.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                    AVG(qr.points_earned) as avg_points,
                    AVG(qr.time_spent_seconds) as avg_time_seconds
                FROM quiz_questions qq
                LEFT JOIN quiz_responses qr ON qq.id = qr.question_id
                LEFT JOIN quiz_attempts qa ON qr.attempt_id = qa.id AND qa.status = 'completed'
                WHERE qq.quiz_id = ?
                GROUP BY qq.id
                ORDER BY qq.order_index
            ");
            $stmt->execute([$quizId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get answer distribution for multiple choice questions
            foreach ($questions as &$question) {
                if ($question['question_type'] == 'multiple_choice') {
                    $stmt = $this->db->prepare("
                        SELECT 
                            ao.id,
                            ao.answer_text,
                            ao.is_correct,
                            COUNT(qr.id) as selection_count
                        FROM quiz_answer_options ao
                        LEFT JOIN quiz_responses qr ON ao.id = qr.answer_option_id
                        LEFT JOIN quiz_attempts qa ON qr.attempt_id = qa.id AND qa.status = 'completed'
                        WHERE ao.question_id = ?
                        GROUP BY ao.id
                        ORDER BY ao.order_index
                    ");
                    $stmt->execute([$question['question_id']]);
                    $question['answer_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            return $questions;
        } catch (PDOException $e) {
            error_log("QuizAttempt getQuestionAnalytics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent attempts for a user
     */
    public function getUserRecentAttempts($userId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    qa.*,
                    q.title as quiz_title,
                    l.title as lesson_title,
                    c.title as course_title
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN lessons l ON q.lesson_id = l.id
                JOIN courses c ON l.course_id = c.id
                WHERE qa.user_id = ?
                ORDER BY qa.start_time DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("QuizAttempt getUserRecentAttempts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get in-progress attempts for a user
     */
    public function getUserInProgressAttempts($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    qa.*,
                    q.title as quiz_title,
                    q.time_limit_minutes,
                    l.title as lesson_title,
                    l.id as lesson_id,
                    c.id as course_id,
                    c.title as course_title,
                    TIMESTAMPDIFF(SECOND, qa.start_time, NOW()) as elapsed_seconds
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN lessons l ON q.lesson_id = l.id
                JOIN courses c ON l.course_id = c.id
                WHERE qa.user_id = ? AND qa.status = 'in_progress'
                ORDER BY qa.start_time DESC
            ");
            $stmt->execute([$userId]);
            $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check for timed-out attempts
            foreach ($attempts as &$attempt) {
                if ($attempt['time_limit_minutes'] && 
                    $attempt['elapsed_seconds'] > ($attempt['time_limit_minutes'] * 60)) {
                    // Mark as timed out
                    $this->markAsTimedOut($attempt['id']);
                    $attempt['status'] = 'timed_out';
                }
            }
            
            return array_filter($attempts, function($a) {
                return $a['status'] == 'in_progress';
            });
        } catch (PDOException $e) {
            error_log("QuizAttempt getUserInProgressAttempts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark attempt as timed out
     */
    public function markAsTimedOut($attemptId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE quiz_attempts
                SET status = 'timed_out',
                    end_time = CURRENT_TIMESTAMP,
                    time_spent_seconds = time_limit_minutes * 60
                WHERE id = ? AND status = 'in_progress'
            ");
            return $stmt->execute([$attemptId]);
        } catch (PDOException $e) {
            error_log("QuizAttempt markAsTimedOut error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get leaderboard for a quiz
     */
    public function getQuizLeaderboard($quizId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id as user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    u.profile_photo,
                    MAX(qa.score_achieved) as best_score,
                    MIN(qa.time_spent_seconds) as best_time,
                    COUNT(qa.id) as attempts,
                    MAX(qa.end_time) as last_attempt
                FROM quiz_attempts qa
                JOIN users u ON qa.user_id = u.id
                WHERE qa.quiz_id = ? AND qa.status = 'completed'
                GROUP BY u.id
                ORDER BY best_score DESC, best_time ASC
                LIMIT ?
            ");
            $stmt->execute([$quizId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("QuizAttempt getQuizLeaderboard error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get quiz statistics
     */
    public function getQuizStats($quizId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT user_id) as unique_takers,
                    COUNT(*) as total_attempts,
                    AVG(score_achieved) as average_score,
                    MIN(score_achieved) as lowest_score,
                    MAX(score_achieved) as highest_score,
                    AVG(time_spent_seconds) as average_time,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as pass_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END) as abandoned_count
                FROM quiz_attempts
                WHERE quiz_id = ?
            ");
            $stmt->execute([$quizId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate pass rate
            if ($stats['completed_count'] > 0) {
                $stats['pass_rate'] = ($stats['pass_count'] / $stats['completed_count']) * 100;
            } else {
                $stats['pass_rate'] = 0;
            }
            
            return $stats;
        } catch (PDOException $e) {
            error_log("QuizAttempt getQuizStats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Export quiz results to CSV
     */
    public function exportResults($quizId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.first_name,
                    u.last_name,
                    u.email,
                    qa.attempt_number,
                    qa.start_time,
                    qa.end_time,
                    qa.time_spent_seconds,
                    qa.score_achieved,
                    qa.points_earned,
                    qa.total_points,
                    qa.passed,
                    qa.status
                FROM quiz_attempts qa
                JOIN users u ON qa.user_id = u.id
                WHERE qa.quiz_id = ?
                ORDER BY u.last_name, u.first_name, qa.attempt_number
            ");
            $stmt->execute([$quizId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("QuizAttempt exportResults error: " . $e->getMessage());
            return [];
        }
    }
}