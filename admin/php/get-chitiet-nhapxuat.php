<?php
// 1. Bật báo lỗi để kiểm soát dữ liệu
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../php-api/connectdb.php';
$conn = connect_db();

if (!$conn) {
    die("Lỗi: Không thể kết nối cơ sở dữ liệu!");
}

// 2. Lấy tham số và kiểm tra
$type = isset($_GET['type']) ? $_GET['type'] : '';
$pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$start = isset($_GET['start']) ? $_GET['start'] . ' 00:00:00' : '';
$end = isset($_GET['end']) ? $_GET['end'] . ' 23:59:59' : '';

if ($pid <= 0) {
    die("Lỗi: ID sản phẩm không hợp lệ!");
}

if ($type === 'import') {
    // --- PHẦN CHI TIẾT NHẬP HÀNG ---
    $sql = "SELECT ir.ReceiptID, ir.ImportDate, id.Quantity, id.ImportPrice 
            FROM import_details id 
            JOIN import_receipts ir ON id.ReceiptID = ir.ReceiptID 
            WHERE id.ProductID = $pid AND ir.ImportDate BETWEEN '$start' AND '$end' AND ir.Status = 'completed' 
            ORDER BY ir.ImportDate DESC";
    
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='width:100%; border-collapse: collapse; text-align:center; color: black;'>";
        echo "<tr style='background:#eeeeee;'><th>Mã Phiếu</th><th>Ngày Nhập</th><th>Số Lượng</th><th>Giá Nhập</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>#" . $row['ReceiptID'] . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['ImportDate'])) . "</td>";
            echo "<td><strong style='color:green;'>+" . $row['Quantity'] . "</strong></td>";
            echo "<td>" . number_format($row['ImportPrice'], 0, ',', '.') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='text-align:center;'>Không tìm thấy phiếu nhập nào.</p>";
    }

} elseif ($type === 'export') {
    // --- PHẦN CHI TIẾT XUẤT HÀNG (ĐÃ SỬA TÊN CỘT UNITPRICE) ---
    $sql = "SELECT o.OrderID, o.DateGeneration, od.Quantity, od.UnitPrice 
            FROM orderdetails od 
            JOIN orders o ON od.OrderID = o.OrderID 
            WHERE od.ProductID = $pid 
            AND o.DateGeneration BETWEEN '$start' AND '$end' 
            AND o.Status IN ('success', 'execute', 'confirmed') 
            ORDER BY o.DateGeneration DESC";

    $result = $conn->query($sql);

    // Kiểm tra lỗi SQL
    if (!$result) {
        die("Lỗi SQL chi tiết: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        echo "<table border='1' style='width:100%; border-collapse: collapse; text-align:center; color: black;'>";
        echo "<tr style='background:#eeeeee;'>
                <th style='padding:8px;'>Mã Đơn Hàng</th>
                <th style='padding:8px;'>Ngày Đặt Hàng</th>
                <th style='padding:8px;'>Số Lượng</th>
                <th style='padding:8px;'>Giá (VNĐ)</th>
              </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding:8px; border: 1px solid #ddd;'>" . $row['OrderID'] . "</td>";
            echo "<td style='padding:8px; border: 1px solid #ddd;'>" . date('d/m/Y', strtotime($row['DateGeneration'])) . "</td>";
            echo "<td style='padding:8px; border: 1px solid #ddd;'><strong style='color:red;'>-" . $row['Quantity'] . "</strong></td>";
            // Sửa hiển thị theo cột UnitPrice
            echo "<td style='padding:8px; border: 1px solid #ddd;'>" . number_format($row['UnitPrice'], 0, ',', '.') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='text-align:center; color: red; padding: 10px;'>
                Rất tiếc! Không tìm thấy đơn hàng nào của sản phẩm này.
              </p>";
    }
} else {
    echo "Yêu cầu không hợp lệ (Type: $type)";
}

$conn->close();
?>