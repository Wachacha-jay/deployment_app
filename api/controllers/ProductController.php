<?php
/**
 * Product Controller
 */

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class ProductController {
    private $productModel;
    private $validator;

    public function __construct($mysqli) {
        $this->productModel = new Product($mysqli);
        $this->validator = new Validator();
    }

    /**
     * GET /api/products - Get all products
     */
    public function getAll() {
        try {
            $limit = $_GET['limit'] ?? 20;
            $offset = $_GET['offset'] ?? 0;
            
            $limit = min((int)$limit, 100); // Max 100 per page
            $offset = (int)$offset;

            $products = $this->productModel->getAll($limit, $offset);

            Response::success('Products retrieved successfully', [
                'products' => $products,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (Exception $e) {
            Response::error('Failed to retrieve products: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/products/:id - Get single product
     */
    public function getById($id) {
        try {
            $id = (int)$id;
            if ($id <= 0) {
                Response::error('Invalid product ID', null, 400);
            }

            $product = $this->productModel->getById($id);

            if (!$product) {
                Response::notFound('Product not found');
            }

            Response::success('Product retrieved successfully', $product);
        } catch (Exception $e) {
            Response::error('Failed to retrieve product: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/products/category/:category - Get products by category
     */
    public function getByCategory($category) {
        try {
            $limit = $_GET['limit'] ?? 20;
            $offset = $_GET['offset'] ?? 0;

            $limit = min((int)$limit, 100);
            $offset = (int)$offset;

            $products = $this->productModel->getByCategory($category, $limit, $offset);

            Response::success('Products retrieved successfully', [
                'products' => $products,
                'category' => $category
            ]);
        } catch (Exception $e) {
            Response::error('Failed to retrieve products: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/products/featured - Get featured products
     */
    public function getFeatured() {
        try {
            $limit = $_GET['limit'] ?? 6;
            $limit = min((int)$limit, 50);

            $products = $this->productModel->getFeatured($limit);

            Response::success('Featured products retrieved successfully', $products);
        } catch (Exception $e) {
            Response::error('Failed to retrieve featured products: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/products/search - Search products
     */
    public function search() {
        try {
            $term = $_GET['q'] ?? '';

            if (strlen($term) < 2) {
                Response::error('Search term must be at least 2 characters', null, 400);
            }

            $products = $this->productModel->search($term, 20);

            Response::success('Search completed', $products);
        } catch (Exception $e) {
            Response::error('Failed to search products: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/products - Create product (Admin)
     */
    public function create() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $rules = [
                'name' => ['required', 'min:3', 'max:255'],
                'description' => ['required', 'min:10'],
                'base_price' => ['required', 'numeric'],
                'category' => ['required'],
            ];

            if (!$this->validator->validate($input, $rules)) {
                Response::validationError($this->validator->getErrors());
            }

            $data = [
                'name' => sanitize($input['name']),
                'description' => sanitize($input['description']),
                'base_price' => (float)$input['base_price'],
                'category' => sanitize($input['category']),
                'in_stock' => $input['in_stock'] ?? 1,
                'featured' => $input['featured'] ?? 0,
            ];

            $productId = $this->productModel->create($data);

            Response::created('Product created successfully', ['id' => $productId], 201);
        } catch (Exception $e) {
            Response::error('Failed to create product: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /api/products/:id - Update product (Admin)
     */
    public function update($id) {
        try {
            $id = (int)$id;
            $input = json_decode(file_get_contents('php://input'), true);

            $product = $this->productModel->getById($id);
            if (!$product) {
                Response::notFound('Product not found');
            }

            $allowedFields = ['name', 'description', 'base_price', 'category', 'in_stock', 'featured'];
            $data = [];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if (in_array($field, ['name', 'description', 'category'])) {
                        $data[$field] = sanitize($input[$field]);
                    } else {
                        $data[$field] = $input[$field];
                    }
                }
            }

            if (empty($data)) {
                Response::error('No fields to update', null, 400);
            }

            $this->productModel->update($id, $data);

            Response::success('Product updated successfully', ['id' => $id]);
        } catch (Exception $e) {
            Response::error('Failed to update product: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /api/products/:id - Delete product (Admin)
     */
    public function delete($id) {
        try {
            $id = (int)$id;
            $product = $this->productModel->getById($id);

            if (!$product) {
                Response::notFound('Product not found');
            }

            $this->productModel->delete($id);

            Response::success('Product deleted successfully');
        } catch (Exception $e) {
            Response::error('Failed to delete product: ' . $e->getMessage(), null, 500);
        }
    }
}

/**
 * Helper function to sanitize strings
 */
function sanitize($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

?>
