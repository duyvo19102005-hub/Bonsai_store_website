<?php
include 'connect.php';
session_name('admin_session');
session_start();

if(isset($_SESSION['Username'])) {
    $stmt = $conn->prepare("SELECT Status FROM users WHERE Username = ? AND Role = 'admin'");
    $stmt->bind_param("s", $_SESSION['Username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['Status'] === 'Block') {
            session_unset();
            echo "<script>
                alert('Tài khoản của bạn đã bị khóa. 🔒');
                    window.location.href = '../index.php';
            </script>";
            exit();
        }
    }
    $stmt->close();
} else {
    header("Location: ../index.php");
    exit();
}
?>