<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$campaign_id = (int)($_GET['campaign_id'] ?? 0);

if ($campaign_id <= 0) {
    header('Location: campaigns.php');
    exit();
}

// Get campaign
$campaign = Database::fetch(
    "SELECT * FROM campaigns WHERE campaign_id = ? AND status = 'active'",
    [$campaign_id]
);

if (!$campaign) {
    setFlashMessage('error', 'Chiến dịch không tồn tại hoặc đã kết thúc.');
    header('Location: campaigns.php');
    exit();
}

// Get campaign items
$items = Database::fetchAll(
    "SELECT * FROM v_campaign_items_progress WHERE campaign_id = ? ORDER BY progress_percentage ASC",
    [$campaign_id]
);

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = sanitize($_POST['item_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unit = sanitize($_POST['unit'] ?? 'cái');
    $condition_status = sanitize($_POST['condition_status'] ?? 'good');
    $campaign_item_id = (int)($_POST['campaign_item_id'] ?? 0);
    // Chuẩn hóa dữ liệu tránh lỗi FK/NOT NULL
    $category_id = $category_id > 0 ? $category_id : null;
    $unit = $unit ?: 'cai';
    
    if (empty($item_name) || $quantity <= 0) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        try {
            Database::beginTransaction();
            
            // Handle image upload
            $images = [];
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $uploadDir = 'uploads/donations/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error' => $_FILES['images']['error'][$i],
                            'size' => $_FILES['images']['size'][$i]
                        ];
                        
                        $uploadResult = uploadFile($file, $uploadDir);
                        if ($uploadResult['success']) {
                            $images[] = $uploadResult['filename'];
                        }
                    }
                }
            }
            
            // Insert donation
            $sql = "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, 
                    condition_status, images, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())";
            
            Database::execute($sql, [
                $_SESSION['user_id'],
                $item_name,
                $description,
                $category_id,
                $quantity,
                $unit,
                $condition_status,
                json_encode($images)
            ]);
            
            $donation_id = Database::lastInsertId();
            
            // Link donation to campaign
            $sql = "INSERT INTO campaign_donations (campaign_id, donation_id, campaign_item_id, quantity_contributed, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            Database::execute($sql, [
                $campaign_id,
                $donation_id,
                $campaign_item_id > 0 ? $campaign_item_id : null,
                $quantity
            ]);

            // Update requested item progress if linked
            if ($campaign_item_id > 0) {
                Database::execute(
                    "UPDATE campaign_items 
                     SET quantity_received = GREATEST(quantity_received + ?, 0)
                     WHERE item_id = ? AND campaign_id = ?",
                    [$quantity, $campaign_item_id, $campaign_id]
                );
            }

            // Sync campaign current_items with sum of received quantities
            Database::execute(
                "UPDATE campaigns c
                 SET current_items = (
                     SELECT COALESCE(SUM(quantity_received), 0)
                     FROM campaign_items
                     WHERE campaign_id = c.campaign_id
                 )
                 WHERE c.campaign_id = ?",
                [$campaign_id]
            );
            
            // Add to inventory
            Database::execute(
                "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, 
                 condition_status, images, status, price_type, sale_price, is_for_sale, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', 'free', 0, TRUE, NOW())",
                [
                    $donation_id,
                    $item_name,
                    $description,
                    $category_id,
                    $quantity,
                    $unit,
                    $condition_status,
                    json_encode($images)
                ]
            );
            
            Database::commit();
            
            logActivity($_SESSION['user_id'], 'donate_to_campaign', "Donated to campaign #$campaign_id");
            
            $success = 'Quyên góp thành công! Cảm ơn bạn đã đóng góp cho chiến dịch.';
            
            // Redirect after 2 seconds
            header("refresh:2;url=campaign-detail.php?id=$campaign_id");
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Donate to campaign error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra. Vui lòng thử lại.';
        }
    }
}

$pageTitle = "Quyên góp cho chiến dịch";
include 'includes/header.php';
?>

<div class="container mt-5 pt-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Campaign Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="fw-bold mb-3">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                        <?php echo htmlspecialchars($campaign['name']); ?>
                    </h4>
                    <p class="text-muted"><?php echo htmlspecialchars(substr($campaign['description'], 0, 200)); ?>...</p>
                    <a href="campaign-detail.php?id=<?php echo $campaign_id; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>Xem chi tiết chiến dịch
                    </a>
                </div>
            </div>

            <!-- Donation Form -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-success text-white">
                    <h2 class="card-title mb-0">
                        <i class="bi bi-gift me-2"></i>Quyên góp cho chiến dịch
                    </h2>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Campaign Items Need -->
                    <?php if (!empty($items)): ?>
                        <div class="alert alert-info">
                            <h6 class="fw-bold mb-3">
                                <i class="bi bi-info-circle me-2"></i>Chiến dịch cần:
                            </h6>
                            <div class="row">
                                <?php foreach (array_slice($items, 0, 4) as $item): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                <i class="bi bi-check2-circle me-1"></i>
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                            </span>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo $item['remaining']; ?> <?php echo $item['unit']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <!-- Quick select campaign item -->
                        <?php if (!empty($items)): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Chọn nhanh vật phẩm cần quyên góp</label>
                                <select class="form-select" id="quickSelect" name="campaign_item_id">
                                    <option value="0">-- Hoặc nhập vật phẩm khác --</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['item_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                data-category="<?php echo $item['category_id']; ?>"
                                                data-unit="<?php echo $item['unit']; ?>">
                                            <?php echo htmlspecialchars($item['item_name']); ?> 
                                            (Cần: <?php echo $item['remaining']; ?> <?php echo $item['unit']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="item_name" class="form-label">Tên vật phẩm *</label>
                                <input type="text" class="form-control" id="item_name" name="item_name" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="category_id" class="form-label">Danh mục *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Chọn danh mục</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Mô tả chi tiết</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="quantity" class="form-label">Số lượng *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="unit" class="form-label">Đơn vị</label>
                                <select class="form-select" id="unit" name="unit">
                                    <option value="cái">Cái</option>
                                    <option value="bộ">Bộ</option>
                                    <option value="kg">Kg</option>
                                    <option value="cuốn">Cuốn</option>
                                    <option value="thùng">Thùng</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="condition_status" class="form-label">Tình trạng</label>
                                <select class="form-select" id="condition_status" name="condition_status">
                                    <option value="new">Mới</option>
                                    <option value="like_new">Như mới</option>
                                    <option value="good" selected>Tốt</option>
                                    <option value="fair">Khá</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="images" class="form-label">Hình ảnh (tùy chọn)</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-gift me-2"></i>Gửi quyên góp
                            </button>
                            <a href="campaign-detail.php?id=<?php echo $campaign_id; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Quay lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additionalScripts = "
<script>
// Auto-fill form when selecting quick item
document.getElementById('quickSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (this.value > 0) {
        document.getElementById('item_name').value = selectedOption.dataset.name;
        document.getElementById('category_id').value = selectedOption.dataset.category;
        document.getElementById('unit').value = selectedOption.dataset.unit;
    }
});

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
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
</script>
";

include 'includes/footer.php';
?>
