<?php
/**
 * Global Helper Functions
 * 
 * Common utility functions used throughout the application
 */

/**
 * Escape output for HTML
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

// Note: isLoggedIn() and getCurrentUserId() are now in session.php

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $user = null;
    if ($user === null && isset($_SESSION['user_id'])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    
    return $user;
}

/**
 * Check if current user has a role
 */
function hasRole($roleName, $projectId = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = getDB();
    $sql = "SELECT COUNT(*) FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = ? AND r.name = ?";
    
    $params = [getCurrentUserId(), $roleName];
    
    if ($projectId !== null) {
        $sql .= " AND (ur.project_id = ? OR ur.project_id IS NULL)";
        $params[] = $projectId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Format date for display
 */
function formatDate($date, $format = null) {
    if (empty($date)) {
        return '';
    }
    
    if ($format === null) {
        $format = DISPLAY_DATE_FORMAT;
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = null) {
    if (empty($datetime)) {
        return '';
    }
    
    if ($format === null) {
        $format = DISPLAY_DATETIME_FORMAT;
    }
    
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * Get time ago string
 */
function timeAgo($datetime) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($timestamp);
    }
}

/**
 * Generate a random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate .edu email
 */
function isEduEmail($email) {
    return isValidEmail($email) && preg_match('/\.edu$/i', $email);
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return trim(strip_tags($input));
}

/**
 * Create URL slug
 */
function createSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2) . ' MB';
    } else {
        return round($bytes / 1073741824, 2) . ' GB';
    }
}

/**
 * Log activity
 */
function logActivity($type, $description, $entityType = null, $entityId = null, $projectId = null) {
    if (!isLoggedIn()) {
        return;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO activities (user_id, project_id, type, entity_type, entity_id, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        getCurrentUserId(),
        $projectId,
        $type,
        $entityType,
        $entityId,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

/**
 * Send notification
 */
function sendNotification($userId, $type, $title, $message = null, $data = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message, data)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $type,
        $title,
        $message,
        $data ? json_encode($data) : null
    ]);
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount() {
    if (!isLoggedIn()) {
        return 0;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
    $stmt->execute([getCurrentUserId()]);
    
    return $stmt->fetchColumn();
}

/**
 * Parse mentions in text
 */
function parseMentions($text) {
    preg_match_all('/@(\w+)/', $text, $matches);
    return array_unique($matches[1]);
}

/**
 * Convert mentions to links
 */
function linkifyMentions($text) {
    return preg_replace('/@(\w+)/', '<a href="/users/$1">@$1</a>', e($text));
}

/**
 * Generate pagination HTML
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav><ul class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Get time ago string
 */
function getTimeAgo($timestamp) {
    if (empty($timestamp)) {
        return 'Never';
    }
    
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Sanitize prompt text for database storage
 * Removes special characters that can cause issues when saving prompts
 */
function sanitizePromptText($text) {
    // Replace carriage returns and line feeds with a period and space
    $text = str_replace(["\r\n", "\r", "\n"], ". ", $text);

    // Remove left and right parenthesis
    $text = str_replace(['(', ')'], '', $text);

    // Replace forward slashes with space and comma
    $text = str_replace('/', ' ,', $text);

    // Replace colons with comma and space
    $text = str_replace(':', ', ', $text);

    // Escape single quotes
    $text = str_replace("'", "\\'", $text);

    // Clean up multiple spaces that might result from replacements
    $text = preg_replace('/\s+/', ' ', $text);

    // Clean up multiple periods that might result from replacements
    $text = preg_replace('/\.+/', '.', $text);

    // Trim any leading/trailing whitespace
    $text = trim($text);

    return $text;
}