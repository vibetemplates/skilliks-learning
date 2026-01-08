-- Create course_recommendations table to store personalized recommendations
CREATE TABLE IF NOT EXISTS course_recommendations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    course_id INT(11) NOT NULL,
    recommendation_type ENUM('beginner', 'interest_based', 'skill_gap', 'trending', 'ai_suggested') NOT NULL,
    reason TEXT,
    score DECIMAL(5,2) DEFAULT 0.00,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    viewed_at TIMESTAMP NULL DEFAULT NULL,
    enrolled_at TIMESTAMP NULL DEFAULT NULL,
    dismissed_at TIMESTAMP NULL DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_user_course (user_id, course_id),
    KEY idx_user_active (user_id, is_active),
    KEY idx_type (recommendation_type),
    KEY idx_generated (generated_at),
    CONSTRAINT fk_recommendation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_recommendation_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_course_type (user_id, course_id, recommendation_type)
);

-- Add index for performance
CREATE INDEX idx_user_recommendations ON course_recommendations(user_id, is_active, score DESC);