<?php
require_once '../../php-api/connectdb.php';
$conn = connect_db();

// 1. Xử lý từ khóa tìm kiếm
$search = $_GET['q'] ?? '';
$searchQuery = "";

if ($search !== '') {
    // Chống SQL Injection
    $safe_search = $conn->real_escape_string($search);
    // Tìm kiếm tương đối theo Mã phiếu hoặc Ghi chú
    $searchQuery = " WHERE ReceiptID LIKE '%$safe_search%' OR Note LIKE '%$safe_search%' ";
}

// 2. Lấy danh sách phiếu nhập (có áp dụng tìm kiếm nếu có)
$sql = "SELECT * FROM import_receipts $searchQuery ORDER BY ImportDate DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch Sử Nhập Hàng</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f6f9; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background: #007bff; color: white; }
        .badge { padding: 5px 10px; border-radius: 4px; color: white; font-weight: bold; font-size: 12px; }
        .bg-warning { background: #ffc107; color: #000; }
        .bg-success { background: #28a745; }
        .btn { padding: 8px 15px; cursor: pointer; border: none; border-radius: 4px; text-decoration: none; display: inline-block; color: white; font-size: 14px;}
        .btn-info { background: #17a2b8; }
        .btn-success { background: #28a745; }
        .btn-secondary { background: #6c757d; }
        
        /* CSS cho thanh tìm kiếm */
        .search-bar { display: flex; gap: 10px; margin: 20px 0; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd; }
        .search-bar input { flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Lịch Sử Phiếu Nhập Kho</h2>
        <div style="display: flex; gap: 10px;">
            <a href="add-import.php" class="btn btn-success">+ Lập phiếu mới</a>
            <a href="../index/wareHouse.php" class="btn btn-secondary">Quay lại Kho hàng</a>
        </div>
    </div>

    <form method="GET" action="list-imports.php" class="search-bar">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nhập mã phiếu hoặc nội dung ghi chú để tìm kiếm...">
        <button type="submit" class="btn btn-info">Tìm kiếm</button>
        <?php if ($search !== ''): ?>
            <a href="list-imports.php" class="btn btn-secondary">Xóa tìm kiếm</a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th>Mã Phiếu</th>
                <th>Ngày Nhập</th>
                <th>Tổng Tiền</th>
                <th>Ghi Chú</th>
                <th>Trạng Thái</th>
                <th>Thao Tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?= $row['ReceiptID'] ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['ImportDate'])) ?></td>
                    <td><strong style="color: #d9534f;"><?= number_format($row['TotalAmount']) ?> đ</strong></td>
                    <td><?= htmlspecialchars($row['Note']) ?></td>
                    <td>
                        <?php if ($row['Status'] == 'pending'): ?>
                            <span class="badge bg-warning">Chờ hoàn thành</span>
                        <?php else: ?>
                            <span class="badge bg-success">Đã hoàn thành</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit-import.php?id=<?= $row['ReceiptID'] ?>" class="btn btn-info" style="padding: 5px 10px; font-size: 12px;">Chi tiết / Sửa</a>
                        
                        <?php if ($row['Status'] == 'pending'): ?>
                            <button class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" onclick="completeReceipt(<?= $row['ReceiptID'] ?>)">Hoàn thành</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="padding: 20px; color: #777;">Không tìm thấy phiếu nhập nào phù hợp.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function completeReceipt(id) {
        if(!confirm("Bạn có chắc chắn muốn HOÀN THÀNH phiếu này? Số lượng sẽ được cộng vào kho và cập nhật giá vốn!")) return;

        fetch('api-complete-receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ receiptId: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert("Đã hoàn thành phiếu nhập thành công!");
                location.reload(); 
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => alert("Lỗi hệ thống: " + err));
    }
</script>

</body>
</html>
<?php $conn->close(); ?>