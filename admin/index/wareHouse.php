<?php
include '../php/check_session.php';
// session_name('admin_session');
// session_start();

$product_id = isset($_GET['product_id']) ? $_GET['product_id'] : null;

if ($product_id) {
  echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
      // Tự động tìm kiếm sản phẩm với ID cụ thể
      fetch('../php/get-product.php?id=" . $product_id . "')
        .then(response => response.json())
        .then(data => {
          if (data) {
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
              searchInput.value = data.ProductName;
              searchProducts(1, " . $product_id . ");
            }
          }
        })
        .catch(error => console.error('Error:', error));
    });
  </script>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kho hàng</title>

  <link href="../style/main_warehouse.css" rel="stylesheet">
  <link rel="stylesheet" href="../style/header.css">
  <link rel="stylesheet" href="../style/sidebar.css">
  <link href="../icon/css/all.css" rel="stylesheet">
  <link href="../style/generall.css" rel="stylesheet">
  <link href="../style/main.css" rel="stylesheet">
  <link href="../style/LogInfo.css" rel="stylesheet">
  <link href="asset/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style/responsiveWareHouse.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    /* Popup overlay cho thêm sản phẩm */
    .add-product-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
      margin: auto;
    }

    .add-product-content {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      width: 400px;
      max-height: 80vh;
      overflow-y: auto;
      position: relative;
    }

    /* Popup overlay chung */
    .product-details-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .product-details-content {
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
      width: 400px;
      max-height: 80vh;
      overflow-y: auto;
      position: relative;
    }

    .close-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      background: #ff4444;
      color: white;
      border: none;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      cursor: pointer;
      z-index: 10000000;
    }

    .close-btn:hover {
      background: #cc0000;
    }

    .details-grid p,
    .form-group label,
    .form-group input {
      font-size: 14px;
    }

    .form-grid {
      grid-template-columns: 1fr 2fr;
      gap: 15px;
    }

    .image-preview,
    .edit-image-preview {
      max-width: 150px;
    }

    /* Responsive */
    @media only screen and (max-width: 29.9375em) {

      .product-details-content,
      .add-product-content {
        width: 90%;
        padding: 15px;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .details-grid p,
      .form-group label,
      .form-group input {
        font-size: 12px;
      }

      .image-preview,
      .edit-image-preview {
        max-width: 100px;
      }
    }

    @media only screen and (min-width: 30em) and (max-width: 63.9375em) {
      .product-details-content {
        width: 70%;
      }

      .add-product-content {
        padding: 20px;
        width: 66%;
      }
    }

    @media only screen and (min-width: 64em) {
      .product-details-content {
        width: 40%;
      }

      .add-product-content {
        padding: 25px;
        width: 550px;
      }

      .form-grid {
        grid-template-columns: 1fr 2fr;
        gap: 10px;
      }
    }

    #add-product-btn {
      width: 150px;
    }

    .card {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      width: 350px;
      max-width: 100%;
      width: 100%;
    }

    .card h2 {
      text-align: center;
      font-size: 24px;
      color: #333;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    label {
      font-weight: bold;
      font-size: 14px;
      color: #555;
      display: block;
      margin-bottom: 5px;
    }

    input,
    textarea,
    select {
      width: 100%;
      padding: 10px;
      font-size: 14px;
      border-radius: 5px;
      border: 1px solid #ccc;
      background-color: #f9f9f9;
    }

    textarea {
      resize: vertical;
      height: 80px;
    }

    .btn {
      width: 100%;
      color: white;
      padding: 12px;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      text-align: center;
      margin-top: 20px;
    }

    .alert {
      padding: 10px;
      margin-bottom: 15px;
      background-color: #f44336;
      color: white;
      border-radius: 5px;
      text-align: center;
      font-size: 14px;
    }

    .alert-success {
      background-color: #4CAF50;
    }

    .category-note {
      font-size: 12px;
      color: #777;
      margin-top: 5px;
    }
  </style>
</head>

<body>
  <div class="header">
    <div class="index-menu">
      <i class="fa-solid fa-bars" data-bs-toggle="offcanvas" href="#offcanvasExample" role="button"
        aria-controls="offcanvasExample"></i>
      <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasExample"
        aria-labelledby="offcanvasExampleLabel">
        <div style="border-bottom: 1px solid rgb(176, 176, 176);" class="offcanvas-header">
          <h5 class="offcanvas-title" id="offcanvasExampleLabel">Mục lục</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
          <a href="homePage.php" style="text-decoration: none; color: black;">
            <div class="container-function-selection">
              <button class="button-function-selection">
                <i class="fa-solid fa-house" style="font-size: 20px; color: #FAD4AE;"></i>
              </button>
              <p>Tổng quan</p>
            </div>
          </a>
          <a href="wareHouse.php" style="text-decoration: none; color: black;">
            <div class="container-function-selection">
              <button class="button-function-selection" style="background-color: #6aa173;">
                <i class="fa-solid fa-warehouse" style="font-size: 20px; color: #FAD4AE;"></i>
              </button>
              <p>Kho hàng</p>
            </div>
          </a>
          <a href="customer.php" style="text-decoration: none; color: black;">
            <div class="container-function-selection">
              <button class="button-function-selection">
                <i class="fa-solid fa-users" style="font-size: 20px; color: #FAD4AE;"></i>
              </button>
              <p style="color: black; text-align: center; font-size: 10x;">Người dùng</p>
            </div>
          </a>
          <a href="orderPage.php" style="text-decoration: none; color: black;">
            <div class="container-function-selection">
              <button class="button-function-selection">
                <i class="fa-solid fa-list-check" style="font-size: 18px; color: #FAD4AE;"></i>
              </button>
              <p style="color:black">Đơn hàng</p>
            </div>
          </a>
          <a href="analyzePage.php" style="text-decoration: none; color: black;">
            <div class="container-function-selection">
              <button class="button-function-selection">
                <i class="fa-solid fa-chart-simple" style="font-size: 20px; color: #FAD4AE;"></i>
              </button>
              <p>Thống kê</p>
            </div>
          </a>
          <a href="accountPage.php" style="text-decoration: none; color: black;">
            <div class="container-function-selection">
              <button class="button-function-selection">
                <i class="fa-solid fa-circle-user" style="font-size: 20px; color: #FAD4AE;"></i>
              </button>
              <p style="color:black">Tài khoản</p>
            </div>
          </a>
        </div>
      </div>
    </div>
    <div class="header-left-section">
      <p class="header-left-title">Kho hàng</p>
    </div>
    <div class="header-middle-section">
      <img class="logo-store" src="../../assets/images/LOGO-2.jpg">
    </div>
    <div class="header-right-section">
      <div class="bell-notification">
        <i class="fa-regular fa-bell" style="color: #64792c; font-size: 45px; width:100%;"></i>
      </div>
      <div>
        <div class="position-employee">
          <p id="employee-role">Chức vụ</p>
        </div>
        <div class="name-employee">
          <p id="employee-name">Ẩn danh</p>
        </div>
      </div>
      <div>
        <img class="avatar" src="../../assets/images/admin.jpg" alt="" data-bs-toggle="offcanvas"
          data-bs-target="#offcanvasWithBothOptions" aria-controls="offcanvasWithBothOptions">
      </div>
      <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasWithBothOptions"
        aria-labelledby="offcanvasWithBothOptionsLabel">
        <div style="border-bottom: 1px solid rgb(176, 176, 176);" class="offcanvas-header">
          <img class="avatar" src="../../assets/images/admin.jpg" alt="">
          <div class="admin">
            <h4 class="offcanvas-title" id="offcanvasWithBothOptionsLabel">Username</h4>
            <h5 id="employee-displayname">Họ tên</h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
          <a href="accountPage.php" class="navbar_user">
            <i class="fa-solid fa-user"></i>
            <p>Thông tin cá nhân</p>
          </a>
          <a href="#logoutModal" class="navbar_logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <p>Đăng xuất</p>
          </a>
          <div id="logoutModal" class="modal">
            <div class="modal_content">
              <h2>Xác nhận đăng xuất</h2>
              <p>Bạn có chắc chắn muốn đăng xuất không?</p>
              <div class="modal_actions">
                <a href="../php/logout.php" class="btn_2 confirm">Đăng xuất</a>
                <a href="#" class="btn_2 cancel">Hủy</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="side-bar">
    <div class="backToHome">
      <a href="homePage.php" style="text-decoration: none; color: black;">
        <div class="container-function-selection">
          <button class="button-function-selection" style="margin-top: 35px;">
            <i class="fa-solid fa-house" style="font-size: 20px; color: #FAD4AE;"></i>
          </button>
          <p>Tổng quan</p>
        </div>
      </a>
    </div>
    <a href="wareHouse.php" style="text-decoration: none; color: black;">
      <div class="container-function-selection">
        <button class="button-function-selection" style="background-color: #6aa173;">
          <i class="fa-solid fa-warehouse" style="font-size: 20px; color: #FAD4AE;"></i>
        </button>
        <p>Kho hàng</p>
      </div>
    </a>
    <a href="customer.php" style="text-decoration: none; color: black;">
      <div class="container-function-selection">
        <button class="button-function-selection">
          <i class="fa-solid fa-users" style="font-size: 20px; color: #FAD4AE;"></i>
        </button>
        <p>Người dùng</p>
      </div>
    </a>
    <a href="orderPage.php" style="text-decoration: none; color: black;">
      <div class="container-function-selection">
        <button class="button-function-selection">
          <i class="fa-solid fa-list-check" style="font-size: 20px; color: #FAD4AE;"></i>
        </button>
        <p>Đơn hàng</p>
      </div>
    </a>
    <a href="analyzePage.php" style="text-decoration: none; color: black;">
      <div class="container-function-selection">
        <button class="button-function-selection">
          <i class="fa-solid fa-chart-simple" style="font-size: 20px; color: #FAD4AE;"></i>
        </button>
        <p>Thống kê</p>
      </div>
    </a>
    <a href="accountPage.php" style="text-decoration: none; color: black;">
      <div class="container-function-selection">
        <button class="button-function-selection">
          <i class="fa-solid fa-circle-user" style="font-size: 20px; color: #FAD4AE;"></i>
        </button>
        <p>Tài khoản</p>
      </div>
    </a>
  </div>
  <div class="container-main-warehouse">
    <div class="warehouse-management">
      <div class="search-container">
        <input class="search-input" type="text" placeholder="Tìm kiếm sản phẩm..." onkeyup="searchProducts()">
        <button class="search-btn">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
      </div>

      <div class="management-content">
        <div class="products-section">
         <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="section-title">Quản Lý Kho Hàng</h2>
            <div style="display: flex; gap: 10px;">
                <a href="../php/add-import.php" class="btn" style="background-color: #007bff; color: white; text-decoration: none; display: flex; align-items: center; padding: 0 15px; border-radius: 5px; font-weight: bold;">
                  + Lập Phiếu Nhập
                </a>
                <a href="../php/list-imports.php" class="btn" style="background-color: #6c757d; color: white; text-decoration: none; display: flex; align-items: center; padding: 0 15px; border-radius: 5px; font-weight: bold;">
    Lịch Sử Nhập Hàng
</a>
<a href="../php/manage-prices.php" class="btn" style="background-color: #28a745; color: white; text-decoration: none; display: flex; align-items: center; padding: 0 15px; border-radius: 5px; font-weight: bold;">
    Quản Lý Giá Bán
</a>
<a href="../php/inventory-report.php" class="btn" style="background-color: #dc3545; color: white; text-decoration: none; display: flex; align-items: center; padding: 0 15px; border-radius: 5px; font-weight: bold;">
    Báo Cáo & Tồn Kho
</a>
                <button class="btn btn-success add-product-btn" id="add-product-btn" onclick="showAddProductOverlay()">
                  Thêm Sản Phẩm
                </button>
            </div>
          </div>

          <table class=" products-table" id="productsTable">
            <thead>
              <tr>
                <th>Ảnh</th>
                <th style="text-align: center;">Tên sản phẩm</th>
                <th style="text-align: center;">Danh mục</th>
                <th style="text-align: center;">Tồn kho</th>
                <th style="text-align: center;">Giá nhập BQ</th>
                <th style="text-align: center;">Giá bán (VND)</th>
                <th style="text-align: center;">Thao tác</th>
              </tr>
            </thead>
            <style>
              /* Căn giữa cho các cột mới thêm */
              #productsTable td:nth-child(2),
              #productsTable td:nth-child(3),
              #productsTable td:nth-child(4),
              #productsTable td:nth-child(5),
              #productsTable td:nth-child(6),
              #productsTable td:nth-child(7) {
                text-align: center;
                vertical-align: middle;
              }
            </style>
            <tbody id="productsBody">
              <?php
              $conn = new mysqli("sql111.infinityfree.com", "if0_41378068", "19102005duy123", "if0_41378068_bonsaidb");
              if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
              }
                $conn->set_charset("utf8mb4"); 
$conn->query("SET SQL_BIG_SELECTS=1");

              $items_per_page = 5;
              $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
              $offset = ($page - 1) * $items_per_page;
              
              $total_query = "SELECT COUNT(*) as total FROM products";
              $total_result = $conn->query($total_query);
              $total_row = $total_result->fetch_assoc();
              $total_products = $total_row['total'];
              $total_pages = ceil($total_products / $items_per_page);

              // Query to get products 
              $sql = "SELECT p.*, c.CategoryName as CategoryName 
                      FROM products p 
                      LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
                      WHERE p.Status = 'appear' OR p.Status = 'hidden'
                      ORDER BY p.ProductID DESC
                      LIMIT $offset, $items_per_page";

              $result = $conn->query($sql);

              if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                  // Chống lỗi khi chưa kịp tạo cột trong DB
                  $stock = isset($row['StockQuantity']) ? $row['StockQuantity'] : 0;
                  $avgPrice = isset($row['AvgImportPrice']) ? $row['AvgImportPrice'] : 0;
                  
                  echo "<tr class='product-row'>";
                  echo "<td><img src='../.." . $row['ImageURL'] . "' alt='" . htmlspecialchars($row['ProductName'], ENT_QUOTES) . "' style='width: 100px; height: 100px; object-fit: cover;'></td>";
                  echo "<td>" . $row['ProductName'] . "</td>";
                  echo "<td>" . $row['CategoryName'] . "</td>";
                  echo "<td><span class='badge bg-info text-dark' style='font-size:14px;'>" . $stock . "</span></td>";
                  echo "<td>" . number_format($avgPrice, 0, ',', '.') . "</td>";
                  echo "<td><strong style='color:#dc3545;'>" . number_format($row['Price'], 0, ',', '.') . "</strong></td>";
                  echo "<td class='actions'>";
                  // Nút sửa (màu vàng)
                  echo "<button class='btn btn-warning btn-sm' onclick='editProduct(" . $row['ProductID'] . ")' title='Chỉnh sửa'><i class='fa-solid fa-pen-to-square'></i></button> ";
                  // Nút nhập hàng mới (màu xanh lá)
                  echo "<button class='btn btn-success btn-sm ms-1' onclick='openImportModal(" . $row['ProductID'] . ", \"" . htmlspecialchars($row['ProductName'], ENT_QUOTES) . "\")' title='Nhập lô hàng mới'><i class='fa-solid fa-box-open'></i></button>";
                  echo "</td>";
                  echo "</tr>";
                }
              } else {
                echo "<tr><td colspan='7'>Không có sản phẩm nào</td></tr>";
              }

              echo "</tbody></table>";

              // Xử lý xóa sản phẩm (đã giữ nguyên logic cũ của bạn)
              if (isset($_POST['delete_product'])) {
                $productId = $_POST['productId'];
                $imageQuery = "SELECT ImageURL FROM products WHERE ProductID = ?";
                $stmt = $conn->prepare($imageQuery);
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $result = $stmt->get_result();
                $imageData = $result->fetch_assoc();
                $stmt->close();

                $deleteQuery = "DELETE FROM products WHERE ProductID = ?";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("i", $productId);

                if ($stmt->execute()) {
                  if ($imageData && isset($imageData['ImageURL'])) {
                    $imagePath = $_SERVER['DOCUMENT_ROOT'] . $imageData['ImageURL'];
                    if (file_exists($imagePath)) {
                      unlink($imagePath);
                    }
                  }
                  echo "<script>alert('Xóa sản phẩm thành công!'); window.location.href='wareHouse.php';</script>";
                } else {
                  echo "<script>alert('Lỗi khi xóa sản phẩm!');</script>";
                }
                $stmt->close();
              }
              $conn->close();
              ?>
            </tbody>
          </table>
          <div class="pagination"></div>
        </div>
      </div>
    </div>

    <div class="product-details-overlay" id="productDetailsOverlay">
      <div class="product-details-content" id="productDetailsContent"></div>
    </div>

    <div class="add-product-overlay" id="addProductOverlay">
      <div class="add-product-content">
        <button type="button" id="closeButton" class="btn btn-secondary"
          style="margin: 0 0 10px 0;width: 30px; height: 30px; display: flex; justify-content: center; align-items: center;"
          id="closeButton"><i class="fa-solid fa-xmark"></i></button>
        <div class="card">
          <h2>Thêm Sản Phẩm</h2>
          <form id="productForm" method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label for="productName">Tên sản phẩm(*)</label>
              <input type="text" id="productName" name="productName" required placeholder="Nhập tên sản phẩm">
            </div>

            <div class="form-group">
              <label for="categoryID">Danh mục(*)</label>
              <select id="categoryID" name="categoryID" required>
                <?php
                require_once '../../php-api/connectdb.php'; 
                $conn = connect_db();
                $sql = "SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryID ASC";
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $categoryID = htmlspecialchars($row['CategoryID']);
                    $categoryName = htmlspecialchars($row['CategoryName']);
                    echo "<option value='$categoryID'>$categoryName</option>";
                  }
                } else {
                  echo "<option value=''>Không có danh mục</option>";
                }
                $conn->close();
                ?>
              </select>
            </div>

         

            <div class="form-group">
              <label for="description">Mô tả(*)</label>
              <textarea id="description" name="description" required placeholder="Công dụng, cách chăm sóc, nguồn gốc, ..."></textarea>
            </div>

            <div class="form-group">
              <label for="imageURL">Ảnh sản phẩm(*)</label>
              <input type="file" id="imageURL" name="imageURL" required accept=".jpg ,.jpeg,.png,.gif">
              <p class="category-note">Chọn ảnh sản phẩm (PNG, JPG, JPEG, GIF)</p> <br>
              <p class="category-note">Kích thước tối đa: 2MB</p><br>
              <p class="category-note">Kích thước tối thiểu: 300x300px</p><br>
              <img id="imagePreview" class="image-preview" src="#" alt="Preview image" style="display:none;">
            </div>
            <button type="submit" class="btn btn-success">Thêm Sản Phẩm</button>
          </form>

          <script>
            document.getElementById('imageURL').addEventListener('change', function(event) {
              const file = event.target.files[0];
              if (!file) return;
              const maxSize = 2 * 1024 * 1024; // 2MB
              if (file.size > maxSize) {
                alert("Ảnh vượt quá kích thước tối đa 2MB!");
                event.target.value = ""; 
                document.getElementById('imagePreview').style.display = 'none';
                return;
              }
              const reader = new FileReader();
              reader.onload = function() {
                const imagePreview = document.getElementById('imagePreview');
                imagePreview.style.display = 'block';
                imagePreview.src = reader.result;
              };
              reader.readAsDataURL(file);
            });
            function showAddProductOverlay() {
              const overlay = document.getElementById("addProductOverlay");
              if (overlay) overlay.style.display = "flex";
            }
            document.getElementById('closeButton').addEventListener('click', function() {
              const overlay = document.getElementById('addProductOverlay');
              if (overlay.style.display === 'flex') overlay.style.display = 'none'; 
            });
          </script>
        </div>
      </div>
    </div>
  </div>

  <div class="product-details-overlay" id="editProductOverlay">
    <div class="product-details-content" style="width: 700px;"> <button type="button" class="close-btn" onclick="closeEditOverlay()">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="card" style="width:100%">
        <div class="card-body">
          <h3 class="card-title mb-4">Chỉnh sửa sản phẩm</h3>
          <form id="editProductForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="editProductId" name="productId">

            <div class="row">
              <div class="col-md-4">
                <div class="image-preview-container mb-3">
                  <img id="currentImage" class="img-fluid mb-2" src="#" alt="Current image">
                  <div class="mb-3">
                    <label for="editImageURL" class="form-label">Thay đổi ảnh</label>
                    <input type="file" class="form-control" id="editImageURL" name="imageURL" accept=".jpg,.jpeg,.png">
                    <p class="category-note">Chọn ảnh sản phẩm (PNG, JPG, JPEG)</p>
                    <p class="category-note">Kích thước tối đa: 2MB</p>
                  </div>
                </div>
              </div>

              <div class="col-md-8">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="editProductName" class="form-label">Tên sản phẩm</label>
                    <input type="text" class="form-control" id="editProductName" name="productName" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="editCategoryID" class="form-label">Danh mục</label>
                    <select class="form-control" id="editCategoryID" name="categoryID" required>
                      <option value="1">Cây văn phòng</option>
                      <option value="2">Cây dưới nước</option>
                      <option value="3">Cây dễ chăm</option>
                      <option value="4">Cây để bàn</option>
                    </select>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="editPrice" class="form-label">Giá (VND)</label>
                    <input type="number" class="form-control"readonly style="background-color: #e9ecef; cursor: not-allowed;" id="editPrice" name="price" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="editStatus" class="form-label">Trạng thái</label>
                    <select class="form-control" id="editStatus" name="status" required>
                      <option value="appear">Hiện</option>
                      <option value="hidden">Ẩn</option>
                    </select>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="editDescription" class="form-label">Mô tả</label>
                  <textarea class="form-control" id="editDescription" name="description" rows="3" required></textarea>
                </div>
              </div>
            </div>

            <div class="form-actions text-end mt-3">
              <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
              <button type="button" class="btn btn-danger me-2" onclick="confirmDelete()">Xóa sản phẩm</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="product-details-overlay" id="importProductOverlay">
    <div class="product-details-content" style="width: 450px;">
      <button type="button" class="close-btn" onclick="closeImportOverlay()">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="card" style="width: 100%;">
        <div class="card-body">
          <h3 class="card-title mb-4" style="text-align: center;">
            NHẬP HÀNG<br>
            <span id="importProductNameDisplay" style="color:#6aa173; font-size: 18px; display:block; margin-top:10px;"></span>
          </h3>
          <form id="importProductForm" method="POST">
            <input type="hidden" id="importProductId" name="product_id">
            
            <div class="form-group mb-3">
              <label for="importQty" class="form-label">Số lượng nhập mới (*)</label>
              <input type="number" class="form-control" id="importQty" name="import_qty" min="1" required placeholder="Nhập số lượng lô hàng này">
            </div>
            
            <div class="form-group mb-3">
              <label for="importPrice" class="form-label">Giá nhập lô hàng này (VND) (*)</label>
              <input type="number" class="form-control" id="importPrice" name="import_price" min="0" required placeholder="Ví dụ: 15000">
              <p class="category-note mt-2"><i>Hệ thống sẽ tự động tính toán lại Giá nhập BQ và Cập nhật Giá bán theo quy tắc đã định.</i></p>
            </div>
            
            <div class="form-actions text-center mt-4">
              <button type="submit" class="btn btn-success" style="width:100%; font-weight: bold; background-color: #6aa173; border:none;">XÁC NHẬN NHẬP KHO</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="./asset/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../js/add-product.js"></script>
  <script src="../js/checklog.js"></script>

  <script>
    // --- KHỐI XỬ LÝ NHẬP HÀNG (MỚI) ---
    function openImportModal(productId, productName) {
      document.getElementById('importProductId').value = productId;
      document.getElementById('importProductNameDisplay').innerText = productName;
      document.getElementById('importProductForm').reset();
      document.getElementById('importProductOverlay').style.display = 'flex';
    }

    function closeImportOverlay() {
      document.getElementById('importProductOverlay').style.display = 'none';
    }

    document.getElementById('importProductForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const submitButton = this.querySelector('button[type="submit"]');
      submitButton.disabled = true;

      // Gọi API process_import.php để xử lý tính toán
      fetch('../php/process_import.php', {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              alert('Thành công! Kho hàng và giá bán đã được cập nhật.');
              window.location.reload(); // Tải lại trang để thấy số liệu mới
          } else {
              throw new Error(data.message || 'Có lỗi xảy ra khi nhập hàng');
          }
      })
      .catch(error => {
          console.error('Error:', error);
          alert('Có lỗi xảy ra: ' + error.message);
      })
      .finally(() => {
          submitButton.disabled = false;
      });
    });
    // --- KẾT THÚC KHỐI XỬ LÝ NHẬP HÀNG ---


    function editProduct(productId) {
      fetch(`../php/get-product.php?id=${productId}`)
        .then(response => response.json())
        .then(product => {
          document.getElementById('editProductId').value = product.ProductID;
          document.getElementById('editProductName').value = product.ProductName;
          document.getElementById('editCategoryID').value = product.CategoryID;
          document.getElementById('editPrice').value = product.Price;
          document.getElementById('editDescription').value = product.Description;
          document.getElementById('editStatus').value = product.Status;

          const currentImage = document.getElementById('currentImage');
          currentImage.src = '../../' + product.ImageURL;
          currentImage.style.display = 'block';

          document.getElementById('editProductOverlay').style.display = 'flex';
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Có lỗi khi tải thông tin sản phẩm!');
        });
    }

    function closeEditOverlay() {
      document.getElementById('editProductOverlay').style.display = 'none';
    }

    document.getElementById('editImageURL').addEventListener('change', function(event) {
      const file = event.target.files[0];
      if (!file) return;

      const maxSize = 2 * 1024 * 1024; // 2MB
      if (file.size > maxSize) {
        alert('Ảnh không được vượt quá 2MB');
        this.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('currentImage').src = e.target.result;
      }
      reader.readAsDataURL(file);
    });

    document.getElementById('editProductForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const formData = new FormData(this);
      const submitButton = this.querySelector('button[type="submit"]');
      submitButton.disabled = true;

      fetch('../php/update-product.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Cập nhật sản phẩm thành công!');
            window.location.reload();
          } else {
            throw new Error(data.message || 'Có lỗi xảy ra khi cập nhật sản phẩm');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Có lỗi xảy ra: ' + error.message);
        })
        .finally(() => {
          submitButton.disabled = false;
        });
    });
function confirmDelete() {
  const productId = document.getElementById('editProductId').value;
  
  if (!productId || productId === 'undefined') {
      alert('Lỗi giao diện: Không tìm thấy ID sản phẩm!');
      return;
  }

  if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này không?')) {
    
    // GẮN THẲNG ID LÊN URL (Phương thức GET)
    fetch(`../php/delete-product.php?productId=${productId}`, {
        method: 'GET'
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'deleted' || data.status === 'hidden') {
          alert(data.message);
          closeEditOverlay();
          location.reload();
        } else {
          throw new Error(data.message || 'Có lỗi xảy ra khi xóa sản phẩm');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra: ' + error.message);
      });
  }
}
    document.addEventListener('DOMContentLoaded', () => {
      const cachedUserInfo = localStorage.getItem('userInfo');
      if (cachedUserInfo) {
        const userInfo = JSON.parse(cachedUserInfo);
        document.querySelector('.name-employee p').textContent = userInfo.fullname;
        document.querySelector('.position-employee p').textContent = userInfo.role;
        document.querySelectorAll('.avatar').forEach(img => img.src = userInfo.avatar);
      }
    });

    function searchProducts(page = 1) {
      const searchInput = document.querySelector('.search-input');
      const searchTerm = searchInput.value.trim();
      const tableBody = document.getElementById('productsBody');
      const paginationContainer = document.querySelector('.pagination');

      tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Đang tìm kiếm...</td></tr>';

      const formData = new FormData();
      formData.append('search', searchTerm);
      formData.append('page', page);

      fetch('../php/search-products.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .catch(error => {
          console.error('Parse Error:', error);
          return { error: 'Invalid JSON response' };
        })
        .then(data => {
          if (data.error) {
            tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: red;">Có lỗi xảy ra. Vui lòng thử lại</td></tr>`;
            paginationContainer.innerHTML = '';
            return;
          }

          if (!data.products || data.products.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Không tìm thấy sản phẩm nào phù hợp</td></tr>';
            paginationContainer.innerHTML = '';
            return;
          }

          tableBody.innerHTML = '';
          data.products.forEach(product => {
            // Hiển thị fallback nếu search-products.php chưa được update
            const stock = product.StockQuantity !== undefined ? product.StockQuantity : 0;
            const avgPrice = product.AvgImportPrice ? new Intl.NumberFormat('vi-VN').format(product.AvgImportPrice) : 0;
            const price = product.price ? new Intl.NumberFormat('vi-VN').format(product.price) : 0;

            const row = document.createElement('tr');
            row.innerHTML = `
              <td><img src="${product.image}" alt="${product.name}" style="width: 100px; height: 100px; object-fit: cover;"></td>
              <td>${product.name}</td>
              <td>${product.category}</td>
              <td><span class="badge bg-info text-dark" style="font-size:14px;">${stock}</span></td>
              <td>${avgPrice}</td>
              <td><strong style="color:#dc3545;">${price}</strong></td>
              <td class="actions">
                <button class="btn btn-warning btn-sm" onclick="editProduct(${product.id})" title="Chỉnh sửa">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button class="btn btn-success btn-sm ms-1" onclick="openImportModal(${product.id}, '${product.name}')" title="Nhập lô hàng mới">
                  <i class="fa-solid fa-box-open"></i>
                </button>
              </td>
            `;
            tableBody.appendChild(row);
          });

          // Tạo phân trang 
          if (data.pagination.totalPages > 1) {
            let paginationHTML = '<ul class="pagination justify-content-center">';
            if (data.pagination.currentPage > 1) {
              paginationHTML += `
                <li class="page-item">
                  <a class="page-link" href="#" onclick="searchProducts(${data.pagination.currentPage - 1}); return false;"><</a>
                </li>`;
            }
            for (let i = 1; i <= data.pagination.totalPages; i++) {
              if (
                i === 1 || 
                i === data.pagination.totalPages || 
                (i >= data.pagination.currentPage - 2 && i <= data.pagination.currentPage + 2) 
              ) {
                paginationHTML += `
                  <li class="page-item ${i === data.pagination.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="searchProducts(${i}); return false;">${i}</a>
                  </li>`;
              } else if (
                i === data.pagination.currentPage - 3 ||
                i === data.pagination.currentPage + 3
              ) {
                paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
              }
            }
            if (data.pagination.currentPage < data.pagination.totalPages) {
              paginationHTML += `
                <li class="page-item">
                  <a class="page-link" href="#" onclick="searchProducts(${data.pagination.currentPage + 1}); return false;">></a>
                </li>`;
            }
            paginationHTML += '</ul>';
            paginationContainer.innerHTML = paginationHTML;
          } else {
            paginationContainer.innerHTML = ''; 
          }
        })
        .catch(error => {
          tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: red;">Lỗi kết nối: ${error.message}</td></tr>`;
          paginationContainer.innerHTML = '';
        });
    }

    let searchTimeout;
    document.querySelector('.search-input').addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => searchProducts(1), 300);
    });

    document.querySelector('.search-input').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        clearTimeout(searchTimeout);
        searchProducts(1);
      }
    });

    document.querySelector('.search-btn').addEventListener('click', function() {
      clearTimeout(searchTimeout);
      searchProducts(1);
    });

    document.addEventListener('DOMContentLoaded', function() {
      searchProducts(1);
    });
  </script>

  <style>
    .product-details-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }

    .product-details-content {
      background: white;
      padding: 20px;
      border-radius: 8px;
      width: 90%;
      max-width: 800px;
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
    }

    .image-preview-container img {
      max-width: 100%;
      height: auto;
      border-radius: 4px;
    }

    .form-actions {
      border-top: 1px solid #dee2e6;
      padding-top: 1rem;
    }

    .category-note {
      font-size: 12px;
      color: #777;
      margin-top: 5px;
    }
  </style>
</body>
</html>