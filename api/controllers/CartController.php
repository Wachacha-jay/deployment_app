<?php
/**
 * Cart Validation Controller
 * Used for cart operations (optional if managing cart on frontend only)
 */

require_once __DIR__ . '/../utils/Response.php';

class CartController {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * POST /api/cart/validate - Validate cart items and get latest pricing
     */
    public function validateCart() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['items']) || !is_array($input['items'])) {
                Response::error('Invalid cart structure', null, 400);
            }

            $validatedItems = [];
            $total = 0;
            $errors = [];

            foreach ($input['items'] as $item) {
                if (!isset($item['variant_id'], $item['quantity'])) {
                    continue;
                }

                $variantId = (int)$item['variant_id'];
                $quantity = (int)$item['quantity'];

                // Get variant details
                $query = "SELECT pv.*, p.id as product_id, p.name as product_name 
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         WHERE pv.id = ?";
                
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param("i", $variantId);
                $stmt->execute();
                $result = $stmt->get_result();

                if (!$variant = $result->fetch_assoc()) {
                    $errors[] = "Variant not found: $variantId";
                    continue;
                }

                // Check stock
                if ($variant['stock'] < $quantity) {
                    $errors[] = "Insufficient stock for {$variant['product_name']} ({$variant['name']}). Available: {$variant['stock']}, Requested: $quantity";
                    continue;
                }

                $itemTotal = $variant['price'] * $quantity;
                $total += $itemTotal;

                $validatedItems[] = [
                    'variant_id' => $variantId,
                    'product_id' => $variant['product_id'],
                    'product_name' => $variant['product_name'],
                    'variant_name' => $variant['name'],
                    'quantity' => $quantity,
                    'price' => (float)$variant['price'],
                    'item_total' => (float)$itemTotal,
                    'stock_available' => $variant['stock'],
                ];
            }

            Response::success('Cart validated', [
                'items' => $validatedItems,
                'total' => (float)$total,
                'errors' => $errors,
            ]);

        } catch (Exception $e) {
            Response::error('Cart validation failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/cart/check-stock - Check stock for specific variants
     */
    public function checkStock() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['variant_ids']) || !is_array($input['variant_ids'])) {
                Response::error('Invalid variant IDs', null, 400);
            }

            $stockInfo = [];

            foreach ($input['variant_ids'] as $variantId) {
                $variantId = (int)$variantId;
                
                $query = "SELECT id, stock, price FROM product_variants WHERE id = ?";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param("i", $variantId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($variant = $result->fetch_assoc()) {
                    $stockInfo[] = [
                        'variant_id' => $variantId,
                        'stock' => (int)$variant['stock'],
                        'price' => (float)$variant['price'],
                        'in_stock' => $variant['stock'] > 0,
                    ];
                }
            }

            Response::success('Stock information retrieved', $stockInfo);

        } catch (Exception $e) {
            Response::error('Stock check failed: ' . $e->getMessage(), null, 500);
        }
    }
}

?>
