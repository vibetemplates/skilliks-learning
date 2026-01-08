<?php
/**
 * Survey Class
 * 
 * Handles all survey-related operations including questions, responses, and completion tracking
 */

require_once __DIR__ . '/../config/database.php';

class Survey {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new survey
     */
    public function create($data) {
        $sql = "INSERT INTO surveys (community_id, name, description, type, sub_type, when_used, is_active, created_by, created_at) 
                VALUES (:community_id, :name, :description, :type, :sub_type, :when_used, :is_active, :created_by, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':community_id' => $data['community_id'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':type' => $data['type'] ?? 'project',
            ':sub_type' => $data['sub_type'] ?? null,
            ':when_used' => $data['when_used'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':created_by' => $data['created_by']
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Get survey by community and type
     */
    public function getSurveyByCommunity($communityId, $type = 'skills', $subType = null) {
        // Check if sub_type column exists
        $columnExists = false;
        try {
            $checkStmt = $this->db->query("SHOW COLUMNS FROM surveys LIKE 'sub_type'");
            $columnExists = $checkStmt->fetch() !== false;
        } catch (PDOException $e) {
            // Column doesn't exist
        }
        
        if ($subType && $columnExists) {
            $stmt = $this->db->prepare("
                SELECT * FROM surveys 
                WHERE community_id = ? AND type = ? AND sub_type = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$communityId, $type, $subType]);
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM surveys 
                WHERE community_id = ? AND type = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$communityId, $type]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all project surveys for a community
     */
    public function getProjectSurveys($communityId) {
        // Check if sub_type column exists
        $columnExists = false;
        try {
            $checkStmt = $this->db->query("SHOW COLUMNS FROM surveys LIKE 'sub_type'");
            $columnExists = $checkStmt->fetch() !== false;
        } catch (PDOException $e) {
            // Column doesn't exist
        }
        
        if ($columnExists) {
            $stmt = $this->db->prepare("
                SELECT * FROM surveys 
                WHERE community_id = ? AND type = 'project' AND is_active = 1
                ORDER BY 
                    CASE sub_type 
                        WHEN 'general' THEN 1
                        WHEN 'requirements' THEN 2
                    WHEN 'tech-stack' THEN 3
                    WHEN 'design-notes' THEN 4
                    ELSE 5
                END
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM surveys 
                WHERE community_id = ? AND type = 'project' AND is_active = 1
            ");
        }
        $stmt->execute([$communityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get survey by ID
     */
    public function getSurveyById($surveyId) {
        $stmt = $this->db->prepare("
            SELECT * FROM surveys WHERE id = ?
        ");
        $stmt->execute([$surveyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all sections for a survey
     */
    public function getSurveySections($surveyId) {
        $stmt = $this->db->prepare("
            SELECT * FROM survey_sections 
            WHERE survey_id = ? AND is_active = 1
            ORDER BY display_order ASC
        ");
        $stmt->execute([$surveyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get questions for a section with their answer options
     */
    public function getSectionQuestions($sectionId) {
        // First get all questions
        $stmt = $this->db->prepare("
            SELECT * FROM survey_questions 
            WHERE section_id = ? AND is_active = 1
            ORDER BY display_order ASC
        ");
        $stmt->execute([$sectionId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Then get answer options for each question
        foreach ($questions as &$question) {
            $stmt = $this->db->prepare("
                SELECT id, option_text as text, option_value as value, description, display_order 
                FROM survey_answer_options 
                WHERE question_id = ? AND is_active = 1
                ORDER BY display_order ASC
            ");
            $stmt->execute([$question['id']]);
            $question['answer_options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $questions;
    }
    
    /**
     * Get user's response for a question
     */
    public function getUserResponse($userId, $questionId) {
        $stmt = $this->db->prepare("
            SELECT * FROM survey_responses 
            WHERE user_id = ? AND question_id = ?
        ");
        $stmt->execute([$userId, $questionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save or update user response
     */
    public function saveResponse($surveyId, $userId, $questionId, $answerText = null, $answerOptionId = null, $rankValue = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO survey_responses (survey_id, user_id, question_id, answer_text, answer_option_id, rank_value)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    answer_text = VALUES(answer_text),
                    answer_option_id = VALUES(answer_option_id),
                    rank_value = VALUES(rank_value),
                    updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$surveyId, $userId, $questionId, $answerText, $answerOptionId, $rankValue]);
        } catch (PDOException $e) {
            error_log("Error saving survey response: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save multiple responses (for checkbox questions)
     */
    public function saveMultipleResponses($surveyId, $userId, $questionId, $answerOptionIds) {
        try {
            // First, delete existing responses for this question
            $stmt = $this->db->prepare("
                DELETE FROM survey_responses 
                WHERE user_id = ? AND question_id = ?
            ");
            $stmt->execute([$userId, $questionId]);
            
            // Then insert new responses
            if (!empty($answerOptionIds)) {
                $stmt = $this->db->prepare("
                    INSERT INTO survey_responses (survey_id, user_id, question_id, answer_option_id)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($answerOptionIds as $optionId) {
                    $stmt->execute([$surveyId, $userId, $questionId, $optionId]);
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error saving multiple survey responses: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's responses for checkbox questions
     */
    public function getUserMultipleResponses($userId, $questionId) {
        $stmt = $this->db->prepare("
            SELECT answer_option_id FROM survey_responses 
            WHERE user_id = ? AND question_id = ? AND answer_option_id IS NOT NULL
        ");
        $stmt->execute([$userId, $questionId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'answer_option_id');
    }
    
    /**
     * Start or update survey completion tracking
     */
    public function startSurvey($surveyId, $userId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO survey_completions (survey_id, user_id, started_at, completion_percentage)
                VALUES (?, ?, CURRENT_TIMESTAMP, 0)
                ON DUPLICATE KEY UPDATE 
                    started_at = IFNULL(started_at, CURRENT_TIMESTAMP)
            ");
            return $stmt->execute([$surveyId, $userId]);
        } catch (PDOException $e) {
            error_log("Error starting survey: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update survey completion percentage
     */
    public function updateCompletionPercentage($surveyId, $userId) {
        try {
            // Count total questions (both required and optional)
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT q.id) as total_questions
                FROM survey_questions q
                JOIN survey_sections s ON q.section_id = s.id
                WHERE s.survey_id = ? AND q.is_active = 1
            ");
            $stmt->execute([$surveyId]);
            $totalQuestions = $stmt->fetch()['total_questions'];
            
            // Count answered questions
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT r.question_id) as answered_questions
                FROM survey_responses r
                JOIN survey_questions q ON r.question_id = q.id
                JOIN survey_sections s ON q.section_id = s.id
                WHERE s.survey_id = ? AND r.user_id = ?
                AND (r.answer_text IS NOT NULL OR r.answer_option_id IS NOT NULL OR r.rank_value IS NOT NULL)
            ");
            $stmt->execute([$surveyId, $userId]);
            $answeredQuestions = $stmt->fetch()['answered_questions'];
            
            // Calculate percentage based on all questions
            $percentage = $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100) : 0;
            
            // Update completion record
            $stmt = $this->db->prepare("
                UPDATE survey_completions 
                SET completion_percentage = ?,
                    completed_at = CASE WHEN ? = 100 THEN CURRENT_TIMESTAMP ELSE NULL END
                WHERE survey_id = ? AND user_id = ?
            ");
            $stmt->execute([$percentage, $percentage, $surveyId, $userId]);
            
            return $percentage;
        } catch (PDOException $e) {
            error_log("Error updating completion percentage: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get survey completion status
     */
    public function getCompletionStatus($surveyId, $userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM survey_completions
            WHERE survey_id = ? AND user_id = ?
        ");
        $stmt->execute([$surveyId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all user responses for a survey
     */
    public function getAllUserResponses($surveyId, $userId) {
        $stmt = $this->db->prepare("
            SELECT 
                r.*,
                q.question_text,
                q.question_type,
                s.name as section_name,
                ao.option_text
            FROM survey_responses r
            JOIN survey_questions q ON r.question_id = q.id
            JOIN survey_sections s ON q.section_id = s.id
            LEFT JOIN survey_answer_options ao ON r.answer_option_id = ao.id
            WHERE r.survey_id = ? AND r.user_id = ?
            ORDER BY s.display_order, q.display_order
        ");
        $stmt->execute([$surveyId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if user has started survey
     */
    public function hasUserStartedSurvey($surveyId, $userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM survey_responses
            WHERE survey_id = ? AND user_id = ?
        ");
        $stmt->execute([$surveyId, $userId]);
        return $stmt->fetch()['count'] > 0;
    }
    
    /**
     * Save ranking responses
     */
    public function saveRankingResponses($surveyId, $userId, $questionId, $rankings) {
        try {
            // First, delete existing ranking responses for this question
            $stmt = $this->db->prepare("
                DELETE FROM survey_responses 
                WHERE user_id = ? AND question_id = ?
            ");
            $stmt->execute([$userId, $questionId]);
            
            // Then insert new rankings
            if (!empty($rankings)) {
                $stmt = $this->db->prepare("
                    INSERT INTO survey_responses (survey_id, user_id, question_id, answer_option_id, rank_value)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($rankings as $optionId => $rank) {
                    $stmt->execute([$surveyId, $userId, $questionId, $optionId, $rank]);
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error saving ranking responses: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's ranking responses
     */
    public function getUserRankingResponses($userId, $questionId) {
        $stmt = $this->db->prepare("
            SELECT answer_option_id, rank_value 
            FROM survey_responses 
            WHERE user_id = ? AND question_id = ? AND rank_value IS NOT NULL
            ORDER BY rank_value ASC
        ");
        $stmt->execute([$userId, $questionId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $rankings = [];
        foreach ($results as $result) {
            $rankings[$result['answer_option_id']] = $result['rank_value'];
        }
        
        return $rankings;
    }
}
?>