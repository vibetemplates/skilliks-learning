<?php
/**
 * Calendar Get API
 * 
 * Gets calendar event details
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get event ID
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing event ID']);
    exit;
}

$calendar = new Calendar();
$event = $calendar->getEventById($eventId);

if (!$event) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

// Check if user has access to this event's community
$userId = getCurrentUserId();
$db = getDB();

$stmt = $db->prepare("
    SELECT 1 FROM community_members 
    WHERE user_id = :user_id 
    AND community_id = :community_id 
    AND is_active = 1
");
$stmt->execute([
    ':user_id' => $userId,
    ':community_id' => $event['community_id']
]);

if (!$stmt->fetch() && !isCurrentUserAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get attendees if requested
if (isset($_GET['include_attendees']) && $_GET['include_attendees'] === 'true') {
    $event['attendees'] = $calendar->getEventAttendees($eventId);
}

echo json_encode([
    'success' => true,
    'event' => $event
]);