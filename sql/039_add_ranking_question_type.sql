-- Add ranking question type to survey system
-- Created: 2025-07-19

-- Add 'ranking' to the question_type enum
ALTER TABLE survey_questions 
MODIFY COLUMN question_type ENUM('text', 'textarea', 'radio', 'checkbox', 'dropdown', 'scale', 'date', 'ranking') NOT NULL;

-- For ranking questions, we'll store the rank value in a new column
ALTER TABLE survey_responses 
ADD COLUMN rank_value INT UNSIGNED DEFAULT NULL AFTER answer_option_id,
ADD INDEX idx_rank_value (question_id, user_id, rank_value);

-- Add sample ranking question to the Interests section
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text)
SELECT 
    ss.id,
    'Put the topics that interest you in order of most interested to least interested',
    'ranking',
    FALSE,
    2,
    'Drag and drop items to rank them from top (most interested) to bottom (least interested)'
FROM survey_sections ss
JOIN surveys s ON ss.survey_id = s.id
WHERE ss.name = 'Interests' AND s.type = 'skills';

-- Add answer options for the ranking question
INSERT INTO survey_answer_options (question_id, option_text, display_order)
SELECT 
    sq.id,
    opt.option_text,
    opt.display_order
FROM survey_questions sq
JOIN survey_sections ss ON sq.section_id = ss.id
JOIN surveys s ON ss.survey_id = s.id
CROSS JOIN (
    SELECT 'Building Automations' as option_text, 1 as display_order
    UNION ALL SELECT 'Building MCP Servers', 2
    UNION ALL SELECT 'Vibe Coding', 3
    UNION ALL SELECT 'Retrieval Augmented Generation', 4
    UNION ALL SELECT 'Supervised Fine Tuning', 5
    UNION ALL SELECT 'Running Local Models', 6
    UNION ALL SELECT 'Project Management', 7
    UNION ALL SELECT 'Infrastructure', 8
) opt
WHERE sq.question_text = 'Put the topics that interest you in order of most interested to least interested'
AND sq.question_type = 'ranking'
AND ss.name = 'Interests' 
AND s.type = 'skills';