-- Turi-A-Mumbi Arts Shop Database Schema
-- MySQL

-- Products table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `base_price` DECIMAL(10, 2) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `in_stock` TINYINT(1) DEFAULT 1,
  `featured` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_category` (`category`),
  INDEX `idx_featured` (`featured`),
  INDEX `idx_in_stock` (`in_stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product variants table
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `sku` VARCHAR(100) UNIQUE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_product_id` (`product_id`),
  INDEX `idx_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_email` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `total` DECIMAL(10, 2) NOT NULL,
  `status` ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  `payment_status` ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
  `payment_method` VARCHAR(50),
  `mpesa_reference` VARCHAR(255),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`customer_email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items table
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`),
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table (for M-Pesa tracking)
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'KES',
  `payment_method` VARCHAR(50),
  `mpesa_request_id` VARCHAR(255),
  `mpesa_reference` VARCHAR(255),
  `status` ENUM('initiated', 'pending', 'completed', 'failed') DEFAULT 'initiated',
  `response_data` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_mpesa_reference` (`mpesa_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images table (optional, for multiple images per product)
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `alt_text` VARCHAR(255),
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data
-- Sample data
INSERT INTO `products` (`id`, `name`, `description`, `base_price`, `category`, `in_stock`, `featured`) VALUES
(1, 'Art carving', 'Handcrafted traditional art carving made by local artisans', 5000, 'Art', 1, 1),
(2, 'Cultural Art Print', 'Beautiful print featuring traditional Kikuyu art', 2000, 'Art', 1, 1),
(3, 'Decorated callabash', 'Traditional decorated callabash for cultural use', 800, 'Instruments', 1, 0),
(4, 'Gĩkũyũ Cultural Art Frame', 'Handcrafted cultural art featuring traditional Gĩkũyũ warrior with spear', 3500, 'Art', 1, 1),
(5, 'Mŭmbi Heritage Art Frame', 'Beautiful handcrafted art celebrating Mŭmbi heritage with traditional fabrics', 3500, 'Art', 1, 1),
(6, 'Warrior Cultural Art', 'Traditional warrior art piece with authentic cultural elements', 3200, 'Art', 1, 0),
(7, 'Cultural Bookmark Set', 'Handcrafted bookmarks with traditional designs', 500, 'Art', 1, 0),
(8, 'Traditional Textile Art', 'Authentic textile art with traditional African patterns', 4000, 'Art', 1, 1),
(9, 'Tŭrĩ A Mŭmbi Greeting Cards', 'Beautiful handcrafted greeting cards with cultural designs', 1500, 'Art', 1, 0);

-- Sample product variants
INSERT INTO `product_variants` (`product_id`, `name`, `price`, `stock`, `sku`) VALUES
(1, 'Small', 5000, 10, 'DRUM-S-001'),
(1, 'Medium', 7500, 5, 'DRUM-M-001'),
(1, 'Large', 10000, 3, 'DRUM-L-001'),
(2, 'A4', 2000, 20, 'ART-A4-001'),
(2, 'A3', 3500, 15, 'ART-A3-001'),
(3, '100g', 800, 50, 'TEA-100-001'),
(3, '250g', 1800, 30, 'TEA-250-001'),
(4, 'Standard', 3500, 8, 'ART-GIKUYU-001'),
(5, 'Standard', 3500, 6, 'ART-MUMBI-001'),
(6, 'Standard', 3200, 10, 'ART-WARRIOR-001'),
(7, 'Single', 500, 25, 'BOOK-MARK-001'),
(7, 'Set of 3', 1200, 15, 'BOOK-MARK-SET-001'),
(8, 'Standard', 4000, 5, 'ART-TEXTILE-001'),
(9, 'Set of 5', 1500, 20, 'CARDS-SET-001');

-- Sample product images
INSERT INTO `product_images` (`product_id`, `image_url`, `sort_order`) VALUES
(1, '/images/artistic2.jpg', 0),
(2, '/images/artistic1.jpg', 0),
(3, '/images/callabash.jpg', 0),
(4, '/images/product-gikuyu-art.jpg', 0),
(5, '/images/product-mumbi-art.jpg', 0),
(6, '/images/product-warrior-art.jpg', 0),
(7, '/images/product-bookmark.jpg', 0),
(8, '/images/product-textile-art.jpg', 0),
(9, '/images/product-cards.jpg', 0);
