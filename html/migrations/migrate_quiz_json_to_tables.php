<?php
/**
 * Migration Script: Convert JSON quiz data to normalized tables
 * 
 * This script migrates existing quiz data from the JSON columns
 * in the lessons table to the new normalized quiz tables.
 */

require_once __DIR__ . '/../config/database.php';

class QuizDataMigration {
    private $pdo;
    private $migratedCount = 0;
    private $errorCount = 0;
    private $errors = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Run the migration
     */
    public function migrate() {
        echo "Starting quiz data migration...\n";
        
        try {
            $this->pdo->beginTransaction();
            
            // Get all lessons with quiz data
            $lessons = $this->getQuizLessons();
            
            if (empty($lessons)) {
                echo "No quiz lessons found to migrate.\n";
                $this->pdo->commit();
                return;
            }
            
            echo "Found " . count($lessons) . " quiz lessons to migrate.\n\n";
            
            foreach ($lessons as $lesson) {
                $this->migrateLesson($lesson);
            }
            
            $this->pdo->commit();
            
            echo "\n\nMigration completed!\n";
            echo "Successfully migrated: {$this->migratedCount} quizzes\n";
            echo "Errors encountered: {$this->errorCount}\n";
            
            if (!empty($this->errors)) {
                echo "\nError details:\n";
                foreach ($this->errors as $error) {
                    echo "- {$error}\n";
                }
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "Migration failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Get all lessons with quiz data
     */
    private function getQuizLessons() {
        $stmt = $this->pdo->prepare("
            SELECT l.*, c.created_by as course_creator
            FROM lessons l
            JOIN courses c ON l.course_id = c.id
            WHERE l.lesson_type = 'quiz' 
            AND l.quiz_data IS NOT NULL
            AND JSON_LENGTH(l.quiz_data) > 0
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Migrate a single lesson's quiz data
     */
    private function migrateLesson($lesson) {
        try {
            $quizData = json_decode($lesson['quiz_data'], true);
            
            if (empty($quizData)) {
                throw new Exception("Invalid quiz data for lesson ID: {$lesson['id']}");
            }
            
            echo "Migrating quiz for lesson: {$lesson['title']} (ID: {$lesson['id']})\n";
            
            // Create quiz record
            $quizId = $this->createQuiz($lesson, $quizData);
            
            // Migrate questions
            if (!empty($quizData['questions'])) {
                $this->migrateQuestions($quizId, $quizData['questions']);
            }
            
            // Migrate existing attempts from lesson_progress
            $this->migrateAttempts($lesson['id'], $quizId);
            
            $this->migratedCount++;
            echo "  ✓ Successfully migrated\n";
            
        } catch (Exception $e) {
            $this->errorCount++;
            $this->errors[] = "Lesson {$lesson['id']}: " . $e->getMessage();
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Create quiz record
     */
    private function createQuiz($lesson, $quizData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO quizzes (
                lesson_id, title, description, instructions,
                passing_score, max_attempts, time_limit_minutes,
                shuffle_questions, shuffle_answers,
                show_correct_answers, show_score_immediately,
                allow_review, created_by, created_at, updated_at
            ) VALUES (
                :lesson_id, :title, :description, :instructions,
                :passing_score, :max_attempts, :time_limit,
                :shuffle_questions, :shuffle_answers,
                :show_correct_answers, :show_score_immediately,
                :allow_review, :created_by, :created_at, :updated_at
            )
        ");
        
        $stmt->execute([
            'lesson_id' => $lesson['id'],
            'title' => $quizData['title'] ?? $lesson['title'],
            'description' => $quizData['description'] ?? $lesson['description'],
            'instructions' => $quizData['instructions'] ?? null,
            'passing_score' => $quizData['passing_score'] ?? 70.00,
            'max_attempts' => $quizData['max_attempts'] ?? 3,
            'time_limit' => $quizData['time_limit'] ?? null,
            'shuffle_questions' => $quizData['shuffle_questions'] ?? 0,
            'shuffle_answers' => $quizData['shuffle_answers'] ?? 0,
            'show_correct_answers' => $quizData['show_correct_answers'] ?? 1,
            'show_score_immediately' => $quizData['show_score_immediately'] ?? 1,
            'allow_review' => $quizData['allow_review'] ?? 1,
            'created_by' => $lesson['created_by'],
            'created_at' => $lesson['created_at'],
            'updated_at' => $lesson['updated_at']
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Migrate questions for a quiz
     */
    private function migrateQuestions($quizId, $questions) {
        $questionStmt = $this->pdo->prepare("
            INSERT INTO quiz_questions (
                quiz_id, question_text, question_type,
                explanation, hint, points, order_index,
                is_required, created_at, updated_at
            ) VALUES (
                :quiz_id, :question_text, :question_type,
                :explanation, :hint, :points, :order_index,
                :is_required, NOW(), NOW()
            )
        ");
        
        $answerStmt = $this->pdo->prepare("
            INSERT INTO quiz_answer_options (
                question_id, answer_text, is_correct,
                feedback, order_index, created_at
            ) VALUES (
                :question_id, :answer_text, :is_correct,
                :feedback, :order_index, NOW()
            )
        ");
        
        foreach ($questions as $index => $question) {
            // Determine question type
            $questionType = $this->mapQuestionType($question);
            
            // Insert question
            $questionStmt->execute([
                'quiz_id' => $quizId,
                'question_text' => $question['text'] ?? $question['question'] ?? '',
                'question_type' => $questionType,
                'explanation' => $question['explanation'] ?? null,
                'hint' => $question['hint'] ?? null,
                'points' => $question['points'] ?? 1.00,
                'order_index' => $question['order'] ?? $index,
                'is_required' => $question['required'] ?? 1
            ]);
            
            $questionId = $this->pdo->lastInsertId();
            
            // Insert answer options
            $this->migrateAnswerOptions($questionId, $question, $questionType, $answerStmt);
        }
    }

    /**
     * Map old question types to new enum values
     */
    private function mapQuestionType($question) {
        $type = strtolower($question['type'] ?? 'multiple_choice');
        
        $typeMap = [
            'multiple-choice' => 'multiple_choice',
            'multiple_choice' => 'multiple_choice',
            'true-false' => 'true_false',
            'true_false' => 'true_false',
            'short-answer' => 'short_answer',
            'short_answer' => 'short_answer',
            'essay' => 'essay',
            'matching' => 'matching',
            'fill-blank' => 'fill_blank',
            'fill_blank' => 'fill_blank'
        ];
        
        return $typeMap[$type] ?? 'multiple_choice';
    }

    /**
     * Migrate answer options for a question
     */
    private function migrateAnswerOptions($questionId, $question, $questionType, $stmt) {
        // Handle different answer formats
        if ($questionType === 'true_false') {
            // Create True/False options
            $correctAnswer = $question['correct_answer'] ?? $question['answer'] ?? 'true';
            
            $stmt->execute([
                'question_id' => $questionId,
                'answer_text' => 'True',
                'is_correct' => (strtolower($correctAnswer) === 'true') ? 1 : 0,
                'feedback' => null,
                'order_index' => 0
            ]);
            
            $stmt->execute([
                'question_id' => $questionId,
                'answer_text' => 'False',
                'is_correct' => (strtolower($correctAnswer) === 'false') ? 1 : 0,
                'feedback' => null,
                'order_index' => 1
            ]);
            
        } elseif ($questionType === 'multiple_choice') {
            // Handle multiple choice options
            $options = $question['options'] ?? $question['answers'] ?? [];
            $correctAnswer = $question['correct_answer'] ?? $question['correct'] ?? null;
            
            foreach ($options as $index => $option) {
                $isCorrect = 0;
                $answerText = '';
                $feedback = null;
                
                if (is_array($option)) {
                    $answerText = $option['text'] ?? $option['answer'] ?? '';
                    $isCorrect = $option['is_correct'] ?? ($option['correct'] ?? false) ? 1 : 0;
                    $feedback = $option['feedback'] ?? null;
                } else {
                    $answerText = $option;
                    $isCorrect = ($correctAnswer !== null && $option === $correctAnswer) ? 1 : 0;
                }
                
                $stmt->execute([
                    'question_id' => $questionId,
                    'answer_text' => $answerText,
                    'is_correct' => $isCorrect,
                    'feedback' => $feedback,
                    'order_index' => $index
                ]);
            }
            
        } elseif ($questionType === 'short_answer') {
            // Store acceptable answers
            $correctAnswers = [];
            
            if (isset($question['correct_answer'])) {
                $correctAnswers[] = $question['correct_answer'];
            }
            if (isset($question['correct_answers']) && is_array($question['correct_answers'])) {
                $correctAnswers = array_merge($correctAnswers, $question['correct_answers']);
            }
            
            foreach ($correctAnswers as $index => $answer) {
                $stmt->execute([
                    'question_id' => $questionId,
                    'answer_text' => $answer,
                    'is_correct' => 1,
                    'feedback' => null,
                    'order_index' => $index
                ]);
            }
        }
        // Essay and other types don't have predefined answers
    }

    /**
     * Migrate existing quiz attempts from lesson_progress
     */
    private function migrateAttempts($lessonId, $quizId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM lesson_progress 
            WHERE lesson_id = :lesson_id 
            AND quiz_responses IS NOT NULL
            AND JSON_LENGTH(quiz_responses) > 0
        ");
        $stmt->execute(['lesson_id' => $lessonId]);
        $progressRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($progressRecords as $progress) {
            try {
                $this->migrateProgressRecord($progress, $quizId);
            } catch (Exception $e) {
                // Log error but continue with other records
                $this->errors[] = "Failed to migrate attempts for user {$progress['user_id']}: " . $e->getMessage();
            }
        }
    }

    /**
     * Migrate a single progress record's attempts
     */
    private function migrateProgressRecord($progress, $quizId) {
        $responses = json_decode($progress['quiz_responses'], true);
        
        if (empty($responses) || !is_array($responses)) {
            return;
        }
        
        // Handle both single attempt and multiple attempts formats
        $attempts = isset($responses['attempts']) ? $responses['attempts'] : [$responses];
        
        foreach ($attempts as $attemptData) {
            $this->createAttemptFromData($quizId, $progress, $attemptData);
        }
    }

    /**
     * Create attempt record from migrated data
     */
    private function createAttemptFromData($quizId, $progress, $attemptData) {
        // Insert attempt
        $stmt = $this->pdo->prepare("
            INSERT INTO quiz_attempts (
                quiz_id, user_id, lesson_progress_id,
                attempt_number, start_time, end_time,
                time_spent_seconds, score_achieved,
                points_earned, total_points, status, passed
            ) VALUES (
                :quiz_id, :user_id, :lesson_progress_id,
                :attempt_number, :start_time, :end_time,
                :time_spent_seconds, :score_achieved,
                :points_earned, :total_points, :status, :passed
            )
        ");
        
        $startTime = $attemptData['start_time'] ?? $attemptData['started_at'] ?? $progress['started_at'];
        $endTime = $attemptData['end_time'] ?? $attemptData['completed_at'] ?? $progress['completed_at'];
        $score = $attemptData['score'] ?? $progress['score'] ?? 0;
        
        $stmt->execute([
            'quiz_id' => $quizId,
            'user_id' => $progress['user_id'],
            'lesson_progress_id' => $progress['id'],
            'attempt_number' => $attemptData['attempt_number'] ?? $progress['attempts'] ?? 1,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'time_spent_seconds' => $attemptData['time_taken'] ?? null,
            'score_achieved' => $score,
            'points_earned' => $attemptData['points_earned'] ?? null,
            'total_points' => $attemptData['total_points'] ?? null,
            'status' => 'completed',
            'passed' => $score >= 70 ? 1 : 0
        ]);
        
        $attemptId = $this->pdo->lastInsertId();
        
        // Migrate individual responses if available
        if (!empty($attemptData['responses'])) {
            $this->migrateResponses($attemptId, $quizId, $attemptData['responses']);
        }
    }

    /**
     * Migrate individual question responses
     */
    private function migrateResponses($attemptId, $quizId, $responses) {
        // Get question mapping
        $stmt = $this->pdo->prepare("
            SELECT id, order_index FROM quiz_questions 
            WHERE quiz_id = :quiz_id 
            ORDER BY order_index
        ");
        $stmt->execute(['quiz_id' => $quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $responseStmt = $this->pdo->prepare("
            INSERT INTO quiz_responses (
                attempt_id, question_id, answer_text,
                is_correct, points_earned, answered_at
            ) VALUES (
                :attempt_id, :question_id, :answer_text,
                :is_correct, :points_earned, NOW()
            )
        ");
        
        foreach ($responses as $index => $response) {
            if (is_array($response)) {
                $questionId = $questions[$index] ?? null;
                
                if ($questionId) {
                    $responseStmt->execute([
                        'attempt_id' => $attemptId,
                        'question_id' => $questionId,
                        'answer_text' => $response['answer'] ?? json_encode($response),
                        'is_correct' => $response['is_correct'] ?? null,
                        'points_earned' => $response['points'] ?? 0
                    ]);
                }
            }
        }
    }
}

// Run the migration
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $migration = new QuizDataMigration($pdo);
    $migration->migrate();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}