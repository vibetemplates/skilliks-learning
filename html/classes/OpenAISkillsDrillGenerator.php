<?php
require_once __DIR__ . '/SkillsDrill.php';

class OpenAISkillsDrillGenerator {
    private $apiKey;
    private $model;
    private $maxTokens;
    private $temperature;
    
    public function __construct() {
        $config = require __DIR__ . '/../config/openai.php';
        
        if (!isset($config['api_key']) || empty($config['api_key'])) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $this->apiKey = $config['api_key'];
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->maxTokens = 4000; // Balanced for complete responses without timeout
        $this->temperature = $config['temperature'] ?? 0.7;
        
        error_log("OpenAI Skills Drill Generator initialized. Model: {$this->model}, Max tokens: {$this->maxTokens}");
    }
    
    /**
     * Generate skills drill questions from transcript
     */
    public function generateDrillFromTranscript($transcript, $lessonTitle, $existingQuizQuestions = []) {
        if (empty($transcript)) {
            throw new Exception("Transcript is empty");
        }
        
        // Prepare existing quiz questions context
        $existingQuestionsContext = "";
        if (!empty($existingQuizQuestions)) {
            $existingQuestionsContext = "\n\nEXISTING QUIZ QUESTIONS TO AVOID:\n";
            foreach ($existingQuizQuestions as $q) {
                $existingQuestionsContext .= "- " . $q['question_text'] . "\n";
            }
        }
        
        $prompt = $this->buildPrompt($transcript, $lessonTitle, $existingQuestionsContext);
        
        try {
            $response = $this->callOpenAI($prompt);
            $questions = $this->parseResponse($response);
            
            if (empty($questions)) {
                throw new Exception("No questions generated from the response");
            }
            
            return $questions;
            
        } catch (Exception $e) {
            error_log("OpenAI Skills Drill Generation Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Build the prompt for OpenAI
     */
    private function buildPrompt($transcript, $lessonTitle, $existingQuestionsContext) {
        return "You are an expert educational content creator. Your task is to create practice drill questions from the following video transcript.

LESSON TITLE: {$lessonTitle}

TRANSCRIPT:
{$transcript}
{$existingQuestionsContext}

Please generate as many high-quality multiple-choice practice questions as possible from this transcript. These questions should:

1. Be DIFFERENT from the existing quiz questions listed above (if any)
2. Focus on practical application and skill reinforcement
3. Test understanding at various difficulty levels (easy, medium, hard)
4. Include clear, unambiguous questions
5. Have 4 answer options each
6. Include helpful explanations for the correct answer
7. Include hints that guide students without giving away the answer
8. Cover all major concepts in the transcript

Return a JSON object with a 'questions' array containing question objects. Each question should have: question_text (string), difficulty_level (easy/medium/hard), hint (string), explanation (string), and options (array of 4 options with answer_text, is_correct boolean, and feedback).

Generate exactly 10 high-quality questions. Ensure questions are varied and test different aspects of the material.

IMPORTANT: Return only valid JSON without any markdown formatting or extra text. Keep answers concise.";
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI($prompt) {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert educational content creator specializing in creating effective practice drills and exercises.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens
        ];
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Increased timeout to 2 minutes
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("CURL Error: " . $curlError);
            throw new Exception("CURL error: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            error_log("OpenAI API HTTP Error {$httpCode}. Response: " . $response);
            throw new Exception("OpenAI API error: HTTP {$httpCode} - {$response}");
        }
        
        error_log("OpenAI API Response received. HTTP: {$httpCode}, Length: " . strlen($response));
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to parse OpenAI API response JSON: " . json_last_error_msg());
            error_log("Raw API response: " . $response);
            throw new Exception("Failed to parse OpenAI API response: " . json_last_error_msg());
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            error_log("Invalid OpenAI response structure: " . json_encode($result));
            throw new Exception("Invalid OpenAI response structure");
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    /**
     * Parse OpenAI response into questions array
     */
    private function parseResponse($response) {
        // Log the raw response for debugging
        error_log("OpenAI Skills Drill Raw Response: " . substr($response, 0, 500) . "...");
        
        // First, try to parse as-is
        $parsed = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Successfully parsed
        } else {
            // Clean the response if initial parse failed
            // Remove literal newlines in JSON strings
            $cleanedResponse = preg_replace('/\\\\n\s*/', ' ', $response);
            
            // Remove actual newlines that might be breaking the JSON
            $cleanedResponse = preg_replace('/\n\s*/', ' ', $cleanedResponse);
            $cleanedResponse = preg_replace('/\r\s*/', ' ', $cleanedResponse);
            $cleanedResponse = preg_replace('/\t/', ' ', $cleanedResponse);
            
            // Try parsing again
            $parsed = json_decode($cleanedResponse, true);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Parse Error. Response was: " . $response);
            throw new Exception("Failed to parse JSON response: " . json_last_error_msg());
        }
        
        // Extract questions array from the object
        if (isset($parsed['questions']) && is_array($parsed['questions'])) {
            $questions = $parsed['questions'];
        } else {
            // Fallback: try to find array in response
            $searchIn = isset($cleanedResponse) ? $cleanedResponse : $response;
            $jsonStart = strpos($searchIn, '[');
            $jsonEnd = strrpos($searchIn, ']');
            
            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonString = substr($searchIn, $jsonStart, $jsonEnd - $jsonStart + 1);
                $questions = json_decode($jsonString, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Failed to parse questions array");
                }
            } else {
                throw new Exception("No questions array found in response");
            }
        }
        
        if (!is_array($questions)) {
            throw new Exception("Questions is not an array");
        }
        
        // Validate and clean questions
        $validQuestions = [];
        foreach ($questions as $question) {
            if ($this->validateQuestion($question)) {
                $validQuestions[] = $this->cleanQuestion($question);
            }
        }
        
        return $validQuestions;
    }
    
    /**
     * Validate a question structure
     */
    private function validateQuestion($question) {
        return isset($question['question_text']) &&
               isset($question['options']) &&
               is_array($question['options']) &&
               count($question['options']) >= 2;
    }
    
    /**
     * Clean and standardize a question
     */
    private function cleanQuestion($question) {
        $cleaned = [
            'question_text' => trim($question['question_text']),
            'difficulty_level' => $question['difficulty_level'] ?? 'medium',
            'hint' => $question['hint'] ?? null,
            'explanation' => $question['explanation'] ?? null,
            'options' => []
        ];
        
        // Ensure only one correct answer
        $hasCorrect = false;
        foreach ($question['options'] as $option) {
            $isCorrect = isset($option['is_correct']) && $option['is_correct'];
            
            if ($isCorrect && $hasCorrect) {
                // Skip additional correct answers
                continue;
            }
            
            if ($isCorrect) {
                $hasCorrect = true;
            }
            
            $cleaned['options'][] = [
                'answer_text' => trim($option['answer_text']),
                'is_correct' => $isCorrect ? 1 : 0,
                'feedback' => $option['feedback'] ?? null
            ];
        }
        
        // Ensure we have at least one correct answer
        if (!$hasCorrect && count($cleaned['options']) > 0) {
            $cleaned['options'][0]['is_correct'] = 1;
        }
        
        return $cleaned;
    }
    
    /**
     * Save drill questions to database
     */
    public function saveDrillQuestions($lessonId, $questions, $userId) {
        $skillsDrill = new SkillsDrill();
        
        // Check if drill already exists for this lesson
        $existingDrill = $skillsDrill->getByLessonId($lessonId);
        
        if ($existingDrill) {
            $drillId = $existingDrill['id'];
        } else {
            // Get lesson info
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT title FROM lessons WHERE id = ?");
            $stmt->execute([$lessonId]);
            $lesson = $stmt->fetch();
            
            // Create new drill
            $drillData = [
                'lesson_id' => $lessonId,
                'title' => "Skills Practice: " . $lesson['title'],
                'description' => "Practice and reinforce the skills from this lesson",
                'instructions' => "Answer questions to earn points. First try: 1 point, Second try: 0 points, Third try: -0.5 points, Fourth+ try: -1 point",
                'created_by' => $userId
            ];
            
            $drillId = $skillsDrill->create($drillData);
            
            if (!$drillId) {
                throw new Exception("Failed to create skills drill");
            }
        }
        
        // Save questions
        $skillsDrill->saveQuestions($drillId, $questions);
        
        return $drillId;
    }
}