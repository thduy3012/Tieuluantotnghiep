<?php
// Bắt đầu phiên làm việc
session_start();

// Import file cấu hình và các function
require_once __DIR__ . '/../../config/config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Lưu thông báo lỗi
    $_SESSION['message'] = 'Vui lòng đăng nhập để truy cập trang này!';
    $_SESSION['message_type'] = 'danger';
    
    // Chuyển hướng về trang đăng nhập
    header('Location: /quanlykhachsan/auth/login.php');
    exit;
}

// Kiểm tra quyền quản lý
if ($_SESSION['role'] !== 'quản lý') {
    // Lưu thông báo lỗi
    $_SESSION['message'] = 'Bạn không có quyền truy cập chức năng này!';
    $_SESSION['message_type'] = 'danger';
    
    // Chuyển hướng về trang chủ admin
    header('Location: /quanlykhachsan/admin/index.php');
    exit;
}

// Kiểm tra xem có id được truyền vào không
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = 'Vui lòng chọn nhân viên để xóa!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/nhan_vien/index.php');
    exit;
}

$id = (int)$_GET['id'];

// Kiểm tra xem nhân viên có tồn tại không
$nhan_vien = fetchSingleRow("SELECT * FROM nhan_vien WHERE id = ?", [$id]);

if (!$nhan_vien) {
    $_SESSION['message'] = 'Không tìm thấy nhân viên với ID đã chọn!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/nhan_vien/index.php');
    exit;
}

// Kiểm tra xem nhân viên có liên quan đến đặt phòng không
$check_dat_phong = fetchSingleRow("SELECT COUNT(*) AS count FROM dat_phong WHERE id_nhan_vien = ?", [$id]);

if ($check_dat_phong && $check_dat_phong['count'] > 0) {
    $_SESSION['message'] = 'Không thể xóa nhân viên này vì đã có liên kết với ' . $check_dat_phong['count'] . ' đơn đặt phòng!';
    $_SESSION['message_type'] = 'warning';
    header('Location: /quanlykhachsan/admin/nhan_vien/index.php');
    exit;
}

// Kiểm tra xem có phải đang tự xóa tài khoản đang đăng nhập không
if ($_SESSION['user_id'] == $id) {
    $_SESSION['message'] = 'Bạn không thể xóa tài khoản của chính mình!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/nhan_vien/index.php');
    exit;
}

// Xử lý xóa nhân viên nếu người dùng xác nhận
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    try {
        // Thực hiện xóa nhân viên
        $result = executeQuery("DELETE FROM nhan_vien WHERE id = ?", [$id]);
        
        if ($result) {
            $_SESSION['message'] = 'Xóa nhân viên thành công!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Có lỗi xảy ra khi xóa nhân viên!';
            $_SESSION['message_type'] = 'danger';
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Lỗi hệ thống: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    
    header('Location: /quanlykhachsan/admin/nhan_vien/index.php');
    exit;
}

// Hiển thị trang xác nhận xóa
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-danger text-white">
            <h2><i class="fas fa-trash-alt me-2"></i>Xóa nhân viên</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Xác nhận xóa nhân viên</h5>
                <p>Bạn có chắc chắn muốn xóa nhân viên <strong><?php echo htmlspecialchars($nhan_vien['ho_ten']); ?></strong> (<?php echo htmlspecialchars($nhan_vien['ten_dang_nhap']); ?>) không?</p>
                <p><strong>Lưu ý:</strong> Hành động này không thể hoàn tác!</p>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%">ID:</th>
                            <td><?php echo htmlspecialchars($nhan_vien['id']); ?></td>
                        </tr>
                        <tr>
                            <th>Họ tên:</th>
                            <td><?php echo htmlspecialchars($nhan_vien['ho_ten']); ?></td>
                        </tr>
                        <tr>
                            <th>Tên đăng nhập:</th>
                            <td><?php echo htmlspecialchars($nhan_vien['ten_dang_nhap']); ?></td>
                        </tr>
                        <tr>
                            <th>Chức vụ:</th>
                            <td><?php echo htmlspecialchars($nhan_vien['chuc_vu']); ?></td>
                        </tr>
                        <tr>
                            <th>Trạng thái:</th>
                            <td>
                                <?php if ($nhan_vien['trang_thai'] == 1): ?>
                                    <span class="badge bg-success">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Bị khóa</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $id); ?>">
                <input type="hidden" name="confirm_delete" value="yes">
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Xác nhận xóa
                    </button>
                    <a href="/quanlykhachsan/admin/nhan_vien/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Quay lại
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>