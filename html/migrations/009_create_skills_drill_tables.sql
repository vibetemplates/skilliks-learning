-- Migration: Create Skills Drill Tables
-- This migration creates tables for the skills drill system with point-based scoring
-- Date: 2025-08-01

-- =============================================
-- SKILLS DRILLS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `skills_drills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `instructions` text,
  `min_questions_per_session` int(11) DEFAULT 10,
  `max_questions_per_session` int(11) DEFAULT 20,
  `shuffle_questions` tinyint(1) DEFAULT 1,
  `shuffle_answers` tinyint(1) DEFAULT 1,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lesson_drill` (`lesson_id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SKILLS DRILL QUESTIONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `skills_drill_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `drill_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `explanation` text,
  `hint` text,
  `difficulty_level` enum('easy', 'medium', 'hard') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_drill_id` (`drill_id`),
  KEY `idx_difficulty_level` (`difficulty_level`),
  FOREIGN KEY (`drill_id`) REFERENCES `skills_drills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SKILLS DRILL ANSWER OPTIONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `skills_drill_answer_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `feedback` text,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_is_correct` (`is_correct`),
  KEY `idx_order_index` (`order_index`),
  FOREIGN KEY (`question_id`) REFERENCES `skills_drill_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SKILLS DRILL SESSIONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `skills_drill_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `drill_id` int(11) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL,
  `questions_presented` int(11) DEFAULT 0,
  `questions_answered` int(11) DEFAULT 0,
  `total_points` decimal(10,2) DEFAULT 0.00,
  `status` enum('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
  `ip_address` varchar(45),
  `user_agent` text,
  PRIMARY KEY (`id`),
  KEY `idx_drill_id` (`drill_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_drill_user` (`drill_id`, `user_id`),
  FOREIGN KEY (`drill_id`) REFERENCES `skills_drills` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SKILLS DRILL RESPONSES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `skills_drill_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `answer_option_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` decimal(5,2) DEFAULT 0.00,
  `answered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_answer_option_id` (`answer_option_id`),
  KEY `idx_attempt_number` (`attempt_number`),
  KEY `idx_session_question` (`session_id`, `question_id`),
  FOREIGN KEY (`session_id`) REFERENCES `skills_drill_sessions` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `skills_drill_questions` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`answer_option_id`) REFERENCES `skills_drill_answer_options` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- USER SKILLS DRILL STATS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `user_skills_drill_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `drill_id` int(11) NOT NULL,
  `total_sessions` int(11) DEFAULT 0,
  `total_questions_answered` int(11) DEFAULT 0,
  `total_points` decimal(10,2) DEFAULT 0.00,
  `best_session_points` decimal(10,2) DEFAULT 0.00,
  `last_session_date` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_drill` (`user_id`, `drill_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_drill_id` (`drill_id`),
  KEY `idx_total_points` (`total_points`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`drill_id`) REFERENCES `skills_drills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- INDEXES FOR PERFORMANCE
-- =============================================
CREATE INDEX idx_skills_drill_sessions_user_drill ON skills_drill_sessions(user_id, drill_id);
CREATE INDEX idx_skills_drill_responses_session ON skills_drill_responses(session_id, question_id, attempt_number);
CREATE INDEX idx_skills_drill_questions_drill ON skills_drill_questions(drill_id);