-- Cập nhật database cho tính năng chiến dịch nâng cao
-- Chạy file này sau khi đã chọn database goodwill_vietnam trong phpMyAdmin

-- 1. Cập nhật bảng campaigns thêm trường approved_by
-- ALTER TABLE campaigns 
-- ADD COLUMN approved_by INT AFTER status,
-- ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by,
-- ADD COLUMN rejection_reason TEXT AFTER approved_at;

-- Thêm foreign key nếu chưa có
SET @exist := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'goodwill_vietnam' 
    AND TABLE_NAME = 'campaigns' 
    AND CONSTRAINT_NAME = 'campaigns_ibfk_approved_by');

SET @sqlstmt := IF(@exist = 0, 
    'ALTER TABLE campaigns ADD CONSTRAINT campaigns_ibfk_approved_by FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL',
    'SELECT ''FK already exists'' as message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Bảng yêu cầu vật phẩm cho chiến dịch (đã có, bổ sung thêm)
CREATE TABLE IF NOT EXISTS campaign_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    category_id INT,
    quantity_needed INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'cái',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- 3. Bảng quyên góp trực tiếp vào chiến dịch
CREATE TABLE IF NOT EXISTS campaign_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    donation_id INT NOT NULL,
    campaign_item_id INT,
    quantity_contributed INT DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_item_id) REFERENCES campaign_items(item_id) ON DELETE SET NULL,
    UNIQUE KEY unique_campaign_donation (campaign_id, donation_id)
);

-- 4. Bảng tình nguyện viên chiến dịch (cải tiến)
CREATE TABLE IF NOT EXISTS campaign_volunteers (
    volunteer_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    message TEXT COMMENT 'Lời nhắn từ tình nguyện viên',
    skills TEXT COMMENT 'Kỹ năng có thể đóng góp',
    availability TEXT COMMENT 'Thời gian có thể tham gia',
    role VARCHAR(100) COMMENT 'Vai trò trong chiến dịch',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    hours_contributed INT DEFAULT 0,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_campaign_user (campaign_id, user_id)
);

-- 5. Indexes để tăng hiệu suất
-- CREATE INDEX idx_campaign_items_campaign ON campaign_items(campaign_id);
-- CREATE INDEX idx_campaign_donations_campaign ON campaign_donations(campaign_id);
-- CREATE INDEX idx_campaign_volunteers_campaign ON campaign_volunteers(campaign_id);
-- CREATE INDEX idx_campaign_volunteers_user ON campaign_volunteers(user_id);
-- CREATE INDEX idx_campaign_volunteers_status ON campaign_volunteers(status);

-- 6. View chi tiết chiến dịch với thống kê
CREATE OR REPLACE VIEW v_campaign_details AS
SELECT 
    c.*,
    u.name as creator_name,
    u.email as creator_email,
    COUNT(DISTINCT cv.volunteer_id) as volunteer_count,
    COUNT(DISTINCT cd.donation_id) as donation_count,
    SUM(ci.quantity_needed) as total_items_needed,
    SUM(ci.quantity_received) as total_items_received,
    CASE 
        WHEN c.status = 'draft' THEN 'Nháp'
        WHEN c.status = 'pending' THEN 'Chờ duyệt'
        WHEN c.status = 'active' THEN 'Đang hoạt động'
        WHEN c.status = 'paused' THEN 'Tạm dừng'
        WHEN c.status = 'completed' THEN 'Hoàn thành'
        WHEN c.status = 'cancelled' THEN 'Đã hủy'
    END as status_text,
    DATEDIFF(c.end_date, CURDATE()) as days_remaining,
    CASE 
        WHEN SUM(ci.quantity_needed) > 0 
        THEN ROUND((SUM(ci.quantity_received) / SUM(ci.quantity_needed)) * 100, 2)
        ELSE 0 
    END as completion_percentage
FROM campaigns c
LEFT JOIN users u ON c.created_by = u.user_id
LEFT JOIN campaign_volunteers cv ON c.campaign_id = cv.campaign_id AND cv.status = 'approved'
LEFT JOIN campaign_donations cd ON c.campaign_id = cd.campaign_id
LEFT JOIN campaign_items ci ON c.campaign_id = ci.campaign_id
GROUP BY c.campaign_id;

-- 7. View chi tiết vật phẩm cần cho chiến dịch
CREATE OR REPLACE VIEW v_campaign_items_progress AS
SELECT 
    ci.*,
    c.name as campaign_name,
    c.status as campaign_status,
    cat.name as category_name,
    ci.quantity_received as received,
    ci.quantity_needed as needed,
    (ci.quantity_needed - ci.quantity_received) as remaining,
    CASE 
        WHEN ci.quantity_needed > 0 
        THEN ROUND((ci.quantity_received / ci.quantity_needed) * 100, 2)
        ELSE 0 
    END as progress_percentage,
    CASE 
        WHEN ci.quantity_received >= ci.quantity_needed THEN 'Đủ'
        WHEN ci.quantity_received > 0 THEN 'Đang thiếu'
        ELSE 'Chưa có'
    END as status_text
FROM campaign_items ci
LEFT JOIN campaigns c ON ci.campaign_id = c.campaign_id
LEFT JOIN categories cat ON ci.category_id = cat.category_id;

-- 8. Trigger cập nhật số lượng vật phẩm đã nhận
-- DELIMITER $$

-- DROP TRIGGER IF EXISTS after_campaign_donation_insert$$

-- CREATE TRIGGER after_campaign_donation_insert
-- AFTER INSERT ON campaign_donations
-- FOR EACH ROW
-- BEGIN
--     -- Cập nhật quantity_received cho campaign_item
--     IF NEW.campaign_item_id IS NOT NULL THEN
--         UPDATE campaign_items 
--         SET quantity_received = quantity_received + NEW.quantity_contributed
--         WHERE item_id = NEW.campaign_item_id;
--     END IF;
--     
--     -- Cập nhật current_items cho campaign
--     UPDATE campaigns 
--     SET current_items = current_items + NEW.quantity_contributed
--     WHERE campaign_id = NEW.campaign_id;
-- END$$

-- DROP TRIGGER IF EXISTS after_campaign_donation_delete$$

-- CREATE TRIGGER after_campaign_donation_delete
-- AFTER DELETE ON campaign_donations
-- FOR EACH ROW
-- BEGIN
--     -- Trừ quantity_received khi xóa donation
--     IF OLD.campaign_item_id IS NOT NULL THEN
--         UPDATE campaign_items 
--         SET quantity_received = GREATEST(quantity_received - OLD.quantity_contributed, 0)
--         WHERE item_id = OLD.campaign_item_id;
--     END IF;
--     
--     -- Cập nhật current_items cho campaign
--     UPDATE campaigns 
--     SET current_items = GREATEST(current_items - OLD.quantity_contributed, 0)
--     WHERE campaign_id = OLD.campaign_id;
-- END$$

-- DELIMITER ;

-- 9. Stored procedure để approve campaign
DELIMITER $$

DROP PROCEDURE IF EXISTS approve_campaign$$

CREATE PROCEDURE approve_campaign(
    IN p_campaign_id INT,
    IN p_admin_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'Error' as status, 'Failed to approve campaign' as message;
    END;
    
    START TRANSACTION;
    
    UPDATE campaigns 
    SET status = 'active',
        approved_by = p_admin_id,
        approved_at = NOW(),
        updated_at = NOW()
    WHERE campaign_id = p_campaign_id AND status = 'pending';
    
    IF ROW_COUNT() > 0 THEN
        COMMIT;
        SELECT 'Success' as status, 'Campaign approved' as message;
    ELSE
        ROLLBACK;
        SELECT 'Error' as status, 'Campaign not found or already approved' as message;
    END IF;
END$$

DROP PROCEDURE IF EXISTS reject_campaign$$

CREATE PROCEDURE reject_campaign(
    IN p_campaign_id INT,
    IN p_admin_id INT,
    IN p_reason TEXT
)
BEGIN
    UPDATE campaigns 
    SET status = 'cancelled',
        approved_by = p_admin_id,
        approved_at = NOW(),
        rejection_reason = p_reason,
        updated_at = NOW()
    WHERE campaign_id = p_campaign_id;
END$$

DELIMITER ;

-- 10. Cập nhật status mặc định cho campaigns mới
ALTER TABLE campaigns 
MODIFY COLUMN status ENUM('draft', 'pending', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'pending';

-- 11. Thêm sample data (optional)
-- INSERT INTO campaigns (name, description, start_date, end_date, target_items, created_by, status) 
-- VALUES ('Hỗ trợ học sinh vùng cao', 'Chiến dịch quyên góp áo quần, sách vở cho học sinh vùng cao', 
--         '2024-11-01', '2024-12-31', 100, 1, 'active');

COMMIT;

-- Hiển thị kết quả
SELECT 'Database updated successfully!' as message;
SELECT 'Tables created:' as info;
SHOW TABLES LIKE 'campaign%';
SELECT 'Views created:' as info;
SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW' AND Tables_in_goodwill_vietnam LIKE '%campaign%';
