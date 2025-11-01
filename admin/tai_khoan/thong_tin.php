<?php
// Bắt đầu phiên làm việc 
// IMPORTANT: Move session_start() to the very top, before ANY output
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit; // Critical to prevent further script execution
}

// Import file config và header
require_once __DIR__ . '/../../config/config.php';

// Xử lý cập nhật thông tin - MOVE THIS BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $ho_ten = trim($_POST['ho_ten']);
    
    // Validate dữ liệu
    if (empty($ho_ten)) {
        $_SESSION['message'] = "Họ tên không được để trống!";
        $_SESSION['message_type'] = "danger";
        header("Location: /quanlykhachsan/admin/tai_khoan/thong_tin.php");
        exit;
    } else {
        try {
            $sql = "UPDATE nhan_vien SET ho_ten = ? WHERE id = ?";
            $result = executeQuery($sql, [$ho_ten, $_SESSION['user_id']]);
            
            if ($result) {
                $_SESSION['fullname'] = $ho_ten; // Cập nhật session
                $_SESSION['message'] = "Cập nhật thông tin thành công!";
                $_SESSION['message_type'] = "success";
                
                header("Location: /quanlykhachsan/admin/tai_khoan/thong_tin.php");
                exit;
            } else {
                throw new Exception("Có lỗi xảy ra khi cập nhật thông tin!");
            }
        } catch (Exception $e) {
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['message_type'] = "danger";
            header("Location: /quanlykhachsan/admin/tai_khoan/thong_tin.php");
            exit;
        }
    }
}

// Lấy thông tin nhân viên đăng nhập
$nhan_vien_id = $_SESSION['user_id'];
$nhan_vien = null;

try {
    $sql = "SELECT * FROM nhan_vien WHERE id = ?";
    $nhan_vien = fetchSingleRow($sql, [$nhan_vien_id]);
    
    if (!$nhan_vien) {
        throw new Exception("Không tìm thấy thông tin nhân viên!");
    }
} catch (Exception $e) {
    $_SESSION['message'] = $e->getMessage();
    $_SESSION['message_type'] = "danger";
}

// NOW include header AFTER session and database operations
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Thông tin tài khoản</h5>
            </div>
            <div class="card-body">
                <?php if ($nhan_vien): ?>
                    <div class="row mb-4">
                        <div class="col-md-4 text-center mb-3 mb-md-0">
                            <div class="profile-image bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                                <i class="fas fa-user fa-5x text-primary"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="ten_dang_nhap" class="form-label">Tên đăng nhập</label>
                                    <input type="text" class="form-control" id="ten_dang_nhap" value="<?php echo htmlspecialchars($nhan_vien['ten_dang_nhap']); ?>" readonly>
                                    <div class="form-text text-muted">Tên đăng nhập không thể thay đổi</div>
                                </div>
                                <div class="mb-3">
                                    <label for="ho_ten" class="form-label">Họ và tên</label>
                                    <input type="text" class="form-control" id="ho_ten" name="ho_ten" value="<?php echo htmlspecialchars($nhan_vien['ho_ten']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="chuc_vu" class="form-label">Chức vụ</label>
                                    <input type="text" class="form-control" id="chuc_vu" value="<?php echo htmlspecialchars($nhan_vien['chuc_vu']); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="trang_thai" class="form-label">Trạng thái tài khoản</label>
                                    <input type="text" class="form-control" id="trang_thai" value="<?php echo $nhan_vien['trang_thai'] ? 'Đang hoạt động' : 'Đã khóa'; ?>" readonly>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <button type="submit" name="update_info" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Lưu thông tin
                                    </button>
                                    <a href="/quanlykhachsan/admin/tai_khoan/doi_mat_khau.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-key me-2"></i>Đổi mật khẩu
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Lưu ý: Nếu bạn muốn thay đổi mật khẩu, vui lòng sử dụng chức năng "Đổi mật khẩu"
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>Không thể tải thông tin tài khoản. Vui lòng thử lại sau!
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="/quanlykhachsan/admin/index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại trang quản trị
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
?>