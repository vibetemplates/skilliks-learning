-- Migration: Add sample project categories
-- Description: Adds additional project categories for demonstration

INSERT INTO project_categories (name, description, skill_level, display_order) VALUES
('Web Development', 'Projects involving frontend and backend web technologies', 'intermediate', 2),
('Mobile Apps', 'iOS and Android mobile application development projects', 'advanced', 3),
('Data Science', 'Machine learning, data analysis, and AI projects', 'advanced', 4),
('Game Development', 'Video game design and development projects', 'intermediate', 5),
('Open Source', 'Community-driven open source contributions', 'all', 6),
('Student Projects', 'Academic and learning-focused projects for students', 'beginner', 7);