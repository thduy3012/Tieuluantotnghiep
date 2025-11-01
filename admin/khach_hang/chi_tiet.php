<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Lưu URL hiện tại để chuyển hướng lại sau khi đăng nhập
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    // Chuyển hướng đến trang đăng nhập
    header('Location: /quanlykhachsan/auth/login.php');
    exit;
}

// Import file cấu hình và header
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Kiểm tra xem có tham số id được truyền vào không
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = 'ID khách hàng không hợp lệ!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/khach_hang/index.php');
    exit;
}

$id_khach_hang = intval($_GET['id']);

// Lấy thông tin khách hàng
$khach_hang = fetchSingleRow("SELECT * FROM khach_hang WHERE id = ?", [$id_khach_hang]);

if (!$khach_hang) {
    $_SESSION['message'] = 'Không tìm thấy thông tin khách hàng!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/khach_hang/index.php');
    exit;
}

// Lấy lịch sử đặt phòng của khách hàng
$lich_su_dat_phong = fetchAllRows(
    "SELECT dp.*, p.so_phong, p.loai_phong, nv.ho_ten as ten_nhan_vien 
    FROM dat_phong dp
    JOIN phong p ON dp.id_phong = p.id
    JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
    WHERE dp.id_khach_hang = ?
    ORDER BY dp.ngay_nhan_phong DESC",
    [$id_khach_hang]
);

// Tính tổng số tiền khách hàng đã thanh toán
$tong_thanh_toan = fetchSingleRow(
    "SELECT SUM(hd.tong_thanh_toan) as tong_tien
    FROM hoa_don hd
    JOIN dat_phong dp ON hd.id_dat_phong = dp.id
    WHERE dp.id_khach_hang = ? AND hd.trang_thai = 'đã thanh toán'",
    [$id_khach_hang]
);

?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Chi tiết khách hàng</h2>
                <div>
                    <a href="/quanlykhachsan/admin/khach_hang/sua.php?id=<?php echo $khach_hang['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Sửa thông tin
                    </a>
                    <a href="/quanlykhachsan/admin/khach_hang/index.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Thông tin cá nhân</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($khach_hang['ho_ten']); ?></p>
                            <p><strong>Số CMND:</strong> <?php echo htmlspecialchars($khach_hang['so_cmnd'] ?? 'Chưa cập nhật'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($khach_hang['so_dien_thoai'] ?? 'Chưa cập nhật'); ?></p>
                            <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($khach_hang['dia_chi'] ?? 'Chưa cập nhật'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Lịch sử đặt phòng</h5>
                </div>
                <div class="card-body">
                    <?php if ($lich_su_dat_phong && count($lich_su_dat_phong) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Mã đặt</th>
                                        <th>Phòng</th>
                                        <th>Loại phòng</th>
                                        <th>Ngày nhận</th>
                                        <th>Ngày trả</th>
                                        <th>Tiền cọc</th>
                                        <th>Trạng thái</th>
                                        <th>Nhân viên xử lý</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lich_su_dat_phong as $dat_phong): ?>
                                        <tr>
                                            <td><?php echo $dat_phong['id']; ?></td>
                                            <td><?php echo htmlspecialchars($dat_phong['so_phong']); ?></td>
                                            <td>
                                                <?php 
                                                    switch ($dat_phong['loai_phong']) {
                                                        case 'đơn':
                                                            echo '<span class="badge bg-primary">Đơn</span>';
                                                            break;
                                                        case 'đôi':
                                                            echo '<span class="badge bg-success">Đôi</span>';
                                                            break;
                                                        case 'vip':
                                                            echo '<span class="badge bg-warning">VIP</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">Khác</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($dat_phong['ngay_nhan_phong'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($dat_phong['ngay_tra_phong'])); ?></td>
                                            <td><?php echo number_format($dat_phong['tien_coc'], 0, ',', '.'); ?>đ</td>
                                            <td>
                                                <?php 
                                                    switch ($dat_phong['trang_thai']) {
                                                        case 'đã đặt':
                                                            echo '<span class="badge bg-info">Đã đặt</span>';
                                                            break;
                                                        case 'đã nhận phòng':
                                                            echo '<span class="badge bg-success">Đã nhận phòng</span>';
                                                            break;
                                                        case 'đã trả phòng':
                                                            echo '<span class="badge bg-secondary">Đã trả phòng</span>';
                                                            break;
                                                        case 'đã hủy':
                                                            echo '<span class="badge bg-danger">Đã hủy</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">Khác</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($dat_phong['ten_nhan_vien']); ?></td>
                                            <td>
                                                <a href="/quanlykhachsan/admin/dat_phong/chi_tiet.php?id=<?php echo $dat_phong['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($dat_phong['trang_thai'] == 'đã đặt'): ?>
                                                    <a href="/quanlykhachsan/admin/dat_phong/nhan_phong.php?id=<?php echo $dat_phong['id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check-circle"></i> Nhận phòng
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($dat_phong['trang_thai'] == 'đã nhận phòng'): ?>
                                                    <a href="/quanlykhachsan/admin/dat_phong/tra_phong.php?id=<?php echo $dat_phong['id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-sign-out-alt"></i> Trả phòng
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Khách hàng chưa có lịch sử đặt phòng nào.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Thông tin thanh toán</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Tổng số tiền đã thanh toán:</strong> 
                                <span class="text-success fw-bold"><?php echo number_format($tong_thanh_toan['tong_tien'] ?? 0, 0, ',', '.'); ?>đ</span>
                            </p>
                        </div>
                    </div>

                    <?php
                    // Lấy danh sách hóa đơn của khách hàng
                    $hoa_don_list = fetchAllRows(
                        "SELECT hd.*, dp.ngay_nhan_phong, dp.ngay_tra_phong 
                        FROM hoa_don hd
                        JOIN dat_phong dp ON hd.id_dat_phong = dp.id
                        WHERE dp.id_khach_hang = ?
                        ORDER BY hd.ngay_thanh_toan DESC",
                        [$id_khach_hang]
                    );
                    
                    if ($hoa_don_list && count($hoa_don_list) > 0):
                    ?>
                    <h6 class="mt-4">Chi tiết hóa đơn:</h6>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Mã hóa đơn</th>
                                    <th>Ngày thanh toán</th>
                                    <th>Tiền phòng</th>
                                    <th>Tiền dịch vụ</th>
                                    <th>Tổng thanh toán</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hoa_don_list as $hoa_don): ?>
                                    <tr>
                                        <td><?php echo $hoa_don['id']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($hoa_don['ngay_thanh_toan'])); ?></td>
                                        <td><?php echo number_format($hoa_don['tong_tien_phong'], 0, ',', '.'); ?>đ</td>
                                        <td><?php echo number_format($hoa_don['tong_tien_dich_vu'], 0, ',', '.'); ?>đ</td>
                                        <td class="fw-bold"><?php echo number_format($hoa_don['tong_thanh_toan'], 0, ',', '.'); ?>đ</td>
                                        <td>
                                            <?php if ($hoa_don['trang_thai'] == 'đã thanh toán'): ?>
                                                <span class="badge bg-success">Đã thanh toán</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Chưa thanh toán</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/quanlykhachsan/admin/hoa_don/chi_tiet.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Xem
                                            </a>
                                            <?php if ($hoa_don['trang_thai'] == 'chưa thanh toán'): ?>
                                                <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-money-bill-wave"></i> Thanh toán
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>Khách hàng chưa có hóa đơn thanh toán nào.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-concierge-bell me-2"></i>Lịch sử sử dụng dịch vụ</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Lấy lịch sử sử dụng dịch vụ
                    $dich_vu_list = fetchAllRows(
                        "SELECT sddv.*, dv.ten_dich_vu, dp.id as ma_dat_phong, p.so_phong
                        FROM su_dung_dich_vu sddv
                        JOIN dich_vu dv ON sddv.id_dich_vu = dv.id
                        JOIN dat_phong dp ON sddv.id_dat_phong = dp.id
                        JOIN phong p ON dp.id_phong = p.id
                        WHERE dp.id_khach_hang = ?
                        ORDER BY sddv.ngay_su_dung DESC",
                        [$id_khach_hang]
                    );
                    
                    if ($dich_vu_list && count($dich_vu_list) > 0):
                    ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Mã đặt phòng</th>
                                    <th>Phòng</th>
                                    <th>Tên dịch vụ</th>
                                    <th>Số lượng</th>
                                    <th>Ngày sử dụng</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dich_vu_list as $dich_vu): ?>
                                    <tr>
                                        <td><?php echo $dich_vu['ma_dat_phong']; ?></td>
                                        <td><?php echo htmlspecialchars($dich_vu['so_phong']); ?></td>
                                        <td><?php echo htmlspecialchars($dich_vu['ten_dich_vu']); ?></td>
                                        <td><?php echo $dich_vu['so_luong']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($dich_vu['ngay_su_dung'])); ?></td>
                                        <td><?php echo number_format($dich_vu['thanh_tien'], 0, ',', '.'); ?>đ</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Khách hàng chưa sử dụng dịch vụ nào.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4 mb-5 d-flex gap-2">
                <a href="/quanlykhachsan/admin/dat_phong/them.php?id_khach_hang=<?php echo $khach_hang['id']; ?>" class="btn btn-success">
                    <i class="fas fa-calendar-plus me-1"></i> Đặt phòng mới
                </a>
                <a href="/quanlykhachsan/admin/khach_hang/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
?>