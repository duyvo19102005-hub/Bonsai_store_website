<?php
// Ensure no errors are output in the response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    // Database connection
    $conn = new mysqli("sql111.infinityfree.com", "if0_41378068", "19102005duy123", "if0_41378068_bonsaidb");

    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }

    // Lấy dữ liệu và kiểm tra (Đã bỏ yêu cầu kiểm tra price)
    if (
        !isset($_POST['productId']) || !isset($_POST['productName']) || !isset($_POST['categoryID']) ||
        !isset($_POST['description'])
    ) {
        throw new Exception('Missing required fields');
    }

    $productId = (int)$_POST['productId'];
    $productName = trim($_POST['productName']);
    $categoryId = (int)$_POST['categoryID'];
    // Đã xóa biến $price ở đây
    $description = trim($_POST['description']);
    $status = $_POST['status'] ?? 'appear';

    if (empty($productName)) {
        throw new Exception('Tên sản phẩm không được để trống');
    }

    // Đã xóa đoạn báo lỗi "Giá phải lớn hơn 0" ở đây

    if (!in_array($status, ['hidden', 'appear'])) {
        throw new Exception('Trạng thái không hợp lệ');
    }

    // Lấy URL ảnh hiện tại
    $currentImageQuery = "SELECT ImageURL FROM products WHERE ProductID = ?";
    $stmt = $conn->prepare($currentImageQuery);
    if (!$stmt) {
        throw new Exception('Không thể xử lý truy vấn hình ảnh hiện tại: ' . $conn->error);
    }

    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentImageData = $result->fetch_assoc();

    if (!$currentImageData) {
        throw new Exception('Product not found');
    }

    $currentImageURL = $currentImageData['ImageURL'];
    $stmt->close();

    $newImageURL = $currentImageURL; // Mặc định là ảnh cũ

    // Xử lý upload ảnh mới
    if (isset($_FILES['imageURL']) && $_FILES['imageURL']['error'] === 0) {
        $file = $_FILES['imageURL'];

        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Định dạng file không hợp lệ. Chỉ chấp nhận JPG, JPEG và PNG.');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('Kích thước file quá lớn. Tối đa 2MB.');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . uniqid() . '.' . $extension;
        $uploadPath = '../../assets/images/' . $filename;

        $uploadDir = dirname($uploadPath);
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Không thể tải lên hình ảnh');
        }

        $newImageURL = '/assets/images/' . $filename;

        if ($currentImageURL && $currentImageURL !== $newImageURL) {
            $oldImagePath = '../../' . $currentImageURL;
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
    }

    // Cập nhật CSDL (ĐÃ XÓA CỘT PRICE KHỎI LỆNH NÀY)
    $sql = "UPDATE products SET 
            ProductName = ?,
            CategoryID = ?,
            Description = ?,
            Status = ?,
            ImageURL = ?
            WHERE ProductID = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn cập nhật: ' . $conn->error);
    }

    // Đã sửa lại định dạng bind_param từ "sidsssi" thành "sisssi" (xóa kiểu double của price)
    $stmt->bind_param("sisssi", $productName, $categoryId, $description, $status, $newImageURL, $productId);

    if (!$stmt->execute()) {
        throw new Exception('Không thể cập nhật sản phẩm: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Không có thay đổi nào được thực hiện',
            'productId' => $productId
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật sản phẩm thành công',
        'productId' => $productId
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>