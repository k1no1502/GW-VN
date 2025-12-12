
# 🌍 **Goodwill Vietnam – Nền tảng thiện nguyện ❤️**

Goodwill Vietnam là website thiện nguyện trực tuyến, kết nối người tặng – người nhận – ban vận hành chủ yếu với **PHP 8 + MySQL + HTML/CSS/JS + Bootstrap 5**. Giúp các tổ chức phi lợi nhuận quản lý quyên góp, kho vật phẩm, chiến dịch và tình nguyện viên trên một hệ thống duy nhất.

## 📑 **Mục lục**
- [Tính năng nổi bật](#tính-năng-nổi-bật)
- [Công nghệ sử dụng](#công-nghệ-sử-dụng)
- [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
- [Hướng dẫn cài đặt nhanh](#hướng-dẫn-cài-đặt-nhanh)
- [Cấu trúc thư mục](#cấu-trúc-thư-mục)
- [Tài khoản mẫu](#tài-khoản-mẫu)
- [Chi tiết chức năng](#chi-tiết-chức-năng)
- [Bảo mật & tuân thủ](#bảo-mật--tuân-thủ)
- [Quy trình vận hành](#quy-trình-vận-hành)
- [Lộ trình phát triển](#lộ-trình-phát-triển)
- [Hỗ trợ & tài liệu](#hỗ-trợ--tài-liệu)
- [Giấy phép](#giấy-phép)

## 🌟 **Tính năng nổi bật**
- **💰 Form quyên góp thông minh**: tạo nhiều vật phẩm, upload ảnh/link, nhập hàng loạt từ Excel/CSV (.xlsx, .xls, .csv).
- **📊 Theo dõi quyên góp giống đơn hàng**: trang donation-tracking.php hiển thị tiến trình duyệt, nhập kho, phân phối bằng timeline & phần trăm hoàn thành.
- **🛒 Shop thiện nguyện**: lọc danh mục/loại giá, giá hàng, thanh toán COD, tra cứu trạng thái giao hàng.
- **📈 Admin Insight**: dashboard Chart.js, thống kê người dùng, kho hàng, quyên góp, chiến dịch, nhật ký hoạt động.
- **💖 Chiến dịch + tình nguyện viên**: đăng ký trực tuyến, cập nhật tiến độ chiến dịch, số lượng vật phẩm đã nhận.
- **🏪 Kho vật phẩm**: duyệt quyên góp vào kho, đánh giá (miễn phí, giá rẻ, giá thường), gắn chiến dịch, quản lý tồn.

## 💻 **Công nghệ sử dụng**
| Tầng             | Công nghệ |
|------------------|-----------|
| Frontend         | HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js |
| Backend          | PHP 8.x (PDO, session) |
| Database         | MySQL 8.x (utf8mb4) |
| Thư viện khác    | Bootstrap Icons, ZipArchive, SimpleXML |
| Kiến trúc        | MVC đơn giản + module Admin/API |

## 🖥️ **Yêu cầu hệ thống**
- Apache/Nginx (XAMPP, WAMP/LAMP hoặc Laragon đều phù hợp).
- PHP = 8.0, bật pdo_mysql, mbstring, zip, iconv.
- MySQL = 8.0, charset utf8mb4.
- Trình duyệt hiện đại: Chrome, Edge, Firefox.

## 🚀 **Hướng dẫn cài đặt nhanh**
1. **Clone mã nguồn**
   ```bash
   cd C:\laragon\www
   git clone <repo-url> "Cap 1 - 2"
   ```
2. **Tạo database**
   - phpMyAdmin tạo DB goodwill_vietnam (utf8mb4).
   - Import database/schema.sql (và database/update_schema.sql nếu có).
3. **Cấu hình** (config/database.php)
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'goodwill_vietnam');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
4. **Cấp quyền**: thư mục uploads/ phải cho phép ghi.
5. **Truy cập**: http://localhost/Cap%201%20-%202/

> **Lưu ý**: Chỉ cần bật thêm ZipArchive nếu muốn đọc file .xlsx.

## 📂 **Cấu trúc thư mục**
```
Cap 1 - 2/
+-- admin/                # Quản trị: dashboard, donations, inventory...
+-- api/                  # Endpoint AJAX/RESTful
+-- assets/               # CSS, JS, hình ảnh, template Excel
+-- config/               # database.php
+-- database/             # schema, seed
+-- includes/             # header/footer/functions
+-- uploads/              # Ảnh quyên góp, chiến dịch
+-- donation-tracking.php # trang theo dõi quyên góp
+-- donate.php            # form quyên góp
+-- my-donations.php      # lịch sử quyên góp
+-- order-tracking.php    # theo dõi đơn hàng
+-- ...
```

## 🔑 **Tài khoản mẫu**
| Loại  | Email                      | Mật khẩu |
|-------|----------------------------|----------|
| Admin | admin@goodwillvietnam.com | password |
| User  | Tự đăng ký hoặc import     | –        |

> **Lưu ý**: Đổi mật khẩu admin ngay sau khi khởi chạy.

## 🔧 **Chi tiết chức năng**
### Người dùng
- **💳 Quyên góp**: nhập tay hoặc tải từ Excel/CSV, giới hạn 5 ảnh/vật phẩm, đặt lịch nhận, theo dõi tiến trình.
- **🛒 Shop**: lọc danh mục, loại giá, khuyến mãi; giỏ hàng, thanh toán COD, xem lịch sử đơn – tracking theo từng đơn.
- **🎯 Chiến dịch & tình nguyện viên**: xem nhu cầu, đóng góp nhanh, đăng ký tình nguyện viên.
- **👤 Tài khoản**: quản lý hồ sơ, đổi mật khẩu, xem lịch sử quyên góp (my-donations.php).

### Quản trị viên
- Duyệt/Từ chối quyên góp, ghi chú nội dung.
- Quản lý kho: đánh giá, trạng thái vật phẩm, vị trí lưu trữ, liên kết chiến dịch.
- Quản lý đơn hàng, chiến dịch, danh mục, người dùng, phản hồi.
- Dashboard trực quan (Chart.js) + nhật ký hoạt động.

## 🛡️ **Bảo mật & tuân thủ**
- Mật khẩu băm bằng password_hash.
- PDO Prepared Statements chống SQL Injection.
- Kiểm tra session & phân quyền trên mọi trang.
- Kiểm tra MIME type trước khi lưu ảnh.
- Chuẩn hóa UTF-8 khi xử lý Excel/CSV (hạn chế lỗi mã hóa tiếng Việt).

## 🔄 **Quy trình vận hành**
1. **Quyên góp**: gửi đơn cho admin duyệt -> nhập kho -> phân phối -> người tặng theo dõi.
2. **Mua hàng**: chọn sản phẩm -> giỏ hàng -> COD -> admin giao/cập nhật trạng thái.
3. **Chiến dịch**: tạo chiến dịch -> kêu gọi vật phẩm/tình nguyện viên -> theo dõi tiến độ trên dashboard.

## 🛠️ **Lộ trình phát triển**
- [ ] Tích hợp thanh toán trực tuyến (VNPay/MoMo).
- [ ] Thông báo realtime / push notification.
- [ ] Xuất báo cáo PDF/Excel 1-click.
- [ ] API RESTful công khai.
- [ ] Ứng dụng mobile, social login, email marketing.

## 🆘 **Hỗ trợ & tài liệu**
1. **Log lỗi**: Apache/logs/error.log, mysql/data/*.err.
2. **Uploads**: đảm bảo uploads/ được quyền ghi.
3. **Cấu hình DB**: kiểm tra config/database.php.
4. **Phụ lục**: file INSTALL.txt mô tả chi tiết hơn (kèm checklist triển khai, script seed dữ liệu).

## 📜 **Giấy phép**
Dự án phục vụ mục đích giáo dục và cộng đồng, không sử dụng cho mục đích thương mại nếu chưa có sự đồng ý của Goodwill Vietnam Team (2024).

---

**🌱 Chúc bạn triển khai nền tảng thiện nguyện thành công!**
