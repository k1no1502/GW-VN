-- =====================================================
-- CAMPAIGNS UPDATE - SIMPLE VERSION
-- Chạy file này TRONG phpMyAdmin
-- ĐÃ CHỌN database goodwill_vietnam trước
-- =====================================================

-- 1. Bảng yêu cầu vật phẩm cho chiến dịch
CREATE TABLE IF NOT EXISTS `campaign_items` (
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
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng quyên góp vào chiến dịch
CREATE TABLE IF NOT EXISTS `campaign_donations` (
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
    UNIQUE KEY `unique_campaign_donation` (`campaign_id`, `donation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng tình nguyện viên
CREATE TABLE IF NOT EXISTS `campaign_volunteers` (
    `volunteer_id` INT PRIMARY KEY AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    `message` TEXT COMMENT 'Lời nhắn từ tình nguyện viên',
    `skills` TEXT COMMENT 'Kỹ năng',
    `availability` TEXT COMMENT 'Thời gian',
    `role` VARCHAR(100) COMMENT 'Vai trò',
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
    UNIQUE KEY `unique_campaign_user` (`campaign_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tạo indexes
CREATE INDEX `idx_campaign_items_campaign` ON `campaign_items`(`campaign_id`);
CREATE INDEX `idx_campaign_donations_campaign` ON `campaign_donations`(`campaign_id`);
CREATE INDEX `idx_campaign_volunteers_campaign` ON `campaign_volunteers`(`campaign_id`);
CREATE INDEX `idx_campaign_volunteers_user` ON `campaign_volunteers`(`user_id`);
CREATE INDEX `idx_campaign_volunteers_status` ON `campaign_volunteers`(`status`);

-- 5. Thêm cài đặt
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `type`) VALUES
('enable_campaigns', 'true', 'Bật chức năng chiến dịch', 'boolean'),
('campaign_approval_required', 'true', 'Yêu cầu duyệt chiến dịch', 'boolean')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- HOÀN TẤT
SELECT 'Campaigns tables created successfully!' as Status,
       'campaign_items, campaign_donations, campaign_volunteers' as Tables_Created;