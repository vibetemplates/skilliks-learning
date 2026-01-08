-- Create skills master table
CREATE TABLE IF NOT EXISTS skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project skills required table
CREATE TABLE IF NOT EXISTS project_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    importance_level ENUM('required', 'preferred', 'optional') DEFAULT 'preferred',
    added_by INT UNSIGNED,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_project_skill (project_id, skill_id),
    INDEX idx_project_id (project_id),
    INDEX idx_skill_id (skill_id),
    INDEX idx_importance (importance_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create course skills covered table
CREATE TABLE IF NOT EXISTS course_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    skill_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    added_by INT UNSIGNED,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_course_skill (course_id, skill_id),
    INDEX idx_course_id (course_id),
    INDEX idx_skill_id (skill_id),
    INDEX idx_skill_level (skill_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some initial skills
INSERT INTO skills (name, category, description) VALUES
-- Programming Languages
('JavaScript', 'Programming Language', 'JavaScript programming language for web development'),
('Python', 'Programming Language', 'Python programming language'),
('Java', 'Programming Language', 'Java programming language'),
('C++', 'Programming Language', 'C++ programming language'),
('PHP', 'Programming Language', 'PHP server-side scripting language'),
('TypeScript', 'Programming Language', 'TypeScript - typed superset of JavaScript'),
('Ruby', 'Programming Language', 'Ruby programming language'),
('Go', 'Programming Language', 'Go programming language'),
('Rust', 'Programming Language', 'Rust systems programming language'),
('Swift', 'Programming Language', 'Swift programming language for iOS/macOS'),

-- Web Development
('HTML', 'Web Development', 'HyperText Markup Language'),
('CSS', 'Web Development', 'Cascading Style Sheets'),
('React', 'Web Development', 'React JavaScript library for building UIs'),
('Angular', 'Web Development', 'Angular web application framework'),
('Vue.js', 'Web Development', 'Vue.js progressive JavaScript framework'),
('Node.js', 'Web Development', 'Node.js JavaScript runtime'),
('Express.js', 'Web Development', 'Express.js web application framework'),
('Bootstrap', 'Web Development', 'Bootstrap CSS framework'),
('jQuery', 'Web Development', 'jQuery JavaScript library'),
('REST API', 'Web Development', 'RESTful API design and implementation'),

-- Databases
('MySQL', 'Database', 'MySQL relational database'),
('PostgreSQL', 'Database', 'PostgreSQL relational database'),
('MongoDB', 'Database', 'MongoDB NoSQL database'),
('Redis', 'Database', 'Redis in-memory data structure store'),
('SQL', 'Database', 'Structured Query Language'),
('Database Design', 'Database', 'Database schema design and normalization'),

-- DevOps & Tools
('Git', 'DevOps', 'Git version control system'),
('Docker', 'DevOps', 'Docker containerization platform'),
('Kubernetes', 'DevOps', 'Kubernetes container orchestration'),
('CI/CD', 'DevOps', 'Continuous Integration/Continuous Deployment'),
('AWS', 'DevOps', 'Amazon Web Services cloud platform'),
('Linux', 'DevOps', 'Linux operating system'),
('Bash', 'DevOps', 'Bash shell scripting'),

-- Software Development
('Agile', 'Methodology', 'Agile software development methodology'),
('Scrum', 'Methodology', 'Scrum framework'),
('Unit Testing', 'Testing', 'Unit testing and test-driven development'),
('API Design', 'Architecture', 'API design principles and best practices'),
('Design Patterns', 'Architecture', 'Software design patterns'),
('Microservices', 'Architecture', 'Microservices architecture'),
('Security', 'Development', 'Application security best practices'),

-- Data Science & AI
('Machine Learning', 'Data Science', 'Machine learning algorithms and techniques'),
('Data Analysis', 'Data Science', 'Data analysis and visualization'),
('TensorFlow', 'Data Science', 'TensorFlow machine learning framework'),
('Pandas', 'Data Science', 'Pandas data analysis library'),
('NumPy', 'Data Science', 'NumPy numerical computing library'),

-- Mobile Development
('Android', 'Mobile', 'Android app development'),
('iOS', 'Mobile', 'iOS app development'),
('React Native', 'Mobile', 'React Native cross-platform development'),
('Flutter', 'Mobile', 'Flutter cross-platform development'),

-- Soft Skills
('Team Collaboration', 'Soft Skills', 'Working effectively in teams'),
('Communication', 'Soft Skills', 'Technical and interpersonal communication'),
('Problem Solving', 'Soft Skills', 'Analytical problem-solving skills'),
('Project Management', 'Soft Skills', 'Project planning and management'),
('Time Management', 'Soft Skills', 'Effective time management'),
('Code Review', 'Soft Skills', 'Conducting and participating in code reviews');