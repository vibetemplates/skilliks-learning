-- Insert default project survey template
-- This creates a comprehensive project planning survey

-- Insert the project survey (assuming community_id 1 for now, adjust as needed)
INSERT INTO surveys (community_id, name, description, type, is_active, requires_completion) 
VALUES (
    1, 
    'Project Planning Survey', 
    'This survey helps determine the right architecture, technology stack, and requirements for your project. Your responses will be analyzed by AI to generate customized recommendations including CLAUDE.md and requirements.md files.',
    'project', 
    1, 
    1
);

SET @survey_id = LAST_INSERT_ID();

-- Section 1: Project Overview
INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) 
VALUES (@survey_id, 'Project Overview', 'Basic information about your project', 1, 1);
SET @section1_id = LAST_INSERT_ID();

-- Section 1 Questions
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES
(@section1_id, 'What is the primary purpose of your project?', 'textarea', 1, 1, 'Describe the main goal and value proposition'),
(@section1_id, 'Who is the target audience?', 'text', 1, 2, 'Describe your primary users or customers'),
(@section1_id, 'What is the expected project timeline?', 'radio', 1, 3, 'Select the approximate timeline'),
(@section1_id, 'What is the project complexity level?', 'radio', 1, 4, 'Based on features and technical requirements');

-- Add answer options for timeline question
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 3), 'Less than 1 month', 'short', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 3), '1-3 months', 'medium', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 3), '3-6 months', 'long', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 3), 'More than 6 months', 'extended', 4, 1);

-- Add answer options for complexity question
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 4), 'Simple (basic CRUD, few features)', 'simple', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 4), 'Moderate (multiple features, some integrations)', 'moderate', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 4), 'Complex (many features, multiple integrations)', 'complex', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section1_id AND display_order = 4), 'Enterprise (large-scale, high complexity)', 'enterprise', 4, 1);

-- Section 2: Technical Requirements
INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) 
VALUES (@survey_id, 'Technical Requirements', 'Technology preferences and constraints', 2, 1);
SET @section2_id = LAST_INSERT_ID();

-- Section 2 Questions
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES
(@section2_id, 'Preferred programming languages (select all that apply)', 'checkbox', 0, 1, 'Leave blank for AI recommendation'),
(@section2_id, 'Preferred frontend framework', 'dropdown', 0, 2, 'Select your preferred framework or leave blank'),
(@section2_id, 'Preferred backend framework', 'dropdown', 0, 3, 'Select your preferred framework or leave blank'),
(@section2_id, 'Database requirements', 'checkbox', 1, 4, 'Select all that apply'),
(@section2_id, 'Any specific technology constraints or requirements?', 'textarea', 0, 5, 'e.g., must use React, avoid cloud services');

-- Add programming language options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'JavaScript/TypeScript', 'javascript', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'Python', 'python', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'Java', 'java', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'C#/.NET', 'csharp', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'Go', 'go', 5, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'Ruby', 'ruby', 6, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'PHP', 'php', 7, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 1), 'Rust', 'rust', 8, 1);

-- Add frontend framework options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 2), 'No preference (AI will recommend)', 'none', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 2), 'React', 'react', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 2), 'Vue.js', 'vue', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 2), 'Angular', 'angular', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 2), 'Svelte', 'svelte', 5, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 2), 'Next.js', 'nextjs', 6, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 2), 'Plain HTML/CSS/JS', 'vanilla', 7, 1);

-- Add backend framework options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'No preference (AI will recommend)', 'none', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'Express.js (Node)', 'express', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'Django (Python)', 'django', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'Flask (Python)', 'flask', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'Spring Boot (Java)', 'spring', 5, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'ASP.NET Core', 'aspnet', 6, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'Ruby on Rails', 'rails', 7, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'Laravel (PHP)', 'laravel', 8, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 3), 'FastAPI (Python)', 'fastapi', 9, 1);

-- Add database options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 4), 'Relational database (MySQL, PostgreSQL)', 'relational', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 4), 'NoSQL database (MongoDB, DynamoDB)', 'nosql', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 4), 'In-memory cache (Redis, Memcached)', 'cache', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section2_id AND display_order = 4), 'No database needed', 'none', 4, 1);

-- Section 3: Architecture & Deployment
INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) 
VALUES (@survey_id, 'Architecture & Deployment', 'System design and deployment preferences', 3, 1);
SET @section3_id = LAST_INSERT_ID();

-- Section 3 Questions
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES
(@section3_id, 'Preferred architecture pattern', 'radio', 1, 1, 'Select the architecture that best fits your needs'),
(@section3_id, 'Deployment target', 'radio', 1, 2, 'Where will the application be deployed?'),
(@section3_id, 'Expected user load', 'radio', 1, 3, 'Estimated concurrent users at peak'),
(@section3_id, 'Security requirements', 'checkbox', 0, 4, 'Select all that apply'),
(@section3_id, 'Integration requirements', 'checkbox', 0, 5, 'External services or APIs to integrate');

-- Add architecture options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 1), 'Monolithic', 'monolithic', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 1), 'Microservices', 'microservices', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 1), 'Serverless', 'serverless', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 1), 'Event-driven', 'event-driven', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 1), 'Let AI recommend', 'ai-recommend', 5, 1);

-- Add deployment target options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 2), 'Cloud (AWS, Azure, GCP)', 'cloud', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 2), 'On-premises servers', 'on-premise', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 2), 'Hybrid (cloud + on-premises)', 'hybrid', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 2), 'Edge/IoT devices', 'edge', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 2), 'Container platform (Kubernetes)', 'kubernetes', 5, 1);

-- Add user load options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 3), 'Less than 100', 'small', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 3), '100 - 1,000', 'medium', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 3), '1,000 - 10,000', 'large', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 3), 'More than 10,000', 'enterprise', 4, 1);

-- Add security requirement options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 4), 'User authentication', 'auth', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 4), 'Role-based access control', 'rbac', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 4), 'Data encryption', 'encryption', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 4), 'GDPR/Privacy compliance', 'gdpr', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 4), 'PCI compliance', 'pci', 5, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 4), 'HIPAA compliance', 'hipaa', 6, 1);

-- Add integration options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 5), 'Payment processing', 'payment', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 5), 'Email/SMS notifications', 'notifications', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 5), 'Social media APIs', 'social', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 5), 'Analytics/Monitoring', 'analytics', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 5), 'Third-party APIs', 'third-party', 5, 1),
((SELECT id FROM survey_questions WHERE section_id = @section3_id AND display_order = 5), 'Legacy system integration', 'legacy', 6, 1);

-- Section 4: Team & Development
INSERT INTO survey_sections (survey_id, name, description, display_order, is_active) 
VALUES (@survey_id, 'Team & Development', 'Team structure and development practices', 4, 1);
SET @section4_id = LAST_INSERT_ID();

-- Section 4 Questions
INSERT INTO survey_questions (section_id, question_text, question_type, is_required, display_order, help_text) VALUES
(@section4_id, 'Team size', 'radio', 1, 1, 'Number of developers working on the project'),
(@section4_id, 'Team experience level', 'radio', 1, 2, 'Average experience level of the team'),
(@section4_id, 'Development methodology', 'radio', 0, 3, 'Preferred development approach'),
(@section4_id, 'Priority ranking (drag to reorder)', 'ranking', 1, 4, 'Rank these priorities from most to least important'),
(@section4_id, 'Additional requirements or constraints', 'textarea', 0, 5, 'Any other important information for project planning');

-- Add team size options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 1), 'Solo developer', '1', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 1), '2-3 developers', '2-3', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 1), '4-8 developers', '4-8', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 1), 'More than 8 developers', '8+', 4, 1);

-- Add experience level options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 2), 'Beginner (< 2 years)', 'beginner', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 2), 'Intermediate (2-5 years)', 'intermediate', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 2), 'Senior (5-10 years)', 'senior', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 2), 'Expert (10+ years)', 'expert', 4, 1);

-- Add methodology options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 3), 'Agile/Scrum', 'agile', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 3), 'Waterfall', 'waterfall', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 3), 'Kanban', 'kanban', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 3), 'DevOps/Continuous', 'devops', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 3), 'No specific methodology', 'none', 5, 1);

-- Add priority ranking options
INSERT INTO survey_answer_options (question_id, option_text, option_value, display_order, is_active) VALUES
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 4), 'Development speed', 'speed', 1, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 4), 'Code quality', 'quality', 2, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 4), 'Scalability', 'scalability', 3, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 4), 'Cost efficiency', 'cost', 4, 1),
((SELECT id FROM survey_questions WHERE section_id = @section4_id AND display_order = 4), 'Security', 'security', 5, 1);