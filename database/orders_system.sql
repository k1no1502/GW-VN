-- =====================================================
-- ORDERS SYSTEM - E-COMMERCE COMPLETE
-- T?o h? th?ng don h�ng ho�n ch?nh cho s�n thuong m?i
-- =====================================================

-- 1. T?o b?ng orders (don h�ng)
CREATE TABLE IF NOT EXISTS `orders` (
    `order_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `shipping_name` VARCHAR(100) NOT NULL,
    `shipping_phone` VARCHAR(20) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `shipping_note` TEXT,
    `payment_method` ENUM('cod', 'bank_transfer', 'credit_card') NOT NULL DEFAULT 'cod',
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'confirmed', 'shipping', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. T?o b?ng order_items (chi ti?t don h�ng)
CREATE TABLE IF NOT EXISTS `order_items` (
    `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `inventory`(`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Cap nhat bang inventory de ho tro ban hang tot hon (tuong thich MySQL < 8)
SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'is_for_sale');
SET @sql := IF(@col_missing, 'ALTER TABLE inventory ADD COLUMN is_for_sale BOOLEAN DEFAULT TRUE AFTER status', 'SELECT ''column is_for_sale exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'sale_price');
SET @sql := IF(@col_missing, 'ALTER TABLE inventory ADD COLUMN sale_price DECIMAL(10,2) DEFAULT 0.00 AFTER is_for_sale', 'SELECT ''column sale_price exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'price_type');
SET @sql := IF(@col_missing, 'ALTER TABLE inventory ADD COLUMN price_type ENUM(''free'',''cheap'',''normal'') DEFAULT ''free'' AFTER sale_price', 'SELECT ''column price_type exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'unit');
SET @sql := IF(@col_missing, 'ALTER TABLE inventory ADD COLUMN unit VARCHAR(50) DEFAULT ''Cai'' AFTER price_type', 'SELECT ''column unit exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. T?o b?ng notifications (th�ng b�o)
CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. T?o b?ng order_status_history (l?ch s? tr?ng th�i don h�ng)
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `history_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `old_status` VARCHAR(50),
    `new_status` VARCHAR(50) NOT NULL,
    `note` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. T?o indexes d? t?i uu hi?u su?t
CREATE INDEX `idx_orders_user_id` ON `orders`(`user_id`);
CREATE INDEX `idx_orders_status` ON `orders`(`status`);
CREATE INDEX `idx_orders_created_at` ON `orders`(`created_at`);
CREATE INDEX `idx_order_items_order_id` ON `order_items`(`order_id`);
CREATE INDEX `idx_order_items_item_id` ON `order_items`(`item_id`);
CREATE INDEX `idx_notifications_user_id` ON `notifications`(`user_id`);
CREATE INDEX `idx_notifications_is_read` ON `notifications`(`is_read`);

-- 7. C?p nh?t d? li?u m?u cho inventory
UPDATE `inventory` 
SET 
    `is_for_sale` = TRUE,
    `price_type` = CASE 
        WHEN RAND() < 0.3 THEN 'free'
        WHEN RAND() < 0.7 THEN 'cheap'
        ELSE 'normal'
    END,
    `sale_price` = CASE 
        WHEN RAND() < 0.3 THEN 0.00
        ELSE FLOOR(RAND() * 500000) + 50000
    END,
    `unit` = CASE 
        WHEN RAND() < 0.2 THEN 'B?'
        WHEN RAND() < 0.4 THEN 'Chi?c'
        WHEN RAND() < 0.6 THEN 'C�i'
        WHEN RAND() < 0.8 THEN 'Quy?n'
        ELSE 'Th�ng'
    END
WHERE `status` = 'available';

-- 8. T?o trigger d? t? d?ng c?p nh?t tr?ng th�i don h�ng
DELIMITER $$
DROP TRIGGER IF EXISTS `update_order_status_history`$$
CREATE TRIGGER `update_order_status_history` 
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO `order_status_history` (`order_id`, `old_status`, `new_status`, `note`)
        VALUES (NEW.order_id, OLD.status, NEW.status, CONCAT('Tr?ng th�i don h�ng thay d?i t? ', OLD.status, ' sang ', NEW.status));
    END IF;
END$$

DELIMITER ;

-- 9. T?o view d? xem th?ng k� don h�ng
CREATE OR REPLACE VIEW `order_summary` AS
SELECT 
    o.order_id,
    o.user_id,
    u.name as user_name,
    u.email as user_email,
    o.shipping_name,
    o.shipping_phone,
    o.shipping_address,
    o.payment_method,
    o.total_amount,
    o.status,
    o.created_at,
    COUNT(oi.order_item_id) as total_items,
    SUM(oi.quantity) as total_quantity
FROM `orders` o
JOIN `users` u ON o.user_id = u.user_id
LEFT JOIN `order_items` oi ON o.order_id = oi.order_id
GROUP BY o.order_id;

-- 10. T?o stored procedure d? t?o don h�ng
DELIMITER $$
DROP PROCEDURE IF EXISTS `CreateOrder`$$
CREATE PROCEDURE `CreateOrder`(
    IN p_user_id INT,
    IN p_shipping_name VARCHAR(100),
    IN p_shipping_phone VARCHAR(20),
    IN p_shipping_address TEXT,
    IN p_shipping_note TEXT,
    IN p_payment_method VARCHAR(20),
    OUT p_order_id INT
)
BEGIN
    DECLARE v_total_amount DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_cart_count INT DEFAULT 0;
    
    -- Ki?m tra gi? h�ng c� s?n ph?m kh�ng
    SELECT COUNT(*) INTO v_cart_count FROM cart WHERE user_id = p_user_id;
    
    IF v_cart_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gi? h�ng tr?ng';
    END IF;
    
    -- T�nh t?ng ti?n
    SELECT COALESCE(SUM(i.sale_price * c.quantity), 0) INTO v_total_amount
    FROM cart c
    JOIN inventory i ON c.item_id = i.item_id
    WHERE c.user_id = p_user_id AND i.status = 'available';
    
    -- T?o don h�ng
    INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address, 
                       shipping_note, payment_method, total_amount, status, created_at)
    VALUES (p_user_id, p_shipping_name, p_shipping_phone, p_shipping_address, 
            p_shipping_note, p_payment_method, v_total_amount, 'pending', NOW());
    
    SET p_order_id = LAST_INSERT_ID();
    
    -- T?o chi ti?t don h�ng
    INSERT INTO order_items (order_id, item_id, item_name, quantity, unit_price, total_price, created_at)
    SELECT 
        p_order_id,
        c.item_id,
        i.name,
        c.quantity,
        i.sale_price,
        i.sale_price * c.quantity,
        NOW()
    FROM cart c
    JOIN inventory i ON c.item_id = i.item_id
    WHERE c.user_id = p_user_id AND i.status = 'available';
    
    -- C?p nh?t inventory
    UPDATE inventory i
    JOIN cart c ON i.item_id = c.item_id
    SET i.quantity = i.quantity - c.quantity
    WHERE c.user_id = p_user_id;
    
    -- X�a gi? h�ng
    DELETE FROM cart WHERE user_id = p_user_id;
    
END$$

DELIMITER ;

-- 11. T?o function d? l?y th?ng k� don h�ng
DELIMITER $$
DROP FUNCTION IF EXISTS `GetOrderStats`$$
CREATE FUNCTION `GetOrderStats`(p_user_id INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_result JSON;
    
    SELECT JSON_OBJECT(
        'total_orders', COUNT(*),
        'pending_orders', SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END),
        'confirmed_orders', SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END),
        'shipping_orders', SUM(CASE WHEN status = 'shipping' THEN 1 ELSE 0 END),
        'delivered_orders', SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END),
        'cancelled_orders', SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END),
        'total_spent', COALESCE(SUM(total_amount), 0)
    ) INTO v_result
    FROM orders
    WHERE user_id = p_user_id;
    
    RETURN v_result;
END$$

DELIMITER ;

-- 12. Insert d? li?u m?u cho testing
INSERT IGNORE INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES
(1, 'Ch�o m?ng!', 'Ch�o m?ng b?n d?n v?i Goodwill Vietnam!', 'success'),
(1, '�on h�ng m?i', 'B?n c� don h�ng m?i #000001', 'info');

-- 13. T?o event d? t? d?ng c?p nh?t tr?ng th�i don h�ng cu
DELIMITER $$
DROP EVENT IF EXISTS `auto_update_old_orders`$$
CREATE EVENT `auto_update_old_orders`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- T? d?ng chuy?n don h�ng pending > 7 ng�y th�nh cancelled
    UPDATE orders 
    SET status = 'cancelled' 
    WHERE status = 'pending' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- T? d?ng chuy?n don h�ng confirmed > 3 ng�y th�nh shipping
    UPDATE orders 
    SET status = 'shipping' 
    WHERE status = 'confirmed' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY);
    
    -- T? d?ng chuy?n don h�ng shipping > 5 ng�y th�nh delivered
    UPDATE orders 
    SET status = 'delivered' 
    WHERE status = 'shipping' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 5 DAY);
END$$

DELIMITER ;

-- 14. Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- =====================================================
-- HO�N TH�NH T?O H? TH?NG �ON H�NG
-- =====================================================
