<?php
require_once '../../php-api/connectdb.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $receiptId = $data['receiptId'] ?? null;
    $note = $data['note'] ?? '';
    $items = $data['items'] ?? [];

    if (!$receiptId || empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    $conn = connect_db();

    // 1. Kiểm tra trạng thái (Chỉ cho phép sửa phiếu 'pending')
    $checkSql = "SELECT Status FROM import_receipts WHERE ReceiptID = ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("i", $receiptId);
    $stmtCheck->execute();
    $statusRes = $stmtCheck->get_result()->fetch_assoc();

    if (!$statusRes || $statusRes['Status'] == 'completed') {
        echo json_encode(['status' => 'error', 'message' => 'Phiếu này đã hoàn thành, không thể sửa!']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // 2. Cập nhật bảng import_receipts (Ghi chú)
        $updateReceipt = $conn->prepare("UPDATE import_receipts SET Note = ? WHERE ReceiptID = ?");
        $updateReceipt->bind_param("si", $note, $receiptId);
        $updateReceipt->execute();

        // 3. Xóa các chi tiết cũ trong import_details
        $conn->query("DELETE FROM import_details WHERE ReceiptID = $receiptId");

        // 4. Thêm lại các chi tiết mới được gửi lên
        $totalAmount = 0;
        $stmtDetail = $conn->prepare("INSERT INTO import_details (ReceiptID, ProductID, Quantity, ImportPrice) VALUES (?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $stmtDetail->bind_param("iiid", $receiptId, $item['productId'], $item['quantity'], $item['importPrice']);
            $stmtDetail->execute();
            $totalAmount += ($item['quantity'] * $item['importPrice']);
        }

        // 5. Cập nhật lại tổng tiền
        $conn->query("UPDATE import_receipts SET TotalAmount = $totalAmount WHERE ReceiptID = $receiptId");

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Cập nhật phiếu thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    $conn->close();
}
?>