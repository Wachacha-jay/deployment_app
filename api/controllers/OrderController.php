<?php
/**
 * Order Controller
 */

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/helpers.php';

class OrderController {
    private $orderModel;
    private $productModel;
    private $validator;

    public function __construct($mysqli) {
        $this->orderModel = new Order($mysqli);
        $this->productModel = new Product($mysqli);
        $this->validator = new Validator();
    }

    /**
     * POST /api/orders - Create a new order
     */
    public function create() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $rules = [
                'customer_name' => ['required', 'min:3'],
                'customer_email' => ['required', 'email'],
                'customer_phone' => ['required', 'phone'],
                'shipping_address' => ['required', 'min:10'],
                'items' => ['required'],
            ];

            if (!$this->validator->validate($input, $rules)) {
                Response::validationError($this->validator->getErrors());
            }

            if (!is_array($input['items']) || empty($input['items'])) {
                Response::error('Order must contain at least one item', null, 400);
            }

            // Validate and calculate total
            $total = 0;
            $items = [];

            foreach ($input['items'] as $item) {
                if (!isset($item['product_id'], $item['variant_id'], $item['quantity'])) {
                    Response::error('Invalid item structure', null, 400);
                }

                $variant = $this->getVariant($item['variant_id']);
                if (!$variant) {
                    Response::error('Variant not found: ' . $item['variant_id'], null, 400);
                }

                // Check stock
                if ($variant['stock'] < $item['quantity']) {
                    Response::error('Insufficient stock for variant: ' . $variant['name'], null, 400);
                }

                $itemPrice = $variant['price'] * $item['quantity'];
                $total += $itemPrice;

                $items[] = [
                    'product_id' => (int)$item['product_id'],
                    'variant_id' => (int)$item['variant_id'],
                    'quantity' => (int)$item['quantity'],
                    'price' => (float)$variant['price'],
                ];
            }

            $orderData = [
                'customer_name' => sanitize($input['customer_name']),
                'customer_email' => sanitize($input['customer_email']),
                'customer_phone' => sanitize($input['customer_phone']),
                'shipping_address' => sanitize($input['shipping_address']),
                'total' => $total,
                'status' => 'pending',
                'items' => $items,
            ];

            $orderId = $this->orderModel->create($orderData);

            $order = $this->orderModel->getById($orderId);

            Response::created('Order created successfully', $order, 201);

        } catch (Exception $e) {
            Response::error('Failed to create order: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/orders/:id - Get order by ID
     */
    public function getById($id) {
        try {
            $id = (int)$id;

            $order = $this->orderModel->getById($id);

            if (!$order) {
                Response::notFound('Order not found');
            }

            Response::success('Order retrieved successfully', $order);

        } catch (Exception $e) {
            Response::error('Failed to retrieve order: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/orders/email/:email - Get orders by customer email
     */
    public function getByEmail($email) {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email address', null, 400);
            }

            $orders = $this->orderModel->getByEmail($email);

            Response::success('Orders retrieved successfully', $orders);

        } catch (Exception $e) {
            Response::error('Failed to retrieve orders: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /api/orders/:id/status - Update order status (Admin)
     */
    public function updateStatus($id) {
        try {
            $id = (int)$id;
            $input = json_decode(file_get_contents('php://input'), true);

            $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

            if (!isset($input['status']) || !in_array($input['status'], $allowedStatuses)) {
                Response::error('Invalid status', null, 400);
            }

            $order = $this->orderModel->getById($id);
            if (!$order) {
                Response::notFound('Order not found');
            }

            $this->orderModel->updateStatus($id, $input['status']);

            $updatedOrder = $this->orderModel->getById($id);

            Response::success('Order status updated successfully', $updatedOrder);

        } catch (Exception $e) {
            Response::error('Failed to update order status: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/orders - Get all orders (Admin)
     */
    public function getAll() {
        try {
            $limit = $_GET['limit'] ?? 50;
            $offset = $_GET['offset'] ?? 0;

            $limit = min((int)$limit, 100);
            $offset = (int)$offset;

            $orders = $this->orderModel->getAll($limit, $offset);

            Response::success('Orders retrieved successfully', [
                'orders' => $orders,
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (Exception $e) {
            Response::error('Failed to retrieve orders: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/orders/search - Search orders (Admin)
     */
    public function search() {
        try {
            $term = $_GET['q'] ?? '';

            if (strlen($term) < 2) {
                Response::error('Search term must be at least 2 characters', null, 400);
            }

            $orders = $this->orderModel->search($term, 20);

            Response::success('Search completed', $orders);

        } catch (Exception $e) {
            Response::error('Failed to search orders: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Helper: Get variant details
     */
    private function getVariant($variantId) {
        global $mysqli;
        
        $query = "SELECT * FROM product_variants WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $variantId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }
}

?>
