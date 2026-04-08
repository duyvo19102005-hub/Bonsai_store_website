<?php
// 1. Định nghĩa giá trị cho các biến trước khi dùng
$servername = "sql111.infinityfree.com";
$username = "if0_41378068";
$password = "19102005duy123";
$dbname = "if0_41378068_bonsaidb";

// 2. Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// 3. Kiểm tra kết nối (Sửa $conn thành $conn)
if ($conn) {
    // Ép kiểu chữ tiếng Việt chuẩn nhất hiện nay
    $conn->set_charset("utf8mb4"); 
} else {
    die("Kết nối thất bại: " . mysqli_connect_error());
}
$conn->set_charset("utf8mb4");

// Trả về biến $conn để các trang khác sử dụng
return $conn;
?>