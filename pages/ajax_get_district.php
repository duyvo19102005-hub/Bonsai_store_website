<?php 
header('Content-Type: application/json; charset=utf-8');

// Kết nối
$conn = new mysqli("sql111.infinityfree.com", "if0_41378068", "19102005duy123", "if0_41378068_bonsaidb");

if ($conn->connect_error) {
    // Trả về lỗi dạng JSON thay vì dùng die() để tránh làm hỏng cấu trúc AJAX
    echo json_encode(["error" => "Kết nối thất bại"]);
    exit();
}

// BẮT BUỘC: Ép bảng mã tiếng Việt
$conn->set_charset("utf8mb4");

// Kiểm tra xem có nhận được province_id không
$province_id = isset($_GET['province_id']) ? intval($_GET['province_id']) : 0;

$data = []; // KHỞI TẠO mảng rỗng trước

if ($province_id > 0) {
    $sql = "SELECT * FROM `district` WHERE `province_id` = {$province_id}";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'id' => $row['district_id'],
                'name'=> $row['name']
            ];
        }
    }
}

// Trả về kết quả JSON
echo json_encode($data);
?>