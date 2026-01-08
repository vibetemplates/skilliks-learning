<?php
/**
 * Landing Page
 *
 * Redirect to login page as default landing page
 */

require_once 'includes/session.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

// Redirect to login page
header('Location: /login');
exit;