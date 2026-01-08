<?php
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'classes/User.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$userObj = new User();

echo "<h2>Avatar Upload Debug</h2>";

// Check POST data
echo "<h3>POST Data Debug</h3>";
echo "<p><strong>REQUEST_METHOD:</strong> " . ($_SERVER['REQUEST_METHOD'] ?? 'Not set') . "</p>";
echo "<p><strong>POST action:</strong> " . ($_POST['action'] ?? 'Not set') . "</p>";

// Check FILES array
echo "<h3>FILES Array Debug</h3>";
if (empty($_FILES)) {
    echo "<p>❌ \$_FILES array is empty</p>";
} else {
    echo "<p>✓ \$_FILES array contains:</p>";
    echo "<pre>" . print_r($_FILES, true) . "</pre>";
    
    if (isset($_FILES['avatar_upload'])) {
        $file = $_FILES['avatar_upload'];
        echo "<p><strong>File details:</strong></p>";
        echo "<ul>";
        echo "<li>Name: " . ($file['name'] ?? 'Not set') . "</li>";
        echo "<li>Type: " . ($file['type'] ?? 'Not set') . "</li>";
        echo "<li>Size: " . ($file['size'] ?? 'Not set') . " bytes</li>";
        echo "<li>Tmp name: " . ($file['tmp_name'] ?? 'Not set') . "</li>";
        echo "<li>Error: " . ($file['error'] ?? 'Not set') . "</li>";
        echo "</ul>";
        
        // Check file upload errors
        if (isset($file['error'])) {
            switch ($file['error']) {
                case UPLOAD_ERR_OK:
                    echo "<p>✓ No upload errors</p>";
                    break;
                case UPLOAD_ERR_INI_SIZE:
                    echo "<p>❌ File exceeds upload_max_filesize directive</p>";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    echo "<p>❌ File exceeds MAX_FILE_SIZE directive</p>";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    echo "<p>❌ File was only partially uploaded</p>";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    echo "<p>❌ No file was uploaded</p>";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    echo "<p>❌ Missing temporary folder</p>";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    echo "<p>❌ Failed to write file to disk</p>";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    echo "<p>❌ Upload stopped by extension</p>";
                    break;
                default:
                    echo "<p>❌ Unknown upload error</p>";
                    break;
            }
        }
    } else {
        echo "<p>❌ avatar_upload not found in \$_FILES</p>";
    }
}

// Check PHP configuration
echo "<h3>PHP Upload Configuration</h3>";
echo "<p><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "</p>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_file_uploads:</strong> " . ini_get('max_file_uploads') . "</p>";
echo "<p><strong>upload_tmp_dir:</strong> " . (ini_get('upload_tmp_dir') ?: 'Default') . "</p>";

// Check if this is a form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Form Submission Detected</h3>";
    
    if (isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
        echo "<p>✓ Action is upload_avatar</p>";
        
        if (isset($_FILES['avatar_upload'])) {
            echo "<p>✓ avatar_upload file found</p>";
            
            // Test the upload
            $result = $userObj->uploadAvatar($currentUserId, $_FILES['avatar_upload']);
            echo "<p><strong>Upload result:</strong></p>";
            echo "<pre>" . print_r($result, true) . "</pre>";
        } else {
            echo "<p>❌ avatar_upload file NOT found in \$_FILES</p>";
        }
    } else {
        echo "<p>❌ Action is not upload_avatar</p>";
    }
}

// Test form
echo "<h3>Test Upload Form</h3>";
echo '<form method="POST" enctype="multipart/form-data" style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">';
echo '<input type="hidden" name="action" value="upload_avatar">';
echo '<div style="margin-bottom: 10px;">';
echo '<label for="avatar_upload">Choose file:</label><br>';
echo '<input type="file" id="avatar_upload" name="avatar_upload" accept="image/*" required>';
echo '</div>';
echo '<button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px;">Upload Test</button>';
echo '</form>';

echo "<h3>Upload Directory Check</h3>";
$uploadDir = dirname(__FILE__) . '/uploads/avatars/';
echo "<p><strong>Upload directory:</strong> " . $uploadDir . "</p>";
echo "<p><strong>Exists:</strong> " . (is_dir($uploadDir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Writable:</strong> " . (is_writable($uploadDir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Permissions:</strong> " . (file_exists($uploadDir) ? substr(sprintf('%o', fileperms($uploadDir)), -4) : 'N/A') . "</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
ul { margin: 5px 0 5px 20px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>