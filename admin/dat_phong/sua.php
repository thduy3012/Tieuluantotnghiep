<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để truy cập trang này";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import file cấu hình và các hàm chung
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Kiểm tra có ID đặt phòng hay không
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Không tìm thấy thông tin đặt phòng";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit;
}

$id_dat_phong = intval($_GET['id']);

// Xử lý khi form được gửi đi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $id_khach_hang = isset($_POST['id_khach_hang']) ? intval($_POST['id_khach_hang']) : 0;
    $id_phong = isset($_POST['id_phong']) ? intval($_POST['id_phong']) : 0;
    $ngay_nhan_phong = isset($_POST['ngay_nhan_phong']) ? $_POST['ngay_nhan_phong'] : '';
    $ngay_tra_phong = isset($_POST['ngay_tra_phong']) ? $_POST['ngay_tra_phong'] : '';
    $tien_coc = isset($_POST['tien_coc']) ? floatval($_POST['tien_coc']) : 0;
    $trang_thai = isset($_POST['trang_thai']) ? $_POST['trang_thai'] : 'đã đặt';

    // Validate dữ liệu
    $errors = [];
    if ($id_khach_hang <= 0) {
        $errors[] = "Vui lòng chọn khách hàng";
    }
    if ($id_phong <= 0) {
        $errors[] = "Vui lòng chọn phòng";
    }
    if (empty($ngay_nhan_phong)) {
        $errors[] = "Vui lòng chọn ngày nhận phòng";
    }
    if (empty($ngay_tra_phong)) {
        $errors[] = "Vui lòng chọn ngày trả phòng";
    }
    if ($ngay_nhan_phong > $ngay_tra_phong) {
        $errors[] = "Ngày nhận phòng không thể sau ngày trả phòng";
    }

    // Kiểm tra xem phòng có khả dụng không (trừ phòng hiện tại)
    if ($id_phong > 0) {
        $sql_check_phong = "SELECT dp.id FROM dat_phong dp 
                           WHERE dp.id_phong = ? AND dp.id != ? AND dp.trang_thai IN ('đã đặt', 'đã nhận phòng') 
                           AND ((? BETWEEN dp.ngay_nhan_phong AND dp.ngay_tra_phong) 
                                OR (? BETWEEN dp.ngay_nhan_phong AND dp.ngay_tra_phong)
                                OR (dp.ngay_nhan_phong BETWEEN ? AND ?)
                                OR (dp.ngay_tra_phong BETWEEN ? AND ?))";
        
        $result_check = fetchAllRows($sql_check_phong, [
            $id_phong, $id_dat_phong, 
            $ngay_nhan_phong, $ngay_tra_phong, 
            $ngay_nhan_phong, $ngay_tra_phong, 
            $ngay_nhan_phong, $ngay_tra_phong
        ]);
        
        if ($result_check && count($result_check) > 0) {
            $errors[] = "Phòng đã được đặt trong khoảng thời gian này";
        }
    }

    // Xử lý cập nhật nếu không có lỗi
    if (empty($errors)) {
        $sql_update = "UPDATE dat_phong 
                       SET id_khach_hang = ?, id_phong = ?, 
                           ngay_nhan_phong = ?, ngay_tra_phong = ?, 
                           tien_coc = ?, trang_thai = ? 
                       WHERE id = ?";
        
        $result = executeQuery($sql_update, [
            $id_khach_hang, $id_phong, 
            $ngay_nhan_phong, $ngay_tra_phong, 
            $tien_coc, $trang_thai, 
            $id_dat_phong
        ]);
        
        if ($result) {
            // Cập nhật trạng thái phòng nếu cần
            if ($trang_thai == 'đã nhận phòng') {
                executeQuery("UPDATE phong SET trang_thai = 'đang sử dụng' WHERE id = ?", [$id_phong]);
            } else if ($trang_thai == 'đã trả phòng') {
                executeQuery("UPDATE phong SET trang_thai = 'trống' WHERE id = ?", [$id_phong]);
            } else if ($trang_thai == 'đã đặt') {
                executeQuery("UPDATE phong SET trang_thai = 'đã đặt' WHERE id = ?", [$id_phong]);
            }
            
            $_SESSION['message'] = "Cập nhật thông tin đặt phòng thành công";
            $_SESSION['message_type'] = "success";
            header("Location: /quanlykhachsan/admin/dat_phong/index.php");
            exit;
        } else {
            $errors[] = "Đã xảy ra lỗi khi cập nhật thông tin đặt phòng";
        }
    }
}

// Lấy thông tin đặt phòng cần sửa
$sql_dat_phong = "SELECT * FROM dat_phong WHERE id = ?";
$dat_phong = fetchSingleRow($sql_dat_phong, [$id_dat_phong]);

if (!$dat_phong) {
    $_SESSION['message'] = "Không tìm thấy thông tin đặt phòng";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit;
}

// Lấy danh sách khách hàng
$sql_khach_hang = "SELECT id, ho_ten, so_cmnd FROM khach_hang ORDER BY ho_ten ASC";
$khach_hang_list = fetchAllRows($sql_khach_hang);

// Lấy danh sách phòng
$sql_phong = "SELECT id, so_phong, loai_phong, gia_ngay FROM phong ORDER BY so_phong ASC";
$phong_list = fetchAllRows($sql_phong);

// Lấy thông tin khách hàng hiện tại
$sql_current_khach_hang = "SELECT id, ho_ten, so_cmnd FROM khach_hang WHERE id = ?";
$current_khach_hang = fetchSingleRow($sql_current_khach_hang, [$dat_phong['id_khach_hang']]);

// Lấy thông tin phòng hiện tại
$sql_current_phong = "SELECT id, so_phong, loai_phong, gia_ngay FROM phong WHERE id = ?";
$current_phong = fetchSingleRow($sql_current_phong, [$dat_phong['id_phong']]);

// Include header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4><i class="fas fa-edit"></i> Chỉnh sửa đặt phòng</h4>
        </div>
        <div class="card-body">
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="id_khach_hang" class="form-label">Khách hàng <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_khach_hang" name="id_khach_hang" required>
                            <option value="">-- Chọn khách hàng --</option>
                            <?php foreach ($khach_hang_list as $khach_hang): ?>
                                <option value="<?php echo $khach_hang['id']; ?>" <?php echo ($dat_phong['id_khach_hang'] == $khach_hang['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($khach_hang['ho_ten'] . ' - ' . $khach_hang['so_cmnd']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="id_phong" class="form-label">Phòng <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_phong" name="id_phong" required>
                            <option value="">-- Chọn phòng --</option>
                            <?php foreach ($phong_list as $phong): ?>
                                <option value="<?php echo $phong['id']; ?>" <?php echo ($dat_phong['id_phong'] == $phong['id']) ? 'selected' : ''; ?> 
                                    data-gia="<?php echo $phong['gia_ngay']; ?>">
                                    <?php echo htmlspecialchars($phong['so_phong'] . ' - ' . ucfirst($phong['loai_phong']) . ' - ' . number_format($phong['gia_ngay']) . ' VNĐ/ngày'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="ngay_nhan_phong" class="form-label">Ngày nhận phòng <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="ngay_nhan_phong" name="ngay_nhan_phong" value="<?php echo $dat_phong['ngay_nhan_phong']; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="ngay_tra_phong" class="form-label">Ngày trả phòng <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="ngay_tra_phong" name="ngay_tra_phong" value="<?php echo $dat_phong['ngay_tra_phong']; ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="tien_coc" class="form-label">Tiền cọc (VNĐ)</label>
                        <input type="number" class="form-control" id="tien_coc" name="tien_coc" value="<?php echo $dat_phong['tien_coc']; ?>" min="0" step="10000">
                    </div>

                    <div class="col-md-6">
                        <label for="trang_thai" class="form-label">Trạng thái</label>
                        <select class="form-select" id="trang_thai" name="trang_thai">
                            <option value="đã đặt" <?php echo ($dat_phong['trang_thai'] == 'đã đặt') ? 'selected' : ''; ?>>Đã đặt</option>
                            <option value="đã nhận phòng" <?php echo ($dat_phong['trang_thai'] == 'đã nhận phòng') ? 'selected' : ''; ?>>Đã nhận phòng</option>
                            <option value="đã trả phòng" <?php echo ($dat_phong['trang_thai'] == 'đã trả phòng') ? 'selected' : ''; ?>>Đã trả phòng</option>
                            <option value="đã hủy" <?php echo ($dat_phong['trang_thai'] == 'đã hủy') ? 'selected' : ''; ?>>Đã hủy</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Thông tin thanh toán dự kiến</h5>
                                <div id="thong_tin_thanh_toan">
                                    <p>Số ngày ở: <span id="so_ngay">0</span> ngày</p>
                                    <p>Giá phòng: <span id="gia_phong"><?php echo number_format($current_phong['gia_ngay']); ?></span> VNĐ/ngày</p>
                                    <p>Tổng tiền phòng: <span id="tong_tien">0</span> VNĐ</p>
                                    <p>Đã cọc: <span id="da_coc"><?php echo number_format($dat_phong['tien_coc']); ?></span> VNĐ</p>
                                    <p>Còn lại: <span id="con_lai">0</span> VNĐ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div
                </div>

                <div class="d-flex mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Lưu thay đổi
                    </button>
                    <a href="/quanlykhachsan/admin/dat_phong/index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-times me-1"></i> Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hàm tính số ngày giữa hai ngày
    function tinhSoNgay() {
        var ngayNhan = new Date(document.getElementById('ngay_nhan_phong').value);
        var ngayTra = new Date(document.getElementById('ngay_tra_phong').value);
        
        // Kiểm tra nếu ngày hợp lệ
        if (ngayNhan && ngayTra && ngayNhan <= ngayTra) {
            // Tính số mili giây trong một ngày
            var oneDay = 24 * 60 * 60 * 1000;
            
            // Tính số ngày ở (làm tròn lên)
            var soNgay = Math.ceil((ngayTra - ngayNhan) / oneDay);
            
            return soNgay;
        }
        
        return 0;
    }
    
    // Hàm cập nhật thông tin thanh toán
    function capNhatThanhToan() {
        var soNgay = tinhSoNgay();
        var selectPhong = document.getElementById('id_phong');
        var giaPhong = 0;
        
        // Lấy giá phòng từ option được chọn
        if (selectPhong.selectedIndex > 0) {
            var selectedOption = selectPhong.options[selectPhong.selectedIndex];
            giaPhong = parseFloat(selectedOption.getAttribute('data-gia')) || 0;
        }
        
        var tienCoc = parseFloat(document.getElementById('tien_coc').value) || 0;
        var tongTien = soNgay * giaPhong;
        var conLai = tongTien - tienCoc;
        
        // Cập nhật hiển thị
        document.getElementById('so_ngay').textContent = soNgay;
        document.getElementById('gia_phong').textContent = giaPhong.toLocaleString('vi-VN');
        document.getElementById('tong_tien').textContent = tongTien.toLocaleString('vi-VN');
        document.getElementById('da_coc').textContent = tienCoc.toLocaleString('vi-VN');
        document.getElementById('con_lai').textContent = conLai.toLocaleString('vi-VN');
    }
    
    // Đăng ký sự kiện cho các trường nhập liệu
    document.getElementById('ngay_nhan_phong').addEventListener('change', capNhatThanhToan);
    document.getElementById('ngay_tra_phong').addEventListener('change', capNhatThanhToan);
    document.getElementById('id_phong').addEventListener('change', capNhatThanhToan);
    document.getElementById('tien_coc').addEventListener('input', capNhatThanhToan);
    
    // Cập nhật thông tin ban đầu
    capNhatThanhToan();
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>