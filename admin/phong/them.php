<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Kiểm tra quyền hạn (nếu cần thiết)
// if ($_SESSION['role'] !== 'quản lý' && $_SESSION['role'] !== 'nhân viên') {
//     $_SESSION['message'] = "Bạn không có quyền truy cập trang này!";
//     $_SESSION['message_type'] = "danger";
//     header("Location: /quanlykhachsan/index.php");
//     exit;
// }

// Import file cấu hình và các hàm cần thiết
require_once __DIR__ . '/../../config/config.php';

// Xử lý thêm phòng khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $so_phong = trim($_POST['so_phong']);
    $loai_phong = $_POST['loai_phong'];
    $gia_ngay = (float)$_POST['gia_ngay'];
    $trang_thai = $_POST['trang_thai'];
    
    // Kiểm tra dữ liệu
    $errors = [];
    
    // Kiểm tra số phòng
    if (empty($so_phong)) {
        $errors[] = "Vui lòng nhập số phòng";
    } else {
        // Kiểm tra xem số phòng đã tồn tại chưa
        $check_query = "SELECT id FROM phong WHERE so_phong = ?";
        $check_result = fetchSingleRow($check_query, [$so_phong]);
        
        if ($check_result) {
            $errors[] = "Số phòng đã tồn tại. Vui lòng chọn số phòng khác";
        }
    }
    
    // Kiểm tra loại phòng
    if (empty($loai_phong) || !in_array($loai_phong, ['đơn', 'đôi', 'vip'])) {
        $errors[] = "Vui lòng chọn loại phòng hợp lệ";
    }
    
    // Kiểm tra giá ngày
    if (empty($gia_ngay) || $gia_ngay <= 0) {
        $errors[] = "Vui lòng nhập giá ngày hợp lệ";
    }
    
    // Kiểm tra trạng thái
    if (empty($trang_thai) || !in_array($trang_thai, ['trống', 'đã đặt', 'đang sử dụng', 'bảo trì'])) {
        $errors[] = "Vui lòng chọn trạng thái hợp lệ";
    }
    
    // Nếu không có lỗi, thêm phòng vào cơ sở dữ liệu
    if (empty($errors)) {
        $sql = "INSERT INTO phong (so_phong, loai_phong, gia_ngay, trang_thai) VALUES (?, ?, ?, ?)";
        $result = executeQuery($sql, [$so_phong, $loai_phong, $gia_ngay, $trang_thai]);
        
        if ($result) {
            $_SESSION['message'] = "Thêm phòng mới thành công!";
            $_SESSION['message_type'] = "success";
            header("Location: index.php");
            exit;
        } else {
            $errors[] = "Có lỗi xảy ra khi thêm phòng. Vui lòng thử lại!";
        }
    }
}
?>

<?php include_once __DIR__ . '/../../includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Thêm Phòng Mới
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="so_phong" class="form-label">Số Phòng <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="so_phong" name="so_phong" required 
                                       value="<?php echo isset($_POST['so_phong']) ? htmlspecialchars($_POST['so_phong']) : ''; ?>">
                                <div class="form-text">Nhập số phòng (ví dụ: 101, 202, 301)</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="loai_phong" class="form-label">Loại Phòng <span class="text-danger">*</span></label>
                                <select class="form-select" id="loai_phong" name="loai_phong" required>
                                    <option value="">-- Chọn loại phòng --</option>
                                    <option value="đơn" <?php echo (isset($_POST['loai_phong']) && $_POST['loai_phong'] === 'đơn') ? 'selected' : ''; ?>>Phòng đơn</option>
                                    <option value="đôi" <?php echo (isset($_POST['loai_phong']) && $_POST['loai_phong'] === 'đôi') ? 'selected' : ''; ?>>Phòng đôi</option>
                                    <option value="vip" <?php echo (isset($_POST['loai_phong']) && $_POST['loai_phong'] === 'vip') ? 'selected' : ''; ?>>Phòng VIP</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="gia_ngay" class="form-label">Giá Ngày (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="gia_ngay" name="gia_ngay" min="0" step="1000" required
                                       value="<?php echo isset($_POST['gia_ngay']) ? htmlspecialchars($_POST['gia_ngay']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="trang_thai" class="form-label">Trạng Thái <span class="text-danger">*</span></label>
                                <select class="form-select" id="trang_thai" name="trang_thai" required>
                                    <option value="">-- Chọn trạng thái --</option>
                                    <option value="trống" <?php echo (isset($_POST['trang_thai']) && $_POST['trang_thai'] === 'trống') ? 'selected' : ''; ?>>Trống</option>
                                    <option value="đã đặt" <?php echo (isset($_POST['trang_thai']) && $_POST['trang_thai'] === 'đã đặt') ? 'selected' : ''; ?>>Đã đặt</option>
                                    <option value="đang sử dụng" <?php echo (isset($_POST['trang_thai']) && $_POST['trang_thai'] === 'đang sử dụng') ? 'selected' : ''; ?>>Đang sử dụng</option>
                                    <option value="bảo trì" <?php echo (isset($_POST['trang_thai']) && $_POST['trang_thai'] === 'bảo trì') ? 'selected' : ''; ?>>Bảo trì</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Lưu Phòng
                                </button>
                                <a href="index.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i>Quay Lại
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>