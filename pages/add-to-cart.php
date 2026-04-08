<?php
session_start();
require_once('../src/php/connect.php');

// Lấy dữ liệu từ fetch
$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;

$response = ['success' => false];

if ($product_id > 0) {
    $sql = "SELECT * FROM products WHERE ProductID = $product_id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Lấy số lượng tồn kho từ database
        $stock_quantity = isset($product['StockQuantity']) ? (int)$product['StockQuantity'] : 0;

        // Nếu kho trống
        if ($stock_quantity <= 0) {
            $response['message'] = "Sản phẩm này hiện đang hết hàng!";
            echo json_encode($response);
            exit;
        }

        // Nếu chưa có giỏ hàng
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $found = false;
        $current_qty_in_cart = 0;

        // Kiểm tra xem sản phẩm đã có trong giỏ chưa để tính tổng số lượng
        foreach ($_SESSION['cart'] as &$cart_item) {
            if ($cart_item['ProductID'] == $product_id) {
                $current_qty_in_cart = $cart_item['Quantity'];
                
                // KIỂM TRA CHỐT CHẶN TỒN KHO
                if ($current_qty_in_cart + $quantity > $stock_quantity) {
                    $response['message'] = "Bạn chỉ có thể mua tối đa " . $stock_quantity . " sản phẩm này (Trong giỏ đã có " . $current_qty_in_cart . ").";
                    echo json_encode($response);
                    exit;
                }

                $cart_item['Quantity'] += $quantity;
                $found = true;
                break;
            }
        }

        // Nếu chưa có trong giỏ, kiểm tra số lượng thêm mới với tồn kho
        if (!$found) {
            if ($quantity > $stock_quantity) {
                $response['message'] = "Kho chỉ còn " . $stock_quantity . " sản phẩm. Không đủ số lượng bạn yêu cầu.";
                echo json_encode($response);
                exit;
            }

            $item = [
                'ProductID' => $product['ProductID'],
                'ProductName' => $product['ProductName'],
                'Price' => $product['Price'],
                'ImageURL' => $product['ImageURL'],
                'Quantity' => $quantity,
                'StockQuantity' => $stock_quantity // Lưu luôn tồn kho vào session để dùng ở trang giỏ hàng
            ];
            $_SESSION['cart'][] = $item;
        }

        // Đếm số lượng sản phẩm khác nhau trong giỏ hàng
        $total_items = count($_SESSION['cart']);
        
        // Tính tổng tiền
        $total_price = 0;
        foreach ($_SESSION['cart'] as $ci) {
            $total_price += $ci['Price'] * $ci['Quantity'];
        }

        $response['success'] = true;
        $response['totalQuantity'] = $total_items; 
        $response['total_price'] = $total_price;
        $response['cart_items'] = $_SESSION['cart'];
    } else {
        $response['message'] = "Không tìm thấy sản phẩm.";
    }
} else {
    $response['message'] = "Thiếu thông tin sản phẩm.";
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>