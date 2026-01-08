<?php
/**
 * Calendar Class
 * 
 * Handles calendar events and related operations
 */

require_once dirname(__DIR__) . '/config/database.php';

class Calendar {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create a new calendar event
     */
    public function createEvent($data) {
        $sql = "INSERT INTO calendar_events (
            community_id, title, description, start_datetime, end_datetime,
            all_day, location, zoom_link, color, project_id, course_id,
            recurrence_type, recurrence_end_date, created_by
        ) VALUES (
            :community_id, :title, :description, :start_datetime, :end_datetime,
            :all_day, :location, :zoom_link, :color, :project_id, :course_id,
            :recurrence_type, :recurrence_end_date, :created_by
        )";
        
        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':community_id' => $data['community_id'],
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':start_datetime' => $data['start_datetime'],
            ':end_datetime' => $data['end_datetime'],
            ':all_day' => $data['all_day'] ?? false,
            ':location' => $data['location'] ?? null,
            ':zoom_link' => $data['zoom_link'] ?? null,
            ':color' => $data['color'] ?? '#0d6efd',
            ':project_id' => $data['project_id'] ?? null,
            ':course_id' => $data['course_id'] ?? null,
            ':recurrence_type' => $data['recurrence_type'] ?? 'none',
            ':recurrence_end_date' => $data['recurrence_end_date'] ?? null,
            ':created_by' => $data['created_by']
        ];
        
        if ($stmt->execute($params)) {
            $eventId = $this->db->lastInsertId();
            
            // Handle recurring events
            if (isset($data['recurrence_type']) && $data['recurrence_type'] !== 'none') {
                $this->createRecurringEvents($eventId, $data);
            }
            
            return $eventId;
        }
        
        return false;
    }
    
    /**
     * Update an existing calendar event
     */
    public function updateEvent($eventId, $data) {
        $sql = "UPDATE calendar_events SET
            title = :title,
            description = :description,
            start_datetime = :start_datetime,
            end_datetime = :end_datetime,
            all_day = :all_day,
            location = :location,
            zoom_link = :zoom_link,
            color = :color,
            project_id = :project_id,
            course_id = :course_id
        WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':id' => $eventId,
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':start_datetime' => $data['start_datetime'],
            ':end_datetime' => $data['end_datetime'],
            ':all_day' => $data['all_day'] ?? false,
            ':location' => $data['location'] ?? null,
            ':zoom_link' => $data['zoom_link'] ?? null,
            ':color' => $data['color'] ?? '#0d6efd',
            ':project_id' => $data['project_id'] ?? null,
            ':course_id' => $data['course_id'] ?? null
        ];
        
        return $stmt->execute($params);
    }
    
    /**
     * Delete a calendar event
     */
    public function deleteEvent($eventId, $deleteRecurring = false) {
        if ($deleteRecurring) {
            // Delete all recurring instances
            $sql = "DELETE FROM calendar_events 
                    WHERE id = :id OR recurrence_parent_id = :parent_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $eventId, ':parent_id' => $eventId]);
        } else {
            // Delete single event
            $sql = "DELETE FROM calendar_events WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $eventId]);
        }
    }
    
    /**
     * Get events for a specific date range
     */
    public function getEventsByDateRange($communityId, $startDate, $endDate) {
        $sql = "SELECT e.*, 
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                p.name as project_name,
                c.title as course_title
            FROM calendar_events e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN projects p ON e.project_id = p.id
            LEFT JOIN courses c ON e.course_id = c.id
            WHERE e.community_id = :community_id
            AND (
                (e.start_datetime >= :start_date1 AND e.start_datetime <= :end_date1)
                OR (e.end_datetime >= :start_date2 AND e.end_datetime <= :end_date2)
                OR (e.start_datetime <= :start_date3 AND e.end_datetime >= :end_date3)
            )
            ORDER BY e.start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':community_id' => $communityId,
            ':start_date1' => $startDate,
            ':start_date2' => $startDate,
            ':start_date3' => $startDate,
            ':end_date1' => $endDate,
            ':end_date2' => $endDate,
            ':end_date3' => $endDate
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get a single event by ID
     */
    public function getEventById($eventId) {
        $sql = "SELECT e.*, 
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                p.name as project_name,
                c.title as course_title
            FROM calendar_events e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN projects p ON e.project_id = p.id
            LEFT JOIN courses c ON e.course_id = c.id
            WHERE e.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $eventId]);
        
        return $stmt->fetch();
    }
    
    /**
     * Get upcoming events for a user
     */
    public function getUpcomingEvents($communityId, $limit = 5) {
        $sql = "SELECT e.*, 
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                p.name as project_name,
                c.title as course_title
            FROM calendar_events e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN projects p ON e.project_id = p.id
            LEFT JOIN courses c ON e.course_id = c.id
            WHERE e.community_id = :community_id
            AND e.start_datetime >= NOW()
            ORDER BY e.start_datetime ASC
            LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':community_id', $communityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Check if user can manage calendar events
     */
    public function canManageEvents($userId, $communityId) {
        // Check if user is global admin
        $sql = "SELECT global_role FROM users WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();
        
        if ($user && $user['global_role'] === 'admin') {
            return true;
        }
        
        // Check if user is community admin
        $sql = "SELECT role FROM community_members 
                WHERE user_id = :user_id 
                AND community_id = :community_id 
                AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':community_id' => $communityId
        ]);
        $member = $stmt->fetch();
        
        return $member && $member['role'] === 'admin';
    }
    
    /**
     * Add attendee to event
     */
    public function addAttendee($eventId, $userId, $response = 'pending') {
        $sql = "INSERT INTO calendar_event_attendees (event_id, user_id, response)
                VALUES (:event_id, :user_id, :response)
                ON DUPLICATE KEY UPDATE response = :response, responded_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':event_id' => $eventId,
            ':user_id' => $userId,
            ':response' => $response
        ]);
    }
    
    /**
     * Get event attendees
     */
    public function getEventAttendees($eventId) {
        $sql = "SELECT a.*, u.first_name, u.last_name, u.email
                FROM calendar_event_attendees a
                JOIN users u ON a.user_id = u.id
                WHERE a.event_id = :event_id
                ORDER BY a.response DESC, u.last_name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':event_id' => $eventId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create recurring event instances
     */
    private function createRecurringEvents($parentId, $data) {
        $startDate = new DateTime($data['start_datetime']);
        $endDate = new DateTime($data['end_datetime']);
        $recurrenceEndDate = new DateTime($data['recurrence_end_date'] ?? '+1 year');
        
        $interval = $this->getRecurrenceInterval($data['recurrence_type']);
        if (!$interval) return;
        
        $currentStart = clone $startDate;
        $currentEnd = clone $endDate;
        
        while ($currentStart <= $recurrenceEndDate) {
            $currentStart->add($interval);
            $currentEnd->add($interval);
            
            if ($currentStart > $recurrenceEndDate) break;
            
            $recurringData = $data;
            $recurringData['start_datetime'] = $currentStart->format('Y-m-d H:i:s');
            $recurringData['end_datetime'] = $currentEnd->format('Y-m-d H:i:s');
            $recurringData['recurrence_parent_id'] = $parentId;
            $recurringData['recurrence_type'] = 'none'; // Prevent recursive creation
            
            $this->createEvent($recurringData);
        }
    }
    
    /**
     * Get recurrence interval based on type
     */
    private function getRecurrenceInterval($type) {
        switch ($type) {
            case 'daily':
                return new DateInterval('P1D');
            case 'weekly':
                return new DateInterval('P1W');
            case 'monthly':
                return new DateInterval('P1M');
            case 'yearly':
                return new DateInterval('P1Y');
            default:
                return null;
        }
    }
    
    /**
     * Get events for a specific project
     */
    public function getEventsByProject($projectId) {
        $sql = "SELECT e.*, 
                u.first_name as creator_first_name,
                u.last_name as creator_last_name
            FROM calendar_events e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.project_id = :project_id
            ORDER BY e.start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $projectId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get events for a specific course
     */
    public function getEventsByCourse($courseId) {
        $sql = "SELECT e.*, 
                u.first_name as creator_first_name,
                u.last_name as creator_last_name
            FROM calendar_events e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.course_id = :course_id
            ORDER BY e.start_datetime ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':course_id' => $courseId]);
        
        return $stmt->fetchAll();
    }
}