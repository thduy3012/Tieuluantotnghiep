<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    // Lưu URL hiện tại để chuyển hướng sau khi đăng nhập
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import file cấu hình và các hàm
require_once __DIR__ . '/../../config/config.php';

// Nếu không có ID đặt phòng, chuyển về trang danh sách
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID đặt phòng không hợp lệ!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit;
}

$id_dat_phong = $_GET['id'];

// Lấy lý do hủy từ GET nếu có
$ly_do_huy = isset($_GET['ly_do']) ? $_GET['ly_do'] : '';
$hoan_tien_coc = isset($_GET['hoan_tien']) ? $_GET['hoan_tien'] == '1' : true;

// Lấy thông tin đặt phòng để kiểm tra
$sql = "SELECT dp.*, p.so_phong, kh.ho_ten as ten_khach_hang 
        FROM dat_phong dp
        JOIN phong p ON dp.id_phong = p.id
        JOIN khach_hang kh ON dp.id_khach_hang = kh.id
        WHERE dp.id = ?";

$dat_phong = fetchSingleRow($sql, [$id_dat_phong]);

// Kiểm tra xem đặt phòng tồn tại không
if (!$dat_phong) {
    $_SESSION['message'] = "Không tìm thấy thông tin đặt phòng!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit;
}

// Kiểm tra nếu đặt phòng không ở trạng thái "đã đặt" hoặc "đã nhận phòng"
if ($dat_phong['trang_thai'] !== 'đã đặt' && $dat_phong['trang_thai'] !== 'đã nhận phòng') {
    $_SESSION['message'] = "Chỉ có thể hủy đặt phòng ở trạng thái 'đã đặt' hoặc 'đã nhận phòng'!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit;
}

// Xử lý khi form được gửi đi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Lấy lý do hủy từ form
        $ly_do_huy = isset($_POST['ly_do_huy']) ? trim($_POST['ly_do_huy']) : '';
        $hoan_tien_coc = isset($_POST['hoan_tien_coc']) ? 1 : 0;
        
        // Bắt đầu transaction
        $conn = getDatabaseConnection();
        $conn->beginTransaction();
        
        // Bằng đoạn code này (chỉ cập nhật trạng thái)
        $sql_update_dat_phong = "UPDATE dat_phong SET trang_thai = 'đã hủy' WHERE id = ?";
        $result_dat_phong = executeQuery($sql_update_dat_phong, [$id_dat_phong]);
        
        // Cập nhật trạng thái phòng thành "trống"
        $sql_update_phong = "UPDATE phong SET trang_thai = 'trống' WHERE id = ?";
        $result_phong = executeQuery($sql_update_phong, [$dat_phong['id_phong']]);
        
        if ($result_dat_phong && $result_phong) {
            // Commit các thay đổi
            $conn->commit();
            
            $_SESSION['message'] = "Hủy đặt phòng thành công!";
            $_SESSION['message_type'] = "success";
            header("Location: /quanlykhachsan/admin/dat_phong/index.php");
            exit;
        } else {
            // Rollback nếu có lỗi
            $conn->rollBack();
            throw new Exception("Có lỗi xảy ra khi cập nhật dữ liệu.");
        }
    } catch (Exception $e) {
        // Đảm bảo transaction được rollback khi có lỗi
        if ($conn && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

// Import file header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2 class="mt-3 mb-4">Hủy đặt phòng</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/dat_phong/index.php">Danh sách đặt phòng</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Hủy đặt phòng</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Xác nhận hủy đặt phòng</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Lưu ý:</strong> Sau khi hủy, phòng sẽ được đưa về trạng thái trống và có thể được đặt bởi khách hàng khác.
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Thông tin đặt phòng</h5>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Mã đặt phòng:</span>
                                    <strong><?php echo $dat_phong['id']; ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Khách hàng:</span>
                                    <strong><?php echo htmlspecialchars($dat_phong['ten_khach_hang']); ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Số phòng:</span>
                                    <strong><?php echo htmlspecialchars($dat_phong['so_phong']); ?></strong>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>Thời gian</h5>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Ngày nhận phòng:</span>
                                    <strong><?php echo date('d/m/Y', strtotime($dat_phong['ngay_nhan_phong'])); ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Ngày trả phòng:</span>
                                    <strong><?php echo date('d/m/Y', strtotime($dat_phong['ngay_tra_phong'])); ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Tiền cọc:</span>
                                    <strong><?php echo number_format($dat_phong['tien_coc'], 0, ',', '.'); ?> VNĐ</strong>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <form method="post" action="">
                        <?php if ($dat_phong['tien_coc'] > 0): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="hoan_tien_coc" name="hoan_tien_coc" value="1" <?php echo $hoan_tien_coc ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="hoan_tien_coc">Hoàn trả tiền cọc cho khách hàng</label>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="ly_do_huy" class="form-label">Lý do hủy:</label>
                            <textarea class="form-control" id="ly_do_huy" name="ly_do_huy" rows="3" required><?php echo htmlspecialchars($ly_do_huy); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="/quanlykhachsan/admin/dat_phong/chi_tiet.php?id=<?php echo $id_dat_phong; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại
                            </a>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn hủy đặt phòng này?');">
                                <i class="fas fa-times-circle me-2"></i>Xác nhận hủy đặt phòng
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Import file footer
include_once __DIR__ . '/../../includes/footer.php';
?>