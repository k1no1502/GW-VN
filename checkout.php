<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Thanh toán";
$hasOrderHistoryTable = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));

$success = '';
$error = '';

// Get user info
$user = getUserById($_SESSION['user_id']);
$shipping_name = $user['name'] ?? '';
$shipping_phone = $user['phone'] ?? '';
$shipping_address = $user['address'] ?? '';
$shipping_note = '';
$payment_method = 'cod';

// Get cart items with explicit columns (avoid quantity/name collisions)
$sql = "SELECT 
            c.cart_id,
            c.user_id,
            c.item_id,
            c.quantity AS cart_quantity,
            c.created_at AS cart_created_at,
            i.name AS item_name,
            i.description,
            i.category_id,
            i.quantity AS inventory_quantity,
            i.condition_status,
            i.price_type,
            i.sale_price,
            i.unit,
            i.images,
            i.status AS inventory_status,
            cat.name as category_name
        FROM cart c
        JOIN inventory i ON c.item_id = i.item_id
        LEFT JOIN categories cat ON i.category_id = cat.category_id
        WHERE c.user_id = ? AND i.status = 'available'
        ORDER BY c.created_at DESC";
$cartItems = Database::fetchAll($sql, [$_SESSION['user_id']]);

if (empty($cartItems)) {
    header('Location: cart.php');
    exit();
}

// Calculate totals
$totalItems = 0;
$totalAmount = 0;
$freeItemsCount = 0;
$paidItemsCount = 0;

foreach ($cartItems as $item) {
    $qty = (int)$item['cart_quantity'];
    $totalItems += $qty;

    $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
    $itemTotal = $unitPrice * $qty;
    $totalAmount += $itemTotal;
    
    if ($item['price_type'] === 'free') {
        $freeItemsCount += $qty;
    } else {
        $paidItemsCount += $qty;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_name = sanitize($_POST['shipping_name'] ?? $shipping_name);
    $shipping_phone = sanitize($_POST['shipping_phone'] ?? $shipping_phone);
    $shipping_address = sanitize($_POST['shipping_address'] ?? $shipping_address);
    $shipping_note = sanitize($_POST['shipping_note'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? $payment_method);
    
    // Validation
    if (empty($shipping_name)) {
        $error = 'Vui lòng nhập họ tên người nhận.';
    } elseif (empty($shipping_phone)) {
        $error = 'Vui lòng nhập số điện thoại.';
    } elseif (empty($shipping_address)) {
        $error = 'Vui lòng nhập địa chỉ giao hàng.';
    } elseif (empty($payment_method)) {
        $error = 'Vui lòng chọn phương thức thanh toán.';
    } else {
        try {
            Database::beginTransaction();

            // Kiểm tra schema bảng orders (hỗ trợ cả 2 kiểu: update_schema & orders_system)
            $hasShippingName = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_name'"));
            $statusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");
            $allowedStatuses = [];
            if (!empty($statusColumn['Type']) && strpos($statusColumn['Type'], "enum(") === 0) {
                preg_match_all("/'([^']+)'/", $statusColumn['Type'], $matches);
                $allowedStatuses = $matches[1] ?? [];
            }
            $orderStatus = in_array('pending', $allowedStatuses, true) ? 'pending' : ($allowedStatuses[0] ?? 'pending');
            $legacyPaymentMethod = $payment_method === 'cod' ? 'cash' : $payment_method;
            $allowedLegacyMethods = ['cash', 'bank_transfer', 'credit_card', 'free'];
            if (!in_array($legacyPaymentMethod, $allowedLegacyMethods, true)) {
                $legacyPaymentMethod = 'cash';
            }

            if ($hasShippingName) {
                // Schema mới: có shipping_name, shipping_note (orders_system.sql)
                Database::execute(
                    "INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address, 
                                         shipping_note, payment_method, total_amount, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $_SESSION['user_id'],
                        $shipping_name,
                        $shipping_phone,
                        $shipping_address,
                        $shipping_note,
                        $payment_method,
                        $totalAmount,
                        $orderStatus
                    ]
                );
            } else {
                // Schema cũ trong update_schema.sql: dùng order_number, total_items, notes...
                $order_number = 'ORD-' . date('Ymd-His') . '-' . $_SESSION['user_id'];
                Database::execute(
                    "INSERT INTO orders (
                        order_number, user_id, total_amount, total_items, status, 
                        payment_method, shipping_address, shipping_phone, notes, created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $order_number,
                        $_SESSION['user_id'],
                        $totalAmount,
                        $totalItems,
                        $orderStatus,
                        $legacyPaymentMethod,
                        $shipping_address,
                        $shipping_phone,
                        $shipping_note
                    ]
                );
            }
            
            $order_id = Database::lastInsertId();
            
            // Kiểm tra schema bảng order_items
            $hasUnitPrice = !empty(Database::fetchAll("SHOW COLUMNS FROM order_items LIKE 'unit_price'"));

            // Create order items
            foreach ($cartItems as $item) {
                $qty       = (int)$item['cart_quantity'];
                $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
                $itemTotal = $unitPrice * $qty;
                
                if ($hasUnitPrice) {
                    // Schema mới: unit_price + total_price
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, unit_price, total_price, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $order_id,
                            $item['item_id'],
                            $item['item_name'],
                            $qty,
                            $unitPrice,
                            $itemTotal
                        ]
                    );
                } else {
                    // Schema cũ: price, price_type, subtotal
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, price, price_type, subtotal, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $order_id,
                            $item['item_id'],
                            $item['item_name'],
                            $qty,
                            $unitPrice,
                            $item['price_type'],
                            $itemTotal
                        ]
                    );
                }
                
                // Update inventory
                Database::execute(
                    "UPDATE inventory SET quantity = quantity - ? WHERE item_id = ?",
                    [$item['cart_quantity'], $item['item_id']]
                );
            }
            
            // Clear cart
            Database::execute("DELETE FROM cart WHERE user_id = ?", [$_SESSION['user_id']]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'create_order', "Created order #$order_id");

            // Save order history entry (pending) nếu có bảng
            if ($hasOrderHistoryTable) {
                Database::execute(
                    "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
                     VALUES (?, 'pending', 'pending', 'Tạo đơn hàng mới', NOW())",
                    [$order_id]
                );
            }
            
            Database::commit();
            
            // Redirect to success page
            header("Location: order-success.php?order_id=$order_id");
            exit();
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Checkout error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.';
        }
    }
}

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-credit-card me-2"></i>Thanh toán
            </h1>
            <p class="lead text-muted">Hoan tat don hang của bạn</p>
        </div>
    </div>

    <div class="row">
        <!-- Checkout Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-lines-fill me-2"></i>Thông tin giao hàng
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Shipping Information -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shipping_name" class="form-label">Họ tên người nhận *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="shipping_name" 
                                       name="shipping_name" 
                                       value="<?php echo htmlspecialchars($shipping_name ?: $user['name']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập họ tên người nhận.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="shipping_phone" class="form-label">Số điện thoại *</label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="shipping_phone" 
                                       name="shipping_phone" 
                                       value="<?php echo htmlspecialchars($shipping_phone ?: $user['phone']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập số điện thoại.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Địa chỉ giao hàng *</label>
                            <textarea class="form-control" 
                                      id="shipping_address" 
                                      name="shipping_address" 
                                      rows="3" 
                                      placeholder="Nhập địa chỉ chi tiết (số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố)"
                                      required><?php echo htmlspecialchars($shipping_address ?: $user['address']); ?></textarea>
                            <div class="invalid-feedback">
                                Vui lòng nhập địa chỉ giao hàng.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="shipping_note" class="form-label">Ghi chú giao hàng</label>
                            <textarea class="form-control" 
                                      id="shipping_note" 
                                      name="shipping_note" 
                                      rows="2" 
                                      placeholder="Ghi chú thêm cho đơn hàng (tùy chọn)"><?php echo htmlspecialchars($shipping_note); ?></textarea>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-4">
                            <label class="form-label">Phương thức thanh toán *</label>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="payment_method" 
                                               id="cod" 
                                               value="cod"
                                               <?php echo ($payment_method === 'cod' || empty($payment_method)) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cod">
                                            <i class="bi bi-cash-coin me-2"></i>Thanh toán khi nhận hàng (COD)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="payment_method" 
                                               id="bank_transfer" 
                                               value="bank_transfer"
                                               <?php echo $payment_method === 'bank_transfer' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="bank_transfer">
                                            <i class="bi bi-bank me-2"></i>Chuyển khoản ngân hàng
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button id="submitOrderBtn" type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Hoan tat don hang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 100px;">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Order Items -->
                    <div class="mb-3">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $images = json_decode($item['images'] ?? '[]', true);
                            $firstImage = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                            $itemTotal = $item['price_type'] === 'free' ? 0 : $item['sale_price'] * $item['cart_quantity'];
                            ?>
                            <div class="d-flex align-items-center mb-2">
                                <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                     class="rounded me-2" 
                                     style="width: 40px; height: 40px; object-fit: cover;"
                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                     onerror="this.src='uploads/donations/placeholder-default.svg'">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 small"><?php echo htmlspecialchars(substr($item['item_name'], 0, 30)); ?></h6>
                                    <small class="text-muted">x<?php echo $item['cart_quantity']; ?></small>
                                </div>
                                <div class="text-end">
                                    <small class="fw-bold">
                                        <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : number_format($itemTotal) . ' VNĐ'; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <!-- Order Totals -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng sản phẩm:</span>
                            <strong><?php echo $totalItems; ?> sản phẩm</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sản phẩm miễn phí:</span>
                            <span class="text-success"><?php echo $freeItemsCount; ?> sản phẩm</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sản phẩm trả phí:</span>
                            <span class="text-warning"><?php echo $paidItemsCount; ?> sản phẩm</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span class="text-success">Miễn phí</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Tổng cộng:</span>
                            <span class="fw-bold text-success fs-5">
                                <?php echo $totalAmount > 0 ? number_format($totalAmount) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Security Info -->
                    <div class="p-3 bg-light rounded">
                        <h6 class="text-success mb-2">
                            <i class="bi bi-shield-check me-1"></i>Cam kết
                        </h6>
                        <small class="text-muted">
                            • Giao hàng tận nơi miễn phí<br>
                            • Kiểm tra hàng trước khi thanh toán<br>
                            • Hỗ trợ đổi trả trong 7 ngày<br>
                            • Bảo mật thông tin khách hàng
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Toggle submit button label based on payment method
(function() {
    const submitBtn = document.getElementById('submitOrderBtn');
    const radios = document.querySelectorAll('input[name=\"payment_method\"]');
    const updateLabel = () => {
        if (!submitBtn) return;
        const bankSelected = document.getElementById('bank_transfer')?.checked;
        submitBtn.innerHTML = bankSelected
            ? '<i class="bi bi-check-circle me-2"></i>Hoan tat thanh toan'
            : '<i class="bi bi-check-circle me-2"></i>Hoan tat don hang';
    };
    radios.forEach(r => r.addEventListener('change', updateLabel));
    updateLabel();
})();
</script>

<?php include 'includes/footer.php'; ?>