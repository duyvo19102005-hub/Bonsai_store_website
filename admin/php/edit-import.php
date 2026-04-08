<?php
// Đảm bảo đường dẫn này giống hệt trong file add-import.php của bạn
require_once '../../php-api/connectdb.php';
$conn = connect_db();

$receiptId = $_GET['id'] ?? 0;

// Lấy thông tin phiếu chính
$sqlReceipt = "SELECT * FROM import_receipts WHERE ReceiptID = $receiptId";
$receiptResult = $conn->query($sqlReceipt);
$receipt = $receiptResult->fetch_assoc();

if (!$receipt || $receipt['Status'] == 'completed') {
    die("<div style='padding:20px; font-family:sans-serif;'><h3>Phiếu nhập không tồn tại hoặc đã được hoàn thành (không thể sửa).</h3><a href='list-imports.php'>Quay lại danh sách</a></div>");
}

// Lấy danh sách sản phẩm trong phiếu
$sqlDetails = "SELECT d.*, p.ProductName 
               FROM import_details d 
               JOIN products p ON d.ProductID = p.ProductID 
               WHERE d.ReceiptID = $receiptId";
$details = $conn->query($sqlDetails);

$itemsArray = [];
while ($row = $details->fetch_assoc()) {
    $itemsArray[] = [
        'productId' => $row['ProductID'],
        'name' => $row['ProductName'],
        'quantity' => $row['Quantity'],
        'importPrice' => $row['ImportPrice']
    ];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa Phiếu Nhập Hàng</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f6f9; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .search-box { position: relative; margin-bottom: 20px; }
        #searchInput { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        #searchResults { position: absolute; width: 100%; background: #fff; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .search-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .search-item:hover { background: #f0f0f0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #17a2b8; color: white; }
        .btn { padding: 10px 15px; cursor: pointer; background: #17a2b8; color: white; border: none; border-radius: 4px; text-decoration: none; display: inline-block;}
        .btn-danger { background: #dc3545; padding: 5px 10px; }
        .btn-secondary { background: #6c757d; }
        .total-row { font-weight: bold; font-size: 1.2em; text-align: right; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;">Sửa Phiếu Nhập #<?= $receiptId ?></h2>
        <a href="list-imports.php" class="btn btn-secondary">Quay lại Danh sách</a>
    </div>
    
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Tìm thêm sản phẩm vào phiếu..." onkeyup="searchProduct()">
        <div id="searchResults"></div>
    </div>

    <table id="importTable">
        <thead>
            <tr>
                <th>Tên sản phẩm</th>
                <th>Số lượng nhập</th>
                <th>Giá vốn (đ/SP)</th>
                <th>Thành tiền</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody id="tableBody"></tbody>
    </table>

    <div class="total-row">Tổng cộng: <span id="totalAmount">0</span> VNĐ</div>

    <div>
        <textarea id="noteInput" placeholder="Ghi chú phiếu nhập..." style="width: 100%; height: 80px; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;"><?= htmlspecialchars($receipt['Note']) ?></textarea>
    </div>

    <div style="margin-top: 20px; text-align: right;">
        <button class="btn" onclick="updateReceipt()" style="background: #28a745;">Cập Nhật Phiếu Nhập</button>
    </div>
</div>

<script>
    // Đổ dữ liệu cũ từ PHP vào JavaScript
    let selectedItems = <?= json_encode($itemsArray) ?>;
    let currentReceiptId = <?= $receiptId ?>;

    window.onload = function() {
        renderTable(); // Hiển thị sản phẩm cũ ngay khi mở trang
    };

    function searchProduct() {
        let q = document.getElementById('searchInput').value;
        let resultDiv = document.getElementById('searchResults');
        if (q.length < 2) { resultDiv.style.display = 'none'; return; }

        fetch('api-search-products.php?q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                resultDiv.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(p => {
                        let div = document.createElement('div');
                        div.className = 'search-item';
                        div.innerHTML = `<strong>${p.name}</strong> (Tồn: ${p.stock})`;
                        div.onclick = () => addProduct(p);
                        resultDiv.appendChild(div);
                    });
                    resultDiv.style.display = 'block';
                } else {
                    resultDiv.style.display = 'none';
                }
            });
    }

    function addProduct(product) {
        let exists = selectedItems.find(item => item.productId == product.id);
        if (exists) { alert("Sản phẩm đã có trong phiếu!"); return; }
        selectedItems.push({ productId: product.id, name: product.name, quantity: 1, importPrice: parseFloat(product.avgPrice) || 0 });
        document.getElementById('searchInput').value = '';
        document.getElementById('searchResults').style.display = 'none';
        renderTable();
    }

    function renderTable() {
        let tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        let total = 0;
        selectedItems.forEach((item, index) => {
            let subTotal = item.quantity * item.importPrice;
            total += subTotal;
            tbody.innerHTML += `
                <tr>
                    <td>${item.name}</td>
                    <td><input type="number" min="1" value="${item.quantity}" onchange="updateItem(${index}, 'quantity', this.value)" style="width: 80px; padding: 5px;"></td>
                    <td><input type="number" min="0" value="${item.importPrice}" onchange="updateItem(${index}, 'importPrice', this.value)" style="width: 120px; padding: 5px;"></td>
                    <td>${subTotal.toLocaleString()}</td>
                    <td><button class="btn btn-danger" onclick="removeItem(${index})">Xóa</button></td>
                </tr>
            `;
        });
        document.getElementById('totalAmount').innerText = total.toLocaleString();
    }

    function updateItem(index, field, value) {
        selectedItems[index][field] = parseFloat(value) || 0;
        renderTable();
    }

    function removeItem(index) {
        selectedItems.splice(index, 1);
        renderTable();
    }

    function updateReceipt() {
        if (selectedItems.length === 0) { alert("Phiếu phải có ít nhất 1 sản phẩm!"); return; }
        let note = document.getElementById('noteInput').value;

        fetch('api-update-receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ receiptId: currentReceiptId, note: note, items: selectedItems })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                window.location.href = 'list-imports.php'; // Lưu xong tự động quay lại danh sách
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => alert("Lỗi hệ thống: " + err));
    }
</script>
</body>
</html>