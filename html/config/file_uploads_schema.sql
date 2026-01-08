-- File uploads and attachments schema
-- This file contains the database structure for handling file uploads

CREATE TABLE IF NOT EXISTS `file_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `file_hash` varchar(64) NOT NULL,
  `entity_type` enum('project', 'feature', 'task') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `last_downloaded` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_type_id` (`entity_type`, `entity_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `file_hash` (`file_hash`),
  KEY `upload_date` (`upload_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX `idx_entity_active` ON `file_attachments` (`entity_type`, `entity_id`, `is_active`);
CREATE INDEX `idx_uploader_date` ON `file_attachments` (`uploaded_by`, `upload_date`);

-- Add file upload directory configuration
CREATE TABLE IF NOT EXISTS `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL UNIQUE,
  `config_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration values
INSERT INTO `system_config` (`config_key`, `config_value`, `description`) VALUES
('upload_max_file_size', '50', 'Maximum file size in MB'),
('upload_allowed_extensions', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,csv,json,xml,html,css,js,php,py,java,cpp,c,h,md,zip,rar,7z,tar,gz,jpg,jpeg,png,gif,bmp,svg,mp3,mp4,avi,mov,wmv,flv,webm,ogg', 'Allowed file extensions (comma-separated)'),
('upload_blocked_extensions', 'exe,msi,bat,cmd,com,scr,pif,vbs,js,jar,app,deb,rpm,dmg,pkg', 'Blocked file extensions for security (comma-separated)'),
('upload_base_path', 'uploads/', 'Base directory for file uploads (relative to html directory)'),
('upload_require_login', '1', 'Require user login to upload files (1=yes, 0=no)')
ON DUPLICATE KEY UPDATE 
  config_value = VALUES(config_value),
  description = VALUES(description);