<?php
session_start();
require_once('../src/php/connect.php'); // Đảm bảo đường dẫn tới connect.php đúng

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

$response = ['success' => false, 'message' => 'Lỗi không xác định.'];

if ($product_id > 0 && $quantity > 0) {
    // 1. KIỂM TRA CHỐT CHẶN TỒN KHO TỪ DATABASE
    $stmt = $conn->prepare("SELECT StockQuantity FROM products WHERE ProductID = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stock = 0;
    if ($row = $res->fetch_assoc()) {
        $stock = (int)$row['StockQuantity'];
    }
    $stmt->close();

    // 2. Nếu số lượng yêu cầu lớn hơn tồn kho -> Chặn đứng ngay lập tức!
    if ($quantity > $stock) {
        $response['message'] = "Kho chỉ còn $stock sản phẩm, không đủ số lượng bạn yêu cầu!";
        echo json_encode($response);
        exit;
    }

    // 3. Nếu hợp lệ -> Cho phép cập nhật Giỏ hàng (Session)
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $key => $cart_item) {
            if ($cart_item['ProductID'] == $product_id) {
                $_SESSION['cart'][$key]['Quantity'] = $quantity;
                $response['success'] = true;
                $response['message'] = 'Cập nhật thành công';
                break;
            }
        }
    }
} else {
    $response['message'] = 'Dữ liệu không hợp lệ.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>