<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include necessary files
require_once './config/Database.php';
require_once './core/Response.php';

// Route the request
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$request = str_replace(dirname($scriptName), '', $requestUri);
$request = ltrim($request, '/');
$request = str_replace('index.php/', '', $request);

$parts = explode('/', $request);
$action = $parts[0] ?? '';

if ($action === 'auth') {
    require_once './controllers/AuthController.php';
} elseif ($action === 'products') {
    require_once './controllers/ProductController.php';
} elseif ($action === 'customers') {
    require_once './controllers/CustomerController.php';
} elseif ($action === 'companies') {
    require_once './controllers/CompanyController.php';
} else {
    Response::error('Invalid endpoint', 404);
}
?>

<!-- <?php
// header('Access-Control-Allow-Origin: *');
// header('Content-Type: application/json');
// header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
// header('Access-Control-Allow-Headers: Content-Type, Authorization');

// require_once 'config/db.php';
// require_once 'middleware/AuthMiddleware.php';
// require_once 'routes/api.php';

// // Get request method and path
// $method = $_SERVER['REQUEST_METHOD'];
// $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// $path = str_replace('/api', '', $path);

// // Route handling
// if (strpos($path, '/auth/login') !== false && $method === 'POST') {
//     require_once 'controllers/AuthController.php';
//     $controller = new AuthController();
//     $controller->login();
// } elseif (strpos($path, '/auth/register') !== false && $method === 'POST') {
//     require_once 'controllers/AuthController.php';
//     $controller = new AuthController();
//     $controller->register();
// } else {
//     // Verify auth token for protected routes
//     AuthMiddleware::verifyToken();
//     handleRoutes($method, $path);
// }
?> 