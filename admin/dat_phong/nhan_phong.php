<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Import file cấu hình và các hàm
require_once __DIR__ . '/../../config/config.php';

// Xử lý nhận phòng
if(isset($_POST['nhan_phong']) && isset($_POST['id_dat_phong'])) {
    $id_dat_phong = $_POST['id_dat_phong'];
    
    // Lấy thông tin đặt phòng
    $dat_phong = fetchSingleRow("SELECT * FROM dat_phong WHERE id = ?", [$id_dat_phong]);
    
    if($dat_phong && $dat_phong['trang_thai'] == 'đã đặt') {
        // Cập nhật trạng thái đặt phòng
        $result_update_dat_phong = executeQuery("UPDATE dat_phong SET trang_thai = 'đã nhận phòng' WHERE id = ?", [$id_dat_phong]);
        
        // Cập nhật trạng thái phòng
        $result_update_phong = executeQuery("UPDATE phong SET trang_thai = 'đang sử dụng' WHERE id = ?", [$dat_phong['id_phong']]);
        
        if($result_update_dat_phong && $result_update_phong) {
            $_SESSION['message'] = "Nhận phòng thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Có lỗi xảy ra khi nhận phòng!";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Không thể nhận phòng hoặc phòng đã được nhận!";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: /quanlykhachsan/admin/dat_phong/nhan_phong.php");
    exit();
}

// Lấy danh sách các đặt phòng có trạng thái 'đã đặt'
$danh_sach_dat_phong = fetchAllRows("
    SELECT dp.*, kh.ho_ten as ten_khach_hang, p.so_phong, p.loai_phong, p.gia_ngay 
    FROM dat_phong dp 
    JOIN khach_hang kh ON dp.id_khach_hang = kh.id
    JOIN phong p ON dp.id_phong = p.id
    WHERE dp.trang_thai = 'đã đặt'
    ORDER BY dp.ngay_nhan_phong ASC
");

// Lấy ngày hiện tại
$ngay_hien_tai = date('Y-m-d');

// Include header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2><i class="fas fa-check-circle text-success"></i> Nhận phòng</h2>
            <p class="text-muted">Quản lý việc nhận phòng cho khách hàng đã đặt phòng</p>
        </div>
    </div>

    <?php if(empty($danh_sach_dat_phong)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Không có đặt phòng nào cần nhận phòng!
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Danh sách đặt phòng cần nhận phòng</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Khách hàng</th>
                                <th>Phòng</th>
                                <th>Loại phòng</th>
                                <th>Giá/ngày</th>
                                <th>Ngày nhận phòng</th>
                                <th>Ngày trả phòng</th>
                                <th>Tiền cọc</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($danh_sach_dat_phong as $dat_phong): ?>
                                <?php $sap_den_ngay = ($dat_phong['ngay_nhan_phong'] <= $ngay_hien_tai) ? 'bg-success text-white' : ''; ?>
                                <tr class="<?php echo $sap_den_ngay; ?>">
                                    <td><?php echo $dat_phong['id']; ?></td>
                                    <td><?php echo htmlspecialchars($dat_phong['ten_khach_hang']); ?></td>
                                    <td><?php echo htmlspecialchars($dat_phong['so_phong']); ?></td>
                                    <td><?php echo htmlspecialchars($dat_phong['loai_phong']); ?></td>
                                    <td><?php echo number_format($dat_phong['gia_ngay'], 0, ',', '.'); ?> VNĐ</td>
                                    <td><?php echo date('d/m/Y', strtotime($dat_phong['ngay_nhan_phong'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($dat_phong['ngay_tra_phong'])); ?></td>
                                    <td><?php echo number_format($dat_phong['tien_coc'], 0, ',', '.'); ?> VNĐ</td>
                                    <td><span class="badge bg-warning"><?php echo $dat_phong['trang_thai']; ?></span></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn nhận phòng này?');">
                                            <input type="hidden" name="id_dat_phong" value="<?php echo $dat_phong['id']; ?>">
                                            <button type="submit" name="nhan_phong" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Nhận phòng
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mt-3">
        <a href="/quanlykhachsan/admin/dat_phong/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách đặt phòng
        </a>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>