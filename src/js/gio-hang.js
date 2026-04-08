/**
 * 1. Hàm cưỡng ép giá trị (CỰC MẠNH)
 * Chạy ngay khi khách vừa gõ bất kỳ ký tự nào
 */
function forceNumericMinMax(input) {
  const max = parseInt(input.getAttribute("max"), 10) || 9999;
  const min = parseInt(input.getAttribute("min"), 10) || 1;
  let val = parseInt(input.value);

  if (val > max) {
    // Dùng setTimeout để đè lên hành động vẽ của trình duyệt
    setTimeout(() => {
      input.value = max;
      // Thông báo nhẹ để khách biết
      console.log("Đã ép về số lượng tồn kho tối đa: " + max);
    }, 0);
    alert("Sản phẩm này chỉ còn tối đa " + max + " cây trong kho!");
  } else if (val < min) {
    input.value = min;
  }
}

/**
 * 2. Hàm xử lý cập nhật khi khách rời ô nhập (onchange)
 */
function updateQuantityInput(input) {
  const maxVal = parseInt(input.getAttribute("max"), 10) || 9999;
  let val = parseInt(input.value);

  if (val > maxVal) {
    val = maxVal;
    input.value = val;
  }

  const form = input.closest(".update-form");
  const productId = form.querySelector("input[name='update_product_id']").value;
  sendUpdateQuantity(productId, val, input, val);
}

/**
 * 3. Hàm xử lý nút bấm + và -
 */
function changeQuantity(button, delta) {
  const input = button.closest(".update-form").querySelector(".quantity-input");
  const max = parseInt(input.getAttribute("max"), 10) || 9999;
  let current = parseInt(input.value, 10) || 1;
  let newValue = current + delta;

  if (newValue < 1) return;
  if (newValue > max) {
    alert("Kho chỉ còn tối đa " + max + " sản phẩm!");
    return;
  }

  input.value = newValue;
  const productId = button.closest(".update-form").querySelector("input[name='update_product_id']").value;
  sendUpdateQuantity(productId, newValue, input, current);
}

/**
 * 4. Gửi dữ liệu lên Server
 */
function sendUpdateQuantity(productId, quantity, inputElement, fallbackValue) {
  const formData = new FormData();
  formData.append("product_id", productId);
  formData.append("quantity", quantity);

  fetch("cap-nhat-so-luong.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        updateCartTotal();
      } else {
        alert(data.message);
        inputElement.value = fallbackValue;
        updateCartTotal();
      }
    })
    .catch((err) => console.error("Lỗi:", err));
}

/**
 * 5. Cập nhật tổng tiền hiển thị
 */
function updateCartTotal() {
  let total = 0;
  document.querySelectorAll(".order").forEach((order) => {
    const input = order.querySelector(".quantity-input");
    if (input) {
      const price = parseFloat(input.getAttribute("data-price")) || 0;
      const qty = parseInt(input.value) || 0;
      total += price * qty;
    }
  });
  const totalEl = document.getElementById("total-price");
  if (totalEl) totalEl.textContent = total.toLocaleString("vi-VN") + " VNĐ";
}