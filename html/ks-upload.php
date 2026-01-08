<?php

   //-- Installation at use instructions and video at:
   //--          https://guides.kineticseas.com/

   error_reporting(E_ALL);
   ini_set('display_errors', 1);

   // Instructions
   //    Set the target directory. 
   //    Make sure "www-data:www-data" has write privileges.
   //    A Single file upload will go in the target directory.
   //    A Full Folder upload will create a subdirectory

   //-- SET TARGET DIR --
   $targetDir = "/var/www/uploads";

   $fileName = $_POST['fileName'];
   $fileSize = $_POST['fileSize'];
   if (isset($_POST['justPath'])) $path = dirname($_POST['justPath']);  else $path = "";
   if (isset($_POST['path'])) $full_path = $_POST['path'];  else $full_path = $fileName;

   $chunkOffset = $_POST['chunkOffset'];
   $chunk = $_FILES['fileChunk']['tmp_name'];

   $filePath=$full_path;
   if (!file_exists($targetDir . "/" . $path)) mkdir($targetDir . "/" . $path, 0777, true);

   if ($chunkOffset == 0 && file_exists($filePath)) {
        $backupName = $filePath . "_" . time();
        if (!rename($filePath, $backupName)) {
            echo "Failed to backup existing file\r\n";
            echo $full_path . "\r\n";
            echo $backupName . "\r\n";
            exit;
        }
   }

   $filePath = $targetDir . "/" . $full_path;
   if ($chunkOffset == 0) {
       $fp = fopen($filePath, 'w');
   } else {
       $fp = fopen($filePath, 'ab');
   }
   if ($fp === false) {
       echo "Cannot open file for writing";
       exit;
   }

   if (flock($fp, LOCK_EX)) {
       fseek($fp, $chunkOffset);
       $chunkData = file_get_contents($chunk);
       fwrite($fp, $chunkData);
       flock($fp, LOCK_UN);
   } else {
       echo "Could not lock file for writing";
       fclose($fp);
       exit;
   }
   fclose($fp);

   echo "Chunk at offset $chunkOffset uploaded successfully";

?>

