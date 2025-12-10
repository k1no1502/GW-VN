<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng đăng nhập để cập nhật giỏ hàng.'
    ]);
    exit();
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $cart_id = (int)($input['cart_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 1);
    
    if ($cart_id <= 0 || $quantity <= 0) {
        throw new Exception('Dữ liệu không hợp lệ.');
    }
    
    // Check if cart item exists and belongs to user
    $cartItem = Database::fetch(
        "SELECT c.*, i.quantity as inventory_quantity 
         FROM cart c 
         JOIN inventory i ON c.item_id = i.item_id 
         WHERE c.cart_id = ? AND c.user_id = ?",
        [$cart_id, $_SESSION['user_id']]
    );
    
    if (!$cartItem) {
        throw new Exception('Sản phẩm không tồn tại trong giỏ hàng.');
    }
    
    // Check available quantity theo tồn kho đơn giản
    $availableQuantityRow = Database::fetch(
        "SELECT quantity AS available FROM inventory WHERE item_id = ?",
        [$cartItem['item_id']]
    );
    $availableQuantity = (int)($availableQuantityRow['available'] ?? 0);
    
    if ($quantity > $availableQuantity) {
        throw new Exception('Số lượng vượt quá số có sẵn (' . $availableQuantity . ').');
    }
    
    // Update quantity
    Database::execute(
        "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?",
        [$quantity, $cart_id]
    );
    
    // Log activity
    logActivity($_SESSION['user_id'], 'update_cart', "Updated cart item #$cart_id quantity to $quantity");
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật số lượng thành công!'
    ]);
    
} catch (Exception $e) {
    error_log("Update cart quantity error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
