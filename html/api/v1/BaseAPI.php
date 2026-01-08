<?php
/**
 * Base API Handler Class
 * 
 * Provides common functionality for all API endpoints including:
 * - Request/Response handling
 * - Authentication
 * - Error handling
 * - Rate limiting (future)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../classes/User.php';

class BaseAPI {
    protected $db;
    protected $method;
    protected $headers;
    protected $input;
    protected $userId;
    protected $isAuthenticated = false;
    
    public function __construct() {
        // Set JSON header
        header('Content-Type: application/json');
        
        // Handle CORS
        $this->handleCORS();
        
        // Get request method
        $this->method = $_SERVER['REQUEST_METHOD'];
        
        // Get headers
        $this->headers = getallheaders();
        
        // Get input data
        $this->input = $this->getInputData();
        
        // Initialize database
        $this->db = getDB();
        
        // Check authentication
        $this->authenticate();
    }
    
    /**
     * Handle CORS headers
     */
    protected function handleCORS() {
        // Allow from any origin for now (restrict in production)
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }
            exit(0);
        }
    }
    
    /**
     * Get input data from request
     */
    protected function getInputData() {
        $data = [];
        
        // Get JSON input
        $json = file_get_contents('php://input');
        if ($json) {
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = [];
            }
        }
        
        // Merge with GET/POST data
        if (!empty($_GET)) {
            $data = array_merge($data, $_GET);
        }
        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }
        
        return $data;
    }
    
    /**
     * Authenticate the request
     * Supports both session-based auth and API key auth
     */
    protected function authenticate() {
        // Check for API key in header
        if (isset($this->headers['X-API-Key'])) {
            $this->authenticateWithAPIKey($this->headers['X-API-Key']);
        }
        // Check for Bearer token
        elseif (isset($this->headers['Authorization'])) {
            $auth = $this->headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                $this->authenticateWithAPIKey($matches[1]);
            }
        }
        // Check for API key in query params
        elseif (isset($this->input['api_key'])) {
            $this->authenticateWithAPIKey($this->input['api_key']);
        }
        // Fall back to session auth
        else {
            session_start();
            if (isset($_SESSION['user_id'])) {
                $this->userId = $_SESSION['user_id'];
                $this->isAuthenticated = true;
            }
        }
    }
    
    /**
     * Authenticate with API key
     */
    protected function authenticateWithAPIKey($apiKey) {
        try {
            // For now, we'll check if the API key exists in the users table
            // In the future, you might want a separate api_keys table
            $stmt = $this->db->prepare("
                SELECT id FROM users 
                WHERE api_key = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$apiKey]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $this->userId = $user['id'];
                $this->isAuthenticated = true;
            }
        } catch (Exception $e) {
            // Authentication failed
        }
    }
    
    /**
     * Require authentication
     */
    protected function requireAuth() {
        if (!$this->isAuthenticated) {
            $this->sendError(401, 'Authentication required');
        }
    }
    
    /**
     * Send success response
     */
    protected function sendSuccess($data = null, $message = 'Success') {
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send error response
     */
    protected function sendError($code = 400, $message = 'Error', $errors = null) {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        http_response_code($code);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Validate required parameters
     */
    protected function validateRequired($params) {
        $missing = [];
        foreach ($params as $param) {
            if (!isset($this->input[$param]) || $this->input[$param] === '') {
                $missing[] = $param;
            }
        }
        
        if (!empty($missing)) {
            $this->sendError(400, 'Missing required parameters', ['missing' => $missing]);
        }
    }
    
    /**
     * Get parameter with default value
     */
    protected function getParam($key, $default = null) {
        return isset($this->input[$key]) ? $this->input[$key] : $default;
    }
    
    /**
     * Check if user has permission for a community
     */
    protected function checkCommunityAccess($communityId, $requiredRole = null) {
        if (!$this->isAuthenticated) {
            return false;
        }
        
        try {
            $query = "
                SELECT role FROM community_members 
                WHERE user_id = ? AND community_id = ? AND is_active = 1
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->userId, $communityId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member) {
                return false;
            }
            
            if ($requiredRole) {
                $roleHierarchy = ['member' => 1, 'moderator' => 2, 'admin' => 3, 'owner' => 4];
                $userRoleLevel = $roleHierarchy[$member['role']] ?? 0;
                $requiredRoleLevel = $roleHierarchy[$requiredRole] ?? 0;
                
                return $userRoleLevel >= $requiredRoleLevel;
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}