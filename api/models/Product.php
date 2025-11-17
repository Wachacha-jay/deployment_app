<?php
/**
 * Product Model
 */

class Product {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Get all products
     */
    public function getAll($limit = null, $offset = 0) {
        $query = "SELECT * FROM products ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        }

        $result = $this->mysqli->query($query);
        
        if (!$result) {
            throw new Exception("Database error: " . $this->mysqli->error);
        }

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $row['variants'] = $this->getVariants($row['id']);
            $products[] = $row;
        }

        return $products;
    }

    /**
     * Get single product by ID
     */
    public function getById($id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $row['variants'] = $this->getVariants($row['id']);
            return $row;
        }

        return null;
    }

    /**
     * Get products by category
     */
    public function getByCategory($category, $limit = null, $offset = 0) {
        $query = "SELECT * FROM products WHERE category = ? ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT ? OFFSET ?";
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param("sii", $category, $limit, $offset);
        } else {
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param("s", $category);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $row['variants'] = $this->getVariants($row['id']);
            $products[] = $row;
        }

        return $products;
    }

    /**
     * Get featured products
     */
    public function getFeatured($limit = 6) {
        $query = "SELECT * FROM products WHERE featured = 1 ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $row['variants'] = $this->getVariants($row['id']);
            $products[] = $row;
        }

        return $products;
    }

    /**
     * Get variants for a product
     */
    public function getVariants($productId) {
        $query = "SELECT * FROM product_variants WHERE product_id = ? ORDER BY created_at ASC";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        $variants = [];
        while ($row = $result->fetch_assoc()) {
            $variants[] = $row;
        }

        return $variants;
    }

    /**
     * Create product (Admin)
     */
    public function create($data) {
        $query = "INSERT INTO products (name, description, base_price, category, in_stock, featured, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->mysqli->error);
        }

        $stmt->bind_param(
            "ssdiii",
            $data['name'],
            $data['description'],
            $data['base_price'],
            $data['category'],
            $data['in_stock'],
            $data['featured']
        );

        if ($stmt->execute()) {
            return $this->mysqli->insert_id;
        }

        throw new Exception("Failed to create product: " . $stmt->error);
    }

    /**
     * Update product (Admin)
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        $types = '';

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'description', 'category'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
                $types .= 's';
            } elseif (in_array($key, ['base_price'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
                $types .= 'd';
            } elseif (in_array($key, ['in_stock', 'featured'])) {
                $fields[] = "$key = ?";
                $values[] = (int)$value;
                $types .= 'i';
            }
        }

        if (empty($fields)) {
            throw new Exception("No valid fields to update");
        }

        $fields[] = "updated_at = NOW()";
        $query = "UPDATE products SET " . implode(", ", $fields) . " WHERE id = ?";
        
        $stmt = $this->mysqli->prepare($query);
        $values[] = $id;
        $types .= 'i';

        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update product: " . $stmt->error);
        }

        return $stmt->affected_rows;
    }

    /**
     * Delete product (Admin)
     */
    public function delete($id) {
        // Delete variants first
        $query = "DELETE FROM product_variants WHERE product_id = ?";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Delete product
        $query = "DELETE FROM products WHERE id = ?";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            return $stmt->affected_rows;
        }

        throw new Exception("Failed to delete product: " . $stmt->error);
    }

    /**
     * Search products
     */
    public function search($searchTerm, $limit = 20) {
        $searchTerm = "%$searchTerm%";
        $query = "SELECT * FROM products WHERE name LIKE ? OR description LIKE ? OR category LIKE ? 
                  ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $row['variants'] = $this->getVariants($row['id']);
            $products[] = $row;
        }

        return $products;
    }
}

?>
