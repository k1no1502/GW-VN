-- Script kiểm tra và sửa lỗi database
-- Chạy script này để kiểm tra và sửa các vấn đề

USE goodwill_vietnam;

-- 1. Kiểm tra bảng inventory có đủ cột chưa
SHOW COLUMNS FROM inventory;

-- 2. Thêm cột thiếu vào inventory (nếu chưa có)
-- ALTER TABLE inventory 
-- ADD COLUMN price_type ENUM('free', 'cheap', 'normal') DEFAULT 'free' AFTER actual_value;

-- ALTER TABLE inventory 
-- ADD COLUMN sale_price DECIMAL(10,2) DEFAULT 0 AFTER price_type;

-- ALTER TABLE inventory 
-- ADD COLUMN is_for_sale BOOLEAN DEFAULT TRUE AFTER sale_price;

-- 3. Kiểm tra dữ liệu
SELECT 'Tổng quyên góp:' as info, COUNT(*) as count FROM donations;
SELECT 'Quyên góp đã duyệt:' as info, COUNT(*) as count FROM donations WHERE status = 'approved';
SELECT 'Vật phẩm trong kho:' as info, COUNT(*) as count FROM inventory;
SELECT 'Vật phẩm có thể bán:' as info, COUNT(*) as count FROM inventory WHERE is_for_sale = TRUE;

-- 4. Thêm vật phẩm từ donations đã approved vào inventory (nếu chưa có)
INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, 
    condition_status, estimated_value, actual_value, images, status, price_type, sale_price, is_for_sale, created_at)
SELECT 
    d.donation_id,
    d.item_name,
    d.description,
    d.category_id,
    d.quantity,
    d.unit,
    d.condition_status,
    d.estimated_value,
    d.estimated_value,
    d.images,
    'available',
    'free',
    0,
    TRUE,
    d.created_at
FROM donations d
WHERE d.status = 'approved' 
AND NOT EXISTS (
    SELECT 1 FROM inventory i WHERE i.donation_id = d.donation_id
);

-- 5. Kiểm tra lại sau khi insert
SELECT 'Sau khi sync - Vật phẩm trong kho:' as info, COUNT(*) as count FROM inventory;

-- 6. Hiển thị chi tiết vật phẩm có thể bán
SELECT 
    i.item_id,
    i.name,
    i.price_type,
    i.sale_price,
    i.is_for_sale,
    i.status,
    c.name as category_name,
    d.donation_id
FROM inventory i
LEFT JOIN categories c ON i.category_id = c.category_id
LEFT JOIN donations d ON i.donation_id = d.donation_id
WHERE i.is_for_sale = TRUE AND i.status = 'available'
ORDER BY i.created_at DESC
LIMIT 10;

-- 7. Tạo trigger tự động thêm vào inventory khi approve donation
DELIMITER $$

DROP TRIGGER IF EXISTS after_donation_approved$$

CREATE TRIGGER after_donation_approved
AFTER UPDATE ON donations
FOR EACH ROW
BEGIN
    -- Khi donation được approve và chưa có trong inventory
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        -- Kiểm tra xem đã có trong inventory chưa
        IF NOT EXISTS (SELECT 1 FROM inventory WHERE donation_id = NEW.donation_id) THEN
            INSERT INTO inventory (
                donation_id, name, description, category_id, quantity, unit,
                condition_status, estimated_value, actual_value, images,
                status, price_type, sale_price, is_for_sale, created_at
            ) VALUES (
                NEW.donation_id,
                NEW.item_name,
                NEW.description,
                NEW.category_id,
                NEW.quantity,
                NEW.unit,
                NEW.condition_status,
                NEW.estimated_value,
                NEW.estimated_value,
                NEW.images,
                'available',
                'free',
                0,
                TRUE,
                NOW()
            );
        END IF;
    END IF;
END$$

DELIMITER ;

-- 8. Test trigger
SELECT 'Trigger đã được tạo!' as message;

-- 9. Hiển thị tất cả triggers
SHOW TRIGGERS WHERE `Trigger` LIKE '%donation%';

COMMIT;
