<?php
ob_start();
// Bắt đầu phiên làm việc
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import các tệp cần thiết
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Kiểm tra có ID hóa đơn được truyền vào
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Không tìm thấy hóa đơn";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/hoa_don/index.php");
    exit;
}

$id_hoa_don = intval($_GET['id']);

// Lấy thông tin hóa đơn
$sql_hoa_don = "SELECT hd.*, dp.id as id_dat_phong, dp.ngay_nhan_phong, dp.ngay_tra_phong, 
                dp.tien_coc, dp.trang_thai as trang_thai_dat_phong, 
                kh.ho_ten as ten_khach_hang, kh.so_cmnd, kh.so_dien_thoai,
                p.so_phong, p.loai_phong, p.gia_ngay,
                nv.ho_ten as ten_nhan_vien
                FROM hoa_don hd
                JOIN dat_phong dp ON hd.id_dat_phong = dp.id
                JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                JOIN phong p ON dp.id_phong = p.id
                JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
                WHERE hd.id = ?";

$hoa_don = fetchSingleRow($sql_hoa_don, [$id_hoa_don]);

if (!$hoa_don) {
    $_SESSION['message'] = "Không tìm thấy hóa đơn";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/hoa_don/index.php");
    exit;
}

// Tính số ngày lưu trú
$ngay_nhan = new DateTime($hoa_don['ngay_nhan_phong']);
$ngay_tra = new DateTime($hoa_don['ngay_tra_phong']);
$so_ngay = $ngay_tra->diff($ngay_nhan)->days;
if ($so_ngay == 0) $so_ngay = 1; // Tối thiểu 1 ngày

// Lấy danh sách dịch vụ đã sử dụng
$sql_dich_vu = "SELECT sd.id, sd.so_luong, sd.ngay_su_dung, sd.thanh_tien,
                dv.ten_dich_vu, dv.gia
                FROM su_dung_dich_vu sd
                JOIN dich_vu dv ON sd.id_dich_vu = dv.id
                WHERE sd.id_dat_phong = ?
                ORDER BY sd.ngay_su_dung";

$dich_vu_list = fetchAllRows($sql_dich_vu, [$hoa_don['id_dat_phong']]);
?>

<div class="container">
    <div class="row mb-3">
        <div class="col">
            <h2>
                <i class="fas fa-file-invoice-dollar me-2"></i>Chi tiết hóa đơn #<?php echo $id_hoa_don; ?>
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Trang quản trị</a></li>
                    <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/hoa_don/index.php">Danh sách hóa đơn</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Chi tiết hóa đơn</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông tin hóa đơn</h5>
                    <div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>In hóa đơn
                        </button>
                        <?php if ($hoa_don['trang_thai'] == 'chưa thanh toán'): ?>
                        <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php?id=<?php echo $id_hoa_don; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-money-bill-wave me-1"></i>Thanh toán
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Thông tin khách hàng</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Họ tên:</strong></td>
                                    <td><?php echo htmlspecialchars($hoa_don['ten_khach_hang']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Số CMND:</strong></td>
                                    <td><?php echo htmlspecialchars($hoa_don['so_cmnd']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Số điện thoại:</strong></td>
                                    <td><?php echo htmlspecialchars($hoa_don['so_dien_thoai']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">Thông tin đặt phòng</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Số phòng:</strong></td>
                                    <td><?php echo htmlspecialchars($hoa_don['so_phong']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Loại phòng:</strong></td>
                                    <td><?php echo htmlspecialchars($hoa_don['loai_phong']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Ngày nhận phòng:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($hoa_don['ngay_nhan_phong'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Ngày trả phòng:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($hoa_don['ngay_tra_phong'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Số ngày lưu trú:</strong></td>
                                    <td><?php echo $so_ngay; ?> ngày</td>
                                </tr>
                                <tr>
                                    <td><strong>Tiền cọc:</strong></td>
                                    <td><?php echo number_format($hoa_don['tien_coc'], 0, ',', '.'); ?> VNĐ</td>
                                </tr>
                                <tr>
                                    <td><strong>Trạng thái:</strong></td>
                                    <td>
                                        <span class="badge <?php echo $hoa_don['trang_thai'] == 'đã thanh toán' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo htmlspecialchars($hoa_don['trang_thai']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="fw-bold">Chi tiết thanh toán</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Diễn giải</th>
                                    <th>Đơn giá</th>
                                    <th>Số lượng</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Tiền phòng (<?php echo htmlspecialchars($hoa_don['so_phong']); ?> - <?php echo htmlspecialchars($hoa_don['loai_phong']); ?>)</td>
                                    <td><?php echo number_format($hoa_don['gia_ngay'], 0, ',', '.'); ?> VNĐ/ngày</td>
                                    <td><?php echo $so_ngay; ?> ngày</td>
                                    <td><?php echo number_format($hoa_don['tong_tien_phong'], 0, ',', '.'); ?> VNĐ</td>
                                </tr>
                                
                                <?php if ($dich_vu_list && count($dich_vu_list) > 0): ?>
                                    <?php foreach ($dich_vu_list as $dich_vu): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dich_vu['ten_dich_vu']); ?> (<?php echo date('d/m/Y', strtotime($dich_vu['ngay_su_dung'])); ?>)</td>
                                        <td><?php echo number_format($dich_vu['gia'], 0, ',', '.'); ?> VNĐ</td>
                                        <td><?php echo $dich_vu['so_luong']; ?></td>
                                        <td><?php echo number_format($dich_vu['thanh_tien'], 0, ',', '.'); ?> VNĐ</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Không có dịch vụ sử dụng</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Tổng tiền dịch vụ:</strong></td>
                                    <td><?php echo number_format($hoa_don['tong_tien_dich_vu'], 0, ',', '.'); ?> VNĐ</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Tổng tiền phòng:</strong></td>
                                    <td><?php echo number_format($hoa_don['tong_tien_phong'], 0, ',', '.'); ?> VNĐ</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Tiền cọc:</strong></td>
                                    <td>- <?php echo number_format($hoa_don['tien_coc'], 0, ',', '.'); ?> VNĐ</td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                                    <td><strong><?php echo number_format($hoa_don['tong_thanh_toan'], 0, ',', '.'); ?> VNĐ</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-0"><strong>Nhân viên phụ trách:</strong> <?php echo htmlspecialchars($hoa_don['ten_nhan_vien']); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-0"><strong>Ngày thanh toán:</strong> 
                                <?php echo $hoa_don['ngay_thanh_toan'] ? date('d/m/Y', strtotime($hoa_don['ngay_thanh_toan'])) : 'Chưa thanh toán'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="/quanlykhachsan/admin/hoa_don/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush(); // Xóa bộ nhớ đệm
?>