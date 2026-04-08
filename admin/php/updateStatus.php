<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connect.php';

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8");

// Đọc và kiểm tra input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['orderId']) || !isset($data['status'])) {
    die(json_encode(['success' => false, 'error' => 'Invalid input data']));
}

$orderId = $conn->real_escape_string($data['orderId']);
$newStatus = $conn->real_escape_string($data['status']);

// Kiểm tra trạng thái hợp lệ
$validStatuses = ['execute','confirmed','ship', 'success', 'fail'];
if (!in_array($newStatus, $validStatuses)) {
    die(json_encode(['success' => false, 'error' => 'Invalid status value']));
}

// Cập nhật trạng thái
$sql = "UPDATE orders SET Status = ? WHERE OrderID = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die(json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]));
}

$stmt->bind_param('ss', $newStatus, $orderId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        
        // ==========================================
        // THÊM LOGIC HOÀN KHO KHI HỦY ĐƠN (fail)
        // ==========================================
        if ($newStatus === 'fail') {
            // 1. Lấy danh sách sản phẩm và số lượng của đơn hàng bị hủy
            $sql_get_details = "SELECT ProductID, Quantity FROM orderdetails WHERE OrderID = ?";
            $stmt_get = $conn->prepare($sql_get_details);
            $stmt_get->bind_param("s", $orderId);
            $stmt_get->execute();
            $result_details = $stmt_get->get_result();

            // 2. Chuẩn bị câu lệnh cộng lại số lượng vào kho
            $sql_restore_stock = "UPDATE products SET StockQuantity = StockQuantity + ? WHERE ProductID = ?";
            $stmt_restore = $conn->prepare($sql_restore_stock);

            // 3. Duyệt qua từng sản phẩm và thực thi hoàn kho
            if ($stmt_restore) {
                while ($row = $result_details->fetch_assoc()) {
                    $restore_qty = (int)$row['Quantity'];
                    $restore_pid = (int)$row['ProductID'];
                    
                    $stmt_restore->bind_param("ii", $restore_qty, $restore_pid);
                    $stmt_restore->execute();
                }
                $stmt_restore->close();
            }
            $stmt_get->close();
        }
        // ==========================================

        echo json_encode(['success' => true]);
    } else {
        // Có thể trạng thái mới giống hệt trạng thái cũ nên affected_rows = 0
        // Vẫn trả về thành công hoặc tùy logic của bạn (ở đây giữ nguyên theo code gốc)
        echo json_encode(['success' => false, 'error' => 'No order found with ID: ' . $orderId]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>