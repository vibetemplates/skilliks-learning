<?php
/**
 * Course Class
 * 
 * Handles course-related operations
 */

class Course {
    private $db;
    private $communityId;
    
    public function __construct($db = null, $communityId = null) {
        $this->db = $db ?: getDB();
        $this->communityId = $communityId ?: getCurrentCommunityId();
    }
    
    /**
     * Get a course by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, 
                       COUNT(DISTINCT l.id) as lesson_count,
                       COUNT(DISTINCT e.user_id) as enrolled_count
                FROM courses c
                LEFT JOIN lessons l ON c.id = l.course_id AND l.status = 'published'
                LEFT JOIN course_enrollments e ON c.id = e.course_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all courses with details (for recommendations)
     */
    public function getAllWithDetails($communityId = null) {
        try {
            // Use provided community ID or the instance's community ID
            $communityId = $communityId ?: $this->communityId;
            
            $sql = "
                SELECT c.*, 
                       COUNT(DISTINCT l.id) as lesson_count,
                       COUNT(DISTINCT e.user_id) as enrolled_count,
                       u.first_name as instructor_first_name,
                       u.last_name as instructor_last_name
                FROM courses c
                LEFT JOIN lessons l ON c.id = l.course_id AND l.status = 'published'
                LEFT JOIN course_enrollments e ON c.id = e.course_id
                LEFT JOIN course_instructors ci ON c.id = ci.course_id AND ci.role = 'lead_instructor' AND ci.is_active = 1
                LEFT JOIN users u ON ci.user_id = u.id
                WHERE c.status = 'published'
                AND c.community_id = :community_id
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['community_id' => $communityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getAllWithDetails error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all courses for a community
     */
    public function getAllByCommunity($communityId = null, $includeArchived = false) {
        $communityId = $communityId ?: $this->communityId;
        
        try {
            $sql = "
                SELECT c.*, 
                       COUNT(DISTINCT l.id) as lesson_count,
                       COUNT(DISTINCT e.user_id) as enrolled_count
                FROM courses c
                LEFT JOIN lessons l ON c.id = l.course_id AND l.status = 'published'
                LEFT JOIN course_enrollments e ON c.id = e.course_id
                WHERE c.community_id = ?
            ";
            
            if (!$includeArchived) {
                $sql .= " AND c.status != 'archived'";
            }
            
            $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$communityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getAllByCommunity error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get published courses
     */
    public function getPublished($communityId = null) {
        $communityId = $communityId ?: $this->communityId;
        
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, 
                       COUNT(DISTINCT l.id) as lesson_count,
                       COUNT(DISTINCT e.user_id) as enrolled_count
                FROM courses c
                LEFT JOIN lessons l ON c.id = l.course_id AND l.status = 'published'
                LEFT JOIN course_enrollments e ON c.id = e.course_id
                WHERE c.community_id = ? AND c.status = 'published'
                GROUP BY c.id
                ORDER BY c.featured DESC, c.created_at DESC
            ");
            $stmt->execute([$communityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getPublished error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new course
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO courses (
                    community_id, title, description, course_code, difficulty_level,
                    duration_hours, max_enrollments, price, currency, status,
                    created_by, category_id, prerequisites, learning_outcomes,
                    thumbnail_url, featured
                ) VALUES (
                    :community_id, :title, :description, :course_code, :difficulty_level,
                    :duration_hours, :max_enrollments, :price, :currency, :status,
                    :created_by, :category_id, :prerequisites, :learning_outcomes,
                    :thumbnail_url, :featured
                )
            ");
            
            $params = [
                ':community_id' => $data['community_id'] ?? $this->communityId,
                ':title' => $data['title'],
                ':description' => $data['description'] ?? null,
                ':course_code' => $data['course_code'] ?? null,
                ':difficulty_level' => $data['difficulty_level'] ?? 'beginner',
                ':duration_hours' => $data['duration_hours'] ?? 0,
                ':max_enrollments' => $data['max_enrollments'] ?? null,
                ':price' => $data['price'] ?? 0,
                ':currency' => $data['currency'] ?? 'USD',
                ':status' => $data['status'] ?? 'draft',
                ':created_by' => $data['created_by'] ?? getCurrentUserId(),
                ':category_id' => $data['category_id'] ?? null,
                ':prerequisites' => $data['prerequisites'] ?? null,
                ':learning_outcomes' => $data['learning_outcomes'] ?? null,
                ':thumbnail_url' => $data['thumbnail_url'] ?? null,
                ':featured' => $data['featured'] ?? 0
            ];
            
            $stmt->execute($params);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Course create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a course
     */
    public function update($id, $data) {
        try {
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = [
                'title', 'description', 'course_code', 'difficulty_level',
                'duration_hours', 'max_enrollments', 'price', 'currency',
                'status', 'category_id', 'prerequisites', 'learning_outcomes',
                'thumbnail_url', 'featured'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                return true;
            }
            
            $sql = "UPDATE courses SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Course update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a course
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM courses WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Course delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is enrolled in course
     */
    public function isEnrolled($courseId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM course_enrollments 
                WHERE course_id = ? AND user_id = ? 
                AND status IN ('enrolled', 'in_progress', 'completed')
            ");
            $stmt->execute([$courseId, $userId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Course isEnrolled error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enroll user in course
     */
    public function enrollUser($courseId, $userId, $enrolledBy = null) {
        try {
            // Check if already enrolled
            if ($this->isEnrolled($courseId, $userId)) {
                return true;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO course_enrollments (user_id, course_id, enrolled_by)
                VALUES (:user_id, :course_id, :enrolled_by)
            ");
            
            return $stmt->execute([
                ':user_id' => $userId,
                ':course_id' => $courseId,
                ':enrolled_by' => $enrolledBy ?: $userId
            ]);
        } catch (PDOException $e) {
            error_log("Course enrollUser error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's enrolled courses
     */
    public function getUserEnrolledCourses($userId, $communityId = null) {
        $communityId = $communityId ?: $this->communityId;
        
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, e.enrollment_date, e.progress_percentage, e.status as enrollment_status,
                       COUNT(DISTINCT l.id) as lesson_count,
                       COUNT(DISTINCT lp.id) as completed_lessons
                FROM course_enrollments e
                JOIN courses c ON e.course_id = c.id
                LEFT JOIN lessons l ON c.id = l.course_id AND l.status = 'published'
                LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = e.user_id AND lp.status = 'completed'
                WHERE e.user_id = ? AND c.community_id = ?
                AND e.status IN ('enrolled', 'in_progress', 'completed')
                GROUP BY c.id, e.id
                ORDER BY e.enrollment_date DESC
            ");
            $stmt->execute([$userId, $communityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getUserEnrolledCourses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get course lessons
     */
    public function getLessons($courseId, $publishedOnly = true) {
        try {
            $sql = "SELECT * FROM lessons WHERE course_id = ?";
            if ($publishedOnly) {
                $sql .= " AND status = 'published'";
            }
            $sql .= " ORDER BY order_index ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$courseId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getLessons error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get course instructor
     */
    public function getInstructor($courseId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.name, u.email, u.profile_image
                FROM courses c
                JOIN users u ON c.created_by = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$courseId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getInstructor error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get skills covered by a course
     */
    public function getCourseSkills($courseId) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, cs.skill_level
                FROM course_skills cs
                JOIN skills s ON cs.skill_id = s.id
                WHERE cs.course_id = ? AND s.is_active = 1
                ORDER BY 
                    CASE cs.skill_level
                        WHEN 'advanced' THEN 1
                        WHEN 'intermediate' THEN 2
                        WHEN 'beginner' THEN 3
                    END,
                    s.name ASC
            ");
            $stmt->execute([$courseId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Course getCourseSkills error: " . $e->getMessage());
            return [];
        }
    }
}