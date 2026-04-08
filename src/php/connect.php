<?php
$servername = "sql111.infinityfree.com";
$username = "if0_41378068";
$password = "19102005duy123";
$dbname = "if0_41378068_bonsaidb";
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
  die("Kết nối thất bại: " . $conn->connect_error);
}
