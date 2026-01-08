<?php
/**
 * Calendar Create API
 * 
 * Creates a new calendar event
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../classes/Calendar.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['community_id', 'title', 'start_datetime', 'end_datetime'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$calendar = new Calendar();
$userId = getCurrentUserId();
$communityId = $input['community_id'];

// Check if user can manage events
if (!$calendar->canManageEvents($userId, $communityId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to create events']);
    exit;
}

// Validate dates
$startDate = new DateTime($input['start_datetime']);
$endDate = new DateTime($input['end_datetime']);

if ($endDate < $startDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
    exit;
}

// Prepare event data
$eventData = [
    'community_id' => $communityId,
    'title' => $input['title'],
    'description' => $input['description'] ?? null,
    'start_datetime' => $input['start_datetime'],
    'end_datetime' => $input['end_datetime'],
    'all_day' => isset($input['all_day']) ? (bool)$input['all_day'] : false,
    'location' => $input['location'] ?? null,
    'zoom_link' => $input['zoom_link'] ?? null,
    'color' => $input['color'] ?? '#0d6efd',
    'project_id' => !empty($input['project_id']) ? $input['project_id'] : null,
    'course_id' => !empty($input['course_id']) ? $input['course_id'] : null,
    'recurrence_type' => $input['recurrence_type'] ?? 'none',
    'recurrence_end_date' => $input['recurrence_end_date'] ?? null,
    'created_by' => $userId
];

// Validate zoom link format if provided
if (!empty($eventData['zoom_link']) && !filter_var($eventData['zoom_link'], FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Zoom link format']);
    exit;
}

// Create the event
$eventId = $calendar->createEvent($eventData);

if ($eventId) {
    echo json_encode([
        'success' => true,
        'message' => 'Event created successfully',
        'event_id' => $eventId
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create event']);
}