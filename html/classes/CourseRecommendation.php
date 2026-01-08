<?php
/**
 * CourseRecommendation Class
 * 
 * Handles course recommendation storage and retrieval
 */

class CourseRecommendation {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: getDB();
    }
    
    /**
     * Generate and store recommendations for a user
     */
    public function generateAndStoreRecommendations($userId) {
        // First, deactivate old recommendations
        $this->deactivateOldRecommendations($userId);
        
        // Get user's survey data
        $surveyNarrative = new SurveyNarrative();
        $topInterests = $surveyNarrative->getTopInterests($userId);
        
        // Get user's current community
        $stmt = $this->db->prepare("SELECT default_community_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $communityId = $user['default_community_id'] ?? getCurrentCommunityId();
        
        $course = new Course($this->db, $communityId);
        $allCourses = $course->getAllWithDetails($communityId);
        
        $recommendations = [];
        
        // Check if user has limited interests
        $hasLimitedData = count($topInterests) < 3 || 
                         in_array('AI Fundamentals', $topInterests) || 
                         in_array('Getting Started with AI', $topInterests);
        
        // 1. Find beginner courses - prioritize these for users with limited data
        $beginnerKeywords = ['introduction', 'basics', 'fundamentals', 'getting started', 'beginner', 'ai basics', 'learning approach'];
        foreach ($allCourses as $c) {
            $title = strtolower($c['title']);
            $desc = strtolower($c['description'] ?? '');
            
            foreach ($beginnerKeywords as $keyword) {
                if (strpos($title, $keyword) !== false || strpos($desc, $keyword) !== false) {
                    $score = $this->calculateScore($c, 'beginner');
                    // Boost score for users with limited data
                    if ($hasLimitedData) {
                        $score = min($score + 20, 100);
                    }
                    
                    $recommendations[] = [
                        'user_id' => $userId,
                        'course_id' => $c['id'],
                        'recommendation_type' => 'beginner',
                        'reason' => "This course covers fundamental concepts suitable for beginners",
                        'score' => $score
                    ];
                    break;
                }
            }
        }
        
        // 2. Find interest-based courses
        foreach ($topInterests as $interest) {
            foreach ($allCourses as $c) {
                $title = strtolower($c['title']);
                $desc = strtolower($c['description'] ?? '');
                $interestLower = strtolower($interest);
                
                if (strpos($title, $interestLower) !== false || 
                    strpos($desc, $interestLower) !== false ||
                    $this->isRelatedInterest($interestLower, $title, $desc)) {
                    
                    $recommendations[] = [
                        'user_id' => $userId,
                        'course_id' => $c['id'],
                        'recommendation_type' => 'interest_based',
                        'reason' => "Matches your interest in: $interest",
                        'score' => $this->calculateScore($c, 'interest', $interest)
                    ];
                }
            }
        }
        
        // Store recommendations
        $stored = $this->storeRecommendations($recommendations);
        
        return [
            'total_generated' => count($recommendations),
            'total_stored' => $stored,
            'beginner_courses' => count(array_filter($recommendations, fn($r) => $r['recommendation_type'] === 'beginner')),
            'interest_based' => count(array_filter($recommendations, fn($r) => $r['recommendation_type'] === 'interest_based'))
        ];
    }
    
    /**
     * Store recommendations in database
     */
    private function storeRecommendations($recommendations) {
        $stored = 0;
        
        $sql = "INSERT INTO course_recommendations 
                (user_id, course_id, recommendation_type, reason, score) 
                VALUES (:user_id, :course_id, :recommendation_type, :reason, :score)
                ON DUPLICATE KEY UPDATE 
                    reason = VALUES(reason),
                    score = VALUES(score),
                    generated_at = CURRENT_TIMESTAMP,
                    is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($recommendations as $rec) {
            try {
                $stmt->execute([
                    ':user_id' => $rec['user_id'],
                    ':course_id' => $rec['course_id'],
                    ':recommendation_type' => $rec['recommendation_type'],
                    ':reason' => $rec['reason'],
                    ':score' => $rec['score']
                ]);
                $stored++;
            } catch (PDOException $e) {
                error_log("Failed to store recommendation: " . $e->getMessage());
            }
        }
        
        return $stored;
    }
    
    /**
     * Calculate recommendation score
     */
    private function calculateScore($course, $type, $interest = null) {
        $score = 50.0; // Base score
        
        // Boost for beginner courses
        if ($type === 'beginner') {
            $score += 20;
        }
        
        // Boost for courses with more lessons
        if (isset($course['lesson_count'])) {
            $score += min($course['lesson_count'] * 2, 20);
        }
        
        // Boost for enrolled students
        if (isset($course['enrolled_count']) && $course['enrolled_count'] > 0) {
            $score += min($course['enrolled_count'], 10);
        }
        
        return min($score, 100); // Cap at 100
    }
    
    /**
     * Check if interests are related
     */
    private function isRelatedInterest($interest, $title, $desc) {
        $relatedTerms = [
            'project' => ['project', 'agile', 'scrum', 'management'],
            'prompt' => ['prompt', 'engineering', 'llm', 'ai'],
            'architecture' => ['architecture', 'design', 'system', 'structure']
        ];
        
        foreach ($relatedTerms as $key => $terms) {
            if (strpos($interest, $key) !== false) {
                foreach ($terms as $term) {
                    if (strpos($title, $term) !== false || strpos($desc, $term) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Deactivate old recommendations
     */
    private function deactivateOldRecommendations($userId) {
        $sql = "UPDATE course_recommendations 
                SET is_active = 0 
                WHERE user_id = :user_id 
                AND is_active = 1
                AND generated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
    }
    
    /**
     * Get active recommendations for a user
     */
    public function getActiveRecommendations($userId, $limit = 10) {
        // Get user's current community
        $stmt = $this->db->prepare("SELECT default_community_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $communityId = $user['default_community_id'] ?? getCurrentCommunityId();
        
        $sql = "SELECT cr.*, c.title as course_title, c.description, c.thumbnail_url,
                       c.difficulty_level, c.duration_hours,
                       COUNT(DISTINCT l.id) as lesson_count,
                       COUNT(DISTINCT e.user_id) as enrolled_count
                FROM course_recommendations cr
                JOIN courses c ON cr.course_id = c.id
                LEFT JOIN lessons l ON c.id = l.course_id AND l.status = 'published'
                LEFT JOIN course_enrollments e ON c.id = e.course_id
                WHERE cr.user_id = :user_id
                AND cr.is_active = 1
                AND c.status = 'published'
                AND c.community_id = :community_id
                GROUP BY cr.id
                ORDER BY cr.score DESC, cr.generated_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':community_id', $communityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark recommendation as viewed
     */
    public function markAsViewed($recommendationId) {
        $sql = "UPDATE course_recommendations 
                SET viewed_at = CURRENT_TIMESTAMP 
                WHERE id = :id AND viewed_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $recommendationId]);
    }
    
    /**
     * Mark recommendation as dismissed
     */
    public function dismissRecommendation($recommendationId) {
        $sql = "UPDATE course_recommendations 
                SET dismissed_at = CURRENT_TIMESTAMP, is_active = 0 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $recommendationId]);
    }
    
    /**
     * Get recommendation statistics for a user
     */
    public function getUserStats($userId) {
        $sql = "SELECT 
                    COUNT(*) as total_recommendations,
                    SUM(CASE WHEN viewed_at IS NOT NULL THEN 1 ELSE 0 END) as viewed,
                    SUM(CASE WHEN enrolled_at IS NOT NULL THEN 1 ELSE 0 END) as enrolled,
                    SUM(CASE WHEN dismissed_at IS NOT NULL THEN 1 ELSE 0 END) as dismissed,
                    AVG(score) as avg_score
                FROM course_recommendations 
                WHERE user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}