<?php
/**
 * Quiz Class
 * 
 * Handles quiz-related operations using the normalized database tables
 */

class Quiz {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: getDB();
    }
    
    /**
     * Get a quiz by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT q.*, l.title as lesson_title, l.course_id
                FROM quizzes q
                JOIN lessons l ON q.lesson_id = l.id
                WHERE q.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Quiz getById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get a quiz by lesson ID
     */
    public function getByLessonId($lessonId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM quizzes WHERE lesson_id = ?
            ");
            $stmt->execute([$lessonId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Quiz getByLessonId error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a new quiz
     */
    public function create($lessonId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO quizzes (
                    lesson_id, title, description, instructions,
                    passing_score, max_attempts, time_limit_minutes,
                    shuffle_questions, shuffle_answers,
                    show_correct_answers, show_score_immediately,
                    allow_review, created_by
                ) VALUES (
                    :lesson_id, :title, :description, :instructions,
                    :passing_score, :max_attempts, :time_limit,
                    :shuffle_questions, :shuffle_answers,
                    :show_correct_answers, :show_score_immediately,
                    :allow_review, :created_by
                )
            ");
            
            $result = $stmt->execute([
                'lesson_id' => $lessonId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'passing_score' => $data['passing_score'] ?? 70.00,
                'max_attempts' => $data['max_attempts'] ?? 3,
                'time_limit' => $data['time_limit'] ?? null,
                'shuffle_questions' => $data['shuffle_questions'] ?? 0,
                'shuffle_answers' => $data['shuffle_answers'] ?? 0,
                'show_correct_answers' => $data['show_correct_answers'] ?? 1,
                'show_score_immediately' => $data['show_score_immediately'] ?? 1,
                'allow_review' => $data['allow_review'] ?? 1,
                'created_by' => $_SESSION['user_id']
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Quiz create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a quiz
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $params = ['id' => $id];
            
            $allowedFields = [
                'title', 'description', 'instructions',
                'passing_score', 'max_attempts', 'time_limit_minutes',
                'shuffle_questions', 'shuffle_answers',
                'show_correct_answers', 'show_score_immediately',
                'allow_review'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return true;
            }
            
            $sql = "UPDATE quizzes SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Quiz update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a quiz
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM quizzes WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Quiz delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all questions for a quiz
     */
    public function getQuestions($quizId, $shuffle = false) {
        try {
            $orderBy = $shuffle ? "RAND()" : "order_index ASC";
            
            $stmt = $this->db->prepare("
                SELECT q.*, 
                       COUNT(DISTINCT ao.id) as answer_count
                FROM quiz_questions q
                LEFT JOIN quiz_answer_options ao ON q.id = ao.question_id
                WHERE q.quiz_id = ?
                GROUP BY q.id
                ORDER BY $orderBy
            ");
            $stmt->execute([$quizId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get answer options for each question
            foreach ($questions as &$question) {
                $question['answers'] = $this->getAnswerOptions($question['id'], $shuffle);
            }
            
            return $questions;
        } catch (PDOException $e) {
            error_log("Quiz getQuestions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get answer options for a question
     */
    public function getAnswerOptions($questionId, $shuffle = false) {
        try {
            $orderBy = $shuffle ? "RAND()" : "order_index ASC";
            
            $stmt = $this->db->prepare("
                SELECT * FROM quiz_answer_options 
                WHERE question_id = ?
                ORDER BY $orderBy
            ");
            $stmt->execute([$questionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Quiz getAnswerOptions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add a question to a quiz
     */
    public function addQuestion($quizId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO quiz_questions (
                    quiz_id, question_text, question_type,
                    explanation, hint, points, order_index, is_required
                ) VALUES (
                    :quiz_id, :question_text, :question_type,
                    :explanation, :hint, :points, :order_index, :is_required
                )
            ");
            
            $result = $stmt->execute([
                'quiz_id' => $quizId,
                'question_text' => $data['question_text'],
                'question_type' => $data['question_type'],
                'explanation' => $data['explanation'] ?? null,
                'hint' => $data['hint'] ?? null,
                'points' => $data['points'] ?? 1.00,
                'order_index' => $data['order_index'] ?? 0,
                'is_required' => $data['is_required'] ?? 1
            ]);
            
            if ($result) {
                $questionId = $this->db->lastInsertId();
                
                // Add answer options if provided
                if (!empty($data['answers'])) {
                    foreach ($data['answers'] as $index => $answer) {
                        $this->addAnswerOption($questionId, $answer, $index);
                    }
                }
                
                return $questionId;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Quiz addQuestion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add an answer option to a question
     */
    public function addAnswerOption($questionId, $data, $orderIndex = 0) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO quiz_answer_options (
                    question_id, answer_text, is_correct,
                    feedback, order_index
                ) VALUES (
                    :question_id, :answer_text, :is_correct,
                    :feedback, :order_index
                )
            ");
            
            return $stmt->execute([
                'question_id' => $questionId,
                'answer_text' => $data['answer_text'] ?? $data,
                'is_correct' => $data['is_correct'] ?? 0,
                'feedback' => $data['feedback'] ?? null,
                'order_index' => $data['order_index'] ?? $orderIndex
            ]);
        } catch (PDOException $e) {
            error_log("Quiz addAnswerOption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a question
     */
    public function updateQuestion($id, $data) {
        try {
            $fields = [];
            $params = ['id' => $id];
            
            $allowedFields = [
                'question_text', 'question_type', 'explanation',
                'hint', 'points', 'order_index', 'is_required'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return true;
            }
            
            $sql = "UPDATE quiz_questions SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Quiz updateQuestion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a question
     */
    public function deleteQuestion($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM quiz_questions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Quiz deleteQuestion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reorder questions
     */
    public function reorderQuestions($quizId, $questionIds) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                UPDATE quiz_questions 
                SET order_index = ? 
                WHERE id = ? AND quiz_id = ?
            ");
            
            foreach ($questionIds as $index => $questionId) {
                $stmt->execute([$index, $questionId, $quizId]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Quiz reorderQuestions error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's quiz attempts
     */
    public function getUserAttempts($quizId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM quiz_attempts
                WHERE quiz_id = ? AND user_id = ?
                ORDER BY start_time DESC
            ");
            $stmt->execute([$quizId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Quiz getUserAttempts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get latest attempt for a user
     */
    public function getLatestAttempt($quizId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM quiz_attempts
                WHERE quiz_id = ? AND user_id = ?
                ORDER BY start_time DESC
                LIMIT 1
            ");
            $stmt->execute([$quizId, $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Quiz getLatestAttempt error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Start a new quiz attempt
     */
    public function startAttempt($quizId, $userId, $lessonProgressId = null) {
        try {
            // Get attempt number
            $stmt = $this->db->prepare("
                SELECT COUNT(*) + 1 as attempt_number
                FROM quiz_attempts
                WHERE quiz_id = ? AND user_id = ?
            ");
            $stmt->execute([$quizId, $userId]);
            $attemptNumber = $stmt->fetchColumn();
            
            // Get quiz total points (count questions if total_points doesn't exist)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) * 1.0 as total_points 
                FROM quiz_questions 
                WHERE quiz_id = ?
            ");
            $stmt->execute([$quizId]);
            $totalPoints = $stmt->fetchColumn() ?: 0;
            
            // Create attempt
            $stmt = $this->db->prepare("
                INSERT INTO quiz_attempts (
                    quiz_id, user_id, lesson_progress_id,
                    attempt_number, total_points, ip_address, user_agent
                ) VALUES (
                    :quiz_id, :user_id, :lesson_progress_id,
                    :attempt_number, :total_points, :ip_address, :user_agent
                )
            ");
            
            $result = $stmt->execute([
                'quiz_id' => $quizId,
                'user_id' => $userId,
                'lesson_progress_id' => $lessonProgressId,
                'attempt_number' => $attemptNumber,
                'total_points' => $totalPoints,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Quiz startAttempt error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Submit quiz response
     */
    public function submitResponse($attemptId, $questionId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO quiz_responses (
                    attempt_id, question_id, answer_option_id,
                    answer_text, time_spent_seconds
                ) VALUES (
                    :attempt_id, :question_id, :answer_option_id,
                    :answer_text, :time_spent
                )
                ON DUPLICATE KEY UPDATE
                    answer_option_id = VALUES(answer_option_id),
                    answer_text = VALUES(answer_text),
                    time_spent_seconds = VALUES(time_spent_seconds),
                    answered_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                'attempt_id' => $attemptId,
                'question_id' => $questionId,
                'answer_option_id' => $data['answer_option_id'] ?? null,
                'answer_text' => $data['answer_text'] ?? null,
                'time_spent' => $data['time_spent'] ?? 0
            ]);
        } catch (PDOException $e) {
            error_log("Quiz submitResponse error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Complete and score a quiz attempt
     */
    public function completeAttempt($attemptId) {
        try {
            // Check if a transaction is already active
            $transactionActive = $this->db->inTransaction();
            if (!$transactionActive) {
                $this->db->beginTransaction();
            }
            
            // Get attempt details
            $stmt = $this->db->prepare("
                SELECT qa.*, q.passing_score
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                WHERE qa.id = ?
            ");
            $stmt->execute([$attemptId]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attempt) {
                throw new Exception("Attempt not found");
            }
            
            // Score the attempt
            $stmt = $this->db->prepare("
                SELECT 
                    r.id as response_id,
                    r.question_id,
                    r.answer_option_id,
                    r.answer_text,
                    q.question_type,
                    q.points,
                    ao.is_correct
                FROM quiz_responses r
                JOIN quiz_questions q ON r.question_id = q.id
                LEFT JOIN quiz_answer_options ao ON r.answer_option_id = ao.id
                WHERE r.attempt_id = ?
            ");
            $stmt->execute([$attemptId]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPoints = 0;
            $pointsEarned = 0;
            
            foreach ($responses as $response) {
                $totalPoints += $response['points'];
                $isCorrect = false;
                $points = 0;
                
                // Score based on question type
                switch ($response['question_type']) {
                    case 'multiple_choice':
                    case 'true_false':
                        $isCorrect = $response['is_correct'] == 1;
                        $points = $isCorrect ? $response['points'] : 0;
                        break;
                        
                        
                    default:
                        // Essay and other types need manual grading
                        continue 2;
                }
                
                $pointsEarned += $points;
                
                // Update response scoring
                $stmt = $this->db->prepare("
                    UPDATE quiz_responses
                    SET is_correct = ?, points_earned = ?
                    WHERE id = ?
                ");
                $stmt->execute([$isCorrect ? 1 : 0, $points, $response['response_id']]);
            }
            
            // Calculate score percentage
            $scorePercentage = $totalPoints > 0 ? ($pointsEarned / $totalPoints) * 100 : 0;
            $passed = $scorePercentage >= $attempt['passing_score'];
            
            // Update attempt with final score
            $stmt = $this->db->prepare("
                UPDATE quiz_attempts
                SET end_time = CURRENT_TIMESTAMP,
                    time_spent_seconds = TIMESTAMPDIFF(SECOND, start_time, CURRENT_TIMESTAMP),
                    score_achieved = ?,
                    points_earned = ?,
                    status = 'completed',
                    passed = ?
                WHERE id = ?
            ");
            $stmt->execute([$scorePercentage, $pointsEarned, $passed ? 1 : 0, $attemptId]);
            
            // Update lesson progress if linked
            if ($attempt['lesson_progress_id']) {
                $stmt = $this->db->prepare("
                    UPDATE lesson_progress
                    SET score = ?,
                        status = 'completed',
                        completed_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND score < ?
                ");
                $stmt->execute([$scorePercentage, $attempt['lesson_progress_id'], $scorePercentage]);
            }
            
            // Only commit if we started the transaction
            if (!$transactionActive) {
                $this->db->commit();
            }
            
            return [
                'score' => $scorePercentage,
                'points_earned' => $pointsEarned,
                'total_points' => $totalPoints,
                'passed' => $passed
            ];
            
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if (!$transactionActive && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Quiz completeAttempt error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get attempt details with responses
     */
    public function getAttemptDetails($attemptId) {
        try {
            $stmt = $this->db->prepare("
                SELECT qa.*, q.title as quiz_title, q.allow_review
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                WHERE qa.id = ?
            ");
            $stmt->execute([$attemptId]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attempt) {
                return null;
            }
            
            // Get responses
            $stmt = $this->db->prepare("
                SELECT 
                    r.*,
                    q.question_text,
                    q.question_type,
                    q.explanation,
                    q.points as max_points,
                    ao.answer_text as selected_answer,
                    ao.feedback
                FROM quiz_responses r
                JOIN quiz_questions q ON r.question_id = q.id
                LEFT JOIN quiz_answer_options ao ON r.answer_option_id = ao.id
                WHERE r.attempt_id = ?
                ORDER BY q.order_index
            ");
            $stmt->execute([$attemptId]);
            $attempt['responses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get correct answers for each question
            foreach ($attempt['responses'] as &$response) {
                if ($response['question_type'] == 'multiple_choice' || $response['question_type'] == 'true_false') {
                    $stmt = $this->db->prepare("
                        SELECT answer_text 
                        FROM quiz_answer_options
                        WHERE question_id = ? AND is_correct = 1
                    ");
                    $stmt->execute([$response['question_id']]);
                    $response['correct_answer'] = $stmt->fetchColumn();
                }
            }
            
            return $attempt;
        } catch (PDOException $e) {
            error_log("Quiz getAttemptDetails error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if user can take quiz
     */
    public function canTakeQuiz($quizId, $userId) {
        try {
            // Get quiz settings
            $stmt = $this->db->prepare("
                SELECT max_attempts FROM quizzes WHERE id = ?
            ");
            $stmt->execute([$quizId]);
            $maxAttempts = $stmt->fetchColumn();
            
            if (!$maxAttempts) {
                return true; // Unlimited attempts
            }
            
            // Count user attempts
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM quiz_attempts
                WHERE quiz_id = ? AND user_id = ? AND status = 'completed'
            ");
            $stmt->execute([$quizId, $userId]);
            $attemptCount = $stmt->fetchColumn();
            
            return $attemptCount < $maxAttempts;
        } catch (PDOException $e) {
            error_log("Quiz canTakeQuiz error: " . $e->getMessage());
            return false;
        }
    }
}