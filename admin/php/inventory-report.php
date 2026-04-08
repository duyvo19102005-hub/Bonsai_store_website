<?php
// Bật thông báo lỗi để dễ dàng kiểm tra
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../php-api/connectdb.php';
$conn = connect_db();

if (!$conn) {
    die("Lỗi kết nối Cơ sở dữ liệu!");
}

// --- 1. XỬ LÝ CẬP NHẬT ĐỊNH MỨC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_min_stock'])) {
    $pId = (int)$_POST['product_id'];
    $minStock = (int)$_POST['min_stock'];
    $conn->query("UPDATE products SET MinStockLevel = $minStock WHERE ProductID = $pId");
    echo "<script>alert('Đã cập nhật định mức thành công!'); window.location.href='inventory-report.php';</script>";
}

// --- 2. LẤY DANH SÁCH CẢNH BÁO SẮP HẾT HÀNG ---
$sqlWarning = "SELECT ProductID, ProductName, StockQuantity, MinStockLevel 
               FROM products 
               WHERE StockQuantity <= MinStockLevel 
               ORDER BY StockQuantity ASC";
$warningResult = $conn->query($sqlWarning);

if (!$warningResult) {
    die("<div style='padding:20px; font-family:sans-serif;'>
            <h2 style='color:red;'>LỖI CHƯA CÓ CỘT ĐỊNH MỨC!</h2>
            <p>Vui lòng vào phpMyAdmin chạy lệnh SQL sau:</p>
            <pre style='background:#eee; padding:10px;'>ALTER TABLE products ADD MinStockLevel INT(11) DEFAULT 5;</pre>
         </div>");
}

// --- 3. LẤY DANH SÁCH SẢN PHẨM ---
$products = $conn->query("SELECT ProductID, ProductName FROM products ORDER BY ProductName");

// --- 4. XỬ LÝ TRA CỨU DỮ LIỆU (THUẬT TOÁN TÍNH LÙI) ---
$reportData = null;
if (isset($_GET['product_id']) && isset($_GET['check_date']) && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $pId = (int)$_GET['product_id'];
    $checkDate = $conn->real_escape_string($_GET['check_date']) . ' 23:59:59';
    $startDate = $conn->real_escape_string($_GET['start_date']) . ' 00:00:00';
    $endDate = $conn->real_escape_string($_GET['end_date']) . ' 23:59:59';

    // BƯỚC 4.1: Lấy tồn kho hiện tại làm gốc chuẩn
    $currentStockQuery = $conn->query("SELECT StockQuantity FROM products WHERE ProductID = $pId");
    $currentStock = $currentStockQuery ? (int)$currentStockQuery->fetch_assoc()['StockQuantity'] : 0;

    // BƯỚC 4.2: Tính tổng lượng Nhập / Xuất phát sinh *TỪ SAU* ngày cần tra cứu đến thời điểm hiện tại
    $sqlImportsAfter = "SELECT COALESCE(SUM(id.Quantity), 0) AS ImportsAfter FROM import_details id JOIN import_receipts ir ON id.ReceiptID = ir.ReceiptID WHERE id.ProductID = $pId AND ir.ImportDate > '$checkDate' AND ir.Status = 'completed'";
    $importsAfterResult = $conn->query($sqlImportsAfter);
    $importsAfter = $importsAfterResult ? (int)$importsAfterResult->fetch_assoc()['ImportsAfter'] : 0;

    $sqlExportsAfter = "SELECT COALESCE(SUM(od.Quantity), 0) AS ExportsAfter FROM orderdetails od JOIN orders o ON od.OrderID = o.OrderID WHERE od.ProductID = $pId AND o.DateGeneration > '$checkDate' AND o.Status IN ('success', 'execute', 'confirmed')";
    $exportsAfterResult = $conn->query($sqlExportsAfter);
    $exportsAfter = $exportsAfterResult ? (int)$exportsAfterResult->fetch_assoc()['ExportsAfter'] : 0;

    // BƯỚC 4.3: Tính Tồn Kho Tại Quá Khứ = Hiện Tại - Lượng Đã Nhập Thêm + Lượng Đã Bán Đi
    $stockAtTime = $currentStock - $importsAfter + $exportsAfter;
    
    // BƯỚC 4.4: Tính Tổng Nhập/Xuất chỉ tính riêng trong khoảng thời gian từ start_date đến end_date
    $sqlTotalImport = "SELECT COALESCE(SUM(id.Quantity), 0) AS TotalIn FROM import_details id JOIN import_receipts ir ON id.ReceiptID = ir.ReceiptID WHERE id.ProductID = $pId AND ir.ImportDate BETWEEN '$startDate' AND '$endDate' AND ir.Status = 'completed'";
    $totalInResult = $conn->query($sqlTotalImport);
    $totalIn = $totalInResult ? $totalInResult->fetch_assoc()['TotalIn'] : 0;
    
    $sqlTotalExport = "SELECT COALESCE(SUM(od.Quantity), 0) AS TotalOut FROM orderdetails od JOIN orders o ON od.OrderID = o.OrderID WHERE od.ProductID = $pId AND o.DateGeneration BETWEEN '$startDate' AND '$endDate' AND o.Status IN ('success', 'execute', 'confirmed')";
    $totalOutResult = $conn->query($sqlTotalExport);
    
    if (!$totalOutResult) {
        die("<div style='padding:20px;'><h2 style='color:red;'>LỖI BẢNG ĐƠN HÀNG: " . $conn->error . "</h2></div>");
    }
    
    $totalOut = $totalOutResult->fetch_assoc()['TotalOut'];

    $reportData = [
        'stockAtTime' => $stockAtTime,
        'totalIn' => $totalIn,
        'totalOut' => $totalOut
    ];
}

// Xử lý giữ lại giá trị trên form khi load trang (Tương thích mọi phiên bản PHP)
$selected_product = isset($_GET['product_id']) ? $_GET['product_id'] : '';
$val_check_date = isset($_GET['check_date']) ? $_GET['check_date'] : date('Y-m-d');
$val_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$val_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Báo Cáo Tồn Kho</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f6f9; }
        .container { max-width: 1200px; margin: auto; }
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); flex: 1; min-width: 300px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background: #dc3545; color: white; }
        .btn { padding: 8px 15px; cursor: pointer; border: none; border-radius: 4px; color: white; text-decoration: none; }
        .btn-primary { background: #007bff; }
        .btn-warning { background: #ffc107; color: #000; font-weight: bold; }
        .btn-secondary { background: #6c757d; display: inline-block; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .alert { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 10px; border: 1px solid #f5c6cb; }
        .result-box { background: #e2e3e5; padding: 15px; border-radius: 5px; text-align: center; font-size: 1.2em; margin-top: 10px; border: 1px solid #d6d8db; }
        .result-box strong { color: #007bff; font-size: 1.5em; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Báo Cáo & Thống Kê Tồn Kho</h2>
        <a href="../index/wareHouse.php" class="btn btn-secondary">Quay lại Kho hàng</a>
    </div>

    <div class="row">
        <div class="card" style="flex: 2;">
            <h3 style="color: #dc3545; margin-top: 0;">⚠️ Cảnh Báo Sắp Hết Hàng</h3>
            <?php if ($warningResult && $warningResult->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Mã SP</th>
                            <th>Tên Sản Phẩm</th>
                            <th>Tồn Kho Hiện Tại</th>
                            <th>Định Mức Tối Thiểu</th>
                            <th>Cập Nhật Mức Cảnh Báo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $warningResult->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $row['ProductID'] ?></td>
                            <td style="text-align: left;"><strong><?= htmlspecialchars($row['ProductName']) ?></strong></td>
                            <td><strong style="color: red; font-size: 1.2em;"><?= $row['StockQuantity'] ?></strong></td>
                            <td><?= $row['MinStockLevel'] ?></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 5px; justify-content: center;">
                                    <input type="hidden" name="product_id" value="<?= $row['ProductID'] ?>">
                                    <input type="number" name="min_stock" value="<?= $row['MinStockLevel'] ?>" min="0" style="width: 60px; padding: 5px; text-align: center;">
                                    <button type="submit" name="update_min_stock" class="btn btn-warning" style="padding: 5px 10px;">Đổi</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert" style="background: #d4edda; color: #155724; border-color: #c3e6cb;">
                    Tất cả hàng hóa đều đang ở mức an toàn (số lượng tồn > định mức).
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="flex: 1; background: #f8f9fa;">
            <h3 style="margin-top: 0;">📊 Tra Cứu Số Liệu</h3>
            
            <form method="GET" action="inventory-report.php">
                <div class="form-group">
                    <label>Chọn sản phẩm:</label>
                    <select name="product_id" required>
                        <option value="">-- Chọn một sản phẩm --</option>
                        <?php while ($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['ProductID'] ?>" <?= ($selected_product == $p['ProductID']) ? 'selected' : '' ?>>
                                <?= $p['ProductName'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <hr style="border: 0; border-top: 1px solid #ddd; margin: 15px 0;">
                
                <div class="form-group">
                    <label>1. Tra Tồn Kho tại ngày cụ thể:</label>
                    <input type="date" name="check_date" value="<?= htmlspecialchars($val_check_date) ?>" required>
                </div>
                
                <hr style="border: 0; border-top: 1px solid #ddd; margin: 15px 0;">
                
                <div class="form-group">
                    <label>2. Tổng Nhập/Xuất trong giai đoạn:</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="date" name="start_date" value="<?= htmlspecialchars($val_start_date) ?>" title="Từ ngày" required>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($val_end_date) ?>" title="Đến ngày" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; font-size: 1.1em; padding: 12px;">Tra Cứu Dữ Liệu</button>
            </form>

            <?php if ($reportData): ?>
                <div style="margin-top: 20px;">
                    <div class="result-box">
                        Tồn kho tính đến cuối ngày <b><?= date('d/m/Y', strtotime($val_check_date)) ?></b>:<br>
                        <strong><?= $reportData['stockAtTime'] ?></strong> sản phẩm
                    </div>
                    
                    <div class="result-box" style="background: #d1ecf1; border-color: #bee5eb; color: #0c5460; margin-top: 15px;">
                        Giai đoạn <?= date('d/m/Y', strtotime($val_start_date)) ?> đến <?= date('d/m/Y', strtotime($val_end_date)) ?>:<br>
                       <div style="display: flex; justify-content: space-around; margin-top: 10px;">
    <div>Tổng Nhập<br>
        <a href="#" class="view-detail-btn" data-type="import" data-pid="<?= $pId ?>" data-start="<?= $val_start_date ?>" data-end="<?= $val_end_date ?>" style="text-decoration: none;">
            <strong style="color: #28a745; cursor: pointer; text-decoration: underline;">+<?= $reportData['totalIn'] ?></strong>
        </a>
    </div>
    <div>Tổng Xuất<br>
        <a href="#" class="view-detail-btn" data-type="export" data-pid="<?= $pId ?>" data-start="<?= $val_start_date ?>" data-end="<?= $val_end_date ?>" style="text-decoration: none;">
            <strong style="color: #dc3545; cursor: pointer; text-decoration: underline;">-<?= $reportData['totalOut'] ?></strong>
        </a>
    </div>
</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    <style>
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 70vh; overflow-y: auto; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    .close-modal:hover { color: red; }
</style>

<div id="detailModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="document.getElementById('detailModal').style.display='none'">&times;</span>
        <h3 id="modalTitle" style="margin-top: 0; color: #333;">Chi Tiết</h3>
        <div id="modalBody">
            <p style="text-align: center;">Đang tải dữ liệu...</p>
        </div>
    </div>
</div>
    <script>
document.querySelectorAll('.view-detail-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Lấy thông số từ các thuộc tính data-
        const type = this.getAttribute('data-type');
        const pid = this.getAttribute('data-pid');
        const start = this.getAttribute('data-start');
        const end = this.getAttribute('data-end');
        const val = this.querySelector('strong').innerText;

        // Nếu số lượng bằng 0 thì không cần mở bảng
        if(val === '+0' || val === '-0') {
            alert('Không có dữ liệu phát sinh trong giai đoạn này để xem chi tiết!');
            return;
        }

        // Hiện modal
        document.getElementById('modalTitle').innerText = (type === 'import') ? 'Chi Tiết Phiếu Nhập' : 'Chi Tiết Đơn Hàng Xuất';
        document.getElementById('modalBody').innerHTML = '<p style="text-align:center;">Đang lấy dữ liệu...</p>';
        document.getElementById('detailModal').style.display = 'block';

        // Gọi sang file xử lý PHP để lấy bảng dữ liệu
   fetch(`get-chitiet-nhapxuat.php?type=${type}&pid=${pid}&start=${start}&end=${end}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('modalBody').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('modalBody').innerHTML = '<p style="color:red; text-align:center;">Lỗi khi tải dữ liệu!</p>';
        });
    });
});
</script>

</body>
</html>