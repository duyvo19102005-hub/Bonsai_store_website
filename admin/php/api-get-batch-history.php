<?php
require_once '../../php-api/connectdb.php';
header('Content-Type: application/json');

$productId = $_GET['id'] ?? 0;
$conn = connect_db();

// Lấy lịch sử các lô nhập (chỉ lấy phiếu đã completed)
$sql = "SELECT 
            r.ReceiptID, 
            r.ImportDate, 
            d.Quantity, 
            d.ImportPrice, 
            p.ProfitMargin 
        FROM import_details d
        JOIN import_receipts r ON d.ReceiptID = r.ReceiptID
        JOIN products p ON d.ProductID = p.ProductID
        WHERE d.ProductID = ? AND r.Status = 'completed'
        ORDER BY r.ImportDate DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
while ($row = $result->fetch_assoc()) {
    // Tính Giá bán = Giá vốn + (Giá vốn * % Lợi nhuận / 100)
    $costPrice = $row['ImportPrice'];
    $margin = $row['ProfitMargin'];
    $sellingPrice = $costPrice * (1 + ($margin / 100));

    $batches[] = [
        'receiptId' => $row['ReceiptID'],
        'date' => date('d/m/Y H:i', strtotime($row['ImportDate'])),
        'quantity' => $row['Quantity'],
        'costPrice' => $costPrice,
        'margin' => $margin,
        'sellingPrice' => $sellingPrice
    ];
}

echo json_encode($batches);
$conn->close();
?>