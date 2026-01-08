-- Create calendar_events table
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    all_day BOOLEAN DEFAULT FALSE,
    location VARCHAR(255),
    zoom_link VARCHAR(500),
    color VARCHAR(7) DEFAULT '#0d6efd', -- Bootstrap primary color
    
    -- Linkages (nullable)
    project_id INT UNSIGNED DEFAULT NULL,
    course_id INT DEFAULT NULL,
    
    -- Recurrence settings
    recurrence_type ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') DEFAULT 'none',
    recurrence_end_date DATE DEFAULT NULL,
    recurrence_parent_id INT UNSIGNED DEFAULT NULL, -- For recurring event instances
    
    -- Meta fields
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    CONSTRAINT fk_event_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_event_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    CONSTRAINT fk_event_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_event_parent FOREIGN KEY (recurrence_parent_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_event_community (community_id),
    INDEX idx_event_dates (start_datetime, end_datetime),
    INDEX idx_event_project (project_id),
    INDEX idx_event_course (course_id),
    INDEX idx_event_recurrence (recurrence_parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create calendar_event_attendees table for tracking RSVPs
CREATE TABLE IF NOT EXISTS calendar_event_attendees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    response ENUM('pending', 'yes', 'no', 'maybe') DEFAULT 'pending',
    responded_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Unique constraint to prevent duplicate attendees
    UNIQUE KEY unique_event_user (event_id, user_id),
    
    -- Foreign keys
    CONSTRAINT fk_attendee_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_attendee_event (event_id),
    INDEX idx_attendee_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create calendar_event_reminders table
CREATE TABLE IF NOT EXISTS calendar_event_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reminder_minutes INT NOT NULL DEFAULT 15, -- Minutes before event
    sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    CONSTRAINT fk_reminder_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_reminder_event (event_id),
    INDEX idx_reminder_user (user_id),
    INDEX idx_reminder_sent (sent, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;