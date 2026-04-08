<?php
require_once '../../php-api/connectdb.php';
header('Content-Type: application/json');

// Bật hiển thị lỗi PHP để dễ debug (có thể xóa đi khi web đã chạy ổn định)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// XÓA ĐIỀU KIỆN KIỂM TRA POST
// Ưu tiên lấy ID trực tiếp từ URL (phương thức GET) hoặc dự phòng POST
$productId = $_GET['productId'] ?? $_POST['productId'] ?? null;

if (!$productId) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: Không nhận được Product ID từ URL.']);
    exit;
}

$conn = connect_db();

try {
    // DÙNG SELECT * ĐỂ TRÁNH LỖI NẾU THIẾU CỘT AvgImportPrice TRONG DATABASE
    $getQuery = "SELECT * FROM products WHERE ProductID = ?";
    $stmt = $conn->prepare($getQuery);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không tồn tại trong Database.']);
        exit;
    }

    // Kiểm tra an toàn xem cột AvgImportPrice có tồn tại không
    $avgPrice = isset($product['AvgImportPrice']) ? (float)$product['AvgImportPrice'] : 0;

    if ($avgPrice > 0) {
        // Soft Delete (Ẩn sản phẩm)
        $updateQuery = "UPDATE products SET Status = 'hidden' WHERE ProductID = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $productId);
        
        if ($updateStmt->execute()) {
            echo json_encode(["status" => "hidden", "message" => "Sản phẩm đã có lịch sử nhập hàng nên đã được ẩn đi."]);
        } else {
            throw new Exception("Không thể cập nhật trạng thái ẩn.");
        }
    } else {
        // Hard Delete (Xóa hẳn)
        $deleteQuery = "DELETE FROM products WHERE ProductID = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $productId);
        
        if ($deleteStmt->execute()) {
            if (!empty($product['ImageURL'])) {
                $imagePath = $_SERVER['DOCUMENT_ROOT'] . $product['ImageURL'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            echo json_encode(["status" => "deleted", "message" => "Đã xóa sản phẩm thành công!"]);
        } else {
            throw new Exception("Lỗi khi xóa khỏi cơ sở dữ liệu.");
        }
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
$conn->close();
?>