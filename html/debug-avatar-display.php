<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'classes/User.php';

requireLogin();

$db = Database::getInstance()->getConnection();
$userId = getCurrentUserId();

// Get user data
$stmt = $db->prepare("SELECT id, first_name, last_name, email, profile_photo FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Debug Avatar Display</h2>";
echo "<pre>";
echo "User ID: " . $userId . "\n";
echo "User Data:\n";
print_r($user);
echo "\n";

// Check uploads directory
$uploadDir = dirname(__FILE__) . '/uploads/avatars/';
echo "Upload Directory: " . $uploadDir . "\n";
echo "Directory exists: " . (is_dir($uploadDir) ? 'Yes' : 'No') . "\n";

if ($user['profile_photo']) {
    $fullPath = $uploadDir . $user['profile_photo'];
    echo "\nAvatar filename in DB: " . $user['profile_photo'] . "\n";
    echo "Full path: " . $fullPath . "\n";
    echo "File exists: " . (file_exists($fullPath) ? 'Yes' : 'No') . "\n";
    
    if (file_exists($fullPath)) {
        echo "File size: " . filesize($fullPath) . " bytes\n";
        echo "File permissions: " . decoct(fileperms($fullPath) & 0777) . "\n";
        $imageInfo = getimagesize($fullPath);
        if ($imageInfo) {
            echo "Image dimensions: " . $imageInfo[0] . "x" . $imageInfo[1] . "\n";
            echo "MIME type: " . $imageInfo['mime'] . "\n";
        }
    }
    
    // Check web-accessible path
    $webPath = "/uploads/avatars/" . $user['profile_photo'];
    echo "\nWeb path: " . $webPath . "\n";
    echo "Expected URL: " . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $webPath . "\n";
}

// List all files in avatars directory
echo "\nFiles in avatars directory:\n";
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- " . $file . "\n";
        }
    }
}

echo "</pre>";

// Display current avatar component state
echo "<h3>Current Avatar Display Component:</h3>";
$userObj = new User();
$currentUserId = $userId;
include 'includes/avatar-upload-component.php';
?>