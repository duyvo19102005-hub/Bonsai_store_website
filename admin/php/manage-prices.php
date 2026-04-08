<?php
require_once '../../php-api/connectdb.php';
$conn = connect_db();

// Lấy từ khóa tìm kiếm
$search = $_GET['q'] ?? '';
$searchQuery = "";
if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $searchQuery = " WHERE ProductName LIKE '%$safe_search%' OR ProductID LIKE '%$safe_search%' ";
}

// Lấy danh sách sản phẩm
$sql = "SELECT ProductID, ProductName, AvgImportPrice, ProfitMargin FROM products $searchQuery ORDER BY ProductID DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Giá Bán</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f6f9; }
        .container { max-width: 1100px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background: #28a745; color: white; }
        .btn { padding: 8px 12px; cursor: pointer; border: none; border-radius: 4px; text-decoration: none; color: white; font-size: 13px; display: inline-block; }
        .btn-info { background: #17a2b8; }
        .btn-success { background: #28a745; }
        .btn-secondary { background: #6c757d; }
        .search-bar { display: flex; gap: 10px; margin: 20px 0; }
        .search-bar input { flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        
        /* CSS cho Popup Tra cứu */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: #fff; padding: 20px; border-radius: 8px; width: 800px; max-height: 80vh; overflow-y: auto; position: relative; }
        .close-btn { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; color: #aaa; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Quản Lý Tỉ Lệ Lợi Nhuận & Giá Bán</h2>
        <a href="../index/wareHouse.php" class="btn btn-secondary">Quay lại Kho hàng</a>
    </div>

    <form method="GET" action="manage-prices.php" class="search-bar">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nhập tên hoặc mã sản phẩm...">
        <button type="submit" class="btn btn-info">Tìm kiếm</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Mã SP</th>
                <th style="text-align: left;">Tên Sản Phẩm</th>
                <th>Giá Vốn TB (Đang tồn)</th>
                <th>% Lợi Nhuận</th>
                <th>Giá Bán Hiện Tại</th>
                <th>Thao Tác</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): 
                $avgPrice = $row['AvgImportPrice'];
                $margin = $row['ProfitMargin'];
                $sellingPrice = $avgPrice * (1 + ($margin / 100));
            ?>
            <tr>
                <td>#<?= $row['ProductID'] ?></td>
                <td style="text-align: left;"><strong><?= htmlspecialchars($row['ProductName']) ?></strong></td>
                <td><span style="color: #d9534f; font-weight: bold;"><?= number_format($avgPrice) ?> đ</span></td>
                
                <td>
                    <div style="display: flex; justify-content: center; align-items: center; gap: 5px;">
                        <input type="number" step="0.1" min="0" id="margin_<?= $row['ProductID'] ?>" value="<?= $margin ?>" style="width: 70px; padding: 5px; text-align: center;"> %
                        <button class="btn btn-success" style="padding: 5px 10px;" onclick="saveMargin(<?= $row['ProductID'] ?>)">Lưu</button>
                    </div>
                </td>
                
                <td><strong style="color: #28a745; font-size: 1.1em;" id="price_<?= $row['ProductID'] ?>"><?= number_format($sellingPrice) ?> đ</strong></td>
                
                <td>
                    <button class="btn btn-info" onclick="viewBatches(<?= $row['ProductID'] ?>, '<?= addslashes($row['ProductName']) ?>')">Tra cứu lô hàng</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div id="batchModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle" style="margin-top: 0;">Lịch sử lô hàng</h3>
        <table style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>Ngày Nhập</th>
                    <th>Mã Phiếu</th>
                    <th>SL Nhập</th>
                    <th>Giá Vốn Lô Này</th>
                    <th>% Lợi Nhuận (HT)</th>
                    <th>Giá Bán Lô Này</th>
                </tr>
            </thead>
            <tbody id="batchTableBody">
                </tbody>
        </table>
    </div>
</div>

<script>
    // Hàm lưu % Lợi nhuận
    function saveMargin(productId) {
        let marginVal = document.getElementById('margin_' + productId).value;
        
        fetch('api-update-profit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ productId: productId, margin: parseFloat(marginVal) })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert("Đã cập nhật % lợi nhuận thành công!");
                location.reload(); // Tải lại để tính toán lại Giá Bán Hiện Tại
            } else {
                alert("Lỗi: " + data.message);
            }
        });
    }

    // Hàm mở Popup Tra cứu lô
    function viewBatches(productId, productName) {
        document.getElementById('modalTitle').innerText = "Lịch sử lô nhập: " + productName;
        document.getElementById('batchModal').style.display = 'flex';
        let tbody = document.getElementById('batchTableBody');
        tbody.innerHTML = '<tr><td colspan="6">Đang tải dữ liệu...</td></tr>';

        fetch('api-get-batch-history.php?id=' + productId)
            .then(res => res.json())
            .then(data => {
                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6">Sản phẩm này chưa có lô hàng nào được hoàn thành.</td></tr>';
                    return;
                }

                data.forEach(b => {
    // THỰC HIỆN PHÉP TÍNH NHÂN TẠI ĐÂY
    let totalCost = b.quantity * b.costPrice;       // 10 x 200.000 = 2.000.000
    let totalSelling = b.quantity * b.sellingPrice; // 10 x 240.000 = 2.400.000

    tbody.innerHTML += `
        <tr>
            <td>${b.date}</td>
            <td>#${b.receiptId}</td>
            <td>${b.quantity}</td>
            <td style="color: #d9534f; font-weight: bold;">${Number(totalCost).toLocaleString()} đ</td>
            <td>${b.margin}%</td>
            <td style="color: #28a745; font-weight: bold;">${Number(totalSelling).toLocaleString()} đ</td>
        </tr>
    `;
});
            });
    }

    function closeModal() {
        document.getElementById('batchModal').style.display = 'none';
    }
</script>

</body>
</html>
<?php $conn->close(); ?>