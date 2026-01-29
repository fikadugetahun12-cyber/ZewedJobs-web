<?php
/**
 * Zewed AI Career Assistant - API Backend
 * Version: 1.0.0
 */

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration and dependencies
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/libs/Auth.php';
require_once __DIR__ . '/libs/Response.php';
require_once __DIR__ . '/libs/Validation.php';

// Error reporting (disable in production)
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start session for authentication
session_start();

// Initialize response handler
$response = new Response();

try {
    // Get request method and URI
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = str_replace('/api/', '', $uri);
    
    // Remove query string
    if (strpos($uri, '?') !== false) {
        $uri = substr($uri, 0, strpos($uri, '?'));
    }
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $queryParams = $_GET;
    
    // Initialize authentication
    $auth = new Auth();
    
    // Route the request
    routeRequest($method, $uri, $data, $queryParams, $auth, $response);
    
} catch (Exception $e) {
    $response->error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Route the incoming request
 */
function routeRequest($method, $uri, $data, $queryParams, $auth, $response) {
    // Split URI into parts
    $parts = explode('/', $uri);
    $endpoint = $parts[0] ?? '';
    $action = $parts[1] ?? '';
    $id = $parts[2] ?? null;
    
    switch ($endpoint) {
        case 'auth':
            require_once __DIR__ . '/routes/auth.php';
            handleAuthRoutes($method, $action, $data, $auth, $response);
            break;
            
        case 'chat':
            require_once __DIR__ . '/routes/chat.php';
            handleChatRoutes($method, $action, $id, $data, $auth, $response);
            break;
            
        case 'jobs':
            require_once __DIR__ . '/routes/jobs.php';
            handleJobRoutes($method, $action, $id, $data, $auth, $response);
            break;
            
        case 'resources':
            require_once __DIR__ . '/routes/resources.php';
            handleResourceRoutes($method, $action, $id, $data, $auth, $response);
            break;
            
        case 'users':
            require_once __DIR__ . '/routes/users.php';
            handleUserRoutes($method, $action, $id, $data, $auth, $response);
            break;
            
        case 'ai':
            require_once __DIR__ . '/routes/ai.php';
            handleAIRoutes($method, $action, $id, $data, $auth, $response);
            break;
            
        case 'analytics':
            require_once __DIR__ . '/routes/analytics.php';
            handleAnalyticsRoutes($method, $action, $data, $auth, $response);
            break;
            
        case 'health':
            $response->success([
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => API_VERSION,
                'environment' => ENVIRONMENT
            ]);
            break;
            
        default:
            $response->error('Endpoint not found', 404);
    }
}
?>
