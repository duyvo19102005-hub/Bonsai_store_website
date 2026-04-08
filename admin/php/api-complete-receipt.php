<?php
require_once '../../php-api/connectdb.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $receiptId = $data['receiptId'] ?? null;

    if (!$receiptId) {
        echo json_encode(['status' => 'error', 'message' => 'Thiếu mã phiếu nhập']);
        exit;
    }

    $conn = connect_db();

    // 1. Kiểm tra trạng thái phiếu
    $checkSql = "SELECT Status FROM import_receipts WHERE ReceiptID = ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("i", $receiptId);
    $stmtCheck->execute();
    $statusRes = $stmtCheck->get_result()->fetch_assoc();

    if (!$statusRes || $statusRes['Status'] == 'completed') {
        echo json_encode(['status' => 'error', 'message' => 'Phiếu này không tồn tại hoặc đã hoàn thành trước đó.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // 2. Lấy danh sách sản phẩm trong phiếu này
        $detailsSql = "SELECT ProductID, Quantity, ImportPrice FROM import_details WHERE ReceiptID = ?";
        $stmtDetails = $conn->prepare($detailsSql);
        $stmtDetails->bind_param("i", $receiptId);
        $stmtDetails->execute();
        $details = $stmtDetails->get_result();

        // 3. Vòng lặp cập nhật từng sản phẩm
        $updateProductSql = "UPDATE products SET StockQuantity = ?, AvgImportPrice = ?, Status = 'appear' WHERE ProductID = ?";
        $stmtUpdateProd = $conn->prepare($updateProductSql);

        while ($item = $details->fetch_assoc()) {
            $pId = $item['ProductID'];
            $importQty = $item['Quantity'];
            $importPrice = $item['ImportPrice'];

            // Lấy Tồn kho và Giá nhập cũ
            $prodSql = "SELECT StockQuantity, AvgImportPrice FROM products WHERE ProductID = $pId";
            $prodRes = $conn->query($prodSql)->fetch_assoc();
            
            $oldQty = $prodRes['StockQuantity'];
            $oldAvgPrice = $prodRes['AvgImportPrice'];

            // Công thức tính Tồn kho mới và Giá trung bình mới
            $newQty = $oldQty + $importQty;
            $newAvgPrice = (($oldQty * $oldAvgPrice) + ($importQty * $importPrice)) / $newQty;

            // Tiến hành cập nhật
            $stmtUpdateProd->bind_param("idi", $newQty, $newAvgPrice, $pId);
            $stmtUpdateProd->execute();
        }

        // 4. Cập nhật trạng thái phiếu thành "completed"
        $conn->query("UPDATE import_receipts SET Status = 'completed' WHERE ReceiptID = $receiptId");

        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    $conn->close();
}
?>