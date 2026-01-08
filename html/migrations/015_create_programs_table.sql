-- Create programs table to group courses
CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    short_description VARCHAR(500),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add program_id column to courses table
ALTER TABLE courses 
ADD COLUMN program_id INT AFTER category,
ADD INDEX idx_program (program_id),
ADD CONSTRAINT fk_course_program 
    FOREIGN KEY (program_id) 
    REFERENCES programs(id) 
    ON DELETE SET NULL;

-- Insert the initial General Education program
INSERT INTO programs (name, slug, description, short_description, display_order) 
VALUES (
    'General Education', 
    'general-education', 
    'Core courses that provide foundational knowledge and skills applicable across various disciplines.',
    'Foundational courses for all learners',
    1
);

-- Update all existing courses to belong to General Education program
UPDATE courses 
SET program_id = (SELECT id FROM programs WHERE slug = 'general-education' LIMIT 1)
WHERE program_id IS NULL;