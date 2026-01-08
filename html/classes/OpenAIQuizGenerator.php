<?php

class OpenAIQuizGenerator {
    private $config;
    private $apiKey;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/openai.php';
        $this->apiKey = $this->config['api_key'];
    }
    
    /**
     * Generate quiz questions from a transcript
     */
    public function generateQuizFromTranscript($transcript, $lessonTitle = '') {
        try {
            if (empty($transcript)) {
                throw new Exception("Transcript is empty");
            }
            
            $prompt = $this->buildPrompt($transcript, $lessonTitle);
            $response = $this->callOpenAI($prompt);
            
            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                return $this->parseQuizResponse($content);
            } else {
                throw new Exception("Invalid response from OpenAI");
            }
            
        } catch (Exception $e) {
            error_log("Quiz generation error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Build the prompt for OpenAI
     */
    private function buildPrompt($transcript, $lessonTitle) {
        $prompt = "I have a transcript from a video. I would like you to generate 10 to 15 multiple choice questions that test comprehension and key ideas from the transcript. Each question should have 2 to 4 answer options, with at least two plausible distractors (incorrect answers that are believable and not easily eliminated).\n\n";
        
        $prompt .= "The tone and wording of the questions should resemble those found in school textbooks — formal, clear, and free from conversational or transcript-based language. Avoid phrasing like \"What did the speaker mean...\" or \"According to the transcript...\".\n\n";
        
        $prompt .= "Focus on testing understanding of important concepts, facts, implications, and reasoning presented in the material. Do not force 10–15 questions if the content does not support that many. Prioritize quality and depth over quantity.\n\n";
        
        if ($lessonTitle) {
            $prompt .= "LESSON TITLE: {$lessonTitle}\n\n";
        }
        
        $prompt .= "TRANSCRIPT:\n{$transcript}\n\n";
        
        $prompt .= "FORMAT YOUR RESPONSE EXACTLY AS FOLLOWS:\n\n";
        $prompt .= "QUESTION 1: [Question text]\n";
        $prompt .= "A) [Choice A]\n";
        $prompt .= "B) [Choice B]\n";
        $prompt .= "C) [Choice C]\n";
        $prompt .= "D) [Choice D]\n";
        $prompt .= "CORRECT: [A/B/C/D]\n";
        $prompt .= "EXPLANATION: [Brief explanation]\n\n";
        $prompt .= "QUESTION 2: [Question text]\n";
        $prompt .= "...(continue for all questions)";
        
        return $prompt;
    }
    
    /**
     * Call the OpenAI API
     */
    private function callOpenAI($prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->config['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->config['quiz_settings']['system_prompt']
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $this->config['max_tokens'],
            'temperature' => $this->config['temperature']
        ];
        
        $options = [
            'http' => [
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey
                ],
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 60
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception("OpenAI API error: " . ($error['message'] ?? 'Unknown error'));
        }
        
        // Check HTTP response code
        if (isset($http_response_header)) {
            $status_line = $http_response_header[0];
            preg_match('{HTTP/\S+\s+(\d{3})}', $status_line, $match);
            $http_code = intval($match[1]);
            
            if ($http_code !== 200) {
                throw new Exception("OpenAI API error: HTTP {$http_code} - {$response}");
            }
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Parse the quiz response from OpenAI
     */
    private function parseQuizResponse($content) {
        $questions = [];
        
        // Split by "QUESTION" to get individual questions
        $parts = preg_split('/QUESTION\s+(\d+):\s*/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        for ($i = 0; $i < count($parts) - 1; $i += 2) {
            $questionNum = $parts[$i];
            $questionContent = $parts[$i + 1];
            
            // Parse the question text
            if (preg_match('/^(.+?)(?=\n[A-D]\))/s', $questionContent, $questionMatch)) {
                $questionText = trim($questionMatch[1]);
                
                // Parse all available choices (2-4 options)
                $choices = [];
                if (preg_match_all('/([A-D])\)\s*(.+?)(?=\n[A-D]\)|\nCORRECT:)/s', $questionContent, $choiceMatches, PREG_SET_ORDER)) {
                    foreach ($choiceMatches as $match) {
                        $choices[$match[1]] = trim($match[2]);
                    }
                }
                
                // Parse correct answer and explanation
                $correctAnswer = '';
                $explanation = '';
                if (preg_match('/CORRECT:\s*([A-D])\s*\nEXPLANATION:\s*(.+?)(?=\n\n|\z)/si', $questionContent, $answerMatch)) {
                    $correctAnswer = strtoupper(trim($answerMatch[1]));
                    $explanation = trim($answerMatch[2]);
                }
                
                // Only add question if we have at least 2 choices and a correct answer
                if (count($choices) >= 2 && $correctAnswer && isset($choices[$correctAnswer])) {
                    // Ensure we always have 4 choices for database compatibility
                    // Fill missing choices with empty strings
                    $fullChoices = [
                        'A' => $choices['A'] ?? '',
                        'B' => $choices['B'] ?? '',
                        'C' => $choices['C'] ?? '',
                        'D' => $choices['D'] ?? ''
                    ];
                    
                    $questions[] = [
                        'question' => $questionText,
                        'choices' => $fullChoices,
                        'correct_answer' => $correctAnswer,
                        'explanation' => $explanation
                    ];
                }
            }
        }
        
        return $questions;
    }
    
    /**
     * Save quiz questions to database using existing structure
     */
    public function saveQuizQuestions($lessonId, $questions, $quizTitle = '') {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDB();
            
            // Start transaction
            $pdo->beginTransaction();
            
            // First, check if a quiz already exists for this lesson
            $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE lesson_id = ? LIMIT 1");
            $stmt->execute([$lessonId]);
            $existingQuiz = $stmt->fetch();
            
            if ($existingQuiz) {
                $quizId = $existingQuiz['id'];
                
                // Delete existing questions and options
                $stmt = $pdo->prepare("
                    DELETE qa FROM quiz_answer_options qa
                    JOIN quiz_questions qq ON qa.question_id = qq.id
                    WHERE qq.quiz_id = ?
                ");
                $stmt->execute([$quizId]);
                
                $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
                $stmt->execute([$quizId]);
            } else {
                // Create new quiz
                // Get user ID for created_by field
                $userId = $_SESSION['user_id'] ?? 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO quizzes 
                    (lesson_id, title, description, passing_score, max_attempts, 
                     time_limit_minutes, shuffle_questions, shuffle_answers, 
                     show_correct_answers, show_score_immediately, allow_review, 
                     created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, 70.00, 0, 0, 0, 0, 1, 1, 1, ?, NOW(), NOW())
                ");
                
                $title = $quizTitle ?: "Quiz for Lesson {$lessonId}";
                $description = "AI-generated quiz questions based on lesson transcript";
                $stmt->execute([$lessonId, $title, $description, $userId]);
                $quizId = $pdo->lastInsertId();
            }
            
            // Insert questions and answer options
            $questionStmt = $pdo->prepare("
                INSERT INTO quiz_questions 
                (quiz_id, question_text, question_type, explanation, 
                 points, order_index, is_required, created_at, updated_at) 
                VALUES (?, ?, 'multiple_choice', ?, 1.00, ?, 1, NOW(), NOW())
            ");
            
            $optionStmt = $pdo->prepare("
                INSERT INTO quiz_answer_options 
                (question_id, answer_text, is_correct, order_index, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $savedCount = 0;
            foreach ($questions as $index => $question) {
                // Insert question
                $questionStmt->execute([
                    $quizId,
                    $question['question'],
                    $question['explanation'],
                    $index + 1
                ]);
                $questionId = $pdo->lastInsertId();
                
                // Insert answer options
                $optionIndex = 1;
                foreach ($question['choices'] as $letter => $choice) {
                    // Skip empty choices
                    if (empty(trim($choice))) {
                        continue;
                    }
                    $isCorrect = ($letter === $question['correct_answer']) ? 1 : 0;
                    $optionStmt->execute([
                        $questionId,
                        $choice,
                        $isCorrect,
                        $optionIndex++
                    ]);
                }
                
                $savedCount++;
            }
            
            $pdo->commit();
            return ['quiz_id' => $quizId, 'questions_saved' => $savedCount];
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error saving quiz questions: " . $e->getMessage());
            return false;
        }
    }
}