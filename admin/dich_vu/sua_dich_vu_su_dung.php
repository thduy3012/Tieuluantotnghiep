<?php
// Bắt đầu phiên làm việc
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import cấu hình và kết nối CSDL
require_once __DIR__ . '/../../config/config.php';

// Kiểm tra xem có tham số ID được truyền vào không
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Thiếu thông tin dịch vụ cần sửa";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php");
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Lấy thông tin chi tiết về dịch vụ sử dụng hiện tại
$sql = "SELECT sdv.*, dv.ten_dich_vu, dv.gia, dp.id_khach_hang, kh.ho_ten as ten_khach_hang
        FROM su_dung_dich_vu sdv
        JOIN dich_vu dv ON sdv.id_dich_vu = dv.id
        JOIN dat_phong dp ON sdv.id_dat_phong = dp.id
        JOIN khach_hang kh ON dp.id_khach_hang = kh.id
        WHERE sdv.id = ?";
$dich_vu_su_dung = fetchSingleRow($sql, [$id]);

if (!$dich_vu_su_dung) {
    $_SESSION['message'] = "Không tìm thấy thông tin dịch vụ sử dụng";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php");
    exit;
}

// Lấy danh sách các dịch vụ
$sql = "SELECT * FROM dich_vu WHERE trang_thai = 1 ORDER BY ten_dich_vu";
$dich_vu_list = fetchAllRows($sql);

// Xử lý khi form được gửi đi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy và xác thực dữ liệu từ form
    $id_dich_vu = filter_input(INPUT_POST, 'id_dich_vu', FILTER_VALIDATE_INT);
    $so_luong = filter_input(INPUT_POST, 'so_luong', FILTER_VALIDATE_INT);
    $ngay_su_dung = filter_input(INPUT_POST, 'ngay_su_dung', FILTER_SANITIZE_STRING);
    
    // Kiểm tra dữ liệu
    $errors = [];
    if (!$id_dich_vu) {
        $errors[] = "Vui lòng chọn dịch vụ";
    }
    
    if (!$so_luong || $so_luong <= 0) {
        $errors[] = "Số lượng phải lớn hơn 0";
    }
    
    if (empty($ngay_su_dung)) {
        $errors[] = "Vui lòng chọn ngày sử dụng";
    } else {
        // Kiểm tra định dạng ngày
        $date_parts = explode('-', $ngay_su_dung);
        if (count($date_parts) != 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
            $errors[] = "Ngày sử dụng không hợp lệ";
        }
    }
    
    if (empty($errors)) {
        // Lấy giá của dịch vụ
        $sql = "SELECT gia FROM dich_vu WHERE id = ?";
        $dich_vu = fetchSingleRow($sql, [$id_dich_vu]);
        
        if (!$dich_vu) {
            $_SESSION['message'] = "Không tìm thấy thông tin dịch vụ";
            $_SESSION['message_type'] = "danger";
            header("Location: /quanlykhachsan/admin/dich_vu/sua_dich_vu_su_dung.php?id=$id");
            exit;
        }
        
        // Tính thành tiền
        $thanh_tien = $dich_vu['gia'] * $so_luong;
        
        // Cập nhật thông tin sử dụng dịch vụ
        $sql = "UPDATE su_dung_dich_vu SET 
                id_dich_vu = ?, 
                so_luong = ?, 
                ngay_su_dung = ?, 
                thanh_tien = ? 
                WHERE id = ?";
        
        $updated = executeQuery($sql, [
            $id_dich_vu,
            $so_luong,
            $ngay_su_dung,
            $thanh_tien,
            $id
        ]);
        
        if ($updated) {
            // Cập nhật tổng tiền dịch vụ trong hóa đơn
            $sql = "UPDATE hoa_don hd
                    SET tong_tien_dich_vu = (
                        SELECT SUM(thanh_tien) 
                        FROM su_dung_dich_vu sdv
                        JOIN dat_phong dp ON sdv.id_dat_phong = dp.id
                        WHERE dp.id = (SELECT id_dat_phong FROM su_dung_dich_vu WHERE id = ?)
                    ),
                    tong_thanh_toan = tong_tien_phong + (
                        SELECT SUM(thanh_tien) 
                        FROM su_dung_dich_vu sdv
                        JOIN dat_phong dp ON sdv.id_dat_phong = dp.id
                        WHERE dp.id = (SELECT id_dat_phong FROM su_dung_dich_vu WHERE id = ?)
                    )
                    WHERE id_dat_phong = (SELECT id_dat_phong FROM su_dung_dich_vu WHERE id = ?)";
            
            executeQuery($sql, [$id, $id, $id]);
            
            $_SESSION['message'] = "Cập nhật dịch vụ thành công";
            $_SESSION['message_type'] = "success";
            header("Location: /quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php");
            exit;
        } else {
            $_SESSION['message'] = "Cập nhật dịch vụ thất bại";
            $_SESSION['message_type'] = "danger";
        }
    }
}

// Include header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Chỉnh sửa dịch vụ sử dụng</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Thông tin sử dụng dịch vụ</h5>
        </div>
        <div class="card-body">
            <form action="" method="post">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="id_dat_phong" class="form-label">Khách hàng</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($dich_vu_su_dung['ten_khach_hang']); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="id_dich_vu" class="form-label">Dịch vụ <span class="text-danger">*</span></label>
                            <select name="id_dich_vu" id="id_dich_vu" class="form-select" required>
                                <option value="">-- Chọn dịch vụ --</option>
                                <?php if ($dich_vu_list): ?>
                                    <?php foreach ($dich_vu_list as $dv): ?>
                                        <option value="<?php echo $dv['id']; ?>" data-gia="<?php echo $dv['gia']; ?>" <?php echo ($dv['id'] == $dich_vu_su_dung['id_dich_vu']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dv['ten_dich_vu']); ?> - <?php echo number_format($dv['gia'], 0, ',', '.'); ?> VNĐ
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="so_luong" class="form-label">Số lượng <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="so_luong" name="so_luong" min="1" value="<?php echo $dich_vu_su_dung['so_luong']; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="ngay_su_dung" class="form-label">Ngày sử dụng <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="ngay_su_dung" name="ngay_su_dung" value="<?php echo $dich_vu_su_dung['ngay_su_dung']; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="thanh_tien" class="form-label">Thành tiền</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="thanh_tien" value="<?php echo number_format($dich_vu_su_dung['thanh_tien'], 0, ',', '.'); ?>" readonly>
                        <span class="input-group-text">VNĐ</span>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                    <a href="/quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php" class="btn btn-secondary ms-2">Quay lại</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript để tính thành tiền tự động -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const idDichVuSelect = document.getElementById('id_dich_vu');
    const soLuongInput = document.getElementById('so_luong');
    const thanhTienInput = document.getElementById('thanh_tien');
    
    // Hàm tính thành tiền
    function tinhThanhTien() {
        const selectedOption = idDichVuSelect.options[idDichVuSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const gia = parseFloat(selectedOption.getAttribute('data-gia'));
            const soLuong = parseInt(soLuongInput.value) || 0;
            const thanhTien = gia * soLuong;
            
            // Format số với dấu chấm ngăn cách hàng nghìn
            thanhTienInput.value = new Intl.NumberFormat('vi-VN').format(thanhTien);
        } else {
            thanhTienInput.value = '0';
        }
    }
    
    // Gán sự kiện cho các trường đầu vào
    idDichVuSelect.addEventListener('change', tinhThanhTien);
    soLuongInput.addEventListener('input', tinhThanhTien);
    
    // Tính thành tiền ban đầu
    tinhThanhTien();
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>