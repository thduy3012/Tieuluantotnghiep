<?php
// Khai báo sử dụng session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Nhúng file header
require_once __DIR__ . '/../../includes/header.php';

// Nhúng file config để kết nối database
require_once __DIR__ . '/../../config/config.php';

// Lấy ID hóa đơn từ tham số URL
$id_hoa_don = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_hoa_don <= 0) {
    // Nếu không có ID hợp lệ, chuyển hướng về trang danh sách hóa đơn
    $_SESSION['message'] = "ID hóa đơn không hợp lệ";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/hoa_don/index.php");
    exit();
}

// Lấy thông tin hóa đơn
$sql_hoa_don = "SELECT hd.*, dp.ngay_nhan_phong, dp.ngay_tra_phong, dp.tien_coc, dp.trang_thai as trang_thai_dp, 
                kh.ho_ten as ten_khach_hang, kh.so_cmnd, kh.so_dien_thoai, kh.dia_chi,
                p.so_phong, p.loai_phong, p.gia_ngay, nv.ho_ten as ten_nhan_vien
                FROM hoa_don hd
                JOIN dat_phong dp ON hd.id_dat_phong = dp.id
                JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                JOIN phong p ON dp.id_phong = p.id
                JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
                WHERE hd.id = ?";
$hoa_don = fetchSingleRow($sql_hoa_don, [$id_hoa_don]);

if (!$hoa_don) {
    // Nếu không tìm thấy hóa đơn, chuyển hướng về trang danh sách
    $_SESSION['message'] = "Không tìm thấy thông tin hóa đơn";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/hoa_don/index.php");
    exit();
}

// Lấy danh sách dịch vụ đã sử dụng
$sql_dich_vu = "SELECT sddv.*, dv.ten_dich_vu, dv.gia 
                FROM su_dung_dich_vu sddv
                JOIN dich_vu dv ON sddv.id_dich_vu = dv.id
                WHERE sddv.id_dat_phong = ?";
$dich_vu_list = fetchAllRows($sql_dich_vu, [$hoa_don['id_dat_phong']]);

// Tính số ngày lưu trú
$ngay_nhan = new DateTime($hoa_don['ngay_nhan_phong']);
$ngay_tra = new DateTime($hoa_don['ngay_tra_phong']);
$so_ngay = $ngay_tra->diff($ngay_nhan)->days;
if ($so_ngay == 0) $so_ngay = 1; // Tối thiểu 1 ngày
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Chi tiết hóa đơn #<?php echo $id_hoa_don; ?></h1>
        <div>
            <a href="/quanlykhachsan/admin/hoa_don/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
            <button onclick="window.print()" class="btn btn-primary ms-2">
                <i class="fas fa-print me-2"></i>In hóa đơn
            </button>
            <?php if ($hoa_don['trang_thai'] == 'chưa thanh toán'): ?>
            <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php?id=<?php echo $id_hoa_don; ?>" class="btn btn-success ms-2">
                <i class="fas fa-money-bill-wave me-2"></i>Thanh toán
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="mb-3">Thông tin khách hàng</h5>
                    <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($hoa_don['ten_khach_hang']); ?></p>
                    <p><strong>CMND/CCCD:</strong> <?php echo htmlspecialchars($hoa_don['so_cmnd']); ?></p>
                    <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($hoa_don['so_dien_thoai']); ?></p>
                    <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($hoa_don['dia_chi']); ?></p>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-3">Thông tin đặt phòng</h5>
                    <p><strong>Mã đặt phòng:</strong> #<?php echo $hoa_don['id_dat_phong']; ?></p>
                    <p><strong>Phòng:</strong> <?php echo htmlspecialchars($hoa_don['so_phong']); ?> (<?php echo htmlspecialchars($hoa_don['loai_phong']); ?>)</p>
                    <p><strong>Nhận phòng:</strong> <?php echo date('d/m/Y', strtotime($hoa_don['ngay_nhan_phong'])); ?></p>
                    <p><strong>Trả phòng:</strong> <?php echo date('d/m/Y', strtotime($hoa_don['ngay_tra_phong'])); ?></p>
                    <p><strong>Số ngày lưu trú:</strong> <?php echo $so_ngay; ?> ngày</p>
                    <p><strong>Tiền cọc:</strong> <?php echo number_format($hoa_don['tien_coc'], 0, ',', '.'); ?> VNĐ</p>
                    <p><strong>Trạng thái đặt phòng:</strong> <?php echo htmlspecialchars($hoa_don['trang_thai_dp']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Chi tiết thanh toán</h5>
        </div>
        <div class="card-body">
            <h6 class="mb-3">Tiền phòng</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Phòng</th>
                            <th>Loại phòng</th>
                            <th>Giá/ngày</th>
                            <th>Số ngày</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($hoa_don['so_phong']); ?></td>
                            <td><?php echo htmlspecialchars($hoa_don['loai_phong']); ?></td>
                            <td><?php echo number_format($hoa_don['gia_ngay'], 0, ',', '.'); ?> VNĐ</td>
                            <td><?php echo $so_ngay; ?></td>
                            <td class="text-end"><?php echo number_format(abs($hoa_don['tong_tien_phong']), 0, ',', '.'); ?> VNĐ</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h6 class="mb-3">Dịch vụ sử dụng</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Tên dịch vụ</th>
                            <th>Ngày sử dụng</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dich_vu_list && count($dich_vu_list) > 0): ?>
                            <?php foreach ($dich_vu_list as $dv): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dv['ten_dich_vu']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($dv['ngay_su_dung'])); ?></td>
                                    <td><?php echo number_format($dv['gia'], 0, ',', '.'); ?> VNĐ</td>
                                    <td><?php echo $dv['so_luong']; ?></td>
                                    <td class="text-end"><?php echo number_format(abs($dv['thanh_tien']), 0, ',', '.'); ?> VNĐ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Không có dịch vụ nào được sử dụng</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Tổng tiền dịch vụ:</th>
                            <th class="text-end"><?php echo number_format(abs($hoa_don['tong_tien_dich_vu']), 0, ',', '.'); ?> VNĐ</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Thông tin thanh toán</h6>
                    <p><strong>Trạng thái:</strong> 
                        <?php if ($hoa_don['trang_thai'] == 'đã thanh toán'): ?>
                            <span class="badge bg-success">Đã thanh toán</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Chưa thanh toán</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($hoa_don['trang_thai'] == 'đã thanh toán'): ?>
                        <p><strong>Ngày thanh toán:</strong> <?php echo date('d/m/Y', strtotime($hoa_don['ngay_thanh_toan'])); ?></p>
                    <?php endif; ?>
                    <p><strong>Nhân viên phụ trách:</strong> <?php echo htmlspecialchars($hoa_don['ten_nhan_vien']); ?></p>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light">
                    <div class="card-body">
                            <h6 class="card-title">Tổng kết</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tiền phòng:</span>
                                <strong><?php echo number_format(abs($hoa_don['tong_tien_phong']), 0, ',', '.'); ?> VNĐ</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tiền dịch vụ:</span>
                                <strong><?php echo number_format(abs($hoa_don['tong_tien_dich_vu']), 0, ',', '.'); ?> VNĐ</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tiền cọc:</span>
                                <strong>- <?php echo number_format(abs($hoa_don['tien_coc']), 0, ',', '.'); ?> VNĐ</strong>
                            </div>
                            <hr>
                            <?php
                        // Tính toán lại tổng thanh toán
                        $tong_thanh_toan = abs($hoa_don['tong_tien_phong']) + abs($hoa_don['tong_tien_dich_vu']) - abs($hoa_don['tien_coc']);
                        ?>

                        <div class="d-flex justify-content-between">
                            <h6>Tổng thanh toán:</h6>
                            <h5 class="text-primary"><?php echo number_format($tong_thanh_toan, 0, ',', '.'); ?> VNĐ</h5>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style type="text/css" media="print">
    @media print {
        .navbar, header, .btn, footer {
            display: none !important;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
        body {
            font-size: 14px;
        }
        .container-fluid {
            width: 100%;
            padding: 0;
        }
    }
</style>

<?php
// Nhúng file footer
require_once __DIR__ . '/../../includes/footer.php';
?>