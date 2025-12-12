<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Detect history table once so we can log safely in POST handler
$historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));

// Handle status update / cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $action   = $_POST['action'];

    if ($order_id > 0) {
        try {
            $order = Database::fetch("SELECT * FROM orders WHERE order_id = ?", [$order_id]);
            if (!$order) {
                throw new Exception('Đơn hàng không tồn tại.');
            }

            $old_status = $order['status'];
            $new_status = $old_status;

            if ($action === 'update_status') {
                $new_status = $_POST['status'] ?? '';
                if (!in_array($new_status, ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'], true)) {
                    throw new Exception('Trạng thái không hợp lệ.');
                }
                if ($new_status === $old_status) {
                    throw new Exception('Vui lòng chọn trạng thái mới khác trạng thái hiện tại.');
                }

                Database::execute(
                    "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                    [$new_status, $order_id]
                );

                if ($historyTableExists) {
                    Database::execute(
                        "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at) 
                         VALUES (?, ?, ?, ?, NOW())",
                        [$order_id, $old_status, $new_status, 'Cập nhật trạng thái từ admin']
                    );
                }

                setFlashMessage('success', 'Đã cập nhật trạng thái đơn hàng.');
                logActivity($_SESSION['user_id'], 'update_order_status', "Updated order #$order_id from $old_status to $new_status");

            } elseif ($action === 'cancel_order') {
                if ($old_status === 'cancelled') {
                    throw new Exception('Đơn hàng đã bị hủy trước đó.');
                }

                $new_status = 'cancelled';
                Database::execute(
                    "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                    [$new_status, $order_id]
                );

                if ($historyTableExists) {
                    Database::execute(
                        "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at) 
                         VALUES (?, ?, ?, ?, NOW())",
                        [$order_id, $old_status, $new_status, 'Hủy đơn hàng từ admin']
                    );
                }

                setFlashMessage('success', 'Đã hủy đơn hàng.');
                logActivity($_SESSION['user_id'], 'cancel_order_admin', "Cancelled order #$order_id from admin");
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }

        header('Location: orders.php');
        exit();
    }
}

// Filters
$status   = $_GET['status']   ?? '';
$user_id  = (int)($_GET['user_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$page     = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = "1=1";
$params = [];

if ($status !== '') {
    $where   .= " AND o.status = ?";
    $params[] = $status;
}

if ($user_id > 0) {
    $where   .= " AND o.user_id = ?";
    $params[] = $user_id;
}

if ($search !== '') {
    $where      .= " AND (u.name LIKE ? OR u.email LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?)";
    $searchLike  = "%$search%";
    $params[]    = $searchLike;
    $params[]    = $searchLike;
    $params[]    = $searchLike;
    $params[]    = $searchLike;
}

// Get total count
$totalSql   = "SELECT COUNT(*) as count FROM orders o JOIN users u ON o.user_id = u.user_id WHERE $where";
$totalRow   = Database::fetch($totalSql, $params);
$totalCount = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, ceil($totalCount / $per_page));

$sql = "SELECT o.*, 
               u.name  as user_name,
               u.email as user_email,
               COUNT(oi.order_item_id)  as total_items,
               COALESCE(SUM(oi.quantity), 0) as total_quantity
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE $where
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$orders   = Database::fetchAll($sql, $params);

// Get users for filter
$users = Database::fetchAll("SELECT user_id, name, email FROM users ORDER BY name");

// Statistics for top cards
$stats = Database::fetch(
    "SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN status = 'shipping'  THEN 1 ELSE 0 END) as shipping_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue
     FROM orders"
);

$pageTitle = "Quản lý đơn hàng";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">    <style>
        .orders-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .orders-actions form {
            margin: 0;
        }
        .orders-action-btn {
            width: 48px;
            height: 38px;
            border-radius: 14px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            color: #fff;
        }
        .orders-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .orders-action-btn:hover {
            transform: translateY(-1px);
        }
        .orders-action-btn.view { background-color: #0d6efd; }
        .orders-action-btn.status { background-color: #6c757d; }
        .orders-action-btn.cancel { background-color: #dc3545; }
        .orders-action-btn i { pointer-events: none; }
        .modal-action-group {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }
        .modal-action-btn {
            width: 48px;
            height: 40px;
            border: none;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
        }
        .modal-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .modal-action-btn:hover {
            transform: translateY(-1px);
        }
        .modal-action-btn.cancel { background-color: #6c757d; }
        .modal-action-btn.submit { background-color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-cart-check me-2"></i>Quản lý đơn hàng
                    </h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Stats cards -->
                <div class="row mb-4">
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Tổng đơn</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['total_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Chờ xử lý</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['pending_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đã xác nhận</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['confirmed_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đang giao</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['shipping_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đã giao</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['delivered_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body">
                                <h6 class="mb-1">Đã hủy</h6>
                                <h3 class="mb-0"><?php echo number_format($stats['cancelled_orders'] ?? 0); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tìm kiếm</label>
                                <input type="text"
                                       name="search"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Tên, email, người nhận, SĐT...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Trạng thái</label>
                                <select name="status" class="form-select">
                                    <option value="">Tất cả</option>
                                    <option value="pending"   <?php echo $status === 'pending'   ? 'selected' : ''; ?>>Chờ xử lý</option>
                                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                                    <option value="shipping"  <?php echo $status === 'shipping'  ? 'selected' : ''; ?>>Đang giao</option>
                                    <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Đã giao</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Người dùng</label>
                                <select name="user_id" class="form-select">
                                    <option value="">Tất cả</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['user_id']; ?>"
                                            <?php echo $user_id === (int)$u['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Lọc
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Người dùng</th>
                                        <th>Người nhận</th>
                                        <th>SĐT</th>
                                        <th>Sản phẩm</th>
                                        <th>Tổng tiền</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                Không có đơn hàng nào.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                            $statusClass = 'secondary';
                                            $statusText  = $order['status'];
                                            switch ($order['status']) {
                                                case 'pending':
                                                    $statusClass = 'warning';
                                                    $statusText  = 'Chờ xử lý';
                                                    break;
                                                case 'confirmed':
                                                    $statusClass = 'info';
                                                    $statusText  = 'Đã xác nhận';
                                                    break;
                                                case 'shipping':
                                                    $statusClass = 'primary';
                                                    $statusText  = 'Đang giao';
                                                    break;
                                                case 'delivered':
                                                    $statusClass = 'success';
                                                    $statusText  = 'Đã giao';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'danger';
                                                    $statusText  = 'Đã hủy';
                                                    break;
                                            }
                                            ?>
                                            <tr>
                                                                <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['user_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['user_email']); ?></small>
                                                </td>
                                                                <td><?php echo htmlspecialchars($order['shipping_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($order['shipping_phone']); ?></td>
                                                                <td>
                                                    <?php echo (int)$order['total_items']; ?> loại
                                                    (<?php echo (int)$order['total_quantity']; ?> cái)
                                                </td>
                                                                <td>
                                                    <strong class="text-success">
                                                        <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                                                    </strong>
                                                </td>
                                                                <td>
                                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                                                                                                <td>
                                                    <div class="orders-actions">
                                                        <a href="../order-detail.php?id=<?php echo $order['order_id']; ?>"
                                                           class="orders-action-btn view" title="Xem chi tiết">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button"
                                                                class="orders-action-btn status"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#statusModal<?php echo $order['order_id']; ?>"
                                                                title="Đổi trạng thái">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <?php if ($order['status'] !== 'cancelled'): ?>
                                                            <form method="POST" class="d-inline"
                                                                  onsubmit="return confirm('Hủy đơn hàng này?');">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                <input type="hidden" name="action" value="cancel_order">
                                                                <button type="submit" class="orders-action-btn cancel" title="Hủy đơn">
                                                                    <i class="bi bi-x-circle"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Status Modal -->
                                            <div class="modal" id="statusModal<?php echo $order['order_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Cập nhật trạng thái đơn #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                <input type="hidden" name="action" value="update_status">

                                                                <div class="mb-3">
                                                                    <label class="form-label">Trạng thái hiện tại</label>
                                                                    <input type="text" class="form-control" value="<?php echo $statusText; ?>" disabled>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Trạng thái mới *</label>
                                                                    <select name="status" class="form-select" required>
                                                                        <option value="" disabled selected>-- Chọn trạng thái mới --</option>
                                                                        <option value="pending">Chờ xử lý</option>
                                                                        <option value="confirmed">Đã xác nhận</option>
                                                                        <option value="shipping">Đang giao</option>
                                                                        <option value="delivered">Đã giao</option>
                                                                        <option value="cancelled">Đã hủy</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <div class="modal-action-group">
                                                                    <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal" title="Há»§y">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                    <button type="submit" name="action" value="update_status" class="modal-action-btn submit" title="Cập nhật" onclick="this.form.submit();">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link"
                                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
