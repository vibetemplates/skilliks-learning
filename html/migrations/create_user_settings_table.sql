-- Create user_settings table for storing user preferences
CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT UNSIGNED PRIMARY KEY,
    
    -- Notification preferences
    email_task_assigned TINYINT(1) DEFAULT 1,
    email_task_completed TINYINT(1) DEFAULT 1,
    email_project_updates TINYINT(1) DEFAULT 1,
    email_feature_promoted TINYINT(1) DEFAULT 1,
    email_weekly_digest TINYINT(1) DEFAULT 0,
    browser_notifications TINYINT(1) DEFAULT 0,
    
    -- Privacy settings
    profile_public TINYINT(1) DEFAULT 1,
    show_email TINYINT(1) DEFAULT 0,
    show_github TINYINT(1) DEFAULT 1,
    show_skills TINYINT(1) DEFAULT 1,
    allow_direct_messages TINYINT(1) DEFAULT 1,
    
    -- Display preferences
    theme_preference ENUM('auto', 'light', 'dark') DEFAULT 'auto',
    items_per_page INT DEFAULT 20,
    default_task_view ENUM('list', 'kanban', 'grid') DEFAULT 'list',
    show_completed_tasks TINYINT(1) DEFAULT 1,
    compact_mode TINYINT(1) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;