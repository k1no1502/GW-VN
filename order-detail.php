<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Chi tiết đơn hàng";

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header('Location: my-orders.php');
    exit();
}

// Get order details
$order = Database::fetch(
    "SELECT o.*, u.name as user_name, u.email as user_email 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     WHERE o.order_id = ? AND o.user_id = ?",
    [$order_id, $_SESSION['user_id']]
);

if (!$order) {
    header('Location: my-orders.php');
    exit();
}

// Get order items
$orderItems = Database::fetchAll(
    "SELECT oi.*, i.images, i.condition_status, i.unit, i.price_type
     FROM order_items oi
     LEFT JOIN inventory i ON oi.item_id = i.item_id
     WHERE oi.order_id = ?
     ORDER BY oi.created_at",
    [$order_id]
);

// Get order status history (nếu bảng tồn tại)
$statusHistory = [];
try {
    $statusHistory = Database::fetchAll(
        "SELECT * FROM order_status_history 
         WHERE order_id = ? 
         ORDER BY created_at DESC",
        [$order_id]
    );
} catch (Exception $e) {
    // Bảng có thể chưa tồn tại nếu chưa import orders_system.sql -> bỏ qua
    $statusHistory = [];
}

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-6 fw-bold text-success mb-2">
                        <i class="bi bi-receipt me-2"></i>Chi tiết đơn hàng
                    </h1>
                    <p class="text-muted mb-0">
                        Đơn hàng #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
                    </p>
                </div>
                <div>
                    <a href="my-orders.php" class="btn btn-outline-success">
                        <i class="bi bi-arrow-left me-2"></i>Quay lại
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Order Information -->
        <div class="col-lg-8">
            <!-- Order Status -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Trạng thái đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $statusClass = '';
                    $statusText = '';
                    $statusIcon = '';
                    
                    switch ($order['status']) {
                        case 'pending':
                            $statusClass = 'warning';
                            $statusText = 'Chờ xử lý';
                            $statusIcon = 'clock';
                            break;
                        case 'confirmed':
                            $statusClass = 'info';
                            $statusText = 'Đã xác nhận';
                            $statusIcon = 'check-circle';
                            break;
                        case 'shipping':
                            $statusClass = 'primary';
                            $statusText = 'Đang giao';
                            $statusIcon = 'truck';
                            break;
                        case 'delivered':
                            $statusClass = 'success';
                            $statusText = 'Đã giao';
                            $statusIcon = 'house-check';
                            break;
                        case 'cancelled':
                            $statusClass = 'danger';
                            $statusText = 'Đã hủy';
                            $statusIcon = 'x-circle';
                            break;
                    }
                    ?>
                    <div class="text-center">
                        <span class="badge bg-<?php echo $statusClass; ?> fs-4 px-4 py-2">
                            <i class="bi bi-<?php echo $statusIcon; ?> me-2"></i>
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-box me-2"></i>Sản phẩm đã đặt (<?php echo count($orderItems); ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($orderItems as $item): ?>
                        <?php
                        $images = json_decode($item['images'] ?? '[]', true);
                        $firstImage = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                        ?>
                        <div class="border-bottom p-3">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-3">
                                    <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                         class="img-fluid rounded" 
                                         style="width: 80px; height: 80px; object-fit: cover;"
                                         alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                         onerror="this.src='uploads/donations/placeholder-default.svg'">
                                </div>
                                <div class="col-md-4 col-9">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                    <p class="text-muted small mb-1">
                                        Tình trạng: <?php echo ucfirst($item['condition_status'] ?? 'Mới'); ?> | 
                                        Đơn vị: <?php echo $item['unit'] ?? 'Cái'; ?>
                                    </p>
                                    <div class="d-flex gap-1">
                                        <?php if ($item['price_type'] === 'free'): ?>
                                            <span class="badge bg-success">Miễn phí</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Giá rẻ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6 text-center">
                                    <p class="text-muted small mb-1">Số lượng</p>
                                    <p class="mb-0 fw-bold"><?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="col-md-2 col-6 text-center">
                                    <p class="text-muted small mb-1">Đơn giá</p>
                                    <p class="mb-0 fw-bold">
                                        <?php echo $item['unit_price'] > 0 ? number_format($item['unit_price']) . ' VNĐ' : 'Miễn phí'; ?>
                                    </p>
                                </div>
                                <div class="col-md-2 col-12 text-center">
                                    <p class="text-muted small mb-1">Thành tiền</p>
                                    <p class="mb-0 fw-bold text-success">
                                        <?php echo $item['total_price'] > 0 ? number_format($item['total_price']) . ' VNĐ' : 'Miễn phí'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status History -->
            <?php if (!empty($statusHistory)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Lịch sử trạng thái
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($statusHistory as $index => $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo $index === 0 ? 'success' : 'secondary'; ?>"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1">
                                            <?php
                                            $statusTexts = [
                                                'pending' => 'Chờ xử lý',
                                                'confirmed' => 'Đã xác nhận',
                                                'shipping' => 'Đang giao',
                                                'delivered' => 'Đã giao',
                                                'cancelled' => 'Đã hủy'
                                            ];
                                            echo $statusTexts[$history['new_status']] ?? $history['new_status'];
                                            ?>
                                        </h6>
                                        <p class="text-muted small mb-1">
                                            <?php echo date('d/m/Y H:i:s', strtotime($history['created_at'])); ?>
                                        </p>
                                        <?php if ($history['note']): ?>
                                            <p class="small mb-0"><?php echo htmlspecialchars($history['note']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 100px;">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Order Info -->
                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin giao hàng</h6>
                        <p class="mb-1"><strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                        <p class="mb-1"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                        <p class="mb-1"><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                        <?php if ($order['shipping_note']): ?>
                            <p class="mb-1"><strong>Ghi chú:</strong> <?php echo htmlspecialchars($order['shipping_note']); ?></p>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- Payment Info -->
                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin thanh toán</h6>
                        <p class="mb-1"><strong>Phương thức:</strong> 
                            <?php echo $order['payment_method'] === 'cod' ? 'Thanh toán khi nhận hàng (COD)' : 'Chuyển khoản ngân hàng'; ?>
                        </p>
                        <p class="mb-1"><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                        <p class="mb-1"><strong>Cập nhật cuối:</strong> <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
                    </div>

                    <hr>

                    <!-- Order Totals -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng sản phẩm:</span>
                            <strong><?php echo count($orderItems); ?> loại</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng số lượng:</span>
                            <strong><?php echo array_sum(array_column($orderItems, 'quantity')); ?> cái</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span class="text-success">Miễn phí</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Tổng cộng:</span>
                            <span class="fw-bold text-success fs-5">
                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-grid gap-2">
                        <?php if ($order['status'] === 'pending'): ?>
                            <button class="btn btn-outline-danger" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                <i class="bi bi-x-circle me-2"></i>Hủy đơn hàng
                            </button>
                        <?php endif; ?>
                        
                        <a href="order-tracking.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-info">
                            <i class="bi bi-truck me-2"></i>Theo dõi giao hàng
                        </a>
                        
                        <a href="my-orders.php" class="btn btn-outline-success">
                            <i class="bi bi-list-ul me-2"></i>Xem tất cả đơn hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}
</style>

<script>
function cancelOrder(orderId) {
    if (confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) {
        fetch('api/cancel-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Lỗi: ' + (data.message || 'Không thể hủy đơn hàng'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi hủy đơn hàng');
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>
