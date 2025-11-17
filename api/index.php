<?php
/**
 * Main API Router
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/utils/CORS.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/CartController.php';

// Handle CORS
CORS::handle();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove /api from path
$path = str_replace('/api', '', $path);
$segments = array_filter(explode('/', $path));
$segments = array_values($segments); // Re-index array

// Initialize controllers
$productController = new ProductController($mysqli);
$orderController = new OrderController($mysqli);
$paymentController = new PaymentController($mysqli);
$cartController = new CartController($mysqli);

// Route handler
try {
    // Products endpoints
    if ($segments[0] === 'products') {
        if ($method === 'GET') {
            if (isset($segments[1]) && $segments[1] === 'featured') {
                $productController->getFeatured();
            } elseif (isset($segments[1]) && $segments[1] === 'search') {
                $productController->search();
            } elseif (isset($segments[1]) && $segments[1] === 'category' && isset($segments[2])) {
                $productController->getByCategory($segments[2]);
            } elseif (isset($segments[1]) && is_numeric($segments[1])) {
                $productController->getById($segments[1]);
            } else {
                $productController->getAll();
            }
        } elseif ($method === 'POST') {
            $productController->create();
        } elseif ($method === 'PUT' && isset($segments[1]) && is_numeric($segments[1])) {
            $productController->update($segments[1]);
        } elseif ($method === 'DELETE' && isset($segments[1]) && is_numeric($segments[1])) {
            $productController->delete($segments[1]);
        } else {
            Response::error('Method not allowed', null, 405);
        }
    }
    // Orders endpoints
    elseif ($segments[0] === 'orders') {
        if ($method === 'GET') {
            if (isset($segments[1]) && $segments[1] === 'search') {
                $orderController->search();
            } elseif (isset($segments[1]) && $segments[1] === 'email' && isset($segments[2])) {
                $orderController->getByEmail($segments[2]);
            } elseif (isset($segments[1]) && is_numeric($segments[1])) {
                $orderController->getById($segments[1]);
            } else {
                $orderController->getAll();
            }
        } elseif ($method === 'POST') {
            $orderController->create();
        } elseif ($method === 'PUT' && isset($segments[1]) && is_numeric($segments[1]) && isset($segments[2]) && $segments[2] === 'status') {
            $orderController->updateStatus($segments[1]);
        } else {
            Response::error('Method not allowed', null, 405);
        }
    }
    // Payment endpoints
    elseif ($segments[0] === 'payment') {
        if ($method === 'POST' && isset($segments[1])) {
            if ($segments[1] === 'initiate') {
                $paymentController->initiatePayment();
            } elseif ($segments[1] === 'callback') {
                $paymentController->handleCallback();
            } else {
                Response::error('Unknown payment endpoint', null, 404);
            }
        } elseif ($method === 'GET' && isset($segments[1]) && $segments[1] === 'status' && isset($segments[2])) {
            $paymentController->checkPaymentStatus($segments[2]);
        } else {
            Response::error('Method not allowed', null, 405);
        }
    }
    // Cart endpoints
    elseif ($segments[0] === 'cart') {
        if ($method === 'POST') {
            if (isset($segments[1]) && $segments[1] === 'validate') {
                $cartController->validateCart();
            } elseif (isset($segments[1]) && $segments[1] === 'check-stock') {
                $cartController->checkStock();
            } else {
                Response::error('Unknown cart endpoint', null, 404);
            }
        } else {
            Response::error('Method not allowed', null, 405);
        }
    }
    // Health check
    elseif ($segments[0] === 'health' && $method === 'GET') {
        Response::success('API is running');
    }
    // Default 404
    else {
        Response::notFound('Endpoint not found');
    }

} catch (Exception $e) {
    Response::error('An error occurred: ' . $e->getMessage(), null, 500);
} finally {
    $database->close();
}

?>
