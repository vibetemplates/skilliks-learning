-- Migration: Create Quiz Tables (Simplified without triggers)
-- This migration converts the JSON-based quiz system to normalized database tables
-- Date: 2025-01-18

-- =============================================
-- QUIZZES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `instructions` text,
  `passing_score` decimal(5,2) DEFAULT 70.00,
  `max_attempts` int(11) DEFAULT 3,
  `time_limit_minutes` int(11) DEFAULT NULL,
  `shuffle_questions` tinyint(1) DEFAULT 0,
  `shuffle_answers` tinyint(1) DEFAULT 0,
  `show_correct_answers` tinyint(1) DEFAULT 1,
  `show_score_immediately` tinyint(1) DEFAULT 1,
  `allow_review` tinyint(1) DEFAULT 1,
  `total_points` decimal(10,2) DEFAULT 0.00,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lesson_quiz` (`lesson_id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- QUIZ QUESTIONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice', 'true_false', 'short_answer', 'essay', 'matching', 'fill_blank') NOT NULL,
  `explanation` text,
  `hint` text,
  `points` decimal(5,2) DEFAULT 1.00,
  `order_index` int(11) DEFAULT 0,
  `is_required` tinyint(1) DEFAULT 1,
  `media_url` varchar(500),
  `media_type` enum('image', 'video', 'audio') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_question_type` (`question_type`),
  KEY `idx_order_index` (`order_index`),
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- QUIZ ANSWER OPTIONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `quiz_answer_options` (
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
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- QUIZ ATTEMPTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `lesson_progress_id` int(11),
  `attempt_number` int(11) DEFAULT 1,
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL,
  `time_spent_seconds` int(11) DEFAULT 0,
  `score_achieved` decimal(5,2) DEFAULT NULL,
  `points_earned` decimal(10,2) DEFAULT NULL,
  `total_points` decimal(10,2) DEFAULT NULL,
  `status` enum('in_progress', 'completed', 'abandoned', 'timed_out') DEFAULT 'in_progress',
  `passed` tinyint(1) DEFAULT NULL,
  `ip_address` varchar(45),
  `user_agent` text,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_lesson_progress_id` (`lesson_progress_id`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_quiz_user` (`quiz_id`, `user_id`),
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_progress_id`) REFERENCES `lesson_progress` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- QUIZ RESPONSES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `quiz_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_option_id` int(11) DEFAULT NULL,
  `answer_text` text,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_earned` decimal(5,2) DEFAULT 0.00,
  `time_spent_seconds` int(11) DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `graded_by` int unsigned DEFAULT NULL,
  `graded_at` timestamp NULL,
  `grader_feedback` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_attempt_id` (`attempt_id`),
  KEY `idx_question_id` (`question_id`),
  KEY `idx_answer_option_id` (`answer_option_id`),
  KEY `idx_is_correct` (`is_correct`),
  KEY `idx_graded_by` (`graded_by`),
  FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`answer_option_id`) REFERENCES `quiz_answer_options` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- QUIZ QUESTION BANK TABLE (Optional - for reusable questions)
-- =============================================
CREATE TABLE IF NOT EXISTS `question_bank` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) DEFAULT NULL,
  `category` varchar(100),
  `subcategory` varchar(100),
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice', 'true_false', 'short_answer', 'essay', 'matching', 'fill_blank') NOT NULL,
  `difficulty_level` enum('easy', 'medium', 'hard') DEFAULT 'medium',
  `explanation` text,
  `hint` text,
  `points` decimal(5,2) DEFAULT 1.00,
  `tags` text,
  `usage_count` int(11) DEFAULT 0,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_category` (`category`),
  KEY `idx_question_type` (`question_type`),
  KEY `idx_difficulty_level` (`difficulty_level`),
  KEY `idx_created_by` (`created_by`),
  FULLTEXT KEY `idx_fulltext_search` (`question_text`, `tags`),
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- QUESTION BANK ANSWERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `question_bank_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_bank_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `feedback` text,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_question_bank_id` (`question_bank_id`),
  KEY `idx_is_correct` (`is_correct`),
  FOREIGN KEY (`question_bank_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- QUIZ QUESTION LINKS (Links quiz questions to question bank)
-- =============================================
CREATE TABLE IF NOT EXISTS `quiz_question_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_question_id` int(11) NOT NULL,
  `question_bank_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_quiz_question_link` (`quiz_question_id`),
  KEY `idx_quiz_question_id` (`quiz_question_id`),
  KEY `idx_question_bank_id` (`question_bank_id`),
  FOREIGN KEY (`quiz_question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_bank_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- INDEXES FOR PERFORMANCE
-- =============================================
CREATE INDEX idx_quiz_attempts_user_quiz ON quiz_attempts(user_id, quiz_id, attempt_number);
CREATE INDEX idx_quiz_responses_attempt ON quiz_responses(attempt_id, question_id);
CREATE INDEX idx_quiz_questions_quiz ON quiz_questions(quiz_id, order_index);