<?php
ob_start();
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để sử dụng chức năng này!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Import file header và các file cần thiết
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Khai báo biến thông báo
$message = '';
$message_type = '';

// Xử lý form thêm dịch vụ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $id_dat_phong = $_POST['id_dat_phong'] ?? 0;
    $id_dich_vu = $_POST['id_dich_vu'] ?? 0;
    $so_luong = $_POST['so_luong'] ?? 1;
    $ngay_su_dung = $_POST['ngay_su_dung'] ?? date('Y-m-d');
    
    // Kiểm tra dữ liệu
    if (empty($id_dat_phong) || empty($id_dich_vu) || empty($so_luong) || empty($ngay_su_dung)) {
        $message = "Vui lòng điền đầy đủ thông tin!";
        $message_type = "danger";
    } else {
        // Lấy giá dịch vụ từ cơ sở dữ liệu
        $dich_vu = fetchSingleRow("SELECT gia FROM dich_vu WHERE id = ?", [$id_dich_vu]);
        
        if ($dich_vu) {
            $gia = $dich_vu['gia'];
            $thanh_tien = $gia * $so_luong;
            
            // Thêm vào bảng sử dụng dịch vụ
            $sql = "INSERT INTO su_dung_dich_vu (id_dat_phong, id_dich_vu, so_luong, ngay_su_dung, thanh_tien) 
                    VALUES (?, ?, ?, ?, ?)";
            $result = executeQuery($sql, [$id_dat_phong, $id_dich_vu, $so_luong, $ngay_su_dung, $thanh_tien]);
            
            if ($result) {
                // Cập nhật tổng tiền dịch vụ trong hóa đơn nếu đã có hóa đơn
                $sql_update_hd = "UPDATE hoa_don SET tong_tien_dich_vu = (
                                   SELECT SUM(thanh_tien) FROM su_dung_dich_vu WHERE id_dat_phong = ?
                                  ),
                                  tong_thanh_toan = tong_tien_phong + (
                                   SELECT SUM(thanh_tien) FROM su_dung_dich_vu WHERE id_dat_phong = ?
                                  )
                                  WHERE id_dat_phong = ?";
                executeQuery($sql_update_hd, [$id_dat_phong, $id_dat_phong, $id_dat_phong]);
                
                $message = "Đã thêm dịch vụ thành công!";
                $message_type = "success";
            } else {
                $message = "Lỗi khi thêm dịch vụ!";
                $message_type = "danger";
            }
        } else {
            $message = "Không tìm thấy dịch vụ!";
            $message_type = "danger";
        }
    }
}

// Xử lý khi xóa dịch vụ đã sử dụng
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_su_dung = $_GET['id'];
    $id_dat_phong = $_GET['id_dat_phong'] ?? 0;
    
    // Kiểm tra trạng thái đặt phòng trước khi xóa
    $check_status = fetchSingleRow("SELECT dp.trang_thai FROM dat_phong dp 
                                   JOIN su_dung_dich_vu sd ON dp.id = sd.id_dat_phong 
                                   WHERE sd.id = ?", [$id_su_dung]);
    
    if ($check_status && $check_status['trang_thai'] != 'đã trả phòng') {
        // Xóa dịch vụ
        $result = executeQuery("DELETE FROM su_dung_dich_vu WHERE id = ?", [$id_su_dung]);
        
        if ($result) {
            // Cập nhật lại tổng tiền dịch vụ trong hóa đơn
            if ($id_dat_phong > 0) {
                $sql_update_hd = "UPDATE hoa_don SET tong_tien_dich_vu = (
                                   SELECT COALESCE(SUM(thanh_tien), 0) FROM su_dung_dich_vu WHERE id_dat_phong = ?
                                  ),
                                  tong_thanh_toan = tong_tien_phong + (
                                   SELECT COALESCE(SUM(thanh_tien), 0) FROM su_dung_dich_vu WHERE id_dat_phong = ?
                                  )
                                  WHERE id_dat_phong = ?";
                executeQuery($sql_update_hd, [$id_dat_phong, $id_dat_phong, $id_dat_phong]);
            }
            
            $_SESSION['message'] = "Đã xóa dịch vụ thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Lỗi khi xóa dịch vụ!";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Không thể xóa dịch vụ cho đơn đặt phòng đã trả phòng!";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: /quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php");
    exit();
}

// Lấy danh sách phòng đang sử dụng (đã nhận phòng)
$phong_dang_su_dung = fetchAllRows("SELECT dp.id, p.so_phong, kh.ho_ten as ten_khach_hang, 
                                     dp.ngay_nhan_phong, dp.ngay_tra_phong
                                     FROM dat_phong dp
                                     JOIN phong p ON dp.id_phong = p.id
                                     JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                                     WHERE dp.trang_thai = 'đã nhận phòng'
                                     ORDER BY p.so_phong");

// Lấy danh sách dịch vụ
$dich_vu_list = fetchAllRows("SELECT * FROM dich_vu WHERE trang_thai = 1 ORDER BY ten_dich_vu");

// Hiển thị danh sách dịch vụ đã sử dụng nếu chọn phòng
$selected_room = isset($_GET['room_id']) ? $_GET['room_id'] : null;
$dich_vu_da_su_dung = [];

if ($selected_room) {
    $dich_vu_da_su_dung = fetchAllRows("SELECT sd.id, sd.ngay_su_dung, dv.ten_dich_vu, 
                                         sd.so_luong, dv.gia, sd.thanh_tien, sd.id_dat_phong
                                        FROM su_dung_dich_vu sd
                                        JOIN dich_vu dv ON sd.id_dich_vu = dv.id
                                        WHERE sd.id_dat_phong = ?
                                        ORDER BY sd.ngay_su_dung DESC", [$selected_room]);
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Quản lý sử dụng dịch vụ</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Chọn phòng đang sử dụng -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-door-open me-2"></i>Chọn phòng đang sử dụng</h5>
        </div>
        <div class="card-body">
            <?php if ($phong_dang_su_dung && count($phong_dang_su_dung) > 0): ?>
                <form action="" method="get" class="row g-3">
                    <div class="col-md-6">
                        <select name="room_id" class="form-select" required>
                            <option value="">-- Chọn phòng --</option>
                            <?php foreach ($phong_dang_su_dung as $phong): ?>
                                <option value="<?php echo $phong['id']; ?>" <?php echo ($selected_room == $phong['id']) ? 'selected' : ''; ?>>
                                    Phòng <?php echo $phong['so_phong']; ?> - Khách: <?php echo $phong['ten_khach_hang']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Chọn
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> Hiện không có phòng nào đang được sử dụng.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_room): ?>
        <!-- Form thêm dịch vụ -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Thêm dịch vụ</h5>
            </div>
            <div class="card-body">
                <form action="" method="post" class="row g-3">
                    <input type="hidden" name="id_dat_phong" value="<?php echo $selected_room; ?>">
                    
                    <div class="col-md-4">
                        <label for="id_dich_vu" class="form-label">Dịch vụ</label>
                        <select name="id_dich_vu" id="id_dich_vu" class="form-select" required>
                            <option value="">-- Chọn dịch vụ --</option>
                            <?php foreach ($dich_vu_list as $dich_vu): ?>
                                <option value="<?php echo $dich_vu['id']; ?>" data-price="<?php echo $dich_vu['gia']; ?>">
                                    <?php echo $dich_vu['ten_dich_vu']; ?> - <?php echo number_format($dich_vu['gia'], 0, ',', '.'); ?> VND
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="so_luong" class="form-label">Số lượng</label>
                        <input type="number" name="so_luong" id="so_luong" class="form-control" value="1" min="1" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="ngay_su_dung" class="form-label">Ngày sử dụng</label>
                        <input type="date" name="ngay_su_dung" id="ngay_su_dung" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="thanh_tien" class="form-label">Thành tiền (VND)</label>
                        <input type="text" id="thanh_tien" class="form-control" readonly>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" name="submit" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> Thêm dịch vụ
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách dịch vụ đã sử dụng -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Danh sách dịch vụ đã sử dụng</h5>
            </div>
            <div class="card-body">
                <?php if ($dich_vu_da_su_dung && count($dich_vu_da_su_dung) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>STT</th>
                                    <th>Ngày sử dụng</th>
                                    <th>Tên dịch vụ</th>
                                    <th>Đơn giá (VND)</th>
                                    <th>Số lượng</th>
                                    <th>Thành tiền (VND)</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $stt = 1; foreach ($dich_vu_da_su_dung as $item): ?>
                                <tr>
                                    <td><?php echo $stt++; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['ngay_su_dung'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['ten_dich_vu']); ?></td>
                                    <td class="text-end"><?php echo number_format($item['gia'], 0, ',', '.'); ?></td>
                                    <td class="text-center"><?php echo $item['so_luong']; ?></td>
                                    <td class="text-end"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <a href="?action=delete&id=<?php echo $item['id']; ?>&id_dat_phong=<?php echo $item['id_dat_phong']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa dịch vụ này không?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <!-- Tổng thành tiền -->
                                <?php 
                                $tong_tien = 0;
                                foreach ($dich_vu_da_su_dung as $item) {
                                    $tong_tien += $item['thanh_tien'];
                                }
                                ?>
                                <tr class="table-info">
                                    <td colspan="5" class="text-end fw-bold">Tổng tiền:</td>
                                    <td class="text-end fw-bold"><?php echo number_format($tong_tien, 0, ',', '.'); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i> Chưa có dịch vụ nào được sử dụng cho đặt phòng này.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tính thành tiền khi thay đổi dịch vụ hoặc số lượng
    const dichVuSelect = document.getElementById('id_dich_vu');
    const soLuongInput = document.getElementById('so_luong');
    const thanhTienInput = document.getElementById('thanh_tien');
    
    function tinhThanhTien() {
        const selectedOption = dichVuSelect.options[dichVuSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const price = parseFloat(selectedOption.dataset.price);
            const soLuong = parseInt(soLuongInput.value);
            
            if (!isNaN(price) && !isNaN(soLuong) && soLuong > 0) {
                const thanhTien = price * soLuong;
                thanhTienInput.value = thanhTien.toLocaleString('vi-VN');
            } else {
                thanhTienInput.value = '';
            }
        } else {
            thanhTienInput.value = '';
        }
    }
    
    if (dichVuSelect && soLuongInput && thanhTienInput) {
        dichVuSelect.addEventListener('change', tinhThanhTien);
        soLuongInput.addEventListener('input', tinhThanhTien);
        
        // Tính lần đầu nếu đã có giá trị
        tinhThanhTien();
    }
});
</script>

<?php
// Import file footer
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush(); // Xóa bộ nhớ đệm
?>