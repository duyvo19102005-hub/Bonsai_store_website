<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../src/php/connect.php');
require_once('../src/php/token.php');
require_once('../src/php/check_token_v2.php');
require __DIR__ . '/../src/Jwt/vendor/autoload.php';
require_once('../src/php/check_status.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra token
if (!isset($_COOKIE['token'])) {
  header("Location: login.php");
  exit;
}

try {
  $decoded = JWT::decode($_COOKIE['token'], new Key($key, 'HS256'));
  $username = $decoded->data->Username;
  $_SESSION['username'] = $username;
} catch (Exception $e) {
  header("Location: login.php");
  exit;
}

// Kiểm tra giỏ hàng
function isCartEmpty() {
  return (!isset($_SESSION['cart']) || empty($_SESSION['cart']));
}

if (isCartEmpty()) {
  header("Location: gio-hang.php");
  exit;
}

$user = null;
if (isset($_SESSION['username'])) {
  $username = $_SESSION['username'];
  $sql_user = "
        SELECT 
            u.Username, u.FullName, u.Email, u.Phone, u.Address,
            p.name AS Province, d.name AS District, w.name AS Ward,
            u.Province AS ProvinceID, u.District AS DistrictID, u.Ward AS WardID
        FROM users u
        LEFT JOIN province p ON u.Province = p.province_id
        LEFT JOIN district d ON u.District = d.district_id
        LEFT JOIN wards w ON u.Ward = w.wards_id
        WHERE u.Username = ?
    ";
  $stmt = $conn->prepare($sql_user);
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $stmt->close();
}

$cart_items = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cart_count = 0;
$total_amount = 0;
foreach ($cart_items as $item) {
  $cart_count += $item['Quantity'];
  $total_amount += $item['Price'] * $item['Quantity'];
}
$total_price_formatted = number_format($total_amount, 0, ',', '.') . " VNĐ";

// =========================================================================
// XỬ LÝ THANH TOÁN (LÕI PHP XỊN 100%)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paymentMethod'])) {
  try {
    $conn->begin_transaction();

    $paymentMethod = $_POST['paymentMethod'] ?? 'COD';

    // Lấy thông tin dựa theo lựa chọn radio
    if (isset($_POST['default-information']) && $_POST['default-information'] === 'true') {
      $customerName = $user['FullName'];
      $phone = $user['Phone'];
      $address = $user['Address'];
      $provinceID = $user['ProvinceID'];
      $districtID = $user['DistrictID'];
      $wardID = $user['WardID'];
    } else {
      $customerName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
      $phone = isset($_POST['new_sdt']) ? trim($_POST['new_sdt']) : '';
      $address = isset($_POST['new_diachi']) ? trim($_POST['new_diachi']) : '';
      $provinceID = isset($_POST['province']) ? (int)$_POST['province'] : 0;
      $districtID = isset($_POST['district']) ? (int)$_POST['district'] : 0;
      $wardID = isset($_POST['wards']) ? (int)$_POST['wards'] : 0;

      if (empty($customerName) || empty($phone) || empty($address) || $provinceID <= 0 || $districtID <= 0 || $wardID <= 0) {
        throw new Exception("Vui lòng điền đầy đủ thông tin giao hàng mới!");
      }
    }

    // 1. TẠO VỎ ĐƠN HÀNG
    $sql_order = "INSERT INTO orders (CustomerName, Phone, Address, Province, District, Ward, TotalAmount, Status, PaymentMethod, DateGeneration, Username) VALUES (?, ?, ?, ?, ?, ?, ?, 'execute', ?, NOW(), ?)";
    $stmt_order = $conn->prepare($sql_order);
    if (!$stmt_order) {
        throw new Exception("Lỗi SQL orders: " . $conn->error);
    }

    $stmt_order->bind_param("sssiiidss", $customerName, $phone, $address, $provinceID, $districtID, $wardID, $total_amount, $paymentMethod, $username);
    
    if (!$stmt_order->execute()) {
      throw new Exception("Lỗi lưu đơn hàng: " . $stmt_order->error);
    }

    $orderID = $stmt_order->insert_id;
    $_SESSION['order_id'] = $orderID;
    $stmt_order->close();

 // 2. THÊM CHI TIẾT SẢN PHẨM VÀ TRỪ TỒN KHO
    $sql_detail = "INSERT INTO orderdetails (OrderID, ProductID, Quantity, UnitPrice, TotalPrice) VALUES (?, ?, ?, ?, ?)";
    $stmt_detail = $conn->prepare($sql_detail);
    if (!$stmt_detail) {
      throw new Exception("Lỗi SQL orderdetails: " . $conn->error);
    }

    // CHUẨN BỊ CÂU LỆNH TRỪ KHO 
    $sql_stock = "UPDATE products SET StockQuantity = StockQuantity - ? WHERE ProductID = ?";
    $stmt_stock = $conn->prepare($sql_stock);
    if (!$stmt_stock) {
      throw new Exception("Lỗi SQL update products: " . $conn->error);
    }

    $db_productID = 0; $db_quantity = 0; $db_unitPrice = 0; $db_totalPrice = 0;
    
    // Bind dữ liệu cho lệnh insert orderdetails
    $stmt_detail->bind_param("iiidd", $orderID, $db_productID, $db_quantity, $db_unitPrice, $db_totalPrice);
    
    // Bind dữ liệu cho lệnh update kho
    $stmt_stock->bind_param("ii", $db_quantity, $db_productID);

    foreach ($cart_items as $item) {
      $db_productID = $item['ProductID'];
      $db_quantity = $item['Quantity'];
      $db_unitPrice = $item['Price'];
      $db_totalPrice = $db_unitPrice * $db_quantity;
      
      // 2.1 Thực thi lưu chi tiết đơn hàng
      if (!$stmt_detail->execute()) {
        throw new Exception("Lỗi lưu SP (Mã $db_productID): " . $stmt_detail->error);
      }

      // 2.2 Thực thi trừ kho
      if (!$stmt_stock->execute()) {
        throw new Exception("Lỗi trừ kho SP (Mã $db_productID): " . $stmt_stock->error);
      }
    }
    
    $stmt_detail->close();
    $stmt_stock->close();

    // 3. HOÀN TẤT VÀ CHUYỂN TRANG
    $conn->commit();
    unset($_SESSION['cart']);
    header("Location: hoan-tat.php");
    exit;

  } catch (Exception $e) {
    $conn->rollback();
    die("<div style='text-align:center; margin-top:100px; font-family:sans-serif;'>
          <h1 style='color:red;'>⚠️ ĐẶT HÀNG THẤT BẠI!</h1>
          <h2 style='color:#333;'>Lỗi: " . $e->getMessage() . "</h2>
          <a href='thanh-toan.php' style='display:inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top:20px;'>Quay lại trang thanh toán</a>
        </div>");
  }
}

// XỬ LÝ XÓA SẢN PHẨM BẰNG AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_product_id'])) {
  $product_id_to_remove = $_POST['remove_product_id'];
  if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
      if ($item['ProductID'] == $product_id_to_remove) {
        unset($_SESSION['cart'][$key]);
        break;
      }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
  }
  echo json_encode(['status' => 'success']);
  exit();
}

// Cập nhật giá sản phẩm từ DB mới nhất
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
  $cart_product_ids = array_column($_SESSION['cart'], 'ProductID');
  $placeholders = implode(',', array_fill(0, count($cart_product_ids), '?'));
  $sql = "SELECT ProductID, Price FROM products WHERE ProductID IN ($placeholders)";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param(str_repeat('i', count($cart_product_ids)), ...$cart_product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $price_map = [];
    while ($row = $result->fetch_assoc()) {
      $price_map[$row['ProductID']] = $row['Price'];
    }
    foreach ($_SESSION['cart'] as $key => $item) {
      $pid = $item['ProductID'];
      if (isset($price_map[$pid])) {
        $_SESSION['cart'][$key]['Price'] = $price_map[$pid];
      }
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../src/css/thanh-toan-php.css" />
  <link rel="stylesheet" href="../src/css/thanh-toan.css" />
  <link rel="stylesheet" href="../src/css/user-sanpham.css" />
  <link rel="stylesheet" href="../assets/icon/fontawesome-free-6.7.2-web/css/all.min.css" />
  <link rel="stylesheet" href="../src/css/search-styles.css" />
  <link rel="stylesheet" href="../assets/libs/bootstrap-5.3.3-dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../src/css/searchAdvanceMobile.css" />
  <link rel="stylesheet" href="../src/css/footer.css">
  <link rel="stylesheet" href="../src/css/brandname.css">
  <script src="../assets/libs/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  <script src="../src/js/search-common.js"></script>
  <script src="../src/js/onOffSeacrhAdvance.js"></script>
  <script src="../src/js/search-index.js"></script>
  <script src="../src/js/jquery-3.7.1.min.js"></script>
  <title>Hoàn tất thanh toán</title>
</head>

<body>
  <div class="Sticky">
    <div class="container-fluid" style="padding: 0 !important">
      <div class="header">
        <div class="grid">
          <div class="aaa"></div>
          <div class="item-header">
            <div class="search-group">
              <form id="searchForm" method="get">
                <div class="search-container">
                  <div class="search-input-wrapper">
                    <input type="search" placeholder="Tìm kiếm sản phẩm..." id="searchInput" name="search" class="search-input" />
                    <button type="button" class="advanced-search-toggle" id="advanced-search-toggle" onclick="toggleAdvancedSearch()" title="Tìm kiếm nâng cao">
                      <i class="fas fa-sliders-h"></i>
                    </button>
                    <button type="submit" class="search-button" onclick="performSearch()" title="Tìm kiếm">
                      <i class="fas fa-search"></i>
                    </button>
                  </div>
                </div>

                <div id="advancedSearchForm" class="advanced-search-panel" style="display: none">
                  <div class="advanced-search-header">
                    <h5>Tìm kiếm nâng cao</h5>
                    <button type="button" class="close-advanced-search" onclick="toggleAdvancedSearch()">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>

                  <div class="search-filter-container" id="search-filter-container">
                    <div class="filter-group">
                      <label for="categoryFilter"><i class="fas fa-leaf"></i> Phân loại sản phẩm</label>
                      <select id="categoryFilter" name="category" class="form-select">
                        <option value="">Chọn phân loại</option>
                        <?php
                        require_once '../php-api/connectdb.php';
                        $conn_db = connect_db();
                        $sql = "SELECT CategoryName FROM categories ORDER BY CategoryName ASC";
                        $res = $conn_db->query($sql);
                        if ($res && $res->num_rows > 0) {
                          while ($r = $res->fetch_assoc()) {
                            echo "<option value=\"" . htmlspecialchars($r['CategoryName']) . "\">" . htmlspecialchars($r['CategoryName']) . "</option>";
                          }
                        }
                        $conn_db->close();
                        ?>
                      </select>
                    </div>

                    <div class="filter-group">
                      <label for="priceRange"><i class="fas fa-tag"></i> Khoảng giá</label>
                      <div class="price-range-slider">
                        <div class="price-input-group">
                          <input type="number" id="minPrice" name="minPrice" placeholder="Từ" min="0" />
                          <span class="price-separator">-</span>
                          <input type="number" id="maxPrice" name="maxPrice" placeholder="Đến" min="0" />
                        </div>
                      </div>
                    </div>

                    <div class="filter-actions">
                      <button type="submit" class="btn-search" onclick="performSearch()"><i class="fas fa-search"></i> Tìm kiếm</button>
                      <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fas fa-redo-alt"></i> Đặt lại</button>
                    </div>
                  </div>
                </div>
              </form>
            </div>

            <div class="cart-wrapper">
              <div class="cart-icon">
                <a href="gio-hang.php">
                  <img src="../assets/images/cart.svg" alt="cart" />
                  <span class="cart-count" id="mni-cart-count" style="position: absolute; margin-top: -10px; background-color: red; color: white; border-radius: 50%; padding: 2px 5px; font-size: 12px;">
                    <?php echo $cart_count; ?>
                  </span>
                </a>
              </div>
            </div>
            
            <div class="user-icon">
              <label for="tick" style="cursor: pointer">
                <img src="../assets/images/user.svg" alt="" />
              </label>
              <input id="tick" hidden type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample" aria-controls="offcanvasExample" />
              <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
                <div class="offcanvas-header">
                  <h5 class="offcanvas-title" id="offcanvasExampleLabel">
                    <?= isset($loggedInUsername) && $loggedInUsername ? "Xin chào, " . htmlspecialchars($loggedInUsername) : "Xin vui lòng đăng nhập" ?>
                  </h5>
                  <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                  <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                    <?php if (!isset($loggedInUsername) || !$loggedInUsername): ?>
                      <li class="nav-item"><a class="nav-link login-logout" href="user-register.php">Đăng ký</a></li>
                      <li class="nav-item"><a class="nav-link login-logout" href="user-login.php">Đăng nhập</a></li>
                    <?php else: ?>
                      <li class="nav-item"><a class="nav-link hs-ls-dx" href="ho-so.php">Hồ sơ</a></li>
                      <li class="nav-item"><a class="nav-link hs-ls-dx" href="user-History.php">Lịch sử mua hàng</a></li>
                      <li class="nav-item"><a class="nav-link hs-ls-dx" href="../src/php/logout.php">Đăng xuất</a></li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>
            </div>

          </div>
        </div>

        <nav class="navbar position-absolute">
          <div class="a">
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
              <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="offcanvasNavbarLabel">THEE TREE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
              </div>
              <div class="offcanvas-body offcanvas-fullscreen mt-20">
                <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                  <li class="nav-item"><a class="nav-link active" aria-current="page" href="../index.php">Trang chủ</a></li>
                  <li class="nav-item"><a class="nav-link" href="#">Giới thiệu</a></li>
                  <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Sản phẩm</a>
                    <ul class="dropdown-menu">
                      <?php
                      $conn_menu = connect_db();
                      $res_menu = $conn_menu->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryID ASC");
                      if ($res_menu && $res_menu->num_rows > 0) {
                        while ($rm = $res_menu->fetch_assoc()) {
                          echo "<li><a class='dropdown-item' href='./phan-loai.php?category_id=" . $rm['CategoryID'] . "'>" . htmlspecialchars($rm['CategoryName']) . "</a></li>";
                        }
                      }
                      $conn_menu->close();
                      ?>
                    </ul>
                  </li>
                  <li class="nav-item"><a class="nav-link" href="#">Tin tức</a></li>
                  <li class="nav-item"><a class="nav-link" href="#">Liên hệ</a></li>
                </ul>
              </div>
            </div>
          </div>
        </nav>
      </div>
    </div>

    <div class="nav">
      <div class="brand">
        <div class="brand-logo">
          <a href="../index.php"><img class="img-fluid" src="../assets/images/LOGO-2.jpg" alt="LOGO" /></a>
        </div>
        <div class="brand-name">THE TREE</div>
      </div>
      <div class="choose">
        <ul>
          <li><a href="../index.php" style="font-weight: bold">Trang chủ</a></li>
          <li><a href="#">Giới thiệu</a></li>
          <li>
            <div class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Sản phẩm</a>
              <ul class="dropdown-menu">
                <?php
                $conn_menu = connect_db();
                $res_menu = $conn_menu->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryID ASC");
                if ($res_menu && $res_menu->num_rows > 0) {
                  while ($rm = $res_menu->fetch_assoc()) {
                    echo "<li><a class='dropdown-item' href='./phan-loai.php?category_id=" . $rm['CategoryID'] . "'>" . htmlspecialchars($rm['CategoryName']) . "</a></li>";
                  }
                }
                $conn_menu->close();
                ?>
              </ul>
            </div>
          </li>
          <li><a href="">Tin tức</a></li>
          <li><a href="">Liên hệ</a></li>
        </ul>
      </div>
    </div>
  </div>

  <section>
    <div class="loca">
      <a href="../index.php"><span>Trang chủ</span></a>
      <span>></span>
      <a href="#"><span>Thanh toán</span></a>
    </div>
    <style>
      .loca { padding: 20px; margin: 20px 0; font-size: 16px; background-color: #f9f9f9; }
      .loca a { text-decoration: none; color: #666; transition: color 0.3s ease; }
      .loca a:hover { color: rgb(59, 161, 59); }
      .loca span { margin: 0 10px; color: #666; font-weight: bold; }
      @media (max-width: 768px) {
        .loca { padding: 10px; font-size: 14px; }
        .loca span { margin: 0 5px; }
      }
    </style>
  </section>

  <main>
    <div class="container-payment">
      <h2>THANH TOÁN</h2>
     <style>
  /* 1. GIỮ FORM NẰM GIỮA KHUNG TRẮNG, NHƯNG NỘI DUNG FORM DẠT TRÁI */
  #checkoutForm {
      width: 100%;
      max-width: 800px;
      margin: 0 auto; 
      text-align: left; 
  }

  /* 2. CỤM NÚT CHỌN (RADIO) ÉP SÁT LỀ TRÁI CỦA FORM */
  .option-address, .payment-method {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 15px;
      width: 100%;
  }

  /* 3. TIÊU ĐỀ (LABEL) BÊN TRÊN CÁC Ô NHẬP LIỆU - ĐÃ SỬA LỖI PHÔNG CHỮ */
  #default-information-form label, 
  #new-information-form label {
      display: block;
      margin-bottom: 5px;
      margin-top: 10px;
      text-align: left;
      font-weight: normal; /* Trả về bình thường vì HTML đã có sẵn thẻ <strong> */
      font-family: inherit; /* Ép sử dụng phông chữ gốc của trang web */
  }

  /* 4. ĐỒNG BỘ 100% KÍCH THƯỚC Ô NHẬP & SELECT */
  #default-information-form input, 
  #new-information-form input, 
  #new-information-form select {
      width: 100%; 
      box-sizing: border-box; 
      padding: 10px 15px;
      border: 1px solid #28a745; 
      border-radius: 5px;
      font-family: inherit;
      font-size: 15px;
      outline: none;
      transition: all 0.3s ease;
  }

  /* Hiệu ứng khi bấm vào */
  #new-information-form input:focus, 
  #new-information-form select:focus {
      box-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
  }

  /* Làm mờ form mặc định */
  #default-information-form input:disabled {
      background-color: #f1f3f5;
      color: #495057;
      border: 1px solid #ced4da;
      cursor: not-allowed;
  }

  /* 5. CÁC NÚT BẤM (QUAY LẠI / THANH TOÁN) NẰM GỌN TRÁI */
  .payment-button {
      display: flex;
      justify-content: flex-start; 
      gap: 10px;
      margin-top: 20px;
  }
</style>
</style>
      <div class="content">
        <div class="status-order">
          <i class="fa-solid fa-cart-shopping"></i>
          <hr style="border: 1px dashed black; width: 21%;">
          <i style="color: green;" class="fa-solid fa-id-card"></i>
          <hr style="border: 1px dashed black; width: 21%;">
          <i class="fa-solid fa-circle-check"></i>
        </div>

        <form action="" method="POST" id="checkoutForm" onsubmit="return validateForm()">
          
          <div class="option-address">
            <label for="default-information" style="cursor: pointer">
              <input type="radio" name="default-information" value="true" id="default-information" checked onchange="toggleForms()">
              <span>Sử dụng thông tin mặc định</span>
            </label>
            <label for="new-information" style="cursor: pointer">
              <input type="radio" name="default-information" value="false" id="new-information" onchange="toggleForms()">
              <span>Nhập thông tin mới</span>
            </label>
          </div>

          <div id="default-information-form">
            <label><strong>Họ và tên</strong></label>
            <input type="text" value="<?= htmlspecialchars($user['FullName'] ?? '') ?>" disabled>
            <label><strong>Email</strong></label>
            <input type="email" value="<?= htmlspecialchars($user['Email'] ?? '') ?>" disabled>
            <label><strong>Số điện thoại</strong></label>
            <input type="text" value="<?= htmlspecialchars($user['Phone'] ?? '') ?>" disabled>
            <label><strong>Địa chỉ</strong></label>
            <input type="text" value="<?= htmlspecialchars(($user['Address'] ?? '') . ', ' . ($user['Ward'] ?? '') . ', ' . ($user['District'] ?? '') . ', ' . ($user['Province'] ?? '')) ?>" disabled>
          </div>

          <div id="new-information-form" style="display: none;">
            <label><strong>Họ và tên</strong></label>
            <input type="text" name="new_name" id="new_name" placeholder="Họ và tên">
            
            <label><strong>Số điện thoại</strong></label>
            <input type="text" name="new_sdt" id="new_sdt" placeholder="Số điện thoại">
            
            <label><strong>Địa chỉ</strong></label>
            <input type="text" name="new_diachi" id="new_diachi" placeholder="Nhập địa chỉ (số và đường)">

            <label><strong>Tỉnh/Thành phố</strong></label>
            <select name="province" id="province" class="form-select">
              <option value="">Chọn tỉnh/thành phố</option>
              <?php
              $conn_db = connect_db();
              $stmt_prov = $conn_db->prepare("SELECT province_id, name FROM province");
              $stmt_prov->execute();
              $result_prov = $stmt_prov->get_result();
              while ($row = $result_prov->fetch_assoc()) {
                echo '<option value="' . $row['province_id'] . '">' . htmlspecialchars($row['name']) . '</option>';
              }
              $stmt_prov->close();
              ?>
            </select>

            <label><strong>Quận/Huyện</strong></label>
            <select name="district" id="district" class="form-select">
              <option value="">Chọn quận/huyện</option>
            </select>

            <label><strong>Phường/Xã</strong></label>
            <select name="wards" id="wards" class="form-select">
              <option value="">Chọn phường/xã</option>
            </select>
            <script src="../src/js/DiaChi.js"></script>
          </div>

          <div class="infor-goods">
            <hr style="border: 3px dashed green; width: 100%" />
            <?php if (count($cart_items) > 0): ?>
              <?php foreach ($cart_items as $item): ?>
                <div class="order">
                  <div class="order-img">
                   <img src="<?php echo ".." . $item['ImageURL']; ?>" alt="<?php echo $item['ProductName']; ?>" />
                  </div>
                  <div class="frame">
                    <div class="name-price">
                      <p><strong><?php echo htmlspecialchars($item['ProductName']); ?></strong></p>
                      <p class="price" data-price="<?php echo $item['Price']; ?>">
                        <strong><?php echo number_format($item['Price'], 0, ',', '.') . " VNĐ"; ?></strong>
                      </p>
                    </div>
                    <div class="function">
                      <button type="button" class="btn" style="width: 53px; height: 33px;" onclick="xoaSanPham(<?php echo $item['ProductID']; ?>)">
                        <i class="fa-solid fa-trash" style="font-size: 25px;"></i>
                      </button>
                      <div class="add-del">
                        <span class="quantity-display" style="margin-left:35px"><?php echo "x" . $item['Quantity']; ?></span>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else:  ?>
              <p>Giỏ hàng của bạn đang trống</p>
            <?php endif; ?>
            
            <div class="frame-2">
              <div class="thanh-tien">
                Tổng : <span><?php echo $total_price_formatted; ?></span>
              </div>
              <hr style="border: 3px dashed green; width: 100%" />
            </div>
          </div>

          <div class="payment-method">
            <label>
              <input type="radio" name="paymentMethod" value="COD" checked onchange="toggleBankingForm()" style="cursor: pointer">
              <span style="cursor: pointer">Thanh toán khi nhận hàng</span>
            </label>
            <label>
              <input type="radio" name="paymentMethod" value="Banking" onchange="toggleBankingForm()" style="cursor: pointer">
              <span style="cursor: pointer">Chuyển khoản</span>
            </label>
          </div>

          <div id="banking-form" style="display: none; padding: 15px; background: #e9ecef; margin-bottom: 20px; border-radius: 5px;">
            <p><span style="font-weight: bold;">Số tài khoản:</span> 1028974123</p>
            <p><span style="font-weight: bold;">Tên tài khoản:</span> Nguyễn Văn A</p>
            <p><span style="font-weight: bold;">Ngân hàng:</span> Vietcombank</p>
            <p><span style="font-weight: bold;">Chi nhánh:</span> Bắc Bình Dương</p>
            <p><span style="font-weight: bold;">Nội dung chuyển khoản:</span> Mua hàng</p>
          </div>

          <div class="payment-button" style="gap: 10px; flex-wrap: wrap;">
            <a style="text-decoration: none;" href="./gio-hang.php">
              <button type="button" class="btn btn-secondary" style="width: 185px; height: 50px;">Quay lại</button>
            </a>
            <button type="submit" class="btn btn-success" style="width: 185px; height: 50px;">THANH TOÁN</button>
          </div>
        </form>
        <script>
          // Chuyển đổi giữa Thông tin mặc định và Thông tin mới
          function toggleForms() {
            const isDefault = document.getElementById('default-information').checked;
            document.getElementById('default-information-form').style.display = isDefault ? 'block' : 'none';
            document.getElementById('new-information-form').style.display = isDefault ? 'none' : 'block';
          }

          // Hiện thông tin chuyển khoản
          function toggleBankingForm() {
            const method = document.querySelector('input[name="paymentMethod"]:checked').value;
            document.getElementById('banking-form').style.display = (method === 'Banking') ? 'block' : 'none';
          }

          // Xóa sản phẩm an toàn bằng fetch
          function xoaSanPham(productId) {
            if (!confirm('Bạn có chắc chắn muốn xoá sản phẩm này khỏi giỏ hàng?')) return;
            fetch('thanh-toan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'remove_product_id=' + encodeURIComponent(productId)
              })
              .then(response => response.json())
              .then(data => {
                if (data.status === 'success') { window.location.reload(); }
              })
              .catch(err => { alert('Đã xảy ra lỗi khi xoá sản phẩm.'); });
          }

          // Bắt lỗi Form trước khi cho phép submit
          function validateForm() {
            const isNewInfo = document.getElementById('new-information').checked;
            if (isNewInfo) {
              const newName = document.getElementById('new_name').value.trim();
              const newSdt = document.getElementById('new_sdt').value.trim();
              const newDiachi = document.getElementById('new_diachi').value.trim();
              const province = document.getElementById('province').value;
              const district = document.getElementById('district').value;
              const wards = document.getElementById('wards').value;

              if (!newName) { alert("Vui lòng nhập Họ và tên!"); return false; }
              const phoneRegex = /^0[0-9]{9}$/;
              if (!phoneRegex.test(newSdt)) { alert("Số điện thoại không hợp lệ!"); return false; }
              if (!newDiachi) { alert("Vui lòng nhập địa chỉ!"); return false; }
              if (!province) { alert("Vui lòng chọn Tỉnh/Thành phố!"); return false; }
              if (!district) { alert("Vui lòng chọn Quận/Huyện!"); return false; }
              if (!wards) { alert("Vui lòng chọn Phường/Xã!"); return false; }
            }
            return true;
          }
        </script>
      </div>
    </div>
  </main>

  <footer class="footer">
    <div class="footer-column">
      <h3>The Tree</h3>
      <ul>
        <li><a href="#">Cây dễ chăm</a></li>
        <li><a href="#">Cây văn phòng</a></li>
        <li><a href="#">Cây dưới nước</a></li>
        <li><a href="#">Cây để bàn</a></li>
      </ul>
    </div>
    <div class="footer-column">
      <h3>Khám phá</h3>
      <ul>
        <li><a href="#">Cách chăm sóc cây</a></li>
        <li><a href="#">Lợi ích của cây xanh</a></li>
        <li><a href="#">Cây phong thủy</a></li>
      </ul>
    </div>
    <div class="footer-column">
      <h3>Khám phá thêm từ The Tree</h3>
      <ul>
        <li><a href="#">Blog</a></li>
        <li><a href="#">Cộng tác viên</a></li>
        <li><a href="#">Liên hệ</a></li>
        <li><a href="#">Câu hỏi thường gặp</a></li>
        <li><a href="#">Đăng nhập</a></li>
      </ul>
    </div>
    <div class="footer-column newsletter">
      <h3>Theo dõi chúng tôi</h3>
      <div class="social-icons">
        <a href="#"><i class="fa-brands fa-pinterest"></i></a>
        <a href="#"><i class="fa-brands fa-facebook"></i></a>
        <a href="#"><i class="fa-brands fa-instagram"></i></a>
        <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
      </div>
    </div>
    <div class="copyright">
      © 2021 c01.nhahodau
      <div class="policies">
        <a href="#">Điều khoản dịch vụ</a><span>|</span>
        <a href="#">Chính sách bảo mật</a><span>|</span>
        <a href="#">Chính sách hoàn tiền</a><span>|</span>
        <a href="#">Chính sách trợ năng</a>
      </div>
    </div>
  </footer>
</body>
</html>