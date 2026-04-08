<?php
header('Content-Type: application/json');
include 'connect.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8");

if (isset($_POST['district_id'])) {
    $district_id = intval($_POST['district_id']);
    
    // Sử dụng prepared statement với tham số kiểu integer
    $sql = "SELECT w.wards_id, w.name 
            FROM wards w
            WHERE w.district_id = ?
            ORDER BY w.name ASC";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi chuẩn bị câu truy vấn: ' . $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param("i", $district_id);
    
    if (!$stmt->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi thực thi truy vấn: ' . $stmt->error
        ]);
        $stmt->close();
        exit;
    }
    
    $result = $stmt->get_result();
    $wards = array();
    
    while ($row = $result->fetch_assoc()) {
        $wards[] = array(
            'wards_id' => $row['wards_id'],
            'name' => $row['name']
        );
    }
    
    echo json_encode([
        'success' => true,
        'wards' => $wards
    ]);
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu tham số district_id'
    ]);
}

$conn->close();
?>