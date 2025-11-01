<?php
// Bắt đầu phiên làm việc nếu chưa bắt đầu
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Kiểm tra quyền truy cập (tùy chọn - có thể chỉ cho phép quản lý sửa phòng)
// if ($_SESSION['role'] !== 'quản lý') {
//     $_SESSION['message'] = "Bạn không có quyền truy cập trang này!";
//     $_SESSION['message_type'] = "danger";
//     header("Location: /quanlykhachsan/admin/index.php");
//     exit;
// }

// Import file cấu hình
require_once __DIR__ . '/../../config/config.php';

// Kiểm tra xem có id phòng được truyền vào không
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Không tìm thấy thông tin phòng!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/phong/index.php");
    exit;
}

$id = intval($_GET['id']);
$phong = null;

// Lấy thông tin phòng từ database
$sql = "SELECT * FROM phong WHERE id = ?";
$phong = fetchSingleRow($sql, [$id]);

if (!$phong) {
    $_SESSION['message'] = "Không tìm thấy thông tin phòng!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/phong/index.php");
    exit;
}

// Xử lý khi form được submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $so_phong = trim($_POST['so_phong']);
    $loai_phong = $_POST['loai_phong'];
    $gia_ngay = floatval($_POST['gia_ngay']);
    $trang_thai = $_POST['trang_thai'];
    $errors = [];

    // Kiểm tra các trường input
    if (empty($so_phong)) {
        $errors[] = "Số phòng không được để trống";
    }
    
    if (empty($loai_phong)) {
        $errors[] = "Loại phòng không được để trống";
    }
    
    if ($gia_ngay <= 0) {
        $errors[] = "Giá phòng phải lớn hơn 0";
    }

    // Kiểm tra số phòng đã tồn tại chưa (nếu số phòng bị thay đổi)
    if ($so_phong != $phong['so_phong']) {
        $check_sql = "SELECT COUNT(*) as count FROM phong WHERE so_phong = ? AND id != ?";
        $result = fetchSingleRow($check_sql, [$so_phong, $id]);
        
        if ($result && $result['count'] > 0) {
            $errors[] = "Số phòng đã tồn tại, vui lòng chọn số phòng khác";
        }
    }

    // Nếu không có lỗi, thực hiện cập nhật
    if (empty($errors)) {
        $update_sql = "UPDATE phong SET so_phong = ?, loai_phong = ?, gia_ngay = ?, trang_thai = ? WHERE id = ?";
        $result = executeQuery($update_sql, [$so_phong, $loai_phong, $gia_ngay, $trang_thai, $id]);
        
        if ($result) {
            $_SESSION['message'] = "Cập nhật thông tin phòng thành công!";
            $_SESSION['message_type'] = "success";
            header("Location: /quanlykhachsan/admin/phong/index.php");
            exit;
        } else {
            $errors[] = "Có lỗi xảy ra khi cập nhật thông tin phòng";
        }
    }
}

// Hiển thị phần header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mt-4 mb-4">Chỉnh sửa thông tin phòng</h2>
            
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="so_phong" class="form-label">Số phòng</label>
                            <input type="text" class="form-control" id="so_phong" name="so_phong" 
                                   value="<?php echo htmlspecialchars($phong['so_phong']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="loai_phong" class="form-label">Loại phòng</label>
                            <select class="form-select" id="loai_phong" name="loai_phong" required>
                                <option value="">Chọn loại phòng</option>
                                <option value="đơn" <?php echo ($phong['loai_phong'] == 'đơn') ? 'selected' : ''; ?>>Phòng đơn</option>
                                <option value="đôi" <?php echo ($phong['loai_phong'] == 'đôi') ? 'selected' : ''; ?>>Phòng đôi</option>
                                <option value="vip" <?php echo ($phong['loai_phong'] == 'vip') ? 'selected' : ''; ?>>Phòng VIP</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="gia_ngay" class="form-label">Giá phòng (VNĐ/ngày)</label>
                            <input type="number" class="form-control" id="gia_ngay" name="gia_ngay" 
                                   value="<?php echo htmlspecialchars($phong['gia_ngay']); ?>" required min="0" step="10000">
                        </div>
                        
                        <div class="mb-3">
                            <label for="trang_thai" class="form-label">Trạng thái</label>
                            <select class="form-select" id="trang_thai" name="trang_thai" required>
                                <option value="trống" <?php echo ($phong['trang_thai'] == 'trống') ? 'selected' : ''; ?>>Trống</option>
                                <option value="đã đặt" <?php echo ($phong['trang_thai'] == 'đã đặt') ? 'selected' : ''; ?>>Đã đặt</option>
                                <option value="đang sử dụng" <?php echo ($phong['trang_thai'] == 'đang sử dụng') ? 'selected' : ''; ?>>Đang sử dụng</option>
                                <option value="bảo trì" <?php echo ($phong['trang_thai'] == 'bảo trì') ? 'selected' : ''; ?>>Bảo trì</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Lưu thay đổi
                            </button>
                            <a href="/quanlykhachsan/admin/phong/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Hiển thị phần footer
include_once __DIR__ . '/../../includes/footer.php';
?>