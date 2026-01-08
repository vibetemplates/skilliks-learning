<?php
/**
 * API v1 Router
 * 
 * Main entry point for API v1 endpoints
 * Routes requests to appropriate endpoint handlers
 */

// Get the endpoint from the URL
$endpoint = $_GET['endpoint'] ?? '';

// Remove any trailing slashes
$endpoint = rtrim($endpoint, '/');

// Define available endpoints
$endpoints = [
    'communities' => 'communities.php',
    'community' => 'communities.php',
    'projects' => 'projects.php',
    'project' => 'projects.php',
    'programs' => 'programs.php',
    'program' => 'programs.php',
    'courses' => 'courses.php',
    'course' => 'courses.php',
];

// Check if endpoint exists
if (isset($endpoints[$endpoint])) {
    require_once __DIR__ . '/' . $endpoints[$endpoint];
} else {
    // No endpoint specified or invalid endpoint
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint not found',
        'available_endpoints' => array_keys($endpoints)
    ], JSON_PRETTY_PRINT);
}