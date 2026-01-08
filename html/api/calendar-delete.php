<?php
/**
 * Calendar Delete API
 * 
 * Deletes a calendar event
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

// Validate event ID
if (empty($input['event_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing event ID']);
    exit;
}

$eventId = (int)$input['event_id'];
$calendar = new Calendar();

// Get event details to check permissions
$event = $calendar->getEventById($eventId);

if (!$event) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

// Check if user can manage events in this community
$userId = getCurrentUserId();
if (!$calendar->canManageEvents($userId, $event['community_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this event']);
    exit;
}

// Delete the event
$deleteRecurring = isset($input['delete_recurring']) && $input['delete_recurring'] === true;
$success = $calendar->deleteEvent($eventId, $deleteRecurring);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Event deleted successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete event']);
}