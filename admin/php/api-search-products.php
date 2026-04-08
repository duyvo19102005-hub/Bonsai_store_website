<?php
// Sử dụng file kết nối bạn đã gửi
require_once '../../php-api/connectdb.php'; 

header('Content-Type: application/json');

$conn = connect_db();

// Nhận từ khóa tìm kiếm từ ô input (ví dụ: ?q=cây)
$query = $_GET['q'] ?? '';

try {
    // Tìm kiếm sản phẩm chưa bị xóa/ẩn hoàn toàn
    // Cột ProductID và ProductName lấy từ cấu trúc bảng của bạn
    $sql = "SELECT ProductID, ProductName, AvgImportPrice, StockQuantity 
            FROM products 
            WHERE (ProductName LIKE ? OR ProductID LIKE ?) 
            LIMIT 15";

    $stmt = $conn->prepare($sql);
    $searchTerm = "%$query%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['ProductID'],
            'name' => $row['ProductName'],
            'avgPrice' => $row['AvgImportPrice'], // Giá nhập trung bình hiện tại
            'stock' => $row['StockQuantity']      // Số lượng tồn hiện tại
        ];
    }

    // Trả về định dạng JSON để JavaScript dễ dàng đọc được
    echo json_encode($products);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>