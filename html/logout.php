<?php
/**
 * Logout Script
 * 
 * Ends user session and redirects to login
 */

require_once 'includes/session.php';

// Destroy session
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Redirect to login
header('Location: /login');
exit;