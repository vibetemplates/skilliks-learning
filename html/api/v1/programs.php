<?php
/**
 * Programs API Endpoint
 * 
 * Handles:
 * GET /api/v1/programs?community_id={id} - List all programs in a community
 */

require_once 'BaseAPI.php';

class ProgramsAPI extends BaseAPI {
    
    public function handleRequest() {
        if ($this->method !== 'GET') {
            $this->sendError(405, 'Method not allowed');
        }
        
        $this->listPrograms();
    }
    
    /**
     * List programs in a community
     * GET /api/v1/programs?community_id={id}
     * 
     * Query params:
     * - community_id: Required - Community ID to filter programs
     * - is_published: Optional - Filter by published status (1 or 0)
     * - page: page number (default: 1)
     * - limit: items per page (default: 20, max: 100)
     */
    protected function listPrograms() {
        $communityId = $this->getParam('community_id');
        if (!$communityId) {
            $this->sendError(400, 'community_id parameter is required');
        }
        
        // Check if user has access to this community
        if (!$this->checkCommunityAccess($communityId)) {
            $this->sendError(403, 'Access denied to this community');
        }
        
        $isPublished = $this->getParam('is_published');
        $page = max(1, (int)$this->getParam('page', 1));
        $limit = min(100, max(1, (int)$this->getParam('limit', 20)));
        $offset = ($page - 1) * $limit;
        
        try {
            $conditions = ['p.community_id = ?'];
            $params = [$communityId];
            
            if ($isPublished !== null) {
                $conditions[] = 'p.is_published = ?';
                $params[] = (int)$isPublished;
            }
            
            $whereClause = implode(' AND ', $conditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM programs p WHERE $whereClause";
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get programs
            $query = "
                SELECT 
                    p.*,
                    COUNT(DISTINCT c.id) as course_count,
                    COUNT(DISTINCT pe.user_id) as enrolled_count
                FROM programs p
                LEFT JOIN program_courses pc ON p.id = pc.program_id
                LEFT JOIN courses c ON pc.course_id = c.id AND c.is_published = 1
                LEFT JOIN program_enrollments pe ON p.id = pe.program_id
                WHERE $whereClause
                GROUP BY p.id
                ORDER BY p.display_order ASC, p.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get enrollment status for authenticated users
            if ($this->isAuthenticated && !empty($programs)) {
                $programIds = array_column($programs, 'id');
                $placeholders = str_repeat('?,', count($programIds) - 1) . '?';
                $enrollQuery = "
                    SELECT program_id, enrolled_at, completed_at 
                    FROM program_enrollments 
                    WHERE user_id = ? AND program_id IN ($placeholders)
                ";
                $enrollParams = array_merge([$this->userId], $programIds);
                $stmt = $this->db->prepare($enrollQuery);
                $stmt->execute($enrollParams);
                $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $enrollmentMap = [];
                foreach ($enrollments as $enrollment) {
                    $enrollmentMap[$enrollment['program_id']] = $enrollment;
                }
                
                foreach ($programs as &$program) {
                    $program['user_enrollment'] = $enrollmentMap[$program['id']] ?? null;
                }
            }
            
            $response = [
                'programs' => $programs,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
            $this->sendSuccess($response);
            
        } catch (Exception $e) {
            $this->sendError(500, 'Failed to fetch programs: ' . $e->getMessage());
        }
    }
}

// Initialize and handle request
$api = new ProgramsAPI();
$api->handleRequest();