<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Theo dõi đơn hàng";

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header('Location: my-orders.php');
    exit();
}

// Lấy thông tin đơn hàng của chính user
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

// Lấy lịch sử trạng thái (nếu có)
$statusHistory = [];
try {
    $statusHistory = Database::fetchAll(
        "SELECT * FROM order_status_history 
         WHERE order_id = ? 
         ORDER BY created_at ASC",
        [$order_id]
    );
} catch (Exception $e) {
    // Nếu bảng chưa tồn tại thì bỏ qua phần history
    $statusHistory = [];
}

// Map trạng thái -> bước & text
$steps = [
    'pending'   => ['label' => 'Chờ xử lý', 'icon' => 'clock'],
    'confirmed' => ['label' => 'Đã xác nhận', 'icon' => 'check-circle'],
    'shipping'  => ['label' => 'Đang giao', 'icon' => 'truck'],
    'delivered' => ['label' => 'Đã giao', 'icon' => 'house-check'],
    'cancelled' => ['label' => 'Đã hủy', 'icon' => 'x-circle'],
];

$currentStatus = $order['status'];

// Xác định mức độ hoàn thành (progress)
$statusOrder = ['pending', 'confirmed', 'shipping', 'delivered'];
$currentIndex = array_search($currentStatus, $statusOrder, true);
if ($currentIndex === false) {
    $progressPercent = ($currentStatus === 'cancelled') ? 0 : 0;
} else {
    $progressPercent = (($currentIndex + 1) / count($statusOrder)) * 100;
    $progressPercent = min(100, max(0, (int)$progressPercent));
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
                        <i class="bi bi-truck me-2"></i>Theo dõi đơn hàng
                    </h1>
                    <p class="text-muted mb-0">
                        Đơn hàng #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="order-detail.php?id=<?php echo $order_id; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-receipt me-2"></i>Xem chi tiết
                    </a>
                    <a href="my-orders.php" class="btn btn-outline-success">
                        <i class="bi bi-list-ul me-2"></i>Đơn hàng của tôi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Tracking Timeline -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-geo-alt me-2"></i>Trạng thái vận chuyển
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Progress bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tiến độ đơn hàng</span>
                            <strong><?php echo $progressPercent; ?>%</strong>
                        </div>
                        <div class="progress" style="height: 22px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                 role="progressbar"
                                 style="width: <?php echo $progressPercent; ?>%">
                                <?php echo $progressPercent; ?>%
                            </div>
                        </div>
                    </div>

                    <!-- Steps -->
                    <div class="d-flex justify-content-between tracking-steps mb-4">
                        <?php foreach ($statusOrder as $statusKey): ?>
                            <?php
                            $stepInfo   = $steps[$statusKey];
                            $isActive   = ($statusOrder && array_search($statusKey, $statusOrder, true) <= $currentIndex);
                            $isCurrent  = ($statusKey === $currentStatus);
                            $stepClass  = $isActive ? 'step-active' : 'step-inactive';
                            if ($currentStatus === 'cancelled') {
                                $stepClass = $statusKey === 'pending' ? 'step-cancelled' : 'step-inactive';
                            }
                            ?>
                            <div class="tracking-step <?php echo $stepClass; ?>">
                                <div class="step-icon">
                                    <i class="bi bi-<?php echo $stepInfo['icon']; ?>"></i>
                                </div>
                                <div class="step-label">
                                    <?php echo $stepInfo['label']; ?>
                                </div>
                                <?php if ($isCurrent): ?>
                                    <div class="step-current">Hiện tại</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Status history -->
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-clock-history me-2"></i>Lịch sử cập nhật
                    </h6>
                    <?php if (!empty($statusHistory)): ?>
                        <div class="timeline">
                            <?php foreach ($statusHistory as $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1">
                                            <?php
                                            $statusTexts = [
                                                'pending'   => 'Chờ xử lý',
                                                'confirmed' => 'Đã xác nhận',
                                                'shipping'  => 'Đang giao',
                                                'delivered' => 'Đã giao',
                                                'cancelled' => 'Đã hủy'
                                            ];
                                            echo $statusTexts[$history['new_status']] ?? $history['new_status'];
                                            ?>
                                        </h6>
                                        <p class="text-muted small mb-1">
                                            <?php echo date('d/m/Y H:i:s', strtotime($history['created_at'])); ?>
                                        </p>
                                        <?php if (!empty($history['note'])): ?>
                                            <p class="small mb-0"><?php echo htmlspecialchars($history['note']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Chưa có lịch sử trạng thái chi tiết cho đơn hàng này.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
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
                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin giao hàng</h6>
                        <p class="mb-1"><strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name'] ?? $order['user_name']); ?></p>
                        <p class="mb-1"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone'] ?? ''); ?></p>
                        <p class="mb-1"><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></p>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin thanh toán</h6>
                        <p class="mb-1"><strong>Trạng thái đơn:</strong>
                            <?php
                            $statusText = $steps[$currentStatus]['label'] ?? $currentStatus;
                            echo htmlspecialchars($statusText);
                            ?>
                        </p>
                        <p class="mb-1"><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                        <p class="mb-1"><strong>Cập nhật cuối:</strong> <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng tiền:</span>
                            <span class="fw-bold text-success fs-5">
                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                        <small class="text-muted">
                            * Phí vận chuyển: Miễn phí
                        </small>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="order-detail.php?id=<?php echo $order_id; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-receipt me-2"></i>Xem chi tiết đơn
                        </a>
                        <a href="my-orders.php" class="btn btn-outline-success">
                            <i class="bi bi-list-ul me-2"></i>Danh sách đơn hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tracking-steps {
    gap: 10px;
}
.tracking-step {
    flex: 1;
    text-align: center;
}
.tracking-step .step-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e9ecef;
    color: #6c757d;
}
.tracking-step.step-active .step-icon {
    background: #198754;
    color: #fff;
}
.tracking-step.step-cancelled .step-icon {
    background: #dc3545;
    color: #fff;
}
.tracking-step .step-label {
    font-size: 0.85rem;
}
.tracking-step .step-current {
    font-size: 0.75rem;
    color: #198754;
}

/* Timeline reused from order-detail */
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
    background-color: #198754;
}
.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}
</style>

<?php include 'includes/footer.php'; ?>


