<?php
function connect_db()
{
$servername = "sql111.infinityfree.com";
$username = "if0_41378068";
$password = "19102005duy123";
$dbname = "if0_41378068_bonsaidb";

    // Create connection
   $conn = new mysqli($servername, $username, $password, $dbname);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
   $conn->set_charset("utf8mb4"); // Ép đọc chuẩn tiếng Việt
    $conn->query("SET SQL_BIG_SELECTS=1"); // Chống lỗi sập Host khi JOIN nhiều bảng

        return $conn;
}
