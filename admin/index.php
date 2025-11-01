<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Chuyển hướng đến trang đăng nhập nếu chưa đăng nhập
    header('Location: /quanlykhachsan/auth/login.php');
    exit;
}

// Kiểm tra nếu không phải là quản lý (nếu cần)
if (isset($_SESSION['role']) && $_SESSION['role'] != 'quản lý' && $_SESSION['role'] != 'nhân viên') {
    // Hiển thị thông báo lỗi hoặc chuyển hướng
    $_SESSION['message'] = 'Bạn không có quyền truy cập vào trang này.';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/index.php');
    exit;
}

// Import file header
require_once __DIR__ . '/../includes/header.php';

// Import file cấu hình và kết nối CSDL
require_once __DIR__ . '/../config/config.php';

// Lấy thông tin tổng quan
// 1. Số lượng phòng theo trạng thái
$sql_phong = "SELECT 
    SUM(CASE WHEN trang_thai = 'trống' THEN 1 ELSE 0 END) as phong_trong,
    SUM(CASE WHEN trang_thai = 'đã đặt' THEN 1 ELSE 0 END) as phong_da_dat,
    SUM(CASE WHEN trang_thai = 'đang sử dụng' THEN 1 ELSE 0 END) as phong_dang_su_dung,
    SUM(CASE WHEN trang_thai = 'bảo trì' THEN 1 ELSE 0 END) as phong_bao_tri,
    COUNT(*) as tong_phong
FROM phong";
$thong_ke_phong = fetchSingleRow($sql_phong);

// 2. Tổng số khách hàng
$sql_khach_hang = "SELECT COUNT(*) as tong_khach_hang FROM khach_hang";
$thong_ke_khach = fetchSingleRow($sql_khach_hang);

// 3. Số lượng đặt phòng trong tháng hiện tại
$thang_hien_tai = date('m');
$nam_hien_tai = date('Y');
$sql_dat_phong = "SELECT COUNT(*) as so_dat_phong 
                  FROM dat_phong 
                  WHERE MONTH(ngay_nhan_phong) = :thang AND YEAR(ngay_nhan_phong) = :nam";
$thong_ke_dat_phong = fetchSingleRow($sql_dat_phong, [':thang' => $thang_hien_tai, ':nam' => $nam_hien_tai]);

// 4. Doanh thu tháng hiện tại
$sql_doanh_thu = "SELECT COALESCE(SUM(ABS(tong_thanh_toan)), 0) as doanh_thu 
                  FROM hoa_don 
                  WHERE MONTH(ngay_thanh_toan) = :thang AND YEAR(ngay_thanh_toan) = :nam";
$thong_ke_doanh_thu = fetchSingleRow($sql_doanh_thu, [':thang' => $thang_hien_tai, ':nam' => $nam_hien_tai]);

// Đảm bảo doanh thu không âm (thêm hàm abs để chắc chắn)
$doanh_thu = abs((float)($thong_ke_doanh_thu['doanh_thu'] ?? 0));

// 5. Danh sách đặt phòng gần đây
$sql_dat_phong_gan_day = "SELECT dp.id, dp.ngay_nhan_phong, dp.ngay_tra_phong, 
                           dp.tien_coc, dp.trang_thai, 
                           kh.ho_ten as ten_khach_hang, 
                           p.so_phong, p.loai_phong
                         FROM dat_phong dp
                         JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                         JOIN phong p ON dp.id_phong = p.id
                         ORDER BY dp.id DESC LIMIT 5";
$dat_phong_gan_day = fetchAllRows($sql_dat_phong_gan_day);

// 6. Danh sách khách sắp trả phòng hôm nay
$ngay_hien_tai = date('Y-m-d');
$sql_tra_phong_hom_nay = "SELECT dp.id, dp.ngay_nhan_phong, dp.ngay_tra_phong, 
                           kh.ho_ten as ten_khach_hang, kh.so_dien_thoai,
                           p.so_phong, p.loai_phong
                         FROM dat_phong dp
                         JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                         JOIN phong p ON dp.id_phong = p.id
                         WHERE dp.ngay_tra_phong = :ngay_hien_tai AND dp.trang_thai = 'đã nhận phòng'
                         ORDER BY p.so_phong";
$tra_phong_hom_nay = fetchAllRows($sql_tra_phong_hom_nay, [':ngay_hien_tai' => $ngay_hien_tai]);

// 7. Danh sách phòng trống
$sql_phong_trong = "SELECT id, so_phong, loai_phong, gia_ngay
                    FROM phong
                    WHERE trang_thai = 'trống'
                    ORDER BY so_phong
                    LIMIT 10";
$phong_trong = fetchAllRows($sql_phong_trong);

// Hàm định dạng số tiền
function formatMoney($amount) {
    return number_format($amount, 0, ',', '.') . ' đ';
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Bảng điều khiển</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Tổng quan quản lý khách sạn</li>
    </ol>
    
    <!-- Thống kê tổng quan -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h4><?php echo $thong_ke_phong['phong_trong'] ?? 0; ?> / <?php echo $thong_ke_phong['tong_phong'] ?? 0; ?></h4>
                    <div>Phòng trống</div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="/quanlykhachsan/admin/phong/tim_phong_trong.php">Xem chi tiết</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <h4><?php echo $thong_ke_phong['phong_dang_su_dung'] ?? 0; ?> / <?php echo $thong_ke_phong['tong_phong'] ?? 0; ?></h4>
                    <div>Phòng đang sử dụng</div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="/quanlykhachsan/admin/phong/index.php">Xem chi tiết</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h4><?php echo $thong_ke_dat_phong['so_dat_phong'] ?? 0; ?></h4>
                    <div>Đặt phòng tháng <?php echo $thang_hien_tai; ?></div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="/quanlykhachsan/admin/dat_phong/index.php">Xem chi tiết</a>
                    <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                <h4><?php echo number_format(abs($doanh_thu)); ?> VNĐ</h4>
                <div>Doanh thu tháng <?php echo $thang_hien_tai; ?></div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="/quanlykhachsan/admin/bao_cao/doanh_thu.php">Xem chi tiết</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
            </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Danh sách đặt phòng gần đây -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-check me-1"></i>
                    Đặt phòng gần đây
                </div>
                <div class="card-body">
                    <?php if (!empty($dat_phong_gan_day)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Mã</th>
                                    <th>Khách hàng</th>
                                    <th>Phòng</th>
                                    <th>Nhận phòng</th>
                                    <th>Trả phòng</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dat_phong_gan_day as $item): ?>
                                <tr>
                                    <td><?php echo $item['id']; ?></td>
                                    <td><?php echo htmlspecialchars($item['ten_khach_hang']); ?></td>
                                    <td><?php echo htmlspecialchars($item['so_phong']) . ' (' . htmlspecialchars($item['loai_phong']) . ')'; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['ngay_nhan_phong'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['ngay_tra_phong'])); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo match($item['trang_thai']) {
                                                'đã đặt' => 'bg-info',
                                                'đã nhận phòng' => 'bg-success',
                                                'đã trả phòng' => 'bg-secondary',
                                                'đã hủy' => 'bg-danger',
                                                default => 'bg-primary'
                                            };
                                        ?>">
                                            <?php echo htmlspecialchars($item['trang_thai']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center my-3">Không có đặt phòng gần đây</p>
                    <?php endif; ?>
                    <div class="text-end mt-2">
                        <a href="/quanlykhachsan/admin/dat_phong/index.php" class="btn btn-sm btn-primary">Xem tất cả</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Phòng trống có sẵn -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-door-open me-1"></i>
                    Phòng trống hiện có
                </div>
                <div class="card-body">
                    <?php if (!empty($phong_trong)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Số phòng</th>
                                    <th>Loại phòng</th>
                                    <th>Giá/ngày</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($phong_trong as $phong): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($phong['so_phong']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo match($phong['loai_phong']) {
                                                'đơn' => 'bg-info',
                                                'đôi' => 'bg-primary',
                                                'vip' => 'bg-warning text-dark',
                                                default => 'bg-secondary'
                                            };
                                        ?>">
                                            <?php echo htmlspecialchars($phong['loai_phong']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatMoney($phong['gia_ngay']); ?></td>
                                    <td>
                                        <a href="/quanlykhachsan/admin/dat_phong/them.php?phong_id=<?php echo $phong['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-calendar-plus"></i> Đặt phòng
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center my-3">Không có phòng trống</p>
                    <?php endif; ?>
                    <div class="text-end mt-2">
                        <a href="/quanlykhachsan/admin/phong/tim_phong_trong.php" class="btn btn-sm btn-primary">Tìm phòng trống</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Khách trả phòng hôm nay -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    Khách trả phòng hôm nay (<?php echo date('d/m/Y'); ?>)
                </div>
                <div class="card-body">
                    <?php if (!empty($tra_phong_hom_nay)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Mã đặt phòng</th>
                                    <th>Khách hàng</th>
                                    <th>Số điện thoại</th>
                                    <th>Phòng</th>
                                    <th>Ngày nhận</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tra_phong_hom_nay as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['ten_khach_hang']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['so_dien_thoai']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['so_phong']) . ' (' . htmlspecialchars($booking['loai_phong']) . ')'; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['ngay_nhan_phong'])); ?></td>
                                    <td>
                                        <a href="/quanlykhachsan/admin/dat_phong/tra_phong.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-check-circle"></i> Trả phòng
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center my-3">Không có khách trả phòng ngày hôm nay</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Import file footer
require_once __DIR__ . '/../includes/footer.php';
?>