<?php
/**
 * HTMX Login Endpoint
 * Handles login form submission and returns appropriate HTML fragments
 */

require_once '../includes/session.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validate input
if (empty($email) || empty($password)) {
    // Return error message fragment
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'Please enter both email and password.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    exit;
}

// Get database connection
$db = getDB();

// Find user by email
$stmt = $db->prepare("SELECT id, email, password_hash, first_name, last_name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // Login successful
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    
    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Handle remember me
    if ($remember) {
        setcookie('remember_user', $user['email'], time() + (86400 * 30), '/');
    }
    
    // Set success message for next page
    setFlashMessage('success', 'Welcome back, ' . htmlspecialchars($user['first_name']) . '!');
    
    // Return success with HX-Redirect header
    $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard';
    unset($_SESSION['redirect_after_login']);
    
    header('HX-Redirect: ' . $redirect);
    exit;
} else {
    // Return error message fragment
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo 'Invalid email or password.';
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}