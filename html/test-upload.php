<?php
// Test PHP upload configuration
phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_ENVIRONMENT | INFO_VARIABLES);

echo "<h2>Upload Configuration:</h2>";
echo "file_uploads: " . ini_get('file_uploads') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "max_input_time: " . ini_get('max_input_time') . "<br>";

echo "<h2>GD Library Support:</h2>";
if (extension_loaded('gd')) {
    $gdInfo = gd_info();
    foreach ($gdInfo as $key => $value) {
        echo $key . ": " . ($value === true ? 'Enabled' : $value) . "<br>";
    }
} else {
    echo "GD library not installed!";
}