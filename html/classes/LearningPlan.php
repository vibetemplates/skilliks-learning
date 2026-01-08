<?php
/**
 * Learning Plan Management Class
 * 
 * Handles personalized learning recommendations based on survey responses
 */

require_once dirname(__DIR__) . '/config/database.php';

class LearningPlan {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Get recommended projects for a user
     */
    public function getRecommendedProjects($userId, $communityId, $status = null) {
        $query = "
            SELECT 
                mrp.*,
                p.name as project_name,
                p.description as project_description,
                p.status as project_status,
                p.start_date,
                p.end_date,
                (SELECT COUNT(*) FROM project_members WHERE project_id = p.id) as member_count,
                (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count
            FROM member_recommended_projects mrp
            JOIN projects p ON mrp.project_id = p.id
            WHERE mrp.user_id = ? AND mrp.community_id = ?
        ";
        
        $params = [$userId, $communityId];
        
        if ($status) {
            $query .= " AND mrp.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY mrp.priority DESC, mrp.score DESC, mrp.created_at DESC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting recommended projects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recommended courses for a user
     */
    public function getRecommendedCourses($userId, $communityId, $status = null) {
        $query = "
            SELECT 
                mrc.*,
                c.title as course_title,
                c.description as course_description,
                c.difficulty_level,
                c.estimated_hours,
                c.status as course_status,
                (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as enrollment_count,
                (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
                COALESCE(ce.enrolled_at, NULL) as user_enrolled_at,
                COALESCE(cp.progress_percentage, 0) as user_progress
            FROM member_recommended_courses mrc
            JOIN courses c ON mrc.course_id = c.id
            LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.user_id = mrc.user_id
            LEFT JOIN course_progress cp ON cp.course_id = c.id AND cp.user_id = mrc.user_id
            WHERE mrc.user_id = ? AND mrc.community_id = ?
        ";
        
        $params = [$userId, $communityId];
        
        if ($status) {
            $query .= " AND mrc.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY mrc.priority DESC, mrc.score DESC, mrc.created_at DESC";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting recommended courses: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get skill assessments for a user
     */
    public function getSkillAssessments($userId, $communityId) {
        $query = "
            SELECT *
            FROM member_skill_assessments
            WHERE user_id = ? AND community_id = ?
            ORDER BY 
                FIELD(current_level, 'beginner', 'intermediate', 'advanced', 'expert'),
                skill_name ASC
        ";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId, $communityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting skill assessments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add or update a project recommendation
     */
    public function recommendProject($userId, $projectId, $communityId, $reason = '', $priority = 'medium', $score = 0) {
        $query = "
            INSERT INTO member_recommended_projects 
            (user_id, project_id, community_id, recommendation_reason, priority, score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            recommendation_reason = VALUES(recommendation_reason),
            priority = VALUES(priority),
            score = VALUES(score),
            updated_at = CURRENT_TIMESTAMP
        ";
        
        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$userId, $projectId, $communityId, $reason, $priority, $score]);
        } catch (PDOException $e) {
            error_log("Error recommending project: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add or update a course recommendation
     */
    public function recommendCourse($userId, $courseId, $communityId, $reason = '', $priority = 'medium', $score = 0) {
        $query = "
            INSERT INTO member_recommended_courses 
            (user_id, course_id, community_id, recommendation_reason, priority, score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            recommendation_reason = VALUES(recommendation_reason),
            priority = VALUES(priority),
            score = VALUES(score),
            updated_at = CURRENT_TIMESTAMP
        ";
        
        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$userId, $courseId, $communityId, $reason, $priority, $score]);
        } catch (PDOException $e) {
            error_log("Error recommending course: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add or update a skill assessment
     */
    public function assessSkill($userId, $communityId, $skillName, $currentLevel, $targetLevel = null, $score = 0) {
        $query = "
            INSERT INTO member_skill_assessments 
            (user_id, community_id, skill_name, current_level, target_level, assessment_score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            current_level = VALUES(current_level),
            target_level = VALUES(target_level),
            assessment_score = VALUES(assessment_score),
            last_assessed_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        ";
        
        $targetLevel = $targetLevel ?: $this->getNextLevel($currentLevel);
        
        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$userId, $communityId, $skillName, $currentLevel, $targetLevel, $score]);
        } catch (PDOException $e) {
            error_log("Error assessing skill: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update recommendation status
     */
    public function updateRecommendationStatus($type, $recommendationId, $userId, $status) {
        $table = $type === 'project' ? 'member_recommended_projects' : 'member_recommended_courses';
        
        $query = "
            UPDATE $table 
            SET status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ?
        ";
        
        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$status, $recommendationId, $userId]);
        } catch (PDOException $e) {
            error_log("Error updating recommendation status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get learning plan summary stats
     */
    public function getLearningPlanStats($userId, $communityId) {
        try {
            // Count recommendations by status
            $projectQuery = "
                SELECT 
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_projects,
                    COUNT(CASE WHEN status = 'enrolled' THEN 1 END) as enrolled_projects,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_projects
                FROM member_recommended_projects
                WHERE user_id = ? AND community_id = ?
            ";
            
            $courseQuery = "
                SELECT 
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_courses,
                    COUNT(CASE WHEN status = 'enrolled' THEN 1 END) as enrolled_courses,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_courses
                FROM member_recommended_courses
                WHERE user_id = ? AND community_id = ?
            ";
            
            $skillQuery = "
                SELECT COUNT(*) as total_skills
                FROM member_skill_assessments
                WHERE user_id = ? AND community_id = ?
            ";
            
            $stmt = $this->db->prepare($projectQuery);
            $stmt->execute([$userId, $communityId]);
            $projectStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $this->db->prepare($courseQuery);
            $stmt->execute([$userId, $communityId]);
            $courseStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $this->db->prepare($skillQuery);
            $stmt->execute([$userId, $communityId]);
            $skillStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return array_merge($projectStats, $courseStats, $skillStats);
        } catch (PDOException $e) {
            error_log("Error getting learning plan stats: " . $e->getMessage());
            return [
                'pending_projects' => 0,
                'enrolled_projects' => 0,
                'completed_projects' => 0,
                'pending_courses' => 0,
                'enrolled_courses' => 0,
                'completed_courses' => 0,
                'total_skills' => 0
            ];
        }
    }
    
    /**
     * Record learning plan generation
     */
    public function recordGeneration($userId, $communityId, $surveyCompletionId = null, $type = 'survey_based') {
        $query = "
            INSERT INTO learning_plan_generations 
            (user_id, community_id, survey_completion_id, generation_type)
            VALUES (?, ?, ?, ?)
        ";
        
        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$userId, $communityId, $surveyCompletionId, $type]);
        } catch (PDOException $e) {
            error_log("Error recording learning plan generation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get last generation date
     */
    public function getLastGenerationDate($userId, $communityId) {
        $query = "
            SELECT generated_at
            FROM learning_plan_generations
            WHERE user_id = ? AND community_id = ?
            ORDER BY generated_at DESC
            LIMIT 1
        ";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId, $communityId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['generated_at'] : null;
        } catch (PDOException $e) {
            error_log("Error getting last generation date: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Helper to get next skill level
     */
    private function getNextLevel($currentLevel) {
        $levels = ['beginner' => 'intermediate', 'intermediate' => 'advanced', 'advanced' => 'expert', 'expert' => 'expert'];
        return $levels[$currentLevel] ?? 'intermediate';
    }
}