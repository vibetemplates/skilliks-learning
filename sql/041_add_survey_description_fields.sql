-- Add description fields to survey answer options for tooltips
-- Created: 2025-07-21

-- Add description field to survey_answer_options table
ALTER TABLE survey_answer_options 
ADD COLUMN description TEXT DEFAULT NULL AFTER option_value;

-- Update existing ranking options with descriptions
UPDATE survey_answer_options sao
JOIN survey_questions sq ON sao.question_id = sq.id
SET sao.description = CASE sao.option_text
    WHEN 'Building Automations' THEN 'Creating automated workflows and scripts to streamline repetitive tasks'
    WHEN 'Building MCP Servers' THEN 'Developing Model Context Protocol servers for AI integration'
    WHEN 'Vibe Coding' THEN 'Real-time collaborative coding with AI assistance'
    WHEN 'Retrieval Augmented Generation' THEN 'Enhancing AI responses with external knowledge sources'
    WHEN 'Supervised Fine Tuning' THEN 'Training AI models on specific datasets for specialized tasks'
    WHEN 'Running Local Models' THEN 'Setting up and running AI models on local hardware'
    WHEN 'Project Management' THEN 'Planning, organizing, and managing software development projects'
    WHEN 'Infrastructure' THEN 'Managing servers, networks, and deployment systems'
    ELSE NULL
END
WHERE sq.question_type = 'ranking';

-- Update existing skill area options with descriptions
UPDATE survey_answer_options sao
JOIN survey_questions sq ON sao.question_id = sq.id
SET sao.description = CASE sao.option_text
    WHEN 'Frontend Development' THEN 'Building user interfaces with HTML, CSS, JavaScript, and modern frameworks'
    WHEN 'Backend Development' THEN 'Server-side programming, APIs, and database management'
    WHEN 'Mobile Development' THEN 'Creating applications for iOS, Android, and cross-platform solutions'
    WHEN 'DevOps/Infrastructure' THEN 'CI/CD pipelines, cloud services, containerization, and system administration'
    WHEN 'Data Science/ML' THEN 'Machine learning, data analysis, and statistical modeling'
    WHEN 'UI/UX Design' THEN 'User interface design, user experience, and prototyping'
    WHEN 'Database Management' THEN 'SQL, NoSQL, database design, and optimization'
    WHEN 'Security' THEN 'Application security, cryptography, and vulnerability assessment'
    WHEN 'Testing/QA' THEN 'Unit testing, integration testing, and quality assurance'
    WHEN 'Project Management' THEN 'Agile/Scrum, team coordination, and project planning'
    ELSE NULL
END
WHERE sq.question_text = 'Which areas of software development interest you the most?';

-- Update existing framework options with descriptions
UPDATE survey_answer_options sao
JOIN survey_questions sq ON sao.question_id = sq.id
SET sao.description = CASE sao.option_text
    WHEN 'React' THEN 'JavaScript library for building user interfaces'
    WHEN 'Angular' THEN 'TypeScript-based web application framework'
    WHEN 'Vue.js' THEN 'Progressive JavaScript framework for building UIs'
    WHEN 'Node.js' THEN 'JavaScript runtime for server-side development'
    WHEN 'Django' THEN 'High-level Python web framework'
    WHEN 'Laravel' THEN 'PHP web application framework'
    WHEN 'Spring Boot' THEN 'Java-based framework for microservices'
    WHEN 'Flask' THEN 'Lightweight Python web framework'
    WHEN 'Express.js' THEN 'Minimal Node.js web application framework'
    WHEN 'Bootstrap' THEN 'CSS framework for responsive web design'
    ELSE NULL
END
WHERE sq.question_text = 'Which frameworks are you familiar with?';

-- Update question help_text to be more descriptive where needed
UPDATE survey_questions
SET help_text = 'Rate your proficiency level from 1 (just starting to learn) to 5 (expert with extensive experience)'
WHERE question_text LIKE 'Rate your%' AND question_type = 'radio';

UPDATE survey_questions
SET help_text = 'This helps us understand your experience level and match you with appropriate projects'
WHERE question_text = 'How many years of programming experience do you have?';

UPDATE survey_questions
SET help_text = 'We use this to ensure project teams have compatible schedules'
WHERE question_text = 'What is your preferred working schedule?';

UPDATE survey_questions
SET help_text = 'Most projects require some synchronous collaboration for planning and coordination'
WHERE question_text = 'Are you available for team meetings?';