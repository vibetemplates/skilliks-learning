<?php
/**
 * Lesson Class
 * 
 * Handles lesson-related operations
 */

require_once dirname(__DIR__) . '/config/functions.php';

class Lesson {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: getDB();
    }
    
    /**
     * Get a lesson by ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM lessons WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lesson getById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get lessons by course ID
     */
    public function getByCourseId($courseId, $publishedOnly = false) {
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
            error_log("Lesson getByCourseId error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new lesson
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO lessons (
                    course_id, title, description, content, lesson_type,
                    duration_minutes, order_index, status, is_mandatory,
                    video_url, video_duration, attachment_url, quiz_data,
                    assignment_data, created_by
                ) VALUES (
                    :course_id, :title, :description, :content, :lesson_type,
                    :duration_minutes, :order_index, :status, :is_mandatory,
                    :video_url, :video_duration, :attachment_url, :quiz_data,
                    :assignment_data, :created_by
                )
            ");
            
            $params = [
                ':course_id' => $data['course_id'],
                ':title' => $data['title'],
                ':description' => $data['description'] ?? null,
                ':content' => $data['content'] ?? null,
                ':lesson_type' => $data['lesson_type'] ?? 'text',
                ':duration_minutes' => $data['duration_minutes'] ?? 0,
                ':order_index' => $data['order_index'] ?? 0,
                ':status' => $data['status'] ?? 'draft',
                ':is_mandatory' => $data['is_mandatory'] ?? 1,
                ':video_url' => $data['video_url'] ?? null,
                ':video_duration' => $data['video_duration'] ?? 0,
                ':attachment_url' => $data['attachment_url'] ?? null,
                ':quiz_data' => $data['quiz_data'] ?? null,
                ':assignment_data' => $data['assignment_data'] ?? null,
                ':created_by' => $data['created_by'] ?? getCurrentUserId()
            ];
            
            $stmt->execute($params);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Lesson create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a lesson
     */
    public function update($id, $data) {
        try {
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = [
                'title', 'description', 'content', 'lesson_type',
                'duration_minutes', 'order_index', 'status', 'is_mandatory',
                'video_url', 'video_duration', 'attachment_url', 'quiz_data',
                'assignment_data'
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
            
            $updateFields[] = "updated_at = NOW()";
            
            $sql = "UPDATE lessons SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Lesson update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a lesson
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM lessons WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Lesson delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update quiz data
     */
    public function updateQuizData($id, $quizData) {
        try {
            $stmt = $this->db->prepare("
                UPDATE lessons 
                SET quiz_data = :quiz_data, 
                    lesson_type = 'quiz',
                    updated_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                ':quiz_data' => json_encode($quizData),
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Lesson updateQuizData error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user progress for a lesson
     */
    public function getUserProgress($lessonId, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM lesson_progress 
                WHERE lesson_id = ? AND user_id = ?
            ");
            $stmt->execute([$lessonId, $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lesson getUserProgress error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update user progress
     */
    public function updateUserProgress($lessonId, $userId, $data) {
        try {
            // Check if progress exists
            $progress = $this->getUserProgress($lessonId, $userId);
            
            if ($progress) {
                // Update existing progress
                $updateFields = [];
                $params = [':lesson_id' => $lessonId, ':user_id' => $userId];
                
                $allowedFields = [
                    'status', 'progress_percentage', 'score', 'time_spent_minutes',
                    'completed_at', 'attempts', 'quiz_responses', 'assignment_submission',
                    'notes'
                ];
                
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $params[":$field"] = $data[$field];
                    }
                }
                
                if (!empty($updateFields)) {
                    $updateFields[] = "last_accessed = NOW()";
                    $sql = "UPDATE lesson_progress SET " . implode(', ', $updateFields) . 
                           " WHERE lesson_id = :lesson_id AND user_id = :user_id";
                    $stmt = $this->db->prepare($sql);
                    return $stmt->execute($params);
                }
            } else {
                // Create new progress
                $stmt = $this->db->prepare("
                    INSERT INTO lesson_progress (
                        user_id, lesson_id, course_id, status, progress_percentage,
                        score, time_spent_minutes, started_at, completed_at,
                        attempts, quiz_responses, assignment_submission, notes
                    ) VALUES (
                        :user_id, :lesson_id, :course_id, :status, :progress_percentage,
                        :score, :time_spent_minutes, NOW(), :completed_at,
                        :attempts, :quiz_responses, :assignment_submission, :notes
                    )
                ");
                
                // Get course_id from lesson
                $lesson = $this->getById($lessonId);
                
                $params = [
                    ':user_id' => $userId,
                    ':lesson_id' => $lessonId,
                    ':course_id' => $lesson['course_id'],
                    ':status' => $data['status'] ?? 'in_progress',
                    ':progress_percentage' => $data['progress_percentage'] ?? 0,
                    ':score' => $data['score'] ?? null,
                    ':time_spent_minutes' => $data['time_spent_minutes'] ?? 0,
                    ':completed_at' => $data['completed_at'] ?? null,
                    ':attempts' => $data['attempts'] ?? 1,
                    ':quiz_responses' => $data['quiz_responses'] ?? null,
                    ':assignment_submission' => $data['assignment_submission'] ?? null,
                    ':notes' => $data['notes'] ?? null
                ];
                
                return $stmt->execute($params);
            }
        } catch (PDOException $e) {
            error_log("Lesson updateUserProgress error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reorder lessons
     */
    public function reorder($courseId, $lessonOrder) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                UPDATE lessons SET order_index = ? WHERE id = ? AND course_id = ?
            ");
            
            foreach ($lessonOrder as $index => $lessonId) {
                $stmt->execute([$index, $lessonId, $courseId]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Lesson reorder error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get next lesson in course
     */
    public function getNextLesson($courseId, $currentOrderIndex) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM lessons 
                WHERE course_id = ? AND order_index > ? AND status = 'published'
                ORDER BY order_index ASC
                LIMIT 1
            ");
            $stmt->execute([$courseId, $currentOrderIndex]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lesson getNextLesson error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get previous lesson in course
     */
    public function getPreviousLesson($courseId, $currentOrderIndex) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM lessons 
                WHERE course_id = ? AND order_index < ? AND status = 'published'
                ORDER BY order_index DESC
                LIMIT 1
            ");
            $stmt->execute([$courseId, $currentOrderIndex]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lesson getPreviousLesson error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get skills associated with a lesson
     */
    public function getLessonSkills($lessonId) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, ls.skill_level, ls.is_required
                FROM lesson_skills ls
                JOIN skills s ON ls.skill_id = s.id
                WHERE ls.lesson_id = ? AND s.is_active = 1
                ORDER BY ls.is_required DESC, s.name ASC
            ");
            $stmt->execute([$lessonId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lesson getLessonSkills error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update skills for a lesson
     */
    public function updateLessonSkills($lessonId, $skills, $userId = null) {
        try {
            $this->db->beginTransaction();
            
            // Delete existing skill associations
            $stmt = $this->db->prepare("DELETE FROM lesson_skills WHERE lesson_id = ?");
            $stmt->execute([$lessonId]);
            
            // Insert new skill associations
            if (!empty($skills)) {
                $stmt = $this->db->prepare("
                    INSERT INTO lesson_skills (lesson_id, skill_id, skill_level, is_required, added_by)
                    VALUES (:lesson_id, :skill_id, :skill_level, :is_required, :added_by)
                ");
                
                foreach ($skills as $skill) {
                    $params = [
                        ':lesson_id' => $lessonId,
                        ':skill_id' => $skill['skill_id'],
                        ':skill_level' => $skill['skill_level'] ?? 'beginner',
                        ':is_required' => !empty($skill['is_required']) ? 1 : 0,
                        ':added_by' => $userId ?: getCurrentUserId()
                    ];
                    $stmt->execute($params);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Lesson updateLessonSkills error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a single skill to a lesson
     */
    public function addSkill($lessonId, $skillId, $skillLevel = 'beginner', $isRequired = false, $userId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO lesson_skills (lesson_id, skill_id, skill_level, is_required, added_by)
                VALUES (:lesson_id, :skill_id, :skill_level, :is_required, :added_by)
                ON DUPLICATE KEY UPDATE 
                    skill_level = VALUES(skill_level),
                    is_required = VALUES(is_required)
            ");
            
            return $stmt->execute([
                ':lesson_id' => $lessonId,
                ':skill_id' => $skillId,
                ':skill_level' => $skillLevel,
                ':is_required' => $isRequired ? 1 : 0,
                ':added_by' => $userId ?: getCurrentUserId()
            ]);
        } catch (PDOException $e) {
            error_log("Lesson addSkill error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove a skill from a lesson
     */
    public function removeSkill($lessonId, $skillId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM lesson_skills 
                WHERE lesson_id = ? AND skill_id = ?
            ");
            return $stmt->execute([$lessonId, $skillId]);
        } catch (PDOException $e) {
            error_log("Lesson removeSkill error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all available skills for selection
     */
    public function getAllSkills() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM skills 
                WHERE is_active = 1 
                ORDER BY category, name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lesson getAllSkills error: " . $e->getMessage());
            return [];
        }
    }
}