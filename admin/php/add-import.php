<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lập Phiếu Nhập Hàng</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .search-box { position: relative; margin-bottom: 20px; }
        #searchInput { width: 100%; padding: 10px; box-sizing: border-box; }
        #searchResults { position: absolute; width: 100%; background: #fff; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; display: none; z-index: 1000; }
        .search-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .search-item:hover { background: #f0f0f0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .btn { padding: 10px 15px; cursor: pointer; background: #28a745; color: white; border: none; }
        .btn-danger { background: #dc3545; }
        .total-row { font-weight: bold; font-size: 1.2em; text-align: right; }
    </style>
</head>
<body>

<div class="container">
    <h2>Lập Phiếu Nhập Hàng Mới</h2>
    
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Gõ tên hoặc mã sản phẩm để tìm kiếm..." onkeyup="searchProduct()">
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
        <tbody id="tableBody">
            </tbody>
    </table>

    <div class="total-row">
        Tổng cộng: <span id="totalAmount">0</span> VNĐ
    </div>

    <div style="margin-top: 20px;">
        <textarea id="noteInput" placeholder="Ghi chú phiếu nhập (nếu có)..." style="width: 100%; height: 60px; padding: 10px; box-sizing: border-box;"></textarea>
    </div>

    <div style="margin-top: 20px; text-align: right;">
        <button class="btn" onclick="saveReceipt()">Lưu Phiếu Nhập (Trạng thái: Chờ)</button>
    </div>
</div>

<script>
    let selectedItems = []; // Mảng lưu các sản phẩm đang nhập

    // 1. Tìm kiếm sản phẩm
    function searchProduct() {
        let q = document.getElementById('searchInput').value;
        let resultDiv = document.getElementById('searchResults');
        
        if (q.length < 2) {
            resultDiv.style.display = 'none';
            return;
        }

        fetch('api-search-products.php?q=' + q)
            .then(res => res.json())
            .then(data => {
                resultDiv.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(p => {
                        let div = document.createElement('div');
                        div.className = 'search-item';
                        div.innerHTML = `<strong>${p.name}</strong> (Tồn: ${p.stock} | Giá nhập cũ: ${p.avgPrice})`;
                        div.onclick = () => addProduct(p);
                        resultDiv.appendChild(div);
                    });
                    resultDiv.style.display = 'block';
                } else {
                    resultDiv.style.display = 'none';
                }
            });
    }

    // 2. Thêm sản phẩm vào bảng
    function addProduct(product) {
        // Kiểm tra xem sản phẩm đã có trong danh sách chưa
        let exists = selectedItems.find(item => item.productId === product.id);
        if (exists) {
            alert("Sản phẩm này đã có trong danh sách nhập!");
            return;
        }

        selectedItems.push({
            productId: product.id,
            name: product.name,
            quantity: 1,
            importPrice: product.avgPrice // Mặc định lấy giá cũ gợi ý
        });

        document.getElementById('searchInput').value = '';
        document.getElementById('searchResults').style.display = 'none';
        renderTable();
    }

    // 3. Hiển thị bảng và tính tổng tiền
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
                    <td><input type="number" min="1" value="${item.quantity}" onchange="updateItem(${index}, 'quantity', this.value)"></td>
                    <td><input type="number" min="0" value="${item.importPrice}" onchange="updateItem(${index}, 'importPrice', this.value)"></td>
                    <td>${subTotal.toLocaleString()}</td>
                    <td><button class="btn btn-danger" onclick="removeItem(${index})">Xóa</button></td>
                </tr>
            `;
        });

        document.getElementById('totalAmount').innerText = total.toLocaleString();
    }

    // Cập nhật số lượng hoặc giá
    function updateItem(index, field, value) {
        selectedItems[index][field] = parseFloat(value) || 0;
        renderTable();
    }

    // Xóa khỏi bảng
    function removeItem(index) {
        selectedItems.splice(index, 1);
        renderTable();
    }

    // 4. Lưu phiếu nhập
    function saveReceipt() {
        if (selectedItems.length === 0) {
            alert("Vui lòng chọn ít nhất 1 sản phẩm!");
            return;
        }

        let note = document.getElementById('noteInput').value;

        fetch('api-save-receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ note: note, items: selectedItems })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert("Đã lưu phiếu nhập thành công! Mã phiếu: " + data.receiptId);
                // Reset form sau khi lưu thành công
                selectedItems = [];
                document.getElementById('noteInput').value = '';
                renderTable();
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(err => alert("Lỗi hệ thống: " + err));
    }
</script>

</body>
</html>