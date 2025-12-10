<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Đơn hàng của tôi";

// Get orders for current user
$sql = "SELECT o.*, 
        COUNT(oi.order_item_id) as total_items,
        SUM(oi.quantity) as total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC";
$orders = Database::fetchAll($sql, [$_SESSION['user_id']]);

// Get order statistics
$stats = Database::fetch(
    "SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN status = 'shipping' THEN 1 ELSE 0 END) as shipping_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        COALESCE(SUM(total_amount), 0) as total_spent
        FROM orders 
        WHERE user_id = ?",
    [$_SESSION['user_id']]
);

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container py-5 mt-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5 fw-bold text-success mb-3">
                <i class="bi bi-list-ul me-2"></i>Đơn hàng của tôi
            </h1>
            <p class="lead text-muted">Theo dõi và quản lý đơn hàng của bạn</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-primary"><?php echo $stats['total_orders']; ?></h5>
                    <p class="card-text small">Tổng đơn hàng</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-warning"><?php echo $stats['pending_orders']; ?></h5>
                    <p class="card-text small">Chờ xử lý</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-info"><?php echo $stats['confirmed_orders']; ?></h5>
                    <p class="card-text small">Đã xác nhận</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-primary"><?php echo $stats['shipping_orders']; ?></h5>
                    <p class="card-text small">Đang giao</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success"><?php echo $stats['delivered_orders']; ?></h5>
                    <p class="card-text small">Đã giao</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-danger"><?php echo $stats['cancelled_orders']; ?></h5>
                    <p class="card-text small">Đã hủy</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <!-- Empty Orders -->
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-cart-x display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">Chưa có đơn hàng nào</h3>
                    <p class="text-muted">Hãy mua sắm và tạo đơn hàng đầu tiên của bạn</p>
                    <a href="shop.php" class="btn btn-success btn-lg">
                        <i class="bi bi-shop me-2"></i>Mua sắm ngay
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Orders List -->
        <div class="row">
            <div class="col-12">
                <?php foreach ($orders as $order): ?>
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
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-0">
                                        <i class="bi bi-receipt me-2"></i>
                                        Đơn hàng #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="badge bg-<?php echo $statusClass; ?> fs-6">
                                        <i class="bi bi-<?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                        </div>
                                        <div class="col-md-12 mb-2">
                                            <strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?>
                                        </div>
                                        <?php if ($order['shipping_note']): ?>
                                            <div class="col-md-12 mb-2">
                                                <strong>Ghi chú:</strong> <?php echo htmlspecialchars($order['shipping_note']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-end">
                                        <p class="mb-1">
                                            <strong>Sản phẩm:</strong> <?php echo $order['total_items']; ?> loại (<?php echo $order['total_quantity']; ?> cái)
                                        </p>
                                        <p class="mb-1">
                                            <strong>Phương thức:</strong> 
                                            <?php echo $order['payment_method'] === 'cod' ? 'COD' : 'Chuyển khoản'; ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Tổng tiền:</strong> 
                                            <span class="text-success fw-bold fs-5">
                                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order Actions -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="order-detail.php?id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>Xem chi tiết
                                        </a>
                                        
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <button class="btn btn-outline-danger btn-sm" 
                                                    onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="bi bi-x-circle me-1"></i>Hủy đơn hàng
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'delivered'): ?>
                                            <button class="btn btn-outline-success btn-sm" 
                                                    onclick="rateOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="bi bi-star me-1"></i>Đánh giá
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="order-tracking.php?id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-truck me-1"></i>Theo dõi
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

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

function rateOrder(orderId) {
    // TODO: Implement rating system
    alert('Tính năng đánh giá sẽ được phát triển trong phiên bản tiếp theo');
}
</script>

<?php include 'includes/footer.php'; ?>