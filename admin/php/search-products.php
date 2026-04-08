<?php
header('Content-Type: application/json');

// NHÚNG TRỰC TIẾP KẾT NỐI DB ĐỂ TRÁNH LỖI ĐƯỜNG DẪN
$servername = "sql111.infinityfree.com";
$username = "if0_41378068";
$password = "19102005duy123";
$dbname = "if0_41378068_bonsaidb";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Lỗi kết nối DB: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

// ---- THÊM DÒNG NÀY VÀO ĐỂ HOST KHÔNG CHẶN LỆNH JOIN ----
$conn->query("SET SQL_BIG_SELECTS=1");

function removeVietnameseDiacritics($str) {
    $diacritics = array(
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
        'À'=>'A','Á'=>'A','Ạ'=>'A','Ả'=>'A','Ã'=>'A','Â'=>'A','Ầ'=>'A','Ấ'=>'A','Ậ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ă'=>'A','Ằ'=>'A','Ắ'=>'A','Ặ'=>'A','Ẳ'=>'A','Ẵ'=>'A',
        'È'=>'E','É'=>'E','Ẹ'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ê'=>'E','Ề'=>'E','Ế'=>'E','Ệ'=>'E','Ể'=>'E','Ễ'=>'E',
        'Ì'=>'I','Í'=>'I','Ị'=>'I','Ỉ'=>'I','Ĩ'=>'I',
        'Ò'=>'O','Ó'=>'O','Ọ'=>'O','Ỏ'=>'O','Õ'=>'O','Ô'=>'O','Ồ'=>'O','Ố'=>'O','Ộ'=>'O','Ổ'=>'O','Ỗ'=>'O','Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ợ'=>'O','Ở'=>'O','Ỡ'=>'O',
        'Ù'=>'U','Ú'=>'U','Ụ'=>'U','Ủ'=>'U','Ũ'=>'U','Ư'=>'U','Ừ'=>'U','Ứ'=>'U','Ự'=>'U','Ử'=>'U','Ữ'=>'U',
        'Ỳ'=>'Y','Ý'=>'Y','Ỵ'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Đ'=>'D'
    );
    return strtr($str, $diacritics);
}

function normalizeSearchString($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^a-zA-Z0-9àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸĐ\s]/u', '', $str);
    return $str;
}

$searchTerm = isset($_POST['search']) ? trim($_POST['search']) : '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$itemsPerPage = 5;
$offset = ($page - 1) * $itemsPerPage;

try {
    $sqlSelect = "SELECT p.ProductID, p.ProductName, p.Price, p.ImageURL, p.Status, 
                         c.CategoryName, p.Description, p.StockQuantity, p.AvgImportPrice 
                  FROM products p 
                  LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
                  WHERE (p.Status = 'appear' OR p.Status = 'hidden')";

    $sqlCount = "SELECT COUNT(DISTINCT p.ProductID) as total 
                 FROM products p 
                 LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
                 WHERE (p.Status = 'appear' OR p.Status = 'hidden')";

    if (!empty($searchTerm)) {
        $normalizedSearchTerm = normalizeSearchString($searchTerm);
        $searchTermNoAccents = removeVietnameseDiacritics($normalizedSearchTerm);

        if (mb_strlen($searchTerm) === 1) {
            // Trường hợp 1: Tìm 1 ký tự
            $condition = " AND p.ProductName LIKE ?";
            $searchPattern = '%' . $searchTerm . '%';

            $stmtCount = $conn->prepare($sqlCount . $condition);
            $stmtCount->bind_param("s", $searchPattern);
            
            $stmtSelect = $conn->prepare($sqlSelect . $condition . " ORDER BY p.ProductID DESC LIMIT ? OFFSET ?");
            $stmtSelect->bind_param("sii", $searchPattern, $itemsPerPage, $offset);

        } else {
            // Trường hợp 2: Tìm nhiều ký tự
            $condition = " AND (p.ProductName LIKE ? OR p.ProductName LIKE ?)";
            $searchPattern = '%' . $normalizedSearchTerm . '%';
            $searchPatternNoAccents = '%' . $searchTermNoAccents . '%';

            $stmtCount = $conn->prepare($sqlCount . $condition);
            $stmtCount->bind_param("ss", $searchPattern, $searchPatternNoAccents);
            
            $stmtSelect = $conn->prepare($sqlSelect . $condition . " ORDER BY p.ProductID DESC LIMIT ? OFFSET ?");
            $stmtSelect->bind_param("ssii", $searchPattern, $searchPatternNoAccents, $itemsPerPage, $offset);
        }
    } else {
        // Trường hợp 3: Không có từ khóa tìm kiếm (Load mặc định)
        $stmtCount = $conn->prepare($sqlCount);
        
        $stmtSelect = $conn->prepare($sqlSelect . " ORDER BY p.ProductID DESC LIMIT ? OFFSET ?");
        $stmtSelect->bind_param("ii", $itemsPerPage, $offset);
    }

    // Lấy tổng số sản phẩm
    $stmtCount->execute();
    $totalResult = $stmtCount->get_result()->fetch_assoc();
    $totalProducts = $totalResult['total'];
    $totalPages = ceil($totalProducts / $itemsPerPage);
    $stmtCount->close();

    // Lấy danh sách sản phẩm hiển thị
    $stmtSelect->execute();
    $result = $stmtSelect->get_result();

    $products = array();
    while ($row = $result->fetch_assoc()) {
        $products[] = array(
            'id' => $row['ProductID'],
            'name' => $row['ProductName'],
            'category' => $row['CategoryName'],
            'price' => $row['Price'], 
            'image' => '../..' . $row['ImageURL'],
            'status' => $row['Status'],
            'description' => $row['Description'],
            'StockQuantity' => isset($row['StockQuantity']) ? (int)$row['StockQuantity'] : 0,
            'AvgImportPrice' => isset($row['AvgImportPrice']) ? (float)$row['AvgImportPrice'] : 0
        );
    }
    $stmtSelect->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'products' => $products,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
            'itemsPerPage' => $itemsPerPage
        ],
        'searchTerm' => $searchTerm
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'LỖI CSDL: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>