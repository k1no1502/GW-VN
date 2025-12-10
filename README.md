# 🌐 GOODWILL VIETNAM - HỆ THỐNG THIỆN NGUYỆN

Website thiện nguyện hoàn chỉnh được xây dựng với **PHP + MySQL + HTML/CSS/JS + Bootstrap 5**

## 📋 MỤC LỤC
- [Tính năng](#tính-năng)
- [Công nghệ sử dụng](#công-nghệ-sử-dụng)
- [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
- [Cài đặt](#cài-đặt)
- [Cấu trúc dự án](#cấu-trúc-dự-án)
- [Tài khoản mặc định](#tài-khoản-mặc-định)
- [Tính năng chi tiết](#tính-năng-chi-tiết)

## ✨ TÍNH NĂNG

### 🔐 Hệ thống xác thực
- Đăng ký / Đăng nhập với mã hóa mật khẩu
- Phân quyền: Admin, User, Guest
- Quản lý hồ sơ người dùng
- Đặt lại mật khẩu

### 💝 Chức năng quyên góp
- Gửi quyên góp vật phẩm với hình ảnh
- Theo dõi trạng thái quyên góp
- Admin duyệt/từ chối quyên góp
- Tự động thêm vào kho hàng khi duyệt

### 🛒 Chức năng bán hàng
- **Vật phẩm MIỄN PHÍ**: Người dùng có thể nhận miễn phí
- **Vật phẩm GIÁ RẺ**: Bán với giá ưu đãi
- Bộ lọc theo danh mục và loại giá
- Giỏ hàng và thanh toán
- Theo dõi đơn hàng

### 🎯 Admin Panel
- Dashboard với thống kê trực quan
- Quản lý quyên góp (duyệt/từ chối)
- Quản lý kho hàng (thiết lập giá bán)
- Quản lý đơn hàng
- Quản lý người dùng
- Quản lý danh mục
- Báo cáo và thống kê với Chart.js
- Cấu hình hệ thống

### 📊 Báo cáo & Thống kê
- Thống kê số lượng người dùng, quyên góp, vật phẩm
- Biểu đồ quyên góp theo tháng
- Biểu đồ phân bố danh mục
- Hoạt động gần đây

## 🛠️ CÔNG NGHỆ SỬ DỤNG

| Lớp | Công nghệ |
|------|-----------|
| **Frontend** | HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js |
| **Backend** | PHP 8.x (PDO) |
| **Database** | MySQL 8.x |
| **Icons** | Bootstrap Icons |
| **Architecture** | MVC Pattern |

## 📦 YÊU CẦU HỆ THỐNG

- **XAMPP** (hoặc WAMP/LAMP):
  - Apache Web Server
  - PHP 8.0 trở lên
  - MySQL 8.0 trở lên
  - phpMyAdmin
- **Browser**: Chrome, Firefox, Edge (phiên bản mới nhất)

## 🚀 CÀI ĐẶT

### Bước 1: Cài đặt XAMPP
1. Tải XAMPP từ: https://www.apachefriends.org/
2. Cài đặt và khởi động Apache + MySQL

### Bước 2: Clone dự án
```bash
cd C:\xampp\htdocs
git clone [repository-url] "Cap 1 - 2"
```

Hoặc giải nén file zip vào thư mục `C:\xampp\htdocs\Cap 1 - 2`

### Bước 3: Tạo cơ sở dữ liệu
1. Mở phpMyAdmin: http://localhost/phpmyadmin
2. Tạo database mới tên `goodwill_vietnam`
3. Import file: `database/schema.sql`
4. Import file cập nhật: `database/update_schema.sql`

**Hoặc chạy SQL trực tiếp:**
```sql
-- Tạo database
CREATE DATABASE goodwill_vietnam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sau đó import 2 file SQL theo thứ tự:
1. `schema.sql` - Cấu trúc cơ bản
2. `update_schema.sql` - Cập nhật cho chức năng bán hàng

### Bước 4: Cấu hình database
Mở file `config/database.php` và kiểm tra cấu hình:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'goodwill_vietnam');
define('DB_USER', 'root');
define('DB_PASS', ''); // Để trống nếu dùng XAMPP mặc định
```

### Bước 5: Phân quyền thư mục
Đảm bảo thư mục `uploads/` có quyền ghi:
```bash
# Windows: Chuột phải -> Properties -> Security -> Edit
# Cho phép Full Control cho user hiện tại
```

### Bước 6: Truy cập website
1. Mở browser
2. Truy cập: http://localhost/Cap%201%20-%202/

## 📁 CẤU TRÚC DỰ ÁN

```
Cap 1 - 2/
├── admin/                  # Trang quản trị
│   ├── dashboard.php       # Dashboard
│   ├── donations.php       # Quản lý quyên góp
│   ├── inventory.php       # Quản lý kho hàng
│   ├── orders.php          # Quản lý đơn hàng
│   └── includes/
│       └── sidebar.php     # Sidebar admin
├── api/                    # API endpoints
│   ├── add-to-cart.php     # Thêm vào giỏ hàng
│   ├── get-statistics.php  # Lấy thống kê
│   ├── get-cart-count.php  # Đếm giỏ hàng
│   └── ...
├── assets/                 # Tài nguyên tĩnh
│   ├── css/
│   │   └── style.css       # Custom CSS
│   ├── js/
│   │   └── main.js         # Custom JavaScript
│   └── images/
├── config/                 # Cấu hình
│   └── database.php        # Kết nối database
├── database/               # SQL files
│   ├── schema.sql          # Schema cơ bản
│   └── update_schema.sql   # Cập nhật schema
├── includes/               # Thư viện PHP
│   └── functions.php       # Hàm tiện ích
├── uploads/                # Thư mục upload
│   └── donations/          # Ảnh quyên góp
├── index.php               # Trang chủ
├── login.php               # Đăng nhập
├── register.php            # Đăng ký
├── donate.php              # Quyên góp
├── items.php               # Danh sách vật phẩm
├── cart.php                # Giỏ hàng
└── README.md               # File này
```

## 👤 TÀI KHOẢN MẶC ĐỊNH

### Admin
- **Email**: admin@goodwillvietnam.com
- **Password**: password
- **Quyền**: Toàn quyền quản trị hệ thống

> **Lưu ý**: Đổi mật khẩu sau khi đăng nhập lần đầu!

## 🎯 TÍNH NĂNG CHI TIẾT

### 1. Chức năng User

#### 1.1 Quyên góp vật phẩm
- Upload nhiều ảnh (tối đa 5)
- Chọn danh mục
- Nhập thông tin chi tiết
- Chọn ngày giờ nhận hàng
- Theo dõi trạng thái

#### 1.2 Mua sắm vật phẩm
- **Bộ lọc thông minh**:
  - Theo danh mục (Quần áo, Đồ điện tử, Sách vở...)
  - Theo loại giá (Miễn phí / Giá rẻ)
  - Tìm kiếm theo tên
- **Giỏ hàng**:
  - Thêm/xóa vật phẩm
  - Điều chỉnh số lượng
  - Xem tổng tiền
- **Thanh toán**:
  - Nhập địa chỉ giao hàng
  - Chọn phương thức thanh toán
  - Theo dõi đơn hàng

#### 1.3 Quản lý cá nhân
- Xem hồ sơ
- Cập nhật thông tin
- Xem lịch sử quyên góp
- Xem đơn hàng đã đặt

### 2. Chức năng Admin

#### 2.1 Quản lý quyên góp
- Xem danh sách quyên góp
- Lọc theo trạng thái (Chờ duyệt/Đã duyệt/Từ chối)
- Duyệt quyên góp → Tự động thêm vào kho
- Từ chối với lý do
- Xem chi tiết và hình ảnh

#### 2.2 Quản lý kho hàng
- **Thiết lập giá bán**:
  - Miễn phí (0 VNĐ)
  - Giá rẻ (< 100,000 VNĐ)
  - Giá thông thường
- **Quản lý trạng thái**:
  - Có sẵn
  - Đã đặt
  - Đã bán
  - Hư hỏng
- **Hiển thị trong shop**:
  - Bật/tắt hiển thị
  - Cập nhật số lượng

#### 2.3 Dashboard
- Thống kê tổng quan
- Biểu đồ quyên góp theo tháng
- Biểu đồ phân bố danh mục
- Hoạt động gần đây
- Quyên góp cần duyệt

## 🔒 BẢO MẬT

- Mật khẩu được mã hóa bằng `password_hash()`
- Chống SQL Injection bằng PDO Prepared Statements
- Xác thực session cho mọi trang riêng tư
- Phân quyền chi tiết (Admin/User/Guest)
- Validate dữ liệu đầu vào
- Upload file an toàn (kiểm tra MIME type)

## 📊 DATABASE

### Các bảng chính:
- `users` - Người dùng
- `roles` - Vai trò
- `donations` - Quyên góp
- `inventory` - Kho hàng
- `orders` - Đơn hàng
- `order_items` - Chi tiết đơn hàng
- `cart` - Giỏ hàng
- `categories` - Danh mục
- `campaigns` - Chiến dịch
- `feedback` - Phản hồi
- `activity_logs` - Nhật ký hoạt động

### Views:
- `v_statistics` - Thống kê tổng quan
- `v_donation_details` - Chi tiết quyên góp
- `v_inventory_items` - Vật phẩm kho
- `v_saleable_items` - Vật phẩm bán hàng
- `v_order_details` - Chi tiết đơn hàng

## 🎨 GIAO DIỆN

- **Responsive Design**: Tối ưu cho mobile, tablet, desktop
- **Color Theme**: Xanh lá thiện nguyện (#198754)
- **Typography**: Roboto, sans-serif
- **Icons**: Bootstrap Icons
- **Components**: Bootstrap 5
- **Charts**: Chart.js

## 📝 WORKFLOW

### Quy trình quyên góp:
1. User đăng ký/đăng nhập
2. Gửi quyên góp với thông tin và hình ảnh
3. Admin xem và duyệt quyên góp
4. Hệ thống tự động thêm vào kho hàng
5. Admin thiết lập loại giá (Miễn phí/Giá rẻ)
6. Vật phẩm hiển thị trên trang shop

### Quy trình mua hàng:
1. User duyệt vật phẩm và lọc theo nhu cầu
2. Thêm vật phẩm vào giỏ hàng
3. Xem và chỉnh sửa giỏ hàng
4. Thanh toán và tạo đơn hàng
5. Admin xử lý đơn hàng
6. User nhận vật phẩm

## 🚀 NÂNG CẤP TƯƠNG LAI

- [ ] Tích hợp cổng thanh toán online (VNPay, Momo)
- [ ] Hệ thống chat realtime
- [ ] Notification push
- [ ] Export báo cáo Excel/PDF
- [ ] API RESTful
- [ ] Mobile app
- [ ] Social login (Facebook, Google)
- [ ] Email marketing
- [ ] Đánh giá và review vật phẩm

## 📞 HỖ TRỢ

Nếu gặp vấn đề khi cài đặt hoặc sử dụng:

1. **Kiểm tra log lỗi**:
   - PHP errors: `C:\xampp\apache\logs\error.log`
   - MySQL errors: `C:\xampp\mysql\data\*.err`

2. **Thư mục uploads**:
   - Đảm bảo tồn tại: `uploads/donations/`
   - Có quyền ghi

3. **Database**:
   - Kiểm tra kết nối trong `config/database.php`
   - Import đầy đủ 2 file SQL

4. **PHP Version**:
   - Tối thiểu PHP 8.0
   - Bật PDO extension

## 📄 LICENSE

Dự án được phát triển cho mục đích giáo dục và phi lợi nhuận.

## 👨‍💻 PHÁT TRIỂN BỞI

Goodwill Vietnam Team - 2024

---

**Chúc bạn triển khai thành công! 🎉**
