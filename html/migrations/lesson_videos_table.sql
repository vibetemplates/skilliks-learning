-- Migration: Add lesson videos table for multiple YouTube videos per lesson
-- This allows each lesson to have multiple videos with ordering and metadata

CREATE TABLE IF NOT EXISTS `lesson_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `youtube_url` varchar(500) NOT NULL,
  `youtube_id` varchar(50) NOT NULL, -- Extracted YouTube video ID
  `duration_seconds` int(11) DEFAULT 0,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_order_index` (`order_index`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for efficient video ordering
CREATE INDEX idx_lesson_videos_order ON lesson_videos(lesson_id, order_index, is_active);