<?php
header("Content-Type: application/json");

include 'connect.php';

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';

$sql = "SELECT DISTINCT Address 
        FROM users 
        WHERE Address LIKE '%$query%' 
        LIMIT 10";
$result = $conn->query($sql);

$addresses = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row['Address'];
    }
} else {
    $addresses = ['error' => 'Query failed: ' . $conn->error];
}

echo json_encode($addresses);

$conn->close();
?>