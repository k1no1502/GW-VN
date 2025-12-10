<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$campaign_id = (int)($_GET['id'] ?? 0);

if ($campaign_id <= 0) {
    header('Location: campaigns.php');
    exit();
}

// Get campaign details
$campaign = Database::fetch(
    "SELECT c.*, u.name as creator_name, u.email as creator_email,
            (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id) as volunteer_count,
            (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donation_count,
            DATEDIFF(c.end_date, CURDATE()) as days_remaining
     FROM campaigns c
     LEFT JOIN users u ON c.created_by = u.user_id
     WHERE c.campaign_id = ?",
    [$campaign_id]
);

if (!$campaign) {
    header('Location: campaigns.php');
    exit();
}

// Get campaign items
$items = Database::fetchAll(
    "SELECT ci.*, c.name as category_name,
            COALESCE(ci.quantity_received, 0) as quantity_received,
            CASE 
                WHEN ci.quantity_needed > 0 THEN 
                    ROUND((COALESCE(ci.quantity_received, 0) / ci.quantity_needed) * 100, 2)
                ELSE 0
            END as progress_percentage,
            CASE 
                WHEN COALESCE(ci.quantity_received, 0) >= ci.quantity_needed THEN 'Đủ'
                WHEN COALESCE(ci.quantity_received, 0) > 0 THEN 'Đang thiếu'
                ELSE 'Chưa có'
            END as status_text
     FROM campaign_items ci
     LEFT JOIN categories c ON ci.category_id = c.category_id
     WHERE ci.campaign_id = ?
     ORDER BY ci.item_id",
    [$campaign_id]
);

// Get volunteers
$volunteers = Database::fetchAll(
    "SELECT cv.*, u.name, u.email, u.avatar 
     FROM campaign_volunteers cv
     LEFT JOIN users u ON cv.user_id = u.user_id
     WHERE cv.campaign_id = ? AND cv.status = 'approved'
     ORDER BY cv.created_at DESC",
    [$campaign_id]
);

// Check if user is volunteer
$isVolunteer = false;
if (isLoggedIn()) {
    $volunteerCheck = Database::fetch(
        "SELECT * FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ?",
        [$campaign_id, $_SESSION['user_id']]
    );
    $isVolunteer = $volunteerCheck !== false;
}

// Calculate campaign progress
$totalItemsNeeded = 0;
$totalItemsReceived = 0;
foreach ($items as $item) {
    $totalItemsNeeded += $item['quantity_needed'] ?? 0;
    $totalItemsReceived += $item['quantity_received'] ?? 0;
}
$completionPercentage = $totalItemsNeeded > 0 
    ? min(100, round(($totalItemsReceived / $totalItemsNeeded) * 100)) 
    : 0;

// Status text mapping
$statusMap = [
    'draft' => ['class' => 'secondary', 'text' => 'Nháp'],
    'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt'],
    'active' => ['class' => 'success', 'text' => 'Đang hoạt động'],
    'paused' => ['class' => 'info', 'text' => 'Tạm dừng'],
    'completed' => ['class' => 'primary', 'text' => 'Hoàn thành'],
    'cancelled' => ['class' => 'danger', 'text' => 'Đã hủy']
];
$statusInfo = $statusMap[$campaign['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];

$pageTitle = $campaign['name'] ?? 'Chi tiết chiến dịch';
include 'includes/header.php';
?>

<!-- Campaign Detail -->
<div class="container py-5 mt-5">
    <!-- Back button -->
    <a href="campaigns.php" class="btn btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
    </a>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Campaign Header -->
            <div class="card shadow-sm mb-4">
                <?php if ($campaign['image']): ?>
                    <img src="uploads/campaigns/<?php echo htmlspecialchars($campaign['image']); ?>" 
                         class="card-img-top" 
                         style="height: 400px; object-fit: cover;"
                         alt="<?php echo htmlspecialchars($campaign['name']); ?>"
                         onerror="this.src='assets/images/no-image.jpg'">
                <?php else: ?>
                    <div class="card-img-top bg-gradient-primary d-flex align-items-center justify-content-center" 
                         style="height: 400px;">
                        <i class="bi bi-trophy-fill text-white" style="font-size: 8rem;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h2 class="fw-bold"><?php echo htmlspecialchars($campaign['name']); ?></h2>
                        <span class="badge bg-<?php echo $statusInfo['class']; ?> fs-6">
                            <?php echo $statusInfo['text']; ?>
                        </span>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-2">
                                <i class="bi bi-person-circle me-2"></i>
                                <strong>Người tạo:</strong> <?php echo htmlspecialchars($campaign['creator_name'] ?? 'N/A'); ?>
                            </p>
                            <p class="text-muted mb-2">
                                <i class="bi bi-calendar-event me-2"></i>
                                <strong>Thời gian:</strong> 
                                <?php echo formatDate($campaign['start_date'], 'd/m/Y'); ?> - 
                                <?php echo formatDate($campaign['end_date'], 'd/m/Y'); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-2">
                                <i class="bi bi-people-fill me-2"></i>
                                <strong>Tình nguyện viên:</strong> <?php echo number_format($campaign['volunteer_count'] ?? 0); ?> người
                            </p>
                            <p class="text-muted mb-2">
                                <i class="bi bi-clock me-2"></i>
                                <strong>Còn lại:</strong> 
                                <?php 
                                $daysRemaining = $campaign['days_remaining'] ?? 0;
                                echo max(0, $daysRemaining); 
                                ?> ngày
                            </p>
                        </div>
                    </div>
                    
                    <h5 class="fw-bold mb-3">Mô tả chiến dịch</h5>
                    <p class="text-justify"><?php echo nl2br(htmlspecialchars($campaign['description'] ?? 'Chưa có mô tả')); ?></p>
                </div>
            </div>

            <!-- Campaign Items -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-list-check me-2"></i>Vật phẩm cần thiết
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                        <p class="text-muted">Chưa có danh sách vật phẩm.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vật phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Cần thiết</th>
                                        <th>Đã nhận</th>
                                        <th>Tiến độ</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                <?php if (!empty($item['description'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo number_format($item['quantity_needed']); ?> <?php echo htmlspecialchars($item['unit'] ?? 'cái'); ?></td>
                                            <td><strong class="text-success"><?php echo number_format($item['quantity_received']); ?></strong> <?php echo htmlspecialchars($item['unit'] ?? 'cái'); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" 
                                                         role="progressbar" 
                                                         style="width: <?php echo min($item['progress_percentage'], 100); ?>%">
                                                        <?php echo round($item['progress_percentage']); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = $item['status_text'] === 'Đủ' ? 'success' : 
                                                              ($item['status_text'] === 'Đang thiếu' ? 'warning' : 'secondary');
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($item['status_text']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Volunteers List -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="bi bi-people-fill me-2"></i>Tình nguyện viên (<?php echo count($volunteers); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($volunteers)): ?>
                        <p class="text-muted">Chưa có tình nguyện viên nào.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($volunteers as $volunteer): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <?php if ($volunteer['avatar']): ?>
                                            <img src="uploads/avatars/<?php echo htmlspecialchars($volunteer['avatar']); ?>" 
                                                 class="rounded-circle me-3" 
                                                 width="50" 
                                                 height="50" 
                                                 alt="Avatar">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle fs-3 text-success me-3"></i>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($volunteer['name'] ?? 'N/A'); ?></strong>
                                            <?php if (!empty($volunteer['role'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($volunteer['role']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Campaign Progress -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Tiến độ chiến dịch</h5>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Hoàn thành</span>
                            <strong><?php echo $completionPercentage; ?>%</strong>
                        </div>
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: <?php echo $completionPercentage; ?>%">
                                <?php echo $completionPercentage; ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <h3 class="text-primary mb-0"><?php echo number_format($totalItemsReceived); ?></h3>
                            <small class="text-muted">Đã nhận</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success mb-0"><?php echo number_format($totalItemsNeeded); ?></h3>
                            <small class="text-muted">Mục tiêu</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Tham gia</h5>
                    
                    <?php if (!isLoggedIn()): ?>
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            Đăng nhập để tham gia chiến dịch
                        </div>
                        <div class="d-grid gap-2">
                            <a href="login.php?redirect=campaign-detail.php?id=<?php echo $campaign_id; ?>" 
                               class="btn btn-success">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <!-- Donate to Campaign -->
                            <a href="donate-to-campaign.php?campaign_id=<?php echo $campaign_id; ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-gift me-2"></i>Quyên góp cho chiến dịch
                            </a>
                            
                            <!-- Volunteer -->
                            <?php if ($isVolunteer): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="bi bi-check-circle me-2"></i>Đã đăng ký tình nguyện
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        class="btn btn-warning" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#volunteerModal">
                                    <i class="bi bi-person-plus me-2"></i>Đăng ký tình nguyện viên
                                </button>
                            <?php endif; ?>
                            
                            <!-- Share -->
                            <button class="btn btn-outline-secondary" onclick="shareOnSocial()">
                                <i class="bi bi-share me-2"></i>Chia sẻ chiến dịch
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Liên hệ</h6>
                    <p class="small text-muted mb-2">
                        <i class="bi bi-envelope me-2"></i>
                        <?php echo htmlspecialchars($campaign['creator_email'] ?? 'N/A'); ?>
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-telephone me-2"></i>
                        Hotline: +84 123 456 789
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Volunteer Modal -->
<div class="modal fade" id="volunteerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="volunteerForm">
                <div class="modal-header">
                    <h5 class="modal-title">Đăng ký tình nguyện viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Kỹ năng bạn có thể đóng góp</label>
                        <textarea class="form-control" name="skills" rows="2" 
                                  placeholder="VD: Có xe máy, biết dùng máy tính..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Thời gian bạn có thể tham gia</label>
                        <textarea class="form-control" name="availability" rows="2" 
                                  placeholder="VD: Thứ 7, Chủ nhật..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vai trò</label>
                        <input type="text" class="form-control" name="role" 
                               placeholder="VD: Tổ chức, Vận chuyển, Phân phát...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lời nhắn</label>
                        <textarea class="form-control" name="message" rows="3" 
                                  placeholder="Tại sao bạn muốn tham gia chiến dịch này?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-2"></i>Gửi đăng ký
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Volunteer form submit
document.getElementById('volunteerForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = this.querySelector('button[type=submit]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi...';
    
    fetch('api/register-volunteer-detail.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Đăng ký thành công!');
            setTimeout(() => location.reload(), 1500);
        } else {
            alert(data.message || 'Có lỗi xảy ra!');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Lỗi kết nối!');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Share function
function shareOnSocial() {
    const url = window.location.href;
    const title = '<?php echo addslashes($campaign['name']); ?>';
    const text = 'Tham gia chiến dịch thiện nguyện: ' + title;
    
    if (navigator.share) {
        navigator.share({ title: title, text: text, url: url });
    } else {
        const shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
