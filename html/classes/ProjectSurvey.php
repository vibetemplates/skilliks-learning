<?php
/**
 * ProjectSurvey Class
 * 
 * Handles project survey operations including AI recommendations generation
 */

require_once __DIR__ . '/Survey.php';
require_once __DIR__ . '/../config/database.php';

class ProjectSurvey extends Survey {
    
    /**
     * Create or get project survey for a project
     */
    public function getOrCreateProjectSurvey($projectId, $communityId) {
        $db = getDB();
        
        // First check if project has an existing survey
        $stmt = $db->prepare("
            SELECT ps.*, s.* 
            FROM project_surveys ps
            JOIN surveys s ON ps.survey_id = s.id
            WHERE ps.project_id = ?
        ");
        $stmt->execute([$projectId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing;
        }
        
        // Create a new project survey
        $survey = $this->getSurveyByCommunity($communityId, 'project');
        if (!$survey) {
            return null;
        }
        
        // Link survey to project
        $stmt = $db->prepare("
            INSERT INTO project_surveys (project_id, survey_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$projectId, $survey['id']]);
        
        return $survey;
    }
    
    /**
     * Save AI-generated recommendations
     */
    public function saveRecommendations($projectId, $surveyId, $recommendations) {
        $db = getDB();
        
        $stmt = $db->prepare("
            UPDATE project_surveys 
            SET architecture_recommendations = ?,
                tech_stack_recommendations = ?,
                claude_md_content = ?,
                requirements_md_content = ?,
                generated_at = CURRENT_TIMESTAMP
            WHERE project_id = ? AND survey_id = ?
        ");
        
        return $stmt->execute([
            $recommendations['architecture'] ?? null,
            $recommendations['tech_stack'] ?? null,
            $recommendations['claude_md'] ?? null,
            $recommendations['requirements_md'] ?? null,
            $projectId,
            $surveyId
        ]);
    }
    
    /**
     * Save extracted project attributes
     */
    public function saveProjectAttributes($projectSurveyId, $attributes) {
        $db = getDB();
        
        // Delete existing attributes
        $stmt = $db->prepare("DELETE FROM project_survey_attributes WHERE project_survey_id = ?");
        $stmt->execute([$projectSurveyId]);
        
        // Insert new attributes
        $stmt = $db->prepare("
            INSERT INTO project_survey_attributes 
            (project_survey_id, attribute_type, attribute_name, attribute_value, confidence_score)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($attributes as $attr) {
            $stmt->execute([
                $projectSurveyId,
                $attr['type'],
                $attr['name'],
                $attr['value'],
                $attr['confidence'] ?? 0.8
            ]);
        }
        
        return true;
    }
    
    /**
     * Get project survey details
     */
    public function getProjectSurveyDetails($projectId) {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT ps.*, s.name as survey_name, s.description as survey_description,
                   p.name as project_name, p.description as project_description
            FROM project_surveys ps
            JOIN surveys s ON ps.survey_id = s.id
            JOIN projects p ON ps.project_id = p.id
            WHERE ps.project_id = ?
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get project attributes
     */
    public function getProjectAttributes($projectSurveyId) {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT * FROM project_survey_attributes
            WHERE project_survey_id = ?
            ORDER BY attribute_type, attribute_name
        ");
        $stmt->execute([$projectSurveyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Approve project survey recommendations
     */
    public function approveRecommendations($projectId, $surveyId, $userId) {
        $db = getDB();
        
        $stmt = $db->prepare("
            UPDATE project_surveys 
            SET approved_at = CURRENT_TIMESTAMP,
                approved_by = ?
            WHERE project_id = ? AND survey_id = ?
        ");
        
        return $stmt->execute([$userId, $projectId, $surveyId]);
    }
    
    /**
     * Check if project survey is approved
     */
    public function isProjectSurveyApproved($projectId) {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT approved_at IS NOT NULL as is_approved
            FROM project_surveys
            WHERE project_id = ?
        ");
        $stmt->execute([$projectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['is_approved'] : false;
    }
    
    /**
     * Get formatted survey responses for AI processing
     */
    public function getFormattedResponsesForAI($surveyId, $userId) {
        $responses = $this->getAllUserResponses($surveyId, $userId);
        
        $formatted = [];
        $currentSection = '';
        
        foreach ($responses as $response) {
            if ($currentSection !== $response['section_name']) {
                $currentSection = $response['section_name'];
                $formatted[] = "\n## {$currentSection}\n";
            }
            
            $formatted[] = "**{$response['question_text']}**";
            
            if ($response['answer_text']) {
                $formatted[] = $response['answer_text'];
            } elseif ($response['option_text']) {
                $formatted[] = $response['option_text'];
            }
            
            $formatted[] = "";
        }
        
        return implode("\n", $formatted);
    }
}
?>