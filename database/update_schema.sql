-- Cập nhật schema để hỗ trợ chức năng bán hàng giá rẻ/miễn phí

USE goodwill_vietnam;

-- Thêm cột price_type vào bảng inventory
ALTER TABLE inventory 
ADD COLUMN price_type ENUM('free', 'cheap', 'normal') DEFAULT 'free' AFTER actual_value,
ADD COLUMN sale_price DECIMAL(10,2) DEFAULT 0 AFTER price_type,
ADD COLUMN is_for_sale BOOLEAN DEFAULT TRUE AFTER sale_price;

-- Tạo bảng orders (đơn hàng)
CREATE TABLE IF NOT EXISTS orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0,
    total_items INT DEFAULT 0,
    status ENUM('pending', 'confirmed', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'bank_transfer', 'credit_card', 'free') DEFAULT 'free',
    payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    payment_reference VARCHAR(100),
    shipping_address TEXT,
    shipping_phone VARCHAR(20),
    shipping_method ENUM('pickup', 'delivery') DEFAULT 'pickup',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
);

-- Tạo bảng order_items (chi tiết đơn hàng)
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    item_name VARCHAR(200),
    quantity INT DEFAULT 1,
    price DECIMAL(10,2),
    price_type ENUM('free', 'cheap', 'normal'),
    subtotal DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE RESTRICT
);

-- Tạo bảng cart (giỏ hàng)
CREATE TABLE IF NOT EXISTS cart (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id)
);

-- Tạo indexes
CREATE INDEX idx_inventory_price_type ON inventory(price_type);
CREATE INDEX idx_inventory_for_sale ON inventory(is_for_sale);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_cart_user ON cart(user_id);

-- Cập nhật dữ liệu mẫu cho inventory (nếu có)
UPDATE inventory 
SET price_type = 'free', 
    sale_price = 0, 
    is_for_sale = TRUE 
WHERE status = 'available';

-- View cho items có thể bán
CREATE OR REPLACE VIEW v_saleable_items AS
SELECT 
    i.*,
    c.name as category_name,
    c.icon as category_icon,
    d.item_name as donation_name,
    u.name as donor_name,
    CASE 
        WHEN i.price_type = 'free' THEN 'Miễn phí'
        WHEN i.price_type = 'cheap' THEN 'Giá rẻ'
        WHEN i.price_type = 'normal' THEN 'Giá thông thường'
    END as price_type_text,
    CASE 
        WHEN i.status = 'available' THEN 'Có sẵn'
        WHEN i.status = 'reserved' THEN 'Đã đặt'
        WHEN i.status = 'sold' THEN 'Đã bán'
    END as status_text
FROM inventory i
LEFT JOIN categories c ON i.category_id = c.category_id
LEFT JOIN donations d ON i.donation_id = d.donation_id
LEFT JOIN users u ON d.user_id = u.user_id
WHERE i.is_for_sale = TRUE AND i.status IN ('available', 'reserved');

-- View chi tiết đơn hàng
CREATE OR REPLACE VIEW v_order_details AS
SELECT 
    o.*,
    u.name as customer_name,
    u.email as customer_email,
    u.phone as customer_phone,
    COUNT(oi.order_item_id) as total_items_count
FROM orders o
LEFT JOIN users u ON o.user_id = u.user_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
GROUP BY o.order_id;

-- Bảng tình nguyện viên chiến dịch
CREATE TABLE IF NOT EXISTS campaign_volunteers (
    volunteer_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    message TEXT,
    skills TEXT,
    availability TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_campaign_user (campaign_id, user_id)
);

-- Bảng yêu cầu vật phẩm cho chiến dịch
CREATE TABLE IF NOT EXISTS campaign_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    category_id INT,
    quantity_needed INT NOT NULL,
    quantity_received INT DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- Thêm cài đặt mới
INSERT INTO system_settings (setting_key, setting_value, description, type) VALUES
('enable_shop', 'true', 'Bật chức năng bán hàng', 'boolean'),
('cheap_price_threshold', '100000', 'Ngưỡng giá để xác định đồ giá rẻ (VND)', 'number'),
('free_shipping_threshold', '500000', 'Ngưỡng miễn phí vận chuyển (VND)', 'number'),
('order_prefix', 'GW', 'Tiền tố mã đơn hàng', 'string'),
('enable_campaigns', 'true', 'Bật chức năng chiến dịch', 'boolean'),
('campaign_approval_required', 'true', 'Yêu cầu duyệt chiến dịch', 'boolean')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Trigger tạo mã đơn hàng tự động
DELIMITER $$

CREATE TRIGGER before_order_insert 
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    DECLARE order_prefix VARCHAR(10);
    
    -- Lấy prefix từ settings
    SELECT setting_value INTO order_prefix 
    FROM system_settings 
    WHERE setting_key = 'order_prefix' 
    LIMIT 1;
    
    IF order_prefix IS NULL THEN
        SET order_prefix = 'GW';
    END IF;
    
    -- Tạo mã đơn hàng
    SET NEW.order_number = CONCAT(order_prefix, DATE_FORMAT(NOW(), '%Y%m%d'), LPAD((SELECT IFNULL(MAX(order_id), 0) + 1 FROM orders), 4, '0'));
END$$

DELIMITER ;

COMMIT;
