<?php
require_once 'config/database.php';

class Router {
    private $routes = [];
    private $conn;
    
    public function __construct() {
        // Create database connection
        global $db_config;
        $this->conn = new mysqli(
            $db_config['host'],
            $db_config['username'],
            $db_config['password'],
            $db_config['dbname']
        );
        
        if ($this->conn->connect_error) {
            // If database connection fails, just skip dynamic routes
            $this->conn = null;
        }
        
        $this->loadRoutes();
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    private function loadRoutes() {
        // Load routes from configuration
        $routesFile = __DIR__ . '/config/routes.json';
        if (file_exists($routesFile)) {
            $this->routes = json_decode(file_get_contents($routesFile), true);
        }
        
        // Load dynamic routes from database
        $this->loadDynamicRoutes();
    }
    
    private function loadDynamicRoutes() {
        // Skip if no database connection
        if (!$this->conn) {
            return;
        }
        
        // Load course slugs
        $sql = "SELECT id, slug FROM courses WHERE slug IS NOT NULL AND slug != ''";
        $result = $this->conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $this->routes['/' . $row['slug']] = [
                    'page' => 'course-detail.php',
                    'params' => ['id' => $row['id']]
                ];
            }
        }
        
        // Load program slugs
        $sql = "SELECT id, slug FROM programs WHERE slug IS NOT NULL AND slug != ''";
        $result = $this->conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $this->routes['/' . $row['slug']] = [
                    'page' => 'courses.php',
                    'params' => ['program_id' => $row['id']]
                ];
            }
        }
        
        // Load project slugs
        $sql = "SELECT id, slug FROM projects WHERE slug IS NOT NULL AND slug != ''";
        $result = $this->conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $this->routes['/' . $row['slug']] = [
                    'page' => 'project-detail.php',
                    'params' => ['id' => $row['id']]
                ];
            }
        }
        
        // Check if posts table exists before loading
        $table_check = $this->conn->query("SHOW TABLES LIKE 'posts'");
        if ($table_check && $table_check->num_rows > 0) {
            // Load blog post slugs
            $sql = "SELECT id, slug FROM posts WHERE slug IS NOT NULL AND slug != ''";
            $result = $this->conn->query($sql);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $this->routes['/' . $row['slug']] = [
                        'page' => 'blog-detail.php',
                        'params' => ['id' => $row['id']]
                    ];
                }
            }
        }
        
        // Check if team_members table exists before loading
        $table_check = $this->conn->query("SHOW TABLES LIKE 'team_members'");
        if ($table_check && $table_check->num_rows > 0) {
            // Load team member slugs
            $sql = "SELECT id, slug FROM team_members WHERE slug IS NOT NULL AND slug != ''";
            $result = $this->conn->query($sql);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $this->routes['/' . $row['slug']] = [
                        'page' => 'team-member.php',
                        'params' => ['id' => $row['id']]
                    ];
                }
            }
        }
        
        // Check if users table has slug column for team members
        $column_check = $this->conn->query("SHOW COLUMNS FROM users LIKE 'slug'");
        if ($column_check && $column_check->num_rows > 0) {
            // Load user slugs for team member profiles
            $sql = "SELECT id, slug FROM users WHERE slug IS NOT NULL AND slug != ''";
            $result = $this->conn->query($sql);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $this->routes['/' . $row['slug']] = [
                        'page' => 'team-member.php',
                        'params' => ['id' => $row['id']]
                    ];
                }
            }
        }
        
        // Check if communities table exists before loading
        $table_check = $this->conn->query("SHOW TABLES LIKE 'communities'");
        if ($table_check && $table_check->num_rows > 0) {
            // Load community slugs
            $sql = "SELECT id, slug FROM communities WHERE slug IS NOT NULL AND slug != '' AND is_active = 1";
            $result = $this->conn->query($sql);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $this->routes['/' . $row['slug']] = [
                        'page' => 'community-about.php',
                        'params' => ['id' => $row['id']]
                    ];
                }
            }
        }
    }
    
    public function route($requestUri) {
        // Remove query string from URI
        $uri = parse_url($requestUri, PHP_URL_PATH);
        
        // Remove trailing slash except for root
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }
        
        // Check if it's an existing PHP file (without extension in URL)
        $phpFile = $_SERVER['DOCUMENT_ROOT'] . $uri . '.php';
        if (file_exists($phpFile) && is_file($phpFile)) {
            return null; // Let normal routing handle it
        }
        
        // Check if it's an existing directory or file
        $path = $_SERVER['DOCUMENT_ROOT'] . $uri;
        if (file_exists($path)) {
            return null; // Let normal routing handle it
        }
        
        // Check custom routes
        if (isset($this->routes[$uri])) {
            $route = $this->routes[$uri];
            
            // Set up GET parameters
            if (isset($route['params'])) {
                foreach ($route['params'] as $key => $value) {
                    $_GET[$key] = $value;
                    $_REQUEST[$key] = $value;
                }
            }
            
            // Include the target page
            $targetFile = $_SERVER['DOCUMENT_ROOT'] . '/' . $route['page'];
            if (file_exists($targetFile)) {
                include $targetFile;
                return true;
            }
        }
        
        return false; // 404
    }
}

// Get the requested URI
$request_uri = $_SERVER['REQUEST_URI'];

// Initialize router
$router = new Router();

// Route the request
$routed = $router->route($request_uri);

if ($routed === false) {
    // 404 Not Found
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.php')) {
        include __DIR__ . '/404.php';
    } else {
        echo "404 - Page not found";
    }
    exit;
} elseif ($routed === null) {
    // Handle normal file routing
    $path = parse_url($request_uri, PHP_URL_PATH);
    $path = trim($path, '/');
    
    if (empty($path)) {
        $path = 'index';
    }
    
    $php_file = __DIR__ . '/' . $path . '.php';
    if (file_exists($php_file) && is_file($php_file)) {
        // Parse query string to ensure $_GET is populated
        $query_string = parse_url($request_uri, PHP_URL_QUERY);
        if ($query_string) {
            parse_str($query_string, $_GET);
            $_REQUEST = array_merge($_REQUEST, $_GET);
        }
        require $php_file;
        exit;
    }
    
    // If no PHP file found, show 404
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.php')) {
        include __DIR__ . '/404.php';
    } else {
        echo "404 - Page not found";
    }
    exit;
}