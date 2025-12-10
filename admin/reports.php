<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get statistics
$stats = getStatistics();

// Get donation statistics by month

$donationStats = Database::fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as count,
           SUM(quantity) as total_quantity
    FROM donations
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
", [$start_date, $end_date . ' 23:59:59']);

// Get category distribution
$categoryStats = Database::fetchAll("
    SELECT c.name, COUNT(*) as count, SUM(d.quantity) as total_quantity
    FROM donations d
    LEFT JOIN categories c ON d.category_id = c.category_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY c.category_id, c.name
    ORDER BY count DESC
    LIMIT 10
", [$start_date, $end_date . ' 23:59:59']);

// Get top donors
$topDonors = Database::fetchAll("
    SELECT u.name, u.email, COUNT(*) as donation_count, SUM(d.quantity) as total_items
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY u.user_id, u.name, u.email
    ORDER BY donation_count DESC
    LIMIT 10
", [$start_date, $end_date . ' 23:59:59']);

// Get campaign statistics
$campaignStats = Database::fetchAll("
    SELECT c.name, c.status, c.target_items, c.current_items,
           (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donations_count
    FROM campaigns c
    WHERE c.created_at BETWEEN ? AND ?
    ORDER BY c.created_at DESC
", [$start_date, $end_date . ' 23:59:59']);

// Get inventory statistics
$inventoryStats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM inventory")['count'],
    'available' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'available'")['count'],
    'sold' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'sold'")['count'],
    'free' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'free' AND status = 'available'")['count'],
    'cheap' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'cheap' AND status = 'available'")['count'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo thống kê - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-graph-up me-2"></i>Báo cáo thống kê</h1>
                </div>

                <!-- Date Range Filter -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Từ ngày</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="start_date" 
                                       value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Đến ngày</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="end_date" 
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Xem báo cáo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Overview Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Tổng người dùng</h6>
                                <h3><?php echo number_format($stats['users']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Tổng quyên góp</h6>
                                <h3><?php echo number_format($stats['donations']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Vật phẩm trong kho</h6>
                                <h3><?php echo number_format($stats['items']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6>Tổng chiến dịch</h6>
                                <h3><?php echo number_format($stats['campaigns']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Thống kê quyên góp theo tháng</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="donationChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Phân bố danh mục</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Top người quyên góp</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Người dùng</th>
                                                <th>Số lần quyên</th>
                                                <th>Tổng vật phẩm</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($topDonors)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Không có dữ liệu</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($topDonors as $donor): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($donor['name'] ?? 'Khách'); ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($donor['email'] ?? ''); ?></small>
                                                        </td>
                                                        <td><?php echo number_format($donor['donation_count']); ?></td>
                                                        <td><?php echo number_format($donor['total_items']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="mb-0">Thống kê kho hàng</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h5 class="text-primary"><?php echo number_format($inventoryStats['total']); ?></h5>
                                            <small>Tổng vật phẩm</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h5 class="text-success"><?php echo number_format($inventoryStats['available']); ?></h5>
                                            <small>Có sẵn</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h5 class="text-info"><?php echo number_format($inventoryStats['sold']); ?></h5>
                                            <small>Đã bán</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3 text-center">
                                            <h5 class="text-warning"><?php echo number_format($inventoryStats['free']); ?></h5>
                                            <small>Miễn phí</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campaign Statistics -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Thống kê chiến dịch</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tên chiến dịch</th>
                                        <th>Trạng thái</th>
                                        <th>Mục tiêu</th>
                                        <th>Đã nhận</th>
                                        <th>Tiến độ</th>
                                        <th>Số quyên góp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($campaignStats)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Không có chiến dịch nào</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($campaignStats as $campaign): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $campaign['status'] === 'active' ? 'success' : 
                                                            ($campaign['status'] === 'completed' ? 'primary' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($campaign['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($campaign['target_items']); ?></td>
                                                <td><?php echo number_format($campaign['current_items']); ?></td>
                                                <td>
                                                    <?php
                                                    $progress = $campaign['target_items'] > 0 
                                                        ? min(100, round(($campaign['current_items'] / $campaign['target_items']) * 100))
                                                        : 0;
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                                                            <?php echo $progress; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($campaign['donations_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Donation Chart
        const donationCtx = document.getElementById('donationChart').getContext('2d');
        new Chart(donationCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($s) { return "'" . $s['month'] . "'"; }, $donationStats)); ?>],
                datasets: [{
                    label: 'Số quyên góp',
                    data: [<?php echo implode(',', array_column($donationStats, 'count')); ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($s) { return "'" . htmlspecialchars($s['name'] ?? 'Khác', ENT_QUOTES) . "'"; }, $categoryStats)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($categoryStats, 'count')); ?>],
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                        '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>

