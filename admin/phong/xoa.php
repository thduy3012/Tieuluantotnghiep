<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Lưu URL hiện tại để quay lại sau khi đăng nhập
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Kiểm tra quyền quản lý (tùy chọn, có thể bỏ nếu cho phép nhân viên xóa phòng)
if ($_SESSION['role'] !== 'quản lý') {
    $_SESSION['message'] = "Bạn không có quyền thực hiện chức năng này!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/phong/index.php");
    exit;
}

// Import file cấu hình
require_once __DIR__ . '/../../config/config.php';

// Lấy ID phòng từ URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID phòng không hợp lệ!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/phong/index.php");
    exit;
}

$id_phong = intval($_GET['id']);

// Kiểm tra xác nhận xóa bằng method POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    
    // Kiểm tra xem phòng có đang được đặt hoặc sử dụng không
    $check_booking_sql = "SELECT COUNT(*) as count FROM dat_phong 
                         WHERE id_phong = ? 
                         AND (trang_thai = 'đã đặt' OR trang_thai = 'đã nhận phòng')";
    $check_result = fetchSingleRow($check_booking_sql, [$id_phong]);
    
    if ($check_result && $check_result['count'] > 0) {
        $_SESSION['message'] = "Không thể xóa phòng vì phòng đang được đặt hoặc sử dụng!";
        $_SESSION['message_type'] = "danger";
        header("Location: /quanlykhachsan/admin/phong/index.php");
        exit;
    }
    
    // Tiến hành xóa phòng
    $delete_sql = "DELETE FROM phong WHERE id = ?";
    
    if (executeQuery($delete_sql, [$id_phong])) {
        $_SESSION['message'] = "Xóa phòng thành công!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Lỗi khi xóa phòng!";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: /quanlykhachsan/admin/phong/index.php");
    exit;
}

// Lấy thông tin phòng để hiển thị
$sql = "SELECT * FROM phong WHERE id = ?";
$phong = fetchSingleRow($sql, [$id_phong]);

if (!$phong) {
    $_SESSION['message'] = "Không tìm thấy thông tin phòng!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/phong/index.php");
    exit;
}

// Include header
include_once __DIR__ . '/../../includes/header.php';

?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Xóa phòng</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo:</strong> Bạn có chắc chắn muốn xóa phòng này? Hành động này không thể hoàn tác!
                    </div>
                    
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%;">ID Phòng:</th>
                            <td><?php echo htmlspecialchars($phong['id']); ?></td>
                        </tr>
                        <tr>
                            <th>Số phòng:</th>
                            <td><?php echo htmlspecialchars($phong['so_phong']); ?></td>
                        </tr>
                        <tr>
                            <th>Loại phòng:</th>
                            <td><?php echo htmlspecialchars($phong['loai_phong']); ?></td>
                        </tr>
                        <tr>
                            <th>Giá ngày:</th>
                            <td><?php echo number_format($phong['gia_ngay'], 0, ',', '.'); ?> VNĐ</td>
                        </tr>
                        <tr>
                            <th>Trạng thái:</th>
                            <td>
                                <?php 
                                $trang_thai_class = '';
                                switch($phong['trang_thai']) {
                                    case 'trống':
                                        $trang_thai_class = 'badge bg-success';
                                        break;
                                    case 'đã đặt':
                                        $trang_thai_class = 'badge bg-warning';
                                        break;
                                    case 'đang sử dụng':
                                        $trang_thai_class = 'badge bg-primary';
                                        break;
                                    case 'bảo trì':
                                        $trang_thai_class = 'badge bg-danger';
                                        break;
                                }
                                ?>
                                <span class="<?php echo $trang_thai_class; ?>">
                                    <?php echo htmlspecialchars($phong['trang_thai']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                    
                    <form method="POST" class="mt-4">
                        <div class="d-flex justify-content-between">
                            <a href="/quanlykhachsan/admin/phong/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại
                            </a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="fas fa-trash-alt me-2"></i>Xác nhận xóa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>