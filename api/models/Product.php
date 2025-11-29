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
            $row['images'] = $this->getImages($row['id']);
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
            $row['images'] = $this->getImages($row['id']);
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
            $row['images'] = $this->getImages($row['id']);
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
            $row['images'] = $this->getImages($row['id']);
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
     * Get images for a product
     */
    public function getImages($productId) {
        $query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY sort_order ASC";
        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        $images = [];
        while ($row = $result->fetch_assoc()) {
            $images[] = $row['image_url'];
        }
        
        // Return placeholder if no images
        if (empty($images)) {
            return ['/placeholder.svg'];
        }

        return $images;
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
            $productId = $this->mysqli->insert_id;
            
            // Handle images
            if (!empty($data['images']) && is_array($data['images'])) {
                $this->saveImages($productId, $data['images']);
            }
            
            // Handle variants
            if (!empty($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    $this->createVariant($productId, $variant);
                }
            }
            
            return $productId;
        }

        throw new Exception("Failed to create product: " . $stmt->error);
    }

    private function createVariant($productId, $variant) {
        $query = "INSERT INTO product_variants (product_id, name, price, stock, sku) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->mysqli->prepare($query);
        
        // Generate SKU if missing
        $sku = $variant['sku'] ?? strtoupper(substr($variant['name'], 0, 3)) . '-' . time() . '-' . rand(100, 999);
        
        $stmt->bind_param(
            "isdis",
            $productId,
            $variant['name'],
            $variant['price'],
            $variant['stock'],
            $sku
        );
        $stmt->execute();
    }

    private function saveImages($productId, $images) {
        // Clear existing images first if updating
        $this->mysqli->query("DELETE FROM product_images WHERE product_id = " . intval($productId));
        
        $query = "INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)";
        $stmt = $this->mysqli->prepare($query);
        
        foreach ($images as $index => $url) {
            $stmt->bind_param("isi", $productId, $url, $index);
            $stmt->execute();
        }
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

        if (!empty($fields)) {
            $fields[] = "updated_at = NOW()";
            $query = "UPDATE products SET " . implode(", ", $fields) . " WHERE id = ?";
            
            $stmt = $this->mysqli->prepare($query);
            $values[] = $id;
            $types .= 'i';

            $stmt->bind_param($types, ...$values);

            if (!$stmt->execute()) {
                throw new Exception("Failed to update product: " . $stmt->error);
            }
        }
        
        // Update images if provided
        if (isset($data['images']) && is_array($data['images'])) {
            $this->saveImages($id, $data['images']);
        }
        
        // Update variants if provided
        if (isset($data['variants']) && is_array($data['variants'])) {
            // This is a simplified approach: remove all and recreate
            // In a real app, you might want to update existing ones to preserve IDs
            $this->mysqli->query("DELETE FROM product_variants WHERE product_id = " . intval($id));
            foreach ($data['variants'] as $variant) {
                $this->createVariant($id, $variant);
            }
        }

        return true;
    }

    /**
     * Delete product (Admin)
     */
    public function delete($id) {
        // Delete variants first
        $this->mysqli->query("DELETE FROM product_variants WHERE product_id = " . intval($id));
        
        // Delete images
        $this->mysqli->query("DELETE FROM product_images WHERE product_id = " . intval($id));

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
            $row['images'] = $this->getImages($row['id']);
            $products[] = $row;
        }

        return $products;
    }
}

?>
