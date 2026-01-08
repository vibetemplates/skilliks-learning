<?php
/**
 * Courses API Endpoint
 * 
 * Handles:
 * GET /api/v1/courses?community_id={id} - List all courses in a community
 * GET /api/v1/courses/{id}/lessons - List all lessons in a course
 */

require_once 'BaseAPI.php';
require_once __DIR__ . '/../../classes/Course.php';

class CoursesAPI extends BaseAPI {
    
    public function handleRequest() {
        if ($this->method !== 'GET') {
            $this->sendError(405, 'Method not allowed');
        }
        
        // Parse the URI to determine the action
        $pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $courseId = null;
        $action = null;
        
        // Check if we have /api/v1/courses/{id}/lessons
        if (count($pathParts) >= 4 && is_numeric($pathParts[3])) {
            $courseId = (int)$pathParts[3];
            if (isset($pathParts[4]) && $pathParts[4] === 'lessons') {
                $action = 'lessons';
            }
        }
        
        if ($courseId && $action === 'lessons') {
            $this->listLessons($courseId);
        } else {
            $this->listCourses();
        }
    }
    
    /**
     * List courses in a community
     * GET /api/v1/courses?community_id={id}
     * 
     * Query params:
     * - community_id: Required - Community ID to filter courses
     * - program_id: Optional - Filter by program
     * - is_published: Optional - Filter by published status (1 or 0)
     * - page: page number (default: 1)
     * - limit: items per page (default: 20, max: 100)
     */
    protected function listCourses() {
        $communityId = $this->getParam('community_id');
        if (!$communityId) {
            $this->sendError(400, 'community_id parameter is required');
        }
        
        // Check if user has access to this community
        if (!$this->checkCommunityAccess($communityId)) {
            $this->sendError(403, 'Access denied to this community');
        }
        
        $programId = $this->getParam('program_id');
        $isPublished = $this->getParam('is_published');
        $page = max(1, (int)$this->getParam('page', 1));
        $limit = min(100, max(1, (int)$this->getParam('limit', 20)));
        $offset = ($page - 1) * $limit;
        
        try {
            $conditions = ['c.community_id = ?'];
            $params = [$communityId];
            $joins = '';
            
            if ($programId) {
                $joins = 'JOIN program_courses pc ON c.id = pc.course_id';
                $conditions[] = 'pc.program_id = ?';
                $params[] = $programId;
            }
            
            if ($isPublished !== null) {
                $conditions[] = 'c.is_published = ?';
                $params[] = (int)$isPublished;
            }
            
            $whereClause = implode(' AND ', $conditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(DISTINCT c.id) as total FROM courses c $joins WHERE $whereClause";
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get courses
            $query = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT l.id) as lesson_count,
                    COUNT(DISTINCT ce.user_id) as enrolled_count,
                    GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as instructors
                FROM courses c
                $joins
                LEFT JOIN lessons l ON c.id = l.course_id AND l.is_published = 1
                LEFT JOIN course_enrollments ce ON c.id = ce.course_id
                LEFT JOIN course_instructors ci ON c.id = ci.course_id
                LEFT JOIN users u ON ci.user_id = u.id
                WHERE $whereClause
                GROUP BY c.id
                ORDER BY c.display_order ASC, c.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get enrollment status and progress for authenticated users
            if ($this->isAuthenticated && !empty($courses)) {
                $courseIds = array_column($courses, 'id');
                $placeholders = str_repeat('?,', count($courseIds) - 1) . '?';
                
                // Get enrollments
                $enrollQuery = "
                    SELECT course_id, enrolled_at, completed_at 
                    FROM course_enrollments 
                    WHERE user_id = ? AND course_id IN ($placeholders)
                ";
                $enrollParams = array_merge([$this->userId], $courseIds);
                $stmt = $this->db->prepare($enrollQuery);
                $stmt->execute($enrollParams);
                $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $enrollmentMap = [];
                foreach ($enrollments as $enrollment) {
                    $enrollmentMap[$enrollment['course_id']] = $enrollment;
                }
                
                // Get progress
                $progressQuery = "
                    SELECT 
                        l.course_id,
                        COUNT(DISTINCT l.id) as total_lessons,
                        COUNT(DISTINCT lp.lesson_id) as completed_lessons
                    FROM lessons l
                    LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ? AND lp.completed_at IS NOT NULL
                    WHERE l.course_id IN ($placeholders) AND l.is_published = 1
                    GROUP BY l.course_id
                ";
                $progressParams = array_merge([$this->userId], $courseIds);
                $stmt = $this->db->prepare($progressQuery);
                $stmt->execute($progressParams);
                $progressData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $progressMap = [];
                foreach ($progressData as $progress) {
                    $progressMap[$progress['course_id']] = [
                        'total_lessons' => (int)$progress['total_lessons'],
                        'completed_lessons' => (int)$progress['completed_lessons'],
                        'progress_percentage' => $progress['total_lessons'] > 0 
                            ? round(($progress['completed_lessons'] / $progress['total_lessons']) * 100) 
                            : 0
                    ];
                }
                
                foreach ($courses as &$course) {
                    $course['user_enrollment'] = $enrollmentMap[$course['id']] ?? null;
                    $course['user_progress'] = $progressMap[$course['id']] ?? null;
                }
            }
            
            $response = [
                'courses' => $courses,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
            $this->sendSuccess($response);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch courses: ' . $e->getMessage());
        }
    }
    
    /**
     * List lessons in a course
     * GET /api/v1/courses/{id}/lessons
     */
    protected function listLessons($courseId) {
        try {
            // First check if course exists and user has access
            $stmt = $this->db->prepare("
                SELECT c.community_id, c.name as course_name, c.is_published as course_published
                FROM courses c 
                WHERE c.id = ?
            ");
            $stmt->execute([$courseId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$course) {
                $this->sendError(404, 'Course not found');
            }
            
            if (!$this->checkCommunityAccess($course['community_id'])) {
                $this->sendError(403, 'Access denied');
            }
            
            // Get lessons
            $query = "
                SELECT 
                    l.*,
                    m.name as module_name,
                    m.display_order as module_order
                FROM lessons l
                LEFT JOIN modules m ON l.module_id = m.id
                WHERE l.course_id = ? AND l.is_published = 1
                ORDER BY m.display_order ASC, l.display_order ASC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$courseId]);
            $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get lesson progress for authenticated users
            if ($this->isAuthenticated && !empty($lessons)) {
                $lessonIds = array_column($lessons, 'id');
                $placeholders = str_repeat('?,', count($lessonIds) - 1) . '?';
                
                $progressQuery = "
                    SELECT 
                        lesson_id,
                        started_at,
                        completed_at,
                        progress_percentage,
                        time_spent_seconds
                    FROM lesson_progress 
                    WHERE user_id = ? AND lesson_id IN ($placeholders)
                ";
                $progressParams = array_merge([$this->userId], $lessonIds);
                $stmt = $this->db->prepare($progressQuery);
                $stmt->execute($progressParams);
                $progressData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $progressMap = [];
                foreach ($progressData as $progress) {
                    $progressMap[$progress['lesson_id']] = $progress;
                }
                
                foreach ($lessons as &$lesson) {
                    $lesson['user_progress'] = $progressMap[$lesson['id']] ?? null;
                }
            }
            
            // Group lessons by module
            $moduleGroups = [];
            foreach ($lessons as $lesson) {
                $moduleName = $lesson['module_name'] ?? 'No Module';
                if (!isset($moduleGroups[$moduleName])) {
                    $moduleGroups[$moduleName] = [
                        'module_name' => $moduleName,
                        'module_order' => $lesson['module_order'] ?? 999,
                        'lessons' => []
                    ];
                }
                unset($lesson['module_name'], $lesson['module_order']);
                $moduleGroups[$moduleName]['lessons'][] = $lesson;
            }
            
            // Sort modules by order
            uasort($moduleGroups, function($a, $b) {
                return $a['module_order'] <=> $b['module_order'];
            });
            
            $response = [
                'course_id' => $courseId,
                'course_name' => $course['course_name'],
                'lessons' => $lessons,
                'lessons_by_module' => array_values($moduleGroups),
                'total_lessons' => count($lessons)
            ];
            
            $this->sendSuccess($response);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch lessons: ' . $e->getMessage());
        }
    }
}

// Initialize and handle request
$api = new CoursesAPI();
$api->handleRequest();