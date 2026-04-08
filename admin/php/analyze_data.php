<?php
header('Content-Type: application/json');
include 'connect.php';

// MỞ KHÓA CHO LỆNH JOIN (Sửa lỗi MAX_JOIN_SIZE)
$conn->set_charset("utf8mb4");
$conn->query("SET SQL_BIG_SELECTS=1");

$start_date = isset($_POST['start_date']) ? $_POST['start_date'] . ' 00:00:00' : date('Y-m-01') . ' 00:00:00';
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] . ' 23:59:59' : date('Y-m-d') . ' 23:59:59';

// DANH SÁCH TRẠNG THÁI ĐƯỢC TÍNH 
// Lưu ý: Nếu bạn CHỈ muốn tính đơn "Đã xác nhận", hãy đổi thành: $valid_status = "('confirmed')";
// Hiện tại đang tính cả đơn hoàn thành và đang giao.
$valid_status = "('success', 'ship', 'confirmed')";

// 1. TRUY VẤN KHÁCH HÀNG (Thêm GROUP_CONCAT để lấy danh sách mã đơn)
$customer_query = "SELECT 
    u.Username, u.FullName AS customer_name,
    COUNT(DISTINCT o.OrderID) AS order_count,
    SUM(o.TotalAmount) AS total_amount,
    MAX(o.DateGeneration) AS latest_order_date,
    GROUP_CONCAT(DISTINCT o.OrderID) AS order_ids
FROM orders o
JOIN users u ON o.Username = u.Username 
WHERE o.DateGeneration >= ? AND o.DateGeneration <= ?
    AND o.Status IN $valid_status
GROUP BY u.Username, u.FullName
ORDER BY total_amount DESC LIMIT 5";

$stmt = $conn->prepare($customer_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Xử lý chuỗi order_ids thành mảng order_links cho file JS
foreach ($customers as &$customer) {
    $customer['order_links'] = [];
    if (!empty($customer['order_ids'])) {
        $ids = explode(',', $customer['order_ids']);
        foreach ($ids as $id) {
            $customer['order_links'][] = ['id' => $id];
        }
    }
    unset($customer['order_ids']); // Xóa đi cho file JSON gọn gàng
}

// 2. TRUY VẤN MẶT HÀNG BÁN CHẠY (Thêm GROUP_CONCAT)
$product_query = "SELECT 
    p.ProductName AS product_name,
    SUM(od.Quantity) AS quantity_sold,
    SUM(od.TotalPrice) AS total_amount,
    GROUP_CONCAT(DISTINCT o.OrderID) AS order_ids
FROM products p
JOIN orderdetails od ON p.ProductID = od.ProductID
JOIN orders o ON od.OrderID = o.OrderID
WHERE o.DateGeneration >= ? AND o.DateGeneration <= ?
    AND o.Status IN $valid_status
GROUP BY p.ProductID, p.ProductName
ORDER BY quantity_sold DESC LIMIT 5";

$stmt = $conn->prepare($product_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Xử lý chuỗi order_ids thành mảng order_links cho mặt hàng
foreach ($products as &$product) {
    $product['order_links'] = [];
    if (!empty($product['order_ids'])) {
        $ids = explode(',', $product['order_ids']);
        foreach ($ids as $id) {
            $product['order_links'][] = ['id' => $id];
        }
    }
    unset($product['order_ids']);
}

// 3. TÍNH TỔNG DOANH THU
$total_rev_sql = "SELECT SUM(TotalAmount) AS total FROM orders 
                  WHERE DateGeneration >= ? AND DateGeneration <= ? AND Status IN $valid_status";
$stmt = $conn->prepare($total_rev_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

echo json_encode([
    'success' => true,
    'customers' => $customers,
    'products' => $products,
    'total_revenue' => (float)$total_revenue,
    'best_selling' => !empty($products) ? $products[0]['product_name'] : "Chưa có dữ liệu",
    'worst_selling' => !empty($products) ? end($products)['product_name'] : "Chưa có dữ liệu"
], JSON_UNESCAPED_UNICODE);