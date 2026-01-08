<?php
/**
 * Application Constants
 * 
 * Define global constants used throughout the application
 */

// Application settings
define('APP_NAME', 'Classroom Project Tracker');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://skilliks.edhonour.com');

// Directory paths
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('API_PATH', ROOT_PATH . '/api');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// URL paths
define('ASSETS_URL', APP_URL . 'assets/');
define('API_URL', APP_URL . 'api/');
define('UPLOADS_URL', APP_URL . 'uploads/');

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'gif']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Session settings
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_NAME', 'project_tracker_session');

// Security settings
define('BCRYPT_COST', 12);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Email settings
define('EMAIL_FROM', 'support@skilliks.ai');
define('EMAIL_FROM_NAME', 'AI Masters Community');
define('MAILERSEND_API_TOKEN', 'mlsn.64202a1f1576f7d39bf3466910cbacec21841478ad2f5d015daba398a97b358e');

// Date formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M j, Y');
define('DISPLAY_DATETIME_FORMAT', 'M j, Y g:i A');

// User roles
define('ROLE_DEVELOPER', 1);
define('ROLE_PROJECT_MANAGER', 2);
define('ROLE_ADMINISTRATOR', 3);

// Task statuses
define('TASK_TODO', 'todo');
define('TASK_IN_PROGRESS', 'in_progress');
define('TASK_IN_REVIEW', 'in_review');
define('TASK_DONE', 'done');

// Task priorities
define('PRIORITY_CRITICAL', 'critical');
define('PRIORITY_HIGH', 'high');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_LOW', 'low');

// Feature statuses
define('FEATURE_PROPOSED', 'proposed');
define('FEATURE_UNDER_REVIEW', 'under_review');
define('FEATURE_APPROVED', 'approved');
define('FEATURE_REJECTED', 'rejected');
define('FEATURE_IMPLEMENTED', 'implemented');

// Project statuses
define('PROJECT_PLANNING', 'planning');
define('PROJECT_ACTIVE', 'active');
define('PROJECT_COMPLETED', 'completed');
define('PROJECT_ARCHIVED', 'archived');

// Sprint statuses
define('SPRINT_PLANNING', 'planning');
define('SPRINT_ACTIVE', 'active');
define('SPRINT_COMPLETED', 'completed');

// Time zone
date_default_timezone_set('America/New_York');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
