<?php
require_once 'connect.php'; // Đảm bảo đường dẫn này trỏ đúng tới file kết nối DB của bạn
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Nếu không có từ khóa, trả về mảng rỗng
if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$safe_q = $conn->real_escape_string($q);

// Tìm tối đa 5 sản phẩm khớp với từ khóa (Chỉ lấy sản phẩm không bị ẩn)
$sql = "SELECT ProductID, ProductName, Price, ImageURL FROM products 
        WHERE ProductName LIKE '%$safe_q%' AND Status != 'hidden' 
        LIMIT 5";

$result = $conn->query($sql);
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode($products);
$conn->close();
?>