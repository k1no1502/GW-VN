-- =====================================================
-- TẠO CÁC BẢNG CHIẾN DỊCH - ĐƠN GIẢN NHẤT
-- Chạy file này SAU KHI đã import schema.sql và update_schema.sql
-- ĐÃ CHỌN database goodwill_vietnam trong phpMyAdmin
-- =====================================================

-- Xóa các bảng cũ nếu tồn tại (để import lại sạch)
DROP TABLE IF EXISTS `campaign_volunteers`;
DROP TABLE IF EXISTS `campaign_donations`;
DROP TABLE IF EXISTS `campaign_items`;

-- 1. Bảng vật phẩm cần cho chiến dịch
CREATE TABLE `campaign_items` (
    `item_id` INT PRIMARY KEY AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `item_name` VARCHAR(200) NOT NULL,
    `category_id` INT DEFAULT NULL,
    `quantity_needed` INT NOT NULL,
    `quantity_received` INT DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT 'cái',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`campaign_id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL,
    INDEX `idx_campaign_items_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng quyên góp vào chiến dịch
CREATE TABLE `campaign_donations` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `donation_id` INT NOT NULL,
    `campaign_item_id` INT DEFAULT NULL,
    `quantity_contributed` INT DEFAULT 1,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`campaign_id`) ON DELETE CASCADE,
    FOREIGN KEY (`donation_id`) REFERENCES `donations`(`donation_id`) ON DELETE CASCADE,
    FOREIGN KEY (`campaign_item_id`) REFERENCES `campaign_items`(`item_id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_campaign_donation` (`campaign_id`, `donation_id`),
    INDEX `idx_campaign_donations_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng tình nguyện viên chiến dịch
CREATE TABLE `campaign_volunteers` (
    `volunteer_id` INT PRIMARY KEY AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    `message` TEXT,
    `skills` TEXT,
    `availability` TEXT,
    `role` VARCHAR(100),
    `approved_by` INT DEFAULT NULL,
    `approved_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `hours_contributed` INT DEFAULT 0,
    `feedback` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`campaign_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_campaign_user` (`campaign_id`, `user_id`),
    INDEX `idx_campaign_volunteers_campaign` (`campaign_id`),
    INDEX `idx_campaign_volunteers_user` (`user_id`),
    INDEX `idx_campaign_volunteers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Thêm cài đặt hệ thống
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `type`) VALUES
('enable_campaigns', 'true', 'Bật chức năng chiến dịch', 'boolean'),
('campaign_approval_required', 'true', 'Yêu cầu duyệt chiến dịch', 'boolean')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 5. Kiểm tra kết quả
SELECT 'SUCCESS! Campaigns tables created!' as Status;

SELECT TABLE_NAME, TABLE_ROWS 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'goodwill_vietnam' 
AND TABLE_NAME LIKE 'campaign%';

-- HOÀN TẤT!
