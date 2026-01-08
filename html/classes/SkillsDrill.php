<?php
require_once __DIR__ . '/../config/database.php';

class SkillsDrill {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get drill by ID
     */
    public function getById($drillId) {
        $sql = "SELECT sd.*, l.title as lesson_title, l.course_id 
                FROM skills_drills sd
                JOIN lessons l ON sd.lesson_id = l.id
                WHERE sd.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$drillId]);
        return $stmt->fetch();
    }
    
    /**
     * Get drill by lesson ID
     */
    public function getByLessonId($lessonId) {
        $sql = "SELECT * FROM skills_drills WHERE lesson_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lessonId]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new drill
     */
    public function create($data) {
        $sql = "INSERT INTO skills_drills 
                (lesson_id, title, description, instructions, 
                 min_questions_per_session, max_questions_per_session,
                 shuffle_questions, shuffle_answers, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['lesson_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['instructions'] ?? null,
            $data['min_questions_per_session'] ?? 10,
            $data['max_questions_per_session'] ?? 20,
            $data['shuffle_questions'] ?? 1,
            $data['shuffle_answers'] ?? 1,
            $data['created_by']
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    /**
     * Add a question to a drill
     */
    public function addQuestion($data) {
        $sql = "INSERT INTO skills_drill_questions 
                (drill_id, question_text, difficulty_level, hint, explanation)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['drill_id'],
            $data['question_text'],
            $data['difficulty_level'] ?? 'medium',
            $data['hint'] ?? null,
            $data['explanation'] ?? null
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    /**
     * Add an answer option to a question
     */
    public function addAnswerOption($data) {
        $sql = "INSERT INTO skills_drill_answer_options 
                (question_id, answer_text, is_correct, feedback, order_index)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['question_id'],
            $data['answer_text'],
            $data['is_correct'] ?? 0,
            $data['feedback'] ?? null,
            $data['order_index'] ?? 0
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    /**
     * Get drill questions
     */
    public function getQuestions($drillId, $limit = null, $shuffle = true) {
        $sql = "SELECT * FROM skills_drill_questions WHERE drill_id = ?";
        if ($shuffle) {
            $sql .= " ORDER BY RAND()";
        }
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$drillId]);
        $questions = $stmt->fetchAll();
        
        // Get answer options for each question
        foreach ($questions as &$question) {
            $question['options'] = $this->getAnswerOptions($question['id'], $shuffle);
        }
        
        return $questions;
    }
    
    /**
     * Get answer options for a question
     */
    public function getAnswerOptions($questionId, $shuffle = true) {
        $sql = "SELECT * FROM skills_drill_answer_options WHERE question_id = ?";
        if ($shuffle) {
            $sql .= " ORDER BY RAND()";
        } else {
            $sql .= " ORDER BY order_index";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$questionId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Start a new drill session
     */
    public function startSession($drillId, $userId) {
        $sql = "INSERT INTO skills_drill_sessions 
                (drill_id, user_id, ip_address, user_agent)
                VALUES (?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $drillId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    /**
     * Submit an answer for a question
     */
    public function submitAnswer($sessionId, $questionId, $answerOptionId) {
        // Get current attempt number for this question in this session
        $sql = "SELECT MAX(attempt_number) as max_attempt 
                FROM skills_drill_responses 
                WHERE session_id = ? AND question_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId, $questionId]);
        $result = $stmt->fetch();
        $attemptNumber = ($result['max_attempt'] ?? 0) + 1;
        
        // Check if answer is correct
        $sql = "SELECT is_correct FROM skills_drill_answer_options WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$answerOptionId]);
        $answer = $stmt->fetch();
        $isCorrect = $answer ? $answer['is_correct'] : 0;
        
        // Calculate points based on attempt number
        $points = $this->calculatePoints($attemptNumber, $isCorrect);
        
        // Insert response
        $sql = "INSERT INTO skills_drill_responses 
                (session_id, question_id, attempt_number, answer_option_id, is_correct, points_earned)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $sessionId,
            $questionId,
            $attemptNumber,
            $answerOptionId,
            $isCorrect,
            $points
        ]);
        
        if ($result) {
            // Update session stats
            $this->updateSessionStats($sessionId);
            
            return [
                'attempt_number' => $attemptNumber,
                'is_correct' => $isCorrect,
                'points_earned' => $points
            ];
        }
        
        return false;
    }
    
    /**
     * Calculate points based on attempt number
     */
    private function calculatePoints($attemptNumber, $isCorrect) {
        if (!$isCorrect) {
            return 0;
        }
        
        switch ($attemptNumber) {
            case 1:
                return 1;
            case 2:
                return 0;
            case 3:
                return -0.5;
            default:
                return -1;
        }
    }
    
    /**
     * Update session statistics
     */
    private function updateSessionStats($sessionId) {
        // Count questions presented
        $sql = "SELECT COUNT(DISTINCT question_id) as questions_presented,
                       COUNT(DISTINCT CASE WHEN is_correct = 1 THEN question_id END) as questions_answered,
                       SUM(points_earned) as total_points
                FROM skills_drill_responses
                WHERE session_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        $stats = $stmt->fetch();
        
        // Update session
        $sql = "UPDATE skills_drill_sessions 
                SET questions_presented = ?,
                    questions_answered = ?,
                    total_points = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $stats['questions_presented'],
            $stats['questions_answered'],
            $stats['total_points'] ?? 0,
            $sessionId
        ]);
    }
    
    /**
     * Complete a session
     */
    public function completeSession($sessionId) {
        $sql = "UPDATE skills_drill_sessions 
                SET status = 'completed', 
                    end_time = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$sessionId]);
        
        if ($result) {
            // Update user stats
            $this->updateUserStats($sessionId);
        }
        
        return $result;
    }
    
    /**
     * Update user statistics for drills
     */
    private function updateUserStats($sessionId) {
        // Get session info
        $sql = "SELECT user_id, drill_id, total_points, questions_answered 
                FROM skills_drill_sessions 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        
        if (!$session) {
            return;
        }
        
        // Check if user stats exist
        $sql = "SELECT id FROM user_skills_drill_stats 
                WHERE user_id = ? AND drill_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$session['user_id'], $session['drill_id']]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing stats
            $sql = "UPDATE user_skills_drill_stats 
                    SET total_sessions = total_sessions + 1,
                        total_questions_answered = total_questions_answered + ?,
                        total_points = total_points + ?,
                        best_session_points = GREATEST(best_session_points, ?),
                        last_session_date = NOW()
                    WHERE user_id = ? AND drill_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $session['questions_answered'],
                $session['total_points'],
                $session['total_points'],
                $session['user_id'],
                $session['drill_id']
            ]);
        } else {
            // Create new stats record
            $sql = "INSERT INTO user_skills_drill_stats 
                    (user_id, drill_id, total_sessions, total_questions_answered, 
                     total_points, best_session_points, last_session_date)
                    VALUES (?, ?, 1, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $session['user_id'],
                $session['drill_id'],
                $session['questions_answered'],
                $session['total_points'],
                $session['total_points']
            ]);
        }
    }
    
    /**
     * Get user statistics for a drill
     */
    public function getUserStats($userId, $drillId) {
        $sql = "SELECT * FROM user_skills_drill_stats 
                WHERE user_id = ? AND drill_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $drillId]);
        return $stmt->fetch();
    }
    
    /**
     * Get recent sessions for a user
     */
    public function getUserSessions($userId, $drillId = null, $limit = 10) {
        $sql = "SELECT s.*, d.title as drill_title, l.title as lesson_title
                FROM skills_drill_sessions s
                JOIN skills_drills d ON s.drill_id = d.id
                JOIN lessons l ON d.lesson_id = l.id
                WHERE s.user_id = ?";
        
        $params = [$userId];
        
        if ($drillId) {
            $sql .= " AND s.drill_id = ?";
            $params[] = $drillId;
        }
        
        $sql .= " ORDER BY s.start_time DESC LIMIT " . intval($limit);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Save drill questions from array
     */
    public function saveQuestions($drillId, $questions) {
        $this->db->beginTransaction();
        
        try {
            foreach ($questions as $question) {
                // Insert question
                $sql = "INSERT INTO skills_drill_questions 
                        (drill_id, question_text, explanation, hint, difficulty_level)
                        VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $drillId,
                    $question['question_text'],
                    $question['explanation'] ?? null,
                    $question['hint'] ?? null,
                    $question['difficulty_level'] ?? 'medium'
                ]);
                
                $questionId = $this->db->lastInsertId();
                
                // Insert answer options
                if (isset($question['options']) && is_array($question['options'])) {
                    foreach ($question['options'] as $index => $option) {
                        $sql = "INSERT INTO skills_drill_answer_options 
                                (question_id, answer_text, is_correct, feedback, order_index)
                                VALUES (?, ?, ?, ?, ?)";
                        
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            $questionId,
                            $option['answer_text'],
                            $option['is_correct'] ?? 0,
                            $option['feedback'] ?? null,
                            $index
                        ]);
                    }
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}