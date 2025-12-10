<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $donation_id = (int)($_POST['donation_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($donation_id > 0) {
        try {
            if ($action === 'approve') {
                Database::beginTransaction();

                // Get donation details up front so we can validate status & reuse data
                $donation = Database::fetch("SELECT * FROM donations WHERE donation_id = ?", [$donation_id]);
                if (!$donation) {
                    throw new Exception('Donation not found.');
                }

                // Only update status when needed to avoid duplicate inventory rows on re-approval
                if ($donation['status'] !== 'approved') {
                    Database::execute(
                        "UPDATE donations SET status = 'approved', updated_at = NOW() WHERE donation_id = ?",
                        [$donation_id]
                    );
                }

                // Add to inventory once per donation
                $existingInventory = Database::fetch(
                    "SELECT item_id FROM inventory WHERE donation_id = ? LIMIT 1",
                    [$donation_id]
                );

                if (!$existingInventory) {
                    Database::execute(
                        "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, 
                         condition_status, estimated_value, actual_value, images, status, price_type, sale_price, is_for_sale, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', 'free', 0, TRUE, NOW())",
                        [
                            $donation_id,
                            $donation['item_name'],
                            $donation['description'],
                            $donation['category_id'],
                            $donation['quantity'],
                            $donation['unit'],
                            $donation['condition_status'],
                            $donation['estimated_value'],
                            $donation['estimated_value'],
                            $donation['images']
                        ]
                    );
                }

                Database::commit();
                $message = $existingInventory
                    ? 'Donation was already approved earlier. Inventory kept as-is.'
                    : 'Approved donation and added item to inventory.';
                setFlashMessage('success', $message);
                logActivity($_SESSION['user_id'], 'approve_donation', "Approved donation #$donation_id");

            } elseif ($action === 'reject') {
                Database::execute(
                    "UPDATE donations SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE donation_id = ?",
                    [$_POST['reject_reason'] ?? 'Không đạt yêu cầu', $donation_id]
                );
                setFlashMessage('success', 'Đã từ chối quyên góp.');
                logActivity($_SESSION['user_id'], 'reject_donation', "Rejected donation #$donation_id");
            }
        } catch (Exception $e) {
            Database::rollback();
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: donations.php');
    exit();
}

// Get donations
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($status !== '') {
    $where .= " AND d.status = ?";
    $params[] = $status;
}

$totalSql = "SELECT COUNT(*) as count FROM donations d WHERE $where";
$totalDonations = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalDonations / $per_page);

$sql = "SELECT d.*, u.name as donor_name, u.email as donor_email, c.name as category_name
        FROM donations d
        LEFT JOIN users u ON d.user_id = u.user_id
        LEFT JOIN categories c ON d.category_id = c.category_id
        WHERE $where
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$donations = Database::fetchAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý quyên góp - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .donation-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .donation-actions form {
            margin: 0;
        }
        .donation-action-btn {
            width: 52px;
            height: 40px;
            border-radius: 14px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            color: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .donation-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.25);
        }
        .donation-action-btn:hover {
            transform: translateY(-1px);
        }
        .donation-action-btn.view {
            background-color: #19c1ff;
            color: #00354d;
        }
        .donation-action-btn.approve {
            background-color: #198754;
        }
        .donation-action-btn.reject {
            background-color: #dc3545;
        }
        .donation-action-btn i {
            pointer-events: none;
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
            font-size: 1.2rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .modal-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .modal-action-btn:hover {
            transform: translateY(-1px);
        }
        .modal-action-btn.cancel {
            background-color: #6c757d;
        }
        .modal-action-btn.confirm-reject {
            background-color: #dc3545;
        }

        .admin-donations > .d-flex:first-child {
            padding-top: 1rem;
        }
        .donations-tabs {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content admin-donations">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-heart-fill me-2"></i>Quản lý quyên góp</h1>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Filter tabs -->
                <ul class="nav nav-tabs mb-4 donations-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status === '' ? 'active' : ''; ?>" href="donations.php">
                            Tất cả (<?php echo $totalDonations; ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status === 'pending' ? 'active' : ''; ?>" href="donations.php?status=pending">
                            Chờ duyệt
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status === 'approved' ? 'active' : ''; ?>" href="donations.php?status=approved">
                            Đã duyệt
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status === 'rejected' ? 'active' : ''; ?>" href="donations.php?status=rejected">
                            Từ chối
                        </a>
                    </li>
                </ul>

                <!-- Donations table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Vật phẩm</th>
                                        <th>Người quyên góp</th>
                                        <th>Danh mục</th>
                                        <th>Số lượng</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($donations)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có quyên góp nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($donations as $donation): ?>
                                            <tr>
                                                <td><?php echo $donation['donation_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($donation['item_name']); ?></strong>
                                                    <?php if ($donation['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($donation['description'], 0, 50)); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($donation['donor_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($donation['donor_email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($donation['category_name'] ?? 'Không xác định'); ?></td>
                                                <td><?php echo $donation['quantity']; ?> <?php echo $donation['unit']; ?></td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt'],
                                                        'approved' => ['class' => 'success', 'text' => 'Đã duyệt'],
                                                        'rejected' => ['class' => 'danger', 'text' => 'Từ chối'],
                                                        'cancelled' => ['class' => 'secondary', 'text' => 'Đã hủy']
                                                    ];
                                                    $status = $statusMap[$donation['status']] ?? ['class' => 'secondary', 'text' => 'Không xác định'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $status['class']; ?>">
                                                        <?php echo $status['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($donation['created_at']); ?></td>
                                                <td>
                                                    <div class="donation-actions">
                                                        <button type="button" 
                                                                class="donation-action-btn view" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewModal<?php echo $donation['donation_id']; ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($donation['status'] === 'pending'): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Duyệt quyên góp này?');">
                                                                <input type="hidden" name="donation_id" value="<?php echo $donation['donation_id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="donation-action-btn approve">
                                                                    <i class="bi bi-check"></i>
                                                                </button>
                                                            </form>
                                                            <button type="button" 
                                                                    class="donation-action-btn reject" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#rejectModal<?php echo $donation['donation_id']; ?>">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- View Modal -->
                                            <div class="modal" id="viewModal<?php echo $donation['donation_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Chi tiết quyên góp #<?php echo $donation['donation_id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Vật phẩm:</strong> <?php echo htmlspecialchars($donation['item_name']); ?></p>
                                                                    <p><strong>Danh mục:</strong> <?php echo htmlspecialchars($donation['category_name'] ?? 'N/A'); ?></p>
                                                                    <p><strong>Số lượng:</strong> <?php echo $donation['quantity']; ?> <?php echo $donation['unit']; ?></p>
                                                                    <p><strong>Tình trạng:</strong> <?php echo htmlspecialchars($donation['condition_status']); ?></p>
                                                                    <p><strong>Giá trị ước tính:</strong> <?php echo formatCurrency($donation['estimated_value']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Người quyên góp:</strong> <?php echo htmlspecialchars($donation['donor_name']); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($donation['donor_email']); ?></p>
                                                                    <p><strong>SĐT:</strong> <?php echo htmlspecialchars($donation['contact_phone'] ?? 'N/A'); ?></p>
                                                                    <p><strong>Địa chỉ nhận:</strong> <?php echo htmlspecialchars($donation['pickup_address'] ?? 'N/A'); ?></p>
                                                                </div>
                                                            </div>
                                                            <p><strong>Mô tả:</strong></p>
                                                            <p><?php echo nl2br(htmlspecialchars($donation['description'] ?? 'Không có mô tả')); ?></p>
                                                            
                                                            <?php
                                                            $images = json_decode($donation['images'] ?? '[]', true);
                                                            if (!empty($images)):
                                                            ?>
                                                                <p><strong>Hình ảnh:</strong></p>
                                                                <div class="row">
                                                                    <?php foreach ($images as $img): ?>
                                                                        <div class="col-md-3 mb-2">
                                                                            <img src="../uploads/donations/<?php echo $img; ?>" 
                                                                                 class="img-fluid rounded" 
                                                                                 alt="Image">
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Reject Modal -->
                                            <div class="modal" id="rejectModal<?php echo $donation['donation_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Từ chối quyên góp</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="donation_id" value="<?php echo $donation['donation_id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Lý do từ chối:</label>
                                                                    <textarea class="form-control" name="reject_reason" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer gap-2">
                                                                <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal">
                                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                                </button>
                                                                <button type="submit" class="modal-action-btn confirm-reject">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </div>
                                                        </form>
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
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>">
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



