<?php
header('Content-Type: application/json');

// 1. NHÚNG TRỰC TIẾP KẾT NỐI DATABASE VÀO ĐÂY ĐỂ TRÁNH LỖI FILE INCLUDE
$servername = "sql111.infinityfree.com";
$username = "if0_41378068";
$password = "19102005duy123";
$dbname = "if0_41378068_bonsaidb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra nếu kết nối thất bại thì báo lỗi dạng JSON
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL: ' . $conn->connect_error]);
    exit;
}

// 2. XỬ LÝ LOGIC NHẬP HÀNG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $import_qty = isset($_POST['import_qty']) ? (int)$_POST['import_qty'] : 0;
    $import_price = isset($_POST['import_price']) ? (float)$_POST['import_price'] : 0;

    if ($product_id <= 0 || $import_qty <= 0 || $import_price < 0) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu đầu vào không hợp lệ.']);
        exit;
    }

    // Lấy thông tin Số tồn và Giá cũ
    $stmt = $conn->prepare("SELECT StockQuantity, AvgImportPrice, ProfitMargin FROM products WHERE ProductID = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm.']);
        exit;
    }

    $row = $result->fetch_assoc();
    $current_stock = (int)$row['StockQuantity'];
    $current_avg_price = (float)$row['AvgImportPrice'];
    $profit_margin = isset($row['ProfitMargin']) ? (float)$row['ProfitMargin'] : 0.20; 

    // Tính Tỷ lệ bình quân gia quyền
    if ($current_stock == 0) {
        $new_avg_price = $import_price; 
    } else {
        $total_old_value = $current_stock * $current_avg_price;
        $total_new_value = $import_qty * $import_price;
        $new_avg_price = ($total_old_value + $total_new_value) / ($current_stock + $import_qty);
    }

    // Tính tồn kho mới và giá bán mới
    $new_stock = $current_stock + $import_qty;
    $new_selling_price = $new_avg_price * (1 + $profit_margin);

    // Update Database
    $update_stmt = $conn->prepare("UPDATE products SET StockQuantity = ?, AvgImportPrice = ?, Price = ? WHERE ProductID = ?");
    // "iddi" = integer, double, double, integer
    $update_stmt->bind_param("iddi", $new_stock, $new_avg_price, $new_selling_price, $product_id);

    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Nhập hàng thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật CSDL: ' . $conn->error]);
    }

    $stmt->close();
    $update_stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
}
?>