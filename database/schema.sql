-- Goodwill Vietnam Database Schema
-- Tạo cơ sở dữ liệu và các bảng cho hệ thống thiện nguyện

-- Tạo database
CREATE DATABASE IF NOT EXISTS goodwill_vietnam 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE goodwill_vietnam;

-- Bảng vai trò người dùng
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng người dùng
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(128) NULL,
    phone VARCHAR(20),
    address TEXT,
    avatar VARCHAR(255),
    role_id INT DEFAULT 2, -- 1=admin, 2=user, 3=guest
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT
);

-- Bảng danh mục vật phẩm
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(100),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- Bảng quyên góp
CREATE TABLE donations (
    donation_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'cái',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    estimated_value DECIMAL(10,2),
    images JSON, -- Lưu trữ đường dẫn ảnh
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    pickup_address TEXT,
    pickup_date DATE,
    pickup_time TIME,
    contact_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- Bảng kho hàng
CREATE TABLE inventory (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'cái',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    price_type ENUM('free', 'cheap', 'contact') DEFAULT 'free',
    sale_price DECIMAL(10,2) DEFAULT 0,
    estimated_value DECIMAL(10,2),
    actual_value DECIMAL(10,2),
    images JSON,
    location VARCHAR(100),
    status ENUM('available', 'reserved', 'sold', 'damaged', 'disposed') DEFAULT 'available',
    reserved_by INT NULL,
    reserved_until TIMESTAMP NULL,
    sold_to INT NULL,
    sold_at TIMESTAMP NULL,
    sold_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (reserved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (sold_to) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Bảng người thụ hưởng
CREATE TABLE beneficiaries (
    beneficiary_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    organization_type ENUM('individual', 'ngo', 'charity', 'school', 'hospital', 'other') DEFAULT 'individual',
    description TEXT,
    verification_documents JSON,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Bảng giao dịch
CREATE TABLE transactions (
    trans_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT,
    beneficiary_id INT,
    type ENUM('donation', 'purchase', 'reservation', 'cancellation') NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'bank_transfer', 'credit_card', 'free') DEFAULT 'free',
    payment_reference VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory(item_id) ON DELETE SET NULL,
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(beneficiary_id) ON DELETE SET NULL
);

-- Bảng chiến dịch
CREATE TABLE campaigns (
    campaign_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_amount DECIMAL(12,2),
    current_amount DECIMAL(12,2) DEFAULT 0,
    target_items INT,
    current_items INT DEFAULT 0,
    status ENUM('draft', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
);

-- Bảng liên kết chiến dịch với quyên góp
CREATE TABLE campaign_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    donation_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    UNIQUE KEY unique_campaign_donation (campaign_id, donation_id)
);

-- Bảng thông báo
CREATE TABLE notifications (
    notify_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Bảng phản hồi
CREATE TABLE feedback (
    fb_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100),
    email VARCHAR(100),
    subject VARCHAR(200),
    content TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('pending', 'read', 'replied', 'closed') DEFAULT 'pending',
    admin_reply TEXT,
    replied_by INT,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (replied_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Bảng nhân viên
CREATE TABLE staff (
    staff_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    employee_id VARCHAR(20) UNIQUE,
    position VARCHAR(100),
    department VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    hire_date DATE,
    salary DECIMAL(10,2),
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    assigned_area VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Bảng nhật ký hoạt động
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Bảng cấu hình hệ thống
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Bảng sao lưu
CREATE TABLE backups (
    backup_id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    backup_type ENUM('full', 'incremental', 'manual') DEFAULT 'manual',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Chèn dữ liệu mẫu

-- Chèn vai trò
INSERT INTO roles (role_id, role_name, description, permissions) VALUES
(1, 'admin', 'Quản trị viên hệ thống', '{"all": true}'),
(2, 'user', 'Người dùng thông thường', '{"donate": true, "browse": true, "order": true}'),
(3, 'guest', 'Khách truy cập', '{"browse": true}');

-- Chèn danh mục
INSERT INTO categories (name, description, icon, sort_order) VALUES
('Quần áo', 'Quần áo cũ và mới', 'bi-tshirt', 1),
('Đồ điện tử', 'Điện thoại, máy tính, thiết bị điện tử', 'bi-laptop', 2),
('Sách vở', 'Sách giáo khoa, truyện, tài liệu', 'bi-book', 3),
('Đồ gia dụng', 'Đồ dùng trong nhà', 'bi-house', 4),
('Đồ chơi', 'Đồ chơi trẻ em', 'bi-toy', 5),
('Thực phẩm', 'Thực phẩm khô, đồ hộp', 'bi-basket', 6),
('Y tế', 'Thuốc, dụng cụ y tế', 'bi-heart-pulse', 7),
('Khác', 'Các vật phẩm khác', 'bi-box', 8);

-- Chèn người dùng admin mặc định
INSERT INTO users (name, email, password, role_id, status, email_verified) VALUES
('Administrator', 'admin@goodwillvietnam.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active', TRUE),
('Admin2', 'admin2@goodwillvietnam.com', '$2y$10$eImiTXuWVxfM37uY4JANjQ==', 1, 'active', TRUE);

-- Chèn cài đặt hệ thống
INSERT INTO system_settings (setting_key, setting_value, description, type) VALUES
('site_name', 'Goodwill Vietnam', 'Tên website', 'string'),
('site_description', 'Hệ thống thiện nguyện kết nối cộng đồng', 'Mô tả website', 'string'),
('contact_email', 'info@goodwillvietnam.com', 'Email liên hệ', 'string'),
('contact_phone', '+84 123 456 789', 'Số điện thoại liên hệ', 'string'),
('max_file_size', '5242880', 'Kích thước file tối đa (bytes)', 'number'),
('allowed_file_types', '["jpg", "jpeg", "png", "gif"]', 'Các loại file được phép upload', 'json'),
('items_per_page', '12', 'Số vật phẩm hiển thị mỗi trang', 'number'),
('enable_registration', 'true', 'Cho phép đăng ký tài khoản mới', 'boolean'),
('maintenance_mode', 'false', 'Chế độ bảo trì', 'boolean');

-- Tạo indexes để tối ưu hiệu suất
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_remember_token ON users(remember_token);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_donations_user ON donations(user_id);
CREATE INDEX idx_donations_status ON donations(status);
CREATE INDEX idx_donations_created ON donations(created_at);
CREATE INDEX idx_inventory_status ON inventory(status);
CREATE INDEX idx_inventory_category ON inventory(category_id);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);

-- Tạo views để truy vấn dữ liệu phức tạp

-- View thống kê tổng quan
CREATE VIEW v_statistics AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
    (SELECT COUNT(*) FROM donations WHERE status != 'cancelled') as total_donations,
    (SELECT COUNT(*) FROM inventory WHERE status = 'available') as total_items,
    (SELECT COUNT(*) FROM campaigns WHERE status = 'active') as active_campaigns,
    (SELECT COUNT(*) FROM transactions WHERE type = 'donation' AND status = 'completed') as completed_donations,
    (SELECT SUM(amount) FROM transactions WHERE type = 'donation' AND status = 'completed') as total_donation_value;

-- View quyên góp chi tiết
CREATE VIEW v_donation_details AS
SELECT 
    d.*,
    u.name as donor_name,
    u.email as donor_email,
    u.phone as donor_phone,
    c.name as category_name,
    CASE 
        WHEN d.status = 'pending' THEN 'Chờ duyệt'
        WHEN d.status = 'approved' THEN 'Đã duyệt'
        WHEN d.status = 'rejected' THEN 'Từ chối'
        WHEN d.status = 'cancelled' THEN 'Đã hủy'
    END as status_text
FROM donations d
LEFT JOIN users u ON d.user_id = u.user_id
LEFT JOIN categories c ON d.category_id = c.category_id;

-- View vật phẩm trong kho
CREATE VIEW v_inventory_items AS
SELECT 
    i.*,
    d.item_name as donation_name,
    d.description as donation_description,
    u.name as donor_name,
    c.name as category_name,
    CASE 
        WHEN i.status = 'available' THEN 'Có sẵn'
        WHEN i.status = 'reserved' THEN 'Đã đặt'
        WHEN i.status = 'sold' THEN 'Đã bán'
        WHEN i.status = 'damaged' THEN 'Hư hỏng'
        WHEN i.status = 'disposed' THEN 'Đã xử lý'
    END as status_text
FROM inventory i
LEFT JOIN donations d ON i.donation_id = d.donation_id
LEFT JOIN users u ON d.user_id = u.user_id
LEFT JOIN categories c ON i.category_id = c.category_id;
