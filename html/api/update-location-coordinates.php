<?php
/**
 * API endpoint to update user location coordinates
 * Called when geocoding is performed on the map
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$userId = $input['user_id'] ?? null;
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$currentUserId = getCurrentUserId();

// Validate input
if (!$userId || !$latitude || !$longitude) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Only allow admins to update other users' coordinates
if ($userId != $currentUserId && !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized to update other users']);
    exit;
}

try {
    $db = getDB();
    
    // Update user's coordinates
    $stmt = $db->prepare("
        UPDATE users 
        SET location_latitude = :latitude,
            location_longitude = :longitude,
            location_updated_at = NOW()
        WHERE id = :user_id
    ");
    
    $success = $stmt->execute([
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':user_id' => $userId
    ]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Coordinates updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update coordinates'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Update location coordinates error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating coordinates'
    ]);
}