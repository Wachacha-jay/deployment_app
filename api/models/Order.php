<?php
/**
 * Order Model
 */

class Order {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Create a new order
     */
    public function create($data) {
        // Start transaction
        $this->mysqli->begin_transaction();

        try {
            // Insert order
            $query = "INSERT INTO orders (customer_name, customer_email, customer_phone, shipping_address, total, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param(
                "ssssds",
                $data['customer_name'],
                $data['customer_email'],
                $data['customer_phone'],
                $data['shipping_address'],
                $data['total'],
                $data['status']
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }

            $orderId = $this->mysqli->insert_id;

            // Insert order items
            $itemQuery = "INSERT INTO order_items (order_id, product_id, variant_id, quantity, price) 
                          VALUES (?, ?, ?, ?, ?)";
            
            foreach ($data['items'] as $item) {
                $itemStmt = $this->mysqli->prepare($itemQuery);
                $itemStmt->bind_param(
                    "iiiii",
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'],
                    $item['quantity'],
                    $item['price']
                );

                if (!$itemStmt->execute()) {
                    throw new Exception("Failed to add order item: " . $itemStmt->error);
                }
            }

            // Update product variant stock
            foreach ($data['items'] as $item) {
                $stockQuery = "UPDATE product_variants SET stock = stock - ? WHERE id = ?";
                $stockStmt = $this->mysqli->prepare($stockQuery);
                $stockStmt->bind_param("ii", $item['quantity'], $item['variant_id']);
                
                if (!$stockStmt->execute()) {
                    throw new Exception("Failed to update stock: " . $stockStmt->error);
                }
            }

            $this->mysqli->commit();
            return $orderId;

        } catch (Exception $e) {
            $this->mysqli->rollback();
            throw $e;
        }
    }

    /**
     * Get order by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM orders WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($order = $result->fetch_assoc()) {
            $order['items'] = $this->getItems($id);
            return $order;
        }

        return null;
    }

    /**
     * Get order items
     */
    public function getItems($orderId) {
        $query = "SELECT oi.*, p.name as product_name, pv.name as variant_name 
                  FROM order_items oi
                  JOIN products p ON oi.product_id = p.id
                  JOIN product_variants pv ON oi.variant_id = pv.id
                  WHERE oi.order_id = ?";
        
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Get orders by email
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM orders WHERE customer_email = ? ORDER BY created_at DESC";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $row['items'] = $this->getItems($row['id']);
            $orders[] = $row;
        }

        return $orders;
    }

    /**
     * Update order status
     */
    public function updateStatus($id, $status) {
        $query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            return $stmt->affected_rows;
        }

        throw new Exception("Failed to update order status: " . $stmt->error);
    }

    /**
     * Get all orders (Admin)
     */
    public function getAll($limit = 50, $offset = 0) {
        $query = "SELECT * FROM orders ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $row['items'] = $this->getItems($row['id']);
            $orders[] = $row;
        }

        return $orders;
    }

    /**
     * Search orders (Admin)
     */
    public function search($searchTerm, $limit = 20) {
        $searchTerm = "%$searchTerm%";
        $query = "SELECT * FROM orders WHERE customer_name LIKE ? OR customer_email LIKE ? 
                  OR customer_phone LIKE ? ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $row['items'] = $this->getItems($row['id']);
            $orders[] = $row;
        }

        return $orders;
    }
}

?>
