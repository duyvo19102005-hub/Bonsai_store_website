<?php 
header('Content-Type: application/json; charset=utf-8');

// 1. Kết nối Database
$conn = new mysqli("sql111.infinityfree.com", "if0_41378068", "19102005duy123", "if0_41378068_bonsaidb");

if ($conn->connect_error) {
    echo json_encode(["error" => "Kết nối thất bại"]);
    exit();
}

// 2. BẮT BUỘC: Ép bảng mã tiếng Việt (Để không bị lỗi dấu ?)
$conn->set_charset("utf8mb4");

// 3. Lấy district_id từ AJAX gửi lên
$district_id = isset($_GET['district_id']) ? intval($_GET['district_id']) : 0;

$data = []; // Khởi tạo mảng rỗng

if ($district_id > 0) {
    // 4. Truy vấn bảng wards (Hãy chắc chắn tên bảng trong DB của bạn là 'wards')
    $sql = "SELECT * FROM `wards` WHERE `district_id` = {$district_id}";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'id' => $row['wards_id'], // Kiểm tra xem tên cột trong DB có đúng là wards_id không nhé
                'name'=> $row['name']
            ];
        }
    }
}

// 5. Trả về kết quả
echo json_encode($data);
?>