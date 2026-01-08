-- Educational Features Database Schema
-- This script adds comprehensive educational functionality to the project tracking tool

-- =============================================
-- COURSES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `short_description` varchar(500),
  `course_code` varchar(50),
  `category` varchar(100),
  `difficulty_level` enum('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
  `duration_hours` decimal(5,2) DEFAULT 0.00,
  `prerequisites` text,
  `learning_objectives` text,
  `status` enum('draft', 'published', 'archived') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `thumbnail_url` varchar(500),
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` timestamp NULL,
  `order_index` int(11) DEFAULT 0,
  `tags` text,
  `certificate_available` tinyint(1) DEFAULT 0,
  `passing_score` decimal(5,2) DEFAULT 70.00,
  PRIMARY KEY (`id`),
  KEY `idx_course_code` (`course_code`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_difficulty` (`difficulty_level`),
  KEY `idx_featured` (`featured`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- LESSONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `content` longtext,
  `lesson_type` enum('video', 'text', 'interactive', 'quiz', 'assignment', 'resource') DEFAULT 'text',
  `duration_minutes` int(11) DEFAULT 0,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft', 'published', 'archived') DEFAULT 'draft',
  `is_mandatory` tinyint(1) DEFAULT 1,
  `video_url` varchar(500),
  `video_duration` int(11) DEFAULT 0,
  `attachment_url` varchar(500),
  `quiz_data` json,
  `assignment_data` json,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_order_index` (`order_index`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_lesson_type` (`lesson_type`),
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- COURSE ENROLLMENTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `course_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completion_date` timestamp NULL,
  `status` enum('enrolled', 'in_progress', 'completed', 'dropped', 'failed') DEFAULT 'enrolled',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `final_score` decimal(5,2) DEFAULT NULL,
  `certificate_issued` tinyint(1) DEFAULT 0,
  `certificate_url` varchar(500),
  `enrolled_by` int unsigned, -- Who enrolled this user (could be self-enrollment or admin)
  `notes` text,
  `last_accessed` timestamp NULL,
  `time_spent_minutes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_course` (`user_id`, `course_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_status` (`status`),
  KEY `idx_completion_date` (`completion_date`),
  KEY `idx_enrolled_by` (`enrolled_by`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- LESSON PROGRESS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `lesson_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('not_started', 'in_progress', 'completed', 'skipped') DEFAULT 'not_started',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `score` decimal(5,2) DEFAULT NULL,
  `time_spent_minutes` int(11) DEFAULT 0,
  `started_at` timestamp NULL,
  `completed_at` timestamp NULL,
  `last_accessed` timestamp NULL,
  `attempts` int(11) DEFAULT 0,
  `quiz_responses` json,
  `assignment_submission` json,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_lesson` (`user_id`, `lesson_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_status` (`status`),
  KEY `idx_completed_at` (`completed_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- COURSE COMMENTS TABLE (Discussion System)
-- =============================================
CREATE TABLE IF NOT EXISTS `course_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `lesson_id` int(11) NULL, -- NULL for course-level comments
  `user_id` int unsigned NOT NULL,
  `parent_comment_id` int(11) NULL, -- For threading/replies
  `content` text NOT NULL,
  `comment_type` enum('question', 'discussion', 'feedback', 'announcement') DEFAULT 'discussion',
  `status` enum('active', 'hidden', 'deleted') DEFAULT 'active',
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_instructor_response` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `edited_at` timestamp NULL,
  `likes_count` int(11) DEFAULT 0,
  `replies_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent_comment_id` (`parent_comment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_comment_type` (`comment_type`),
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_comment_id`) REFERENCES `course_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- COMMENT LIKES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `comment_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_comment_like` (`user_id`, `comment_id`),
  KEY `idx_comment_id` (`comment_id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`comment_id`) REFERENCES `course_comments` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PROJECT COURSE ASSIGNMENTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `project_course_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int unsigned NOT NULL,
  `course_id` int(11) NOT NULL,
  `assignment_type` enum('required', 'recommended', 'optional') DEFAULT 'recommended',
  `assigned_by` int unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_date` date NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_course` (`project_id`, `course_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_assignment_type` (`assignment_type`),
  FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TASK LESSON ASSIGNMENTS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `task_lesson_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int unsigned NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `assignment_type` enum('prerequisite', 'supporting', 'follow_up') DEFAULT 'supporting',
  `assigned_by` int unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_lesson` (`task_id`, `lesson_id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_assignment_type` (`assignment_type`),
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- LEARNING ANALYTICS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `learning_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `course_id` int(11) NULL,
  `lesson_id` int(11) NULL,
  `project_id` int(11) NULL,
  `task_id` int(11) NULL,
  `action_type` enum('course_start', 'lesson_start', 'lesson_complete', 'quiz_attempt', 'assignment_submit', 'comment_post', 'discussion_view', 'resource_download') NOT NULL,
  `action_data` json,
  `session_id` varchar(255),
  `ip_address` varchar(45),
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_lesson_id` (`lesson_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- COURSE CATEGORIES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `course_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(100),
  `color` varchar(7) DEFAULT '#007bff',
  `parent_id` int(11) NULL, -- For nested categories
  `order_index` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_order_index` (`order_index`),
  FOREIGN KEY (`parent_id`) REFERENCES `course_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- COURSE INSTRUCTORS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS `course_instructors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role` enum('lead_instructor', 'assistant', 'guest') DEFAULT 'lead_instructor',
  `can_edit_course` tinyint(1) DEFAULT 0,
  `can_grade` tinyint(1) DEFAULT 1,
  `can_moderate_discussions` tinyint(1) DEFAULT 1,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int unsigned NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_instructor` (`course_id`, `user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_role` (`role`),
  FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- INSERT INITIAL DATA
-- =============================================

-- Insert default course categories
INSERT INTO `course_categories` (`name`, `description`, `slug`, `icon`, `color`, `order_index`) VALUES
('Programming', 'Software development and programming courses', 'programming', 'bi-code-slash', '#007bff', 1),
('Project Management', 'Project management methodologies and tools', 'project-management', 'bi-kanban', '#28a745', 2),
('Design', 'UI/UX design and graphic design courses', 'design', 'bi-palette', '#e83e8c', 3),
('Business', 'Business skills and entrepreneurship', 'business', 'bi-briefcase', '#ffc107', 4),
('Data Science', 'Data analysis and machine learning', 'data-science', 'bi-graph-up', '#17a2b8', 5),
('Communication', 'Communication and collaboration skills', 'communication', 'bi-chat-dots', '#6f42c1', 6);

-- Create triggers to update comment counts
DELIMITER //

CREATE TRIGGER update_comment_reply_count_insert
AFTER INSERT ON course_comments
FOR EACH ROW
BEGIN
    IF NEW.parent_comment_id IS NOT NULL THEN
        UPDATE course_comments 
        SET replies_count = replies_count + 1 
        WHERE id = NEW.parent_comment_id;
    END IF;
END//

CREATE TRIGGER update_comment_reply_count_delete
AFTER DELETE ON course_comments
FOR EACH ROW
BEGIN
    IF OLD.parent_comment_id IS NOT NULL THEN
        UPDATE course_comments 
        SET replies_count = replies_count - 1 
        WHERE id = OLD.parent_comment_id;
    END IF;
END//

CREATE TRIGGER update_comment_likes_count_insert
AFTER INSERT ON comment_likes
FOR EACH ROW
BEGIN
    UPDATE course_comments 
    SET likes_count = likes_count + 1 
    WHERE id = NEW.comment_id;
END//

CREATE TRIGGER update_comment_likes_count_delete
AFTER DELETE ON comment_likes
FOR EACH ROW
BEGIN
    UPDATE course_comments 
    SET likes_count = likes_count - 1 
    WHERE id = OLD.comment_id;
END//

DELIMITER ;

-- Add indexes for better performance
CREATE INDEX idx_course_enrollments_user_status ON course_enrollments(user_id, status);
CREATE INDEX idx_lesson_progress_user_course ON lesson_progress(user_id, course_id, status);
CREATE INDEX idx_comments_course_lesson ON course_comments(course_id, lesson_id, status);
CREATE INDEX idx_analytics_user_date ON learning_analytics(user_id, created_at);