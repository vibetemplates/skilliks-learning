-- Migration: Create Learning Plan Recommendation Tables
-- Description: Tables for storing personalized learning recommendations based on survey responses
-- Date: 2025-07-19

-- Table for recommended projects
CREATE TABLE IF NOT EXISTS member_recommended_projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    community_id INT UNSIGNED NOT NULL,
    recommendation_reason TEXT,
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    status ENUM('pending', 'enrolled', 'completed', 'dismissed') DEFAULT 'pending',
    score DECIMAL(5,2) DEFAULT 0 COMMENT 'Relevance score based on survey match',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_project (user_id, project_id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_user_priority (user_id, priority),
    INDEX idx_score (score DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for recommended courses
CREATE TABLE IF NOT EXISTS member_recommended_courses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    community_id INT UNSIGNED NOT NULL,
    recommendation_reason TEXT,
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    status ENUM('pending', 'enrolled', 'completed', 'dismissed') DEFAULT 'pending',
    score DECIMAL(5,2) DEFAULT 0 COMMENT 'Relevance score based on survey match',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_course (user_id, course_id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_user_priority (user_id, priority),
    INDEX idx_score (score DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for skill assessments and recommendations
CREATE TABLE IF NOT EXISTS member_skill_assessments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    community_id INT UNSIGNED NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    current_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    target_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
    assessment_score DECIMAL(5,2) DEFAULT 0 COMMENT 'Score from survey or quiz',
    improvement_areas TEXT,
    recommended_resources TEXT COMMENT 'JSON array of resource links',
    last_assessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_skill (user_id, skill_name, community_id),
    INDEX idx_user_level (user_id, current_level),
    INDEX idx_skill_name (skill_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table to track learning plan generation history
CREATE TABLE IF NOT EXISTS learning_plan_generations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    community_id INT UNSIGNED NOT NULL,
    survey_completion_id INT UNSIGNED,
    generation_type ENUM('manual', 'automated', 'survey_based') DEFAULT 'survey_based',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    FOREIGN KEY (survey_completion_id) REFERENCES survey_completions(id) ON DELETE SET NULL,
    
    INDEX idx_user_generated (user_id, generated_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add sample data for testing (remove in production)
-- This will be replaced by actual recommendation engine based on survey responses