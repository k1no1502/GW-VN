<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng đăng nhập để thực hiện thao tác này.'
    ]);
    exit();
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = (int)($input['order_id'] ?? 0);
    
    if ($order_id <= 0) {
        throw new Exception('Dữ liệu không hợp lệ.');
    }
    
    // Check if order exists and belongs to user
    $order = Database::fetch(
        "SELECT * FROM orders WHERE order_id = ? AND user_id = ?",
        [$order_id, $_SESSION['user_id']]
    );
    
    if (!$order) {
        throw new Exception('Đơn hàng không tồn tại.');
    }
    
    // Check if order can be cancelled
    if ($order['status'] !== 'pending') {
        throw new Exception('Chỉ có thể hủy đơn hàng đang chờ xử lý.');
    }
    
    Database::beginTransaction();
    
    // Get order items to restore inventory
    $orderItems = Database::fetchAll(
        "SELECT item_id, quantity FROM order_items WHERE order_id = ?",
        [$order_id]
    );
    
    // Restore inventory
    foreach ($orderItems as $item) {
        Database::execute(
            "UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?",
            [$item['quantity'], $item['item_id']]
        );
    }
    
    // Update order status
    Database::execute(
        "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?",
        [$order_id]
    );
    
    // Add to status history
    Database::execute(
        "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at) 
         VALUES (?, ?, 'cancelled', 'Khách hàng hủy đơn hàng', NOW())",
        [$order_id, $order['status']]
    );
    
    // Create notification
    Database::execute(
        "INSERT INTO notifications (user_id, title, message, type, created_at) 
         VALUES (?, 'Đơn hàng đã hủy', 'Đơn hàng #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " đã được hủy thành công.', 'info', NOW())",
        [$_SESSION['user_id']]
    );
    
    Database::commit();
    
    // Log activity
    logActivity($_SESSION['user_id'], 'cancel_order', "Cancelled order #$order_id");
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã hủy đơn hàng thành công!'
    ]);
    
} catch (Exception $e) {
    Database::rollback();
    error_log("Cancel order error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
