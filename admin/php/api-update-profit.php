<?php
require_once '../../php-api/connectdb.php';
header('Content-Type: application/json');

// Bật báo lỗi để dễ debug nếu cần
ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $productId = $data['productId'] ?? 0;
    $margin = $data['margin'] ?? 0;

    if (!$productId) {
        echo json_encode(['status' => 'error', 'message' => 'Thiếu ID sản phẩm']);
        exit;
    }

    $conn = connect_db();

    // BƯỚC 1: Lấy Giá Vốn (AvgImportPrice) hiện tại của sản phẩm
    $stmt_get = $conn->prepare("SELECT AvgImportPrice FROM products WHERE ProductID = ?");
    $stmt_get->bind_param("i", $productId);
    $stmt_get->execute();
    $result = $stmt_get->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $avgPrice = (float)$row['AvgImportPrice'];

        // BƯỚC 2: Tính toán Giá Bán mới
        $newPrice = $avgPrice * (1 + ($margin / 100));

        // BƯỚC 3: Cập nhật CÙNG LÚC cả % Lợi Nhuận (ProfitMargin) VÀ Giá Bán (Price)
        $stmt_update = $conn->prepare("UPDATE products SET ProfitMargin = ?, Price = ? WHERE ProductID = ?");
        $stmt_update->bind_param("ddi", $margin, $newPrice, $productId);

        if ($stmt_update->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Cập nhật thành công!',
                'new_price' => $newPrice // Gửi trả lại giá mới để JS báo cáo
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi cập nhật CSDL: ' . $conn->error]);
        }
        $stmt_update->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm trong kho!']);
    }

    $stmt_get->close();
    $conn->close();
}
?>