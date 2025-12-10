<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    $item_id = (int)($_GET['item_id'] ?? 0);
    
    if ($item_id <= 0) {
        throw new Exception('Item ID không hợp lệ.');
    }
    
    // Get item with available quantity (dùng tồn kho trực tiếp)
    $item = Database::fetch(
        "SELECT i.*, i.quantity as available_quantity
         FROM inventory i 
         WHERE i.item_id = ? AND i.status = 'available' AND i.is_for_sale = TRUE",
        [$item_id]
    );
    
    if (!$item) {
        throw new Exception('Item không tồn tại hoặc không có sẵn.');
    }
    
    echo json_encode([
        'success' => true,
        'available_quantity' => max(0, $item['available_quantity']),
        'unit' => $item['unit'] ?? 'Cái',
        'item_name' => $item['name']
    ]);
    
} catch (Exception $e) {
    error_log("Get item quantity error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
