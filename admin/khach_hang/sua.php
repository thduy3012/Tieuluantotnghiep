<?php
// Bắt đầu phiên làm việc nếu chưa bắt đầu
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
require_once __DIR__ . '/../../auth/check_login.php';

// Import file cấu hình và kết nối CSDL
require_once __DIR__ . '/../../config/config.php';

// Kiểm tra xem có ID khách hàng được truyền vào hay không
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = 'Không tìm thấy ID khách hàng';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Lấy thông tin khách hàng từ CSDL
$khach_hang = fetchSingleRow("SELECT * FROM khach_hang WHERE id = ?", [$id]);

// Kiểm tra xem khách hàng có tồn tại không
if (!$khach_hang) {
    $_SESSION['message'] = 'Không tìm thấy thông tin khách hàng';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php');
    exit;
}

// Xử lý khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ho_ten = trim($_POST['ho_ten']);
    $so_cmnd = trim($_POST['so_cmnd']);
    $so_dien_thoai = trim($_POST['so_dien_thoai']);
    $dia_chi = trim($_POST['dia_chi']);
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    if (empty($ho_ten)) {
        $errors[] = 'Họ tên khách hàng không được để trống';
    }
    
    // Kiểm tra xem số CMND đã tồn tại chưa (nếu thay đổi)
    if (!empty($so_cmnd) && $so_cmnd !== $khach_hang['so_cmnd']) {
        $existing_cmnd = fetchSingleRow("SELECT id FROM khach_hang WHERE so_cmnd = ? AND id != ?", [$so_cmnd, $id]);
        if ($existing_cmnd) {
            $errors[] = 'Số CMND/CCCD đã tồn tại trong hệ thống';
        }
    }
    
    // Kiểm tra xem số điện thoại đã tồn tại chưa (nếu thay đổi)
    if (!empty($so_dien_thoai) && $so_dien_thoai !== $khach_hang['so_dien_thoai']) {
        $existing_phone = fetchSingleRow("SELECT id FROM khach_hang WHERE so_dien_thoai = ? AND id != ?", [$so_dien_thoai, $id]);
        if ($existing_phone) {
            $errors[] = 'Số điện thoại đã tồn tại trong hệ thống';
        }
    }
    
    // Nếu không có lỗi, tiến hành cập nhật
    if (empty($errors)) {
        $sql = "UPDATE khach_hang SET ho_ten = ?, so_cmnd = ?, so_dien_thoai = ?, dia_chi = ? WHERE id = ?";
        $result = executeQuery($sql, [$ho_ten, $so_cmnd, $so_dien_thoai, $dia_chi, $id]);
        
        if ($result) {
            $_SESSION['message'] = 'Cập nhật thông tin khách hàng thành công';
            $_SESSION['message_type'] = 'success';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Có lỗi xảy ra khi cập nhật thông tin khách hàng';
        }
    }
}

// Kết nối file header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card shadow">
    <div class="card-header bg-primary text-white">
        <h5 class="m-0">
            <i class="fas fa-user-edit me-2"></i>Sửa thông tin khách hàng
        </h5>
    </div>
    <div class="card-body">
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="mb-3">
                <label for="ho_ten" class="form-label">Họ tên <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="ho_ten" name="ho_ten" value="<?php echo htmlspecialchars($khach_hang['ho_ten']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="so_cmnd" class="form-label">Số CMND/CCCD</label>
                <input type="text" class="form-control" id="so_cmnd" name="so_cmnd" value="<?php echo htmlspecialchars($khach_hang['so_cmnd']); ?>">
            </div>
            
            <div class="mb-3">
                <label for="so_dien_thoai" class="form-label">Số điện thoại</label>
                <input type="text" class="form-control" id="so_dien_thoai" name="so_dien_thoai" value="<?php echo htmlspecialchars($khach_hang['so_dien_thoai']); ?>">
            </div>
            
            <div class="mb-3">
                <label for="dia_chi" class="form-label">Địa chỉ</label>
                <textarea class="form-control" id="dia_chi" name="dia_chi" rows="3"><?php echo htmlspecialchars($khach_hang['dia_chi']); ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Hiển thị thông tin lịch sử đặt phòng của khách hàng
$lich_su_dat_phong = fetchAllRows("
    SELECT dp.*, p.so_phong, p.loai_phong, p.gia_ngay 
    FROM dat_phong dp 
    JOIN phong p ON dp.id_phong = p.id 
    WHERE dp.id_khach_hang = ? 
    ORDER BY dp.ngay_nhan_phong DESC", [$id]);

if ($lich_su_dat_phong && count($lich_su_dat_phong) > 0):
?>

<div class="card shadow mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="m-0">
            <i class="fas fa-history me-2"></i>Lịch sử đặt phòng
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Số phòng</th>
                        <th>Loại phòng</th>
                        <th>Ngày nhận</th>
                        <th>Ngày trả</th>
                        <th>Tiền cọc</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lich_su_dat_phong as $item): ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['so_phong']); ?></td>
                        <td>
                            <?php
                            switch ($item['loai_phong']) {
                                case 'đơn': echo 'Phòng đơn'; break;
                                case 'đôi': echo 'Phòng đôi'; break;
                                case 'vip': echo 'Phòng VIP'; break;
                                default: echo htmlspecialchars($item['loai_phong']);
                            }
                            ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($item['ngay_nhan_phong'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($item['ngay_tra_phong'])); ?></td>
                        <td><?php echo number_format($item['tien_coc'], 0, ',', '.'); ?> VNĐ</td>
                        <td>
                            <?php
                            switch ($item['trang_thai']) {
                                case 'đã đặt': 
                                    echo '<span class="badge bg-warning text-dark">Đã đặt</span>';
                                    break;
                                case 'đã nhận phòng': 
                                    echo '<span class="badge bg-primary">Đã nhận phòng</span>';
                                    break;
                                case 'đã trả phòng': 
                                    echo '<span class="badge bg-success">Đã trả phòng</span>';
                                    break;
                                case 'đã hủy': 
                                    echo '<span class="badge bg-danger">Đã hủy</span>';
                                    break;
                                default: 
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($item['trang_thai']) . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
// Kết nối file footer
include_once __DIR__ . '/../../includes/footer.php';
?>