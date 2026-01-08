-- Add survey tables for user skills assessment
-- Created: 2025-07-19

-- Surveys table (for different types of surveys)
CREATE TABLE IF NOT EXISTS surveys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('skills', 'feedback', 'assessment') DEFAULT 'skills',
    is_active BOOLEAN DEFAULT TRUE,
    requires_completion BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    INDEX idx_community_active (community_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Survey sections (e.g., Interests, Skills, Experience, Availability)
CREATE TABLE IF NOT EXISTS survey_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    INDEX idx_survey_order (survey_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Survey questions
CREATE TABLE IF NOT EXISTS survey_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text', 'textarea', 'radio', 'checkbox', 'dropdown', 'scale', 'date') NOT NULL,
    is_required BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    help_text TEXT,
    validation_rules JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES survey_sections(id) ON DELETE CASCADE,
    INDEX idx_section_order (section_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answer options for multiple choice questions
CREATE TABLE IF NOT EXISTS survey_answer_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    option_text VARCHAR(500) NOT NULL,
    option_value VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
    INDEX idx_question_order (question_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User survey responses
CREATE TABLE IF NOT EXISTS survey_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_text TEXT,
    answer_option_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (answer_option_id) REFERENCES survey_answer_options(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_question (user_id, question_id),
    INDEX idx_user_survey (user_id, survey_id),
    INDEX idx_survey_responses (survey_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Survey completion tracking
CREATE TABLE IF NOT EXISTS survey_completions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    completion_percentage INT DEFAULT 0,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_survey (user_id, survey_id),
    INDEX idx_user_completion (user_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default skills survey for each community
INSERT INTO surveys (community_id, name, description, type, is_active)
SELECT id, 'Skills Assessment Survey', 'Help us understand your interests, skills, and availability to better match you with projects and learning opportunities.', 'skills', TRUE
FROM communities;

-- For each survey, create the default sections
INSERT INTO survey_sections (survey_id, name, description, display_order)
SELECT 
    s.id,
    section.name,
    section.description,
    section.display_order
FROM surveys s
CROSS JOIN (
    SELECT 'Interests' as name, 'Tell us about your areas of interest' as description, 1 as display_order
    UNION ALL
    SELECT 'Skills' as name, 'Rate your current skill levels' as description, 2 as display_order
    UNION ALL
    SELECT 'Experience' as name, 'Share your experience and background' as description, 3 as display_order
    UNION ALL
    SELECT 'Availability' as name, 'Let us know your availability' as description, 4 as display_order
) section
WHERE s.type = 'skills';

-- Add sample questions for the Interests section
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text)
SELECT 
    ss.id,
    'Which areas of software development interest you the most?',
    'checkbox',
    TRUE,
    1,
    'Select all that apply'
FROM survey_sections ss
JOIN surveys s ON ss.survey_id = s.id
WHERE ss.name = 'Interests' AND s.type = 'skills';

-- Add answer options for interests question
INSERT INTO survey_answer_options (question_id, option_text, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'Frontend Development' as option_text, 1 as display_order
    UNION ALL SELECT 'Backend Development', 2
    UNION ALL SELECT 'Mobile Development', 3
    UNION ALL SELECT 'DevOps/Infrastructure', 4
    UNION ALL SELECT 'Data Science/ML', 5
    UNION ALL SELECT 'UI/UX Design', 6
    UNION ALL SELECT 'Database Management', 7
    UNION ALL SELECT 'Security', 8
    UNION ALL SELECT 'Testing/QA', 9
    UNION ALL SELECT 'Project Management', 10
) opt
WHERE sq.question_text = 'Which areas of software development interest you the most?' 
AND ss.name = 'Interests' 
AND s.type = 'skills';

-- Add sample questions for Skills section
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text)
SELECT 
    ss.id,
    q.question_text,
    q.question_type,
    q.is_required,
    q.display_order,
    q.help_text
FROM survey_sections ss
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'Rate your JavaScript proficiency' as question_text, 'radio' as question_type, FALSE as is_required, 1 as display_order, '1 = Beginner, 5 = Expert' as help_text
    UNION ALL
    SELECT 'Rate your Python proficiency', 'radio', FALSE, 2, '1 = Beginner, 5 = Expert'
    UNION ALL
    SELECT 'Rate your SQL/Database skills', 'radio', FALSE, 3, '1 = Beginner, 5 = Expert'
    UNION ALL
    SELECT 'Which frameworks are you familiar with?', 'checkbox', FALSE, 4, 'Select all that apply'
) q
WHERE ss.name = 'Skills' AND s.type = 'skills';

-- Add skill level options (1-5 scale) for proficiency questions
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.option_value,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT '1 - Beginner' as option_text, '1' as option_value, 1 as display_order
    UNION ALL SELECT '2 - Novice', '2', 2
    UNION ALL SELECT '3 - Intermediate', '3', 3
    UNION ALL SELECT '4 - Advanced', '4', 4
    UNION ALL SELECT '5 - Expert', '5', 5
) opt
WHERE sq.question_type = 'radio' 
AND sq.question_text LIKE 'Rate your%'
AND ss.name = 'Skills' 
AND s.type = 'skills';

-- Add framework options
INSERT INTO survey_answer_options (question_id, option_text, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'React' as option_text, 1 as display_order
    UNION ALL SELECT 'Angular', 2
    UNION ALL SELECT 'Vue.js', 3
    UNION ALL SELECT 'Node.js', 4
    UNION ALL SELECT 'Django', 5
    UNION ALL SELECT 'Laravel', 6
    UNION ALL SELECT 'Spring Boot', 7
    UNION ALL SELECT 'Flask', 8
    UNION ALL SELECT 'Express.js', 9
    UNION ALL SELECT 'Bootstrap', 10
) opt
WHERE sq.question_text = 'Which frameworks are you familiar with?'
AND ss.name = 'Skills' 
AND s.type = 'skills';

-- Add questions for Experience section
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order)
SELECT 
    ss.id,
    q.question_text,
    q.question_type,
    q.is_required,
    q.display_order
FROM survey_sections ss
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'How many years of programming experience do you have?' as question_text, 'dropdown' as question_type, TRUE as is_required, 1 as display_order
    UNION ALL
    SELECT 'Describe your most significant project or achievement', 'textarea', FALSE, 2
    UNION ALL
    SELECT 'What is your current role or occupation?', 'text', FALSE, 3
) q
WHERE ss.name = 'Experience' AND s.type = 'skills';

-- Add experience level options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.option_value,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'Less than 1 year' as option_text, '0' as option_value, 1 as display_order
    UNION ALL SELECT '1-2 years', '1', 2
    UNION ALL SELECT '3-5 years', '3', 3
    UNION ALL SELECT '6-10 years', '6', 4
    UNION ALL SELECT 'More than 10 years', '10', 5
) opt
WHERE sq.question_text = 'How many years of programming experience do you have?'
AND ss.name = 'Experience' 
AND s.type = 'skills';

-- Add questions for Availability section
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order)
SELECT 
    ss.id,
    q.question_text,
    q.question_type,
    q.is_required,
    q.display_order
FROM survey_sections ss
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'How many hours per week can you dedicate to projects?' as question_text, 'dropdown' as question_type, TRUE as is_required, 1 as display_order
    UNION ALL
    SELECT 'What is your preferred working schedule?', 'radio', FALSE, 2
    UNION ALL
    SELECT 'Are you available for team meetings?', 'radio', TRUE, 3
) q
WHERE ss.name = 'Availability' AND s.type = 'skills';

-- Add hours per week options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.option_value,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'Less than 5 hours' as option_text, '5' as option_value, 1 as display_order
    UNION ALL SELECT '5-10 hours', '10', 2
    UNION ALL SELECT '10-20 hours', '20', 3
    UNION ALL SELECT '20-30 hours', '30', 4
    UNION ALL SELECT 'More than 30 hours', '40', 5
) opt
WHERE sq.question_text = 'How many hours per week can you dedicate to projects?'
AND ss.name = 'Availability' 
AND s.type = 'skills';

-- Add schedule preference options
INSERT INTO survey_answer_options (question_id, option_text, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'Mornings' as option_text, 1 as display_order
    UNION ALL SELECT 'Afternoons', 2
    UNION ALL SELECT 'Evenings', 3
    UNION ALL SELECT 'Weekends', 4
    UNION ALL SELECT 'Flexible', 5
) opt
WHERE sq.question_text = 'What is your preferred working schedule?'
AND ss.name = 'Availability' 
AND s.type = 'skills';

-- Add meeting availability options
INSERT INTO survey_answer_options (question_id, option_text, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'Yes, anytime' as option_text, 1 as display_order
    UNION ALL SELECT 'Yes, with advance notice', 2
    UNION ALL SELECT 'Limited availability', 3
    UNION ALL SELECT 'No', 4
) opt
WHERE sq.question_text = 'Are you available for team meetings?'
AND ss.name = 'Availability' 
AND s.type = 'skills';