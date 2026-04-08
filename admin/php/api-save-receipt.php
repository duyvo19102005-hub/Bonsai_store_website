<?php
require_once '../../php-api/connectdb.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $note = $data['note'] ?? '';
    $items = $data['items'] ?? []; // Mảng chứa các sản phẩm: productId, quantity, importPrice

    if (empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Phiếu nhập phải có ít nhất 1 sản phẩm']);
        exit;
    }

    $conn = connect_db();
    $conn->begin_transaction();

    try {
        // 1. Lưu thông tin chung vào import_receipts
        $stmt = $conn->prepare("INSERT INTO import_receipts (Note, Status) VALUES (?, 'pending')");
        $stmt->bind_param("s", $note);
        $stmt->execute();
        $receiptId = $conn->insert_id;
        $totalAmount = 0;

        // 2. Lưu từng sản phẩm vào import_details
        $stmtDetail = $conn->prepare("INSERT INTO import_details (ReceiptID, ProductID, Quantity, ImportPrice) VALUES (?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $stmtDetail->bind_param("iiid", $receiptId, $item['productId'], $item['quantity'], $item['importPrice']);
            $stmtDetail->execute();
            $totalAmount += ($item['quantity'] * $item['importPrice']);
        }

        // 3. Cập nhật lại tổng tiền của phiếu nhập
        $updateTotal = $conn->prepare("UPDATE import_receipts SET TotalAmount = ? WHERE ReceiptID = ?");
        $updateTotal->bind_param("di", $totalAmount, $receiptId);
        $updateTotal->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Tạo phiếu nhập thành công!', 'receiptId' => $receiptId]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }

    $conn->close();
}
?>