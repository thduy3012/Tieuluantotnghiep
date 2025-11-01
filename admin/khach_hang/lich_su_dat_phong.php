<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Vui lòng đăng nhập để tiếp tục!';
    $_SESSION['message_type'] = 'warning';
    header('Location: /quanlykhachsan/auth/login.php');
    exit;
}

// Import các file cần thiết
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Lấy id khách hàng từ tham số URL
$id_khach_hang = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Nếu không có id khách hàng, chuyển về trang danh sách khách hàng
if ($id_khach_hang <= 0) {
    $_SESSION['message'] = 'Vui lòng chọn khách hàng để xem lịch sử!';
    $_SESSION['message_type'] = 'warning';
    header('Location: /quanlykhachsan/admin/khach_hang/index.php');
    exit;
}

// Lấy thông tin khách hàng
$khach_hang = fetchSingleRow(
    "SELECT * FROM khach_hang WHERE id = ?", 
    [$id_khach_hang]
);

// Nếu không tìm thấy khách hàng
if (!$khach_hang) {
    $_SESSION['message'] = 'Không tìm thấy thông tin khách hàng!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/khach_hang/index.php');
    exit;
}

// Lấy lịch sử đặt phòng của khách hàng
$lich_su_dat_phong = fetchAllRows(
    "SELECT dp.*, p.so_phong, p.loai_phong, nv.ho_ten as ten_nhan_vien,
            (SELECT COUNT(*) FROM su_dung_dich_vu sdv WHERE sdv.id_dat_phong = dp.id) as so_dich_vu,
            (SELECT SUM(thanh_tien) FROM su_dung_dich_vu sdv WHERE sdv.id_dat_phong = dp.id) as tong_tien_dich_vu,
            (SELECT COUNT(*) FROM hoa_don hd WHERE hd.id_dat_phong = dp.id) as co_hoa_don,
            (SELECT trang_thai FROM hoa_don hd WHERE hd.id_dat_phong = dp.id) as trang_thai_hoa_don
     FROM dat_phong dp
     LEFT JOIN phong p ON dp.id_phong = p.id
     LEFT JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
     WHERE dp.id_khach_hang = ?
     ORDER BY dp.ngay_nhan_phong DESC",
    [$id_khach_hang]
);

// Hàm tính số ngày lưu trú
function tinhSoNgayLuuTru($ngay_nhan_phong, $ngay_tra_phong) {
    $date1 = new DateTime($ngay_nhan_phong);
    $date2 = new DateTime($ngay_tra_phong);
    $interval = $date1->diff($date2);
    return $interval->days ?: 1; // Tối thiểu 1 ngày
}

// Hàm định dạng tiền tệ
function formatMoney($amount) {
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

// Hàm hiển thị trạng thái đặt phòng
function hienThiTrangThai($trang_thai) {
    switch ($trang_thai) {
        case 'đã đặt':
            return '<span class="badge bg-info">Đã đặt</span>';
        case 'đã nhận phòng':
            return '<span class="badge bg-primary">Đã nhận phòng</span>';
        case 'đã trả phòng':
            return '<span class="badge bg-success">Đã trả phòng</span>';
        case 'đã hủy':
            return '<span class="badge bg-danger">Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary">Không xác định</span>';
    }
}

// Xác định số trang nếu có nhiều bản ghi
$items_per_page = 10;
$total_items = count($lich_su_dat_phong);
$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Lấy dữ liệu cho trang hiện tại
$current_page_data = array_slice($lich_su_dat_phong, $offset, $items_per_page);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Lịch sử đặt phòng - <?php echo htmlspecialchars($khach_hang['ho_ten']); ?></h1>
        <a href="/quanlykhachsan/admin/khach_hang/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
    </div>

    <!-- Hiển thị thông tin khách hàng -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Thông tin khách hàng</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Họ tên:</th>
                            <td><?php echo htmlspecialchars($khach_hang['ho_ten']); ?></td>
                        </tr>
                        <tr>
                            <th>Số CMND:</th>
                            <td><?php echo htmlspecialchars($khach_hang['so_cmnd'] ?? 'Chưa cập nhật'); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Số điện thoại:</th>
                            <td><?php echo htmlspecialchars($khach_hang['so_dien_thoai'] ?? 'Chưa cập nhật'); ?></td>
                        </tr>
                        <tr>
                            <th>Địa chỉ:</th>
                            <td><?php echo htmlspecialchars($khach_hang['dia_chi'] ?? 'Chưa cập nhật'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Hiển thị lịch sử đặt phòng -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Lịch sử đặt phòng</h5>
        </div>
        <div class="card-body">
            <?php if (empty($lich_su_dat_phong)): ?>
                <div class="alert alert-info">Khách hàng chưa có lịch sử đặt phòng.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Phòng</th>
                                <th>Nhận phòng</th>
                                <th>Trả phòng</th>
                                <th>Số ngày</th>
                                <th>Tiền cọc</th>
                                <th>Dịch vụ</th>
                                <th>Trạng thái</th>
                                <th>Thanh toán</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_page_data as $dp): ?>
                                <?php 
                                    $so_ngay = tinhSoNgayLuuTru($dp['ngay_nhan_phong'], $dp['ngay_tra_phong']);
                                    $co_the_thanh_toan = ($dp['trang_thai'] == 'đã trả phòng' && (!$dp['co_hoa_don'] || $dp['trang_thai_hoa_don'] == 'chưa thanh toán'));
                                    $co_the_huy = ($dp['trang_thai'] == 'đã đặt');
                                    $co_the_nhan_phong = ($dp['trang_thai'] == 'đã đặt');
                                    $co_the_tra_phong = ($dp['trang_thai'] == 'đã nhận phòng');
                                ?>
                                <tr>
                                    <td><?php echo $dp['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($dp['so_phong']); ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($dp['loai_phong']); ?></span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($dp['ngay_nhan_phong'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($dp['ngay_tra_phong'])); ?></td>
                                    <td class="text-center"><?php echo $so_ngay; ?></td>
                                    <td><?php echo formatMoney($dp['tien_coc']); ?></td>
                                    <td>
                                        <?php if ($dp['so_dich_vu'] > 0): ?>
                                            <span class="badge bg-info"><?php echo $dp['so_dich_vu']; ?> dịch vụ</span>
                                            <br>
                                            <small><?php echo formatMoney($dp['tong_tien_dich_vu'] ?? 0); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Không có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo hienThiTrangThai($dp['trang_thai']); ?></td>
                                    <td class="text-center">
                                        <?php if ($dp['co_hoa_don']): ?>
                                            <?php if ($dp['trang_thai_hoa_don'] == 'đã thanh toán'): ?>
                                                <span class="badge bg-success">Đã thanh toán</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Chưa thanh toán</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Chưa có hóa đơn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/quanlykhachsan/admin/dat_phong/chi_tiet.php?id=<?php echo $dp['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($co_the_thanh_toan): ?>
                                                <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php?id_dat_phong=<?php echo $dp['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-money-bill"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($co_the_nhan_phong): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/cap_nhat_trang_thai.php?id=<?php echo $dp['id']; ?>&trang_thai=đã nhận phòng" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($co_the_tra_phong): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/cap_nhat_trang_thai.php?id=<?php echo $dp['id']; ?>&trang_thai=đã trả phòng" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($co_the_huy): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/cap_nhat_trang_thai.php?id=<?php echo $dp['id']; ?>&trang_thai=đã hủy" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn hủy đặt phòng này?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $id_khach_hang; ?>&page=<?php echo $current_page - 1; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $id_khach_hang; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?id=<?php echo $id_khach_hang; ?>&page=<?php echo $current_page + 1; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Thống kê đơn giản -->
            <?php if (!empty($lich_su_dat_phong)): ?>
                <div class="alert alert-info mt-4">
                    <h5 class="mb-3">Thống kê đặt phòng</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Tổng số lần đặt phòng:</strong> <?php echo count($lich_su_dat_phong); ?>
                        </div>
                        <div class="col-md-3">
                            <?php 
                                $so_lan_da_tra_phong = 0;
                                foreach ($lich_su_dat_phong as $dp) {
                                    if ($dp['trang_thai'] == 'đã trả phòng') {
                                        $so_lan_da_tra_phong++;
                                    }
                                }
                            ?>
                            <strong>Đã hoàn thành:</strong> <?php echo $so_lan_da_tra_phong; ?>
                        </div>
                        <div class="col-md-3">
                            <?php 
                                $so_lan_da_huy = 0;
                                foreach ($lich_su_dat_phong as $dp) {
                                    if ($dp['trang_thai'] == 'đã hủy') {
                                        $so_lan_da_huy++;
                                    }
                                }
                            ?>
                            <strong>Đã hủy:</strong> <?php echo $so_lan_da_huy; ?>
                        </div>
                        <div class="col-md-3">
                            <?php 
                                $so_lan_chua_tra_phong = 0;
                                foreach ($lich_su_dat_phong as $dp) {
                                    if ($dp['trang_thai'] == 'đã đặt' || $dp['trang_thai'] == 'đã nhận phòng') {
                                        $so_lan_chua_tra_phong++;
                                    }
                                }
                            ?>
                            <strong>Đang thực hiện:</strong> <?php echo $so_lan_chua_tra_phong; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Nút thêm đặt phòng mới -->
    <div class="mt-4">
        <a href="/quanlykhachsan/admin/dat_phong/them.php?id_khach_hang=<?php echo $id_khach_hang; ?>" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Đặt phòng mới
        </a>
    </div>
</div>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
?>