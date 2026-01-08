<?php
require_once '../../includes/session.php';
require_once '../../config/functions.php';
require_once '../../classes/User.php';

// Set JSON content type
header('Content-Type: application/json');

// Require admin
requireLogin();
$userObj = new User();
if (!$userObj->isAdmin($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// Clear generation session data
unset($_SESSION['generating_drills']);
unset($_SESSION['lessons_to_generate']);
unset($_SESSION['generation_progress']);
unset($_SESSION['generation_total']);
unset($_SESSION['generation_results']);

echo json_encode(['success' => true]);