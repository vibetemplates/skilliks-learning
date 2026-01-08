<?php
/**
 * Serve avatar images directly
 * This bypasses any .htaccess issues
 */

require_once 'config/database.php';

// Check if serving by user_id
if (isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
    $db = getDB();
    
    // Get user's avatar filename from database
    $stmt = $db->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['profile_photo'])) {
        // Return default avatar
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">
                <rect width="80" height="80" fill="#6c757d"/>
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="30" font-family="Arial">
                    <tspan dy=".1em">👤</tspan>
                </text>
              </svg>';
        exit;
    }
    
    // Extract filename from the path
    $filename = basename($user['profile_photo']);
} else {
    // Get the requested filename
    $filename = isset($_GET['file']) ? basename($_GET['file']) : '';
}

if (empty($filename)) {
    http_response_code(404);
    exit('File not specified');
}

// Security: Only allow specific file extensions
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(403);
    exit('Invalid file type');
}

// Build the full path
$avatar_path = dirname(__FILE__) . '/uploads/avatars/' . $filename;

// Check if file exists
if (!file_exists($avatar_path)) {
    // Return default avatar
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">
            <rect width="80" height="80" fill="#6c757d"/>
            <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="30" font-family="Arial">
                <tspan dy=".1em">👤</tspan>
            </text>
          </svg>';
    exit;
}

// Get MIME type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

$mime_type = isset($mime_types[$file_extension]) ? $mime_types[$file_extension] : 'application/octet-stream';

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($avatar_path));
header('Cache-Control: public, max-age=86400'); // Cache for 1 day

// Output the file
readfile($avatar_path);
exit;
?>