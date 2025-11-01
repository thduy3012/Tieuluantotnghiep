<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    // Lưu thông báo và chuyển hướng đến trang đăng nhập
    $_SESSION['message'] = 'Vui lòng đăng nhập để tiếp tục!';
    $_SESSION['message_type'] = 'warning';
    header('Location: /quanlykhachsan/auth/login.php');
    exit;
}

// Import file cấu hình và các hàm chung
require_once __DIR__ . '/../../config/config.php';

// Kiểm tra tham số ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = 'Thiếu thông tin đặt phòng!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/dat_phong/index.php');
    exit;
}

$id_dat_phong = intval($_GET['id']);

// Lấy thông tin đặt phòng, khách hàng, phòng và nhân viên
$sql = "SELECT dp.*, kh.ho_ten as ten_khach_hang, kh.so_cmnd, kh.so_dien_thoai, 
        p.so_phong, p.loai_phong, p.gia_ngay,
        nv.ho_ten as ten_nhan_vien
        FROM dat_phong dp
        JOIN khach_hang kh ON dp.id_khach_hang = kh.id
        JOIN phong p ON dp.id_phong = p.id
        JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
        WHERE dp.id = ?";
        
$dat_phong = fetchSingleRow($sql, [$id_dat_phong]);

if (!$dat_phong) {
    $_SESSION['message'] = 'Không tìm thấy thông tin đặt phòng!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/dat_phong/index.php');
    exit;
}

// Tính số ngày lưu trú
$ngay_nhan_phong = new DateTime($dat_phong['ngay_nhan_phong']);
$ngay_tra_phong = new DateTime($dat_phong['ngay_tra_phong']);
$so_ngay = $ngay_tra_phong->diff($ngay_nhan_phong)->days;
if ($so_ngay === 0) $so_ngay = 1; // Tối thiểu 1 ngày

// Tính tổng tiền phòng
$tong_tien_phong = $so_ngay * $dat_phong['gia_ngay'];

// Lấy thông tin các dịch vụ đã sử dụng
$sql_dich_vu = "SELECT sddv.*, dv.ten_dich_vu, dv.gia
                FROM su_dung_dich_vu sddv
                JOIN dich_vu dv ON sddv.id_dich_vu = dv.id
                WHERE sddv.id_dat_phong = ?
                ORDER BY sddv.ngay_su_dung";
                
$dich_vu_su_dung = fetchAllRows($sql_dich_vu, [$id_dat_phong]);

// Tính tổng tiền dịch vụ
$tong_tien_dich_vu = 0;
if ($dich_vu_su_dung) {
    foreach ($dich_vu_su_dung as $dv) {
        $tong_tien_dich_vu += $dv['thanh_tien'];
    }
}

// Lấy thông tin hóa đơn nếu có
$sql_hoa_don = "SELECT * FROM hoa_don WHERE id_dat_phong = ?";
$hoa_don = fetchSingleRow($sql_hoa_don, [$id_dat_phong]);

// Tính tổng thanh toán
$tong_thanh_toan = $tong_tien_phong + $tong_tien_dich_vu;

// Include header
include __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2>
                <i class="fas fa-info-circle"></i> Chi tiết đặt phòng #<?php echo $id_dat_phong; ?>
                <span class="badge <?php
                    switch($dat_phong['trang_thai']) {
                        case 'đã đặt': echo 'bg-warning'; break;
                        case 'đã nhận phòng': echo 'bg-primary'; break;
                        case 'đã trả phòng': echo 'bg-success'; break;
                        case 'đã hủy': echo 'bg-danger'; break;
                        default: echo 'bg-secondary';
                    }
                ?>">
                    <?php echo ucfirst($dat_phong['trang_thai']); ?>
                </span>
            </h2>
        </div>
        <div class="col-auto">
            <a href="/quanlykhachsan/admin/dat_phong/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            
            <?php if ($dat_phong['trang_thai'] == 'đã đặt'): ?>
            <a href="/quanlykhachsan/admin/dat_phong/nhan_phong.php?id=<?php echo $id_dat_phong; ?>" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> Nhận phòng
            </a>
            <?php endif; ?>
            
            <?php if ($dat_phong['trang_thai'] == 'đã nhận phòng'): ?>
            <a href="/quanlykhachsan/admin/dat_phong/tra_phong.php?id=<?php echo $id_dat_phong; ?>" class="btn btn-success">
                <i class="fas fa-door-open"></i> Trả phòng
            </a>
            <?php endif; ?>
            
            <?php if (($dat_phong['trang_thai'] == 'đã đặt' || $dat_phong['trang_thai'] == 'đã nhận phòng') && $_SESSION['role'] == 'quản lý'): ?>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#huyDatPhongModal">
                <i class="fas fa-times-circle"></i> Hủy đặt phòng
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Thông tin đặt phòng -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Thông tin đặt phòng</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Ngày đặt phòng:</div>
                        <div class="col-sm-7"><?php echo date('d/m/Y', strtotime($dat_phong['ngay_nhan_phong'])); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Ngày trả phòng:</div>
                        <div class="col-sm-7"><?php echo date('d/m/Y', strtotime($dat_phong['ngay_tra_phong'])); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Số ngày lưu trú:</div>
                        <div class="col-sm-7"><?php echo $so_ngay; ?> ngày</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Tiền cọc:</div>
                        <div class="col-sm-7"><?php echo number_format($dat_phong['tien_coc'], 0, ',', '.'); ?> VNĐ</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Nhân viên xử lý:</div>
                        <div class="col-sm-7"><?php echo htmlspecialchars($dat_phong['ten_nhan_vien']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Thông tin khách hàng -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Họ tên:</div>
                        <div class="col-sm-7"><?php echo htmlspecialchars($dat_phong['ten_khach_hang']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Số CMND/CCCD:</div>
                        <div class="col-sm-7"><?php echo htmlspecialchars($dat_phong['so_cmnd']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Số điện thoại:</div>
                        <div class="col-sm-7"><?php echo htmlspecialchars($dat_phong['so_dien_thoai']); ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <a href="/quanlykhachsan/admin/khach_hang/chi_tiet.php?id=<?php echo $dat_phong['id_khach_hang']; ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-eye"></i> Xem chi tiết khách hàng
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Thông tin phòng -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-door-closed"></i> Thông tin phòng</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Số phòng:</div>
                        <div class="col-sm-7"><?php echo htmlspecialchars($dat_phong['so_phong']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Loại phòng:</div>
                        <div class="col-sm-7"><?php echo ucfirst($dat_phong['loai_phong']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Giá/ngày:</div>
                        <div class="col-sm-7"><?php echo number_format($dat_phong['gia_ngay'], 0, ',', '.'); ?> VNĐ</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-bold">Tổng tiền phòng:</div>
                        <div class="col-sm-7 fw-bold text-success"><?php echo number_format($tong_tien_phong, 0, ',', '.'); ?> VNĐ</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Thông tin thanh toán -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Thông tin thanh toán</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-7 fw-bold">Tổng tiền phòng:</div>
                        <div class="col-sm-5 text-end"><?php echo number_format($tong_tien_phong, 0, ',', '.'); ?> VNĐ</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-7 fw-bold">Tổng tiền dịch vụ:</div>
                        <div class="col-sm-5 text-end"><?php echo number_format($tong_tien_dich_vu, 0, ',', '.'); ?> VNĐ</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-7 fw-bold">Tiền cọc:</div>
                        <div class="col-sm-5 text-end">- <?php echo number_format($dat_phong['tien_coc'], 0, ',', '.'); ?> VNĐ</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-7 fw-bold fs-5">Còn lại phải thanh toán:</div>
                        <div class="col-sm-5 text-end fw-bold fs-5 text-danger">
                        <?php echo number_format($tong_thanh_toan - $dat_phong['tien_coc'], 0, ',', '.'); ?> VNĐ
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <?php if ($hoa_don): ?>
                                <?php if ($hoa_don['trang_thai'] == 'đã thanh toán'): ?>
                                    <div class="alert alert-success mb-3">
                                        <i class="fas fa-check-circle"></i> Đã thanh toán vào ngày <?php echo date('d/m/Y', strtotime($hoa_don['ngay_thanh_toan'])); ?>
                                    </div>
                                <?php else: ?>
                                    <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-warning w-100">
                                        <i class="fas fa-money-bill-wave"></i> Thanh toán ngay
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($dat_phong['trang_thai'] == 'đã trả phòng'): ?>
                                <a href="/quanlykhachsan/admin/hoa_don/tao_hoa_don.php?id_dat_phong=<?php echo $id_dat_phong; ?>" class="btn btn-warning w-100">
                                    <i class="fas fa-file-invoice"></i> Tạo hóa đơn
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách dịch vụ đã sử dụng -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-concierge-bell"></i> Dịch vụ đã sử dụng</h5>
            <?php if ($dat_phong['trang_thai'] == 'đã nhận phòng'): ?>
                <a href="/quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php?id_dat_phong=<?php echo $id_dat_phong; ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> Thêm dịch vụ
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($dich_vu_su_dung)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tên dịch vụ</th>
                                <th>Ngày sử dụng</th>
                                <th>Số lượng</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                                <?php if ($dat_phong['trang_thai'] == 'đã nhận phòng'): ?>
                                    <th>Hành động</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $stt = 1; foreach ($dich_vu_su_dung as $dv): ?>
                                <tr>
                                    <td><?php echo $stt++; ?></td>
                                    <td><?php echo htmlspecialchars($dv['ten_dich_vu']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($dv['ngay_su_dung'])); ?></td>
                                    <td><?php echo $dv['so_luong']; ?></td>
                                    <td><?php echo number_format($dv['gia'], 0, ',', '.'); ?> VNĐ</td>
                                    <td><?php echo number_format($dv['thanh_tien'], 0, ',', '.'); ?> VNĐ</td>
                                    <?php if ($dat_phong['trang_thai'] == 'đã nhận phòng'): ?>
                                        <td>
                                            <a href="/quanlykhachsan/admin/dich_vu/sua_dich_vu_su_dung.php?id=<?php echo $dv['id']; ?>&id_dat_phong=<?php echo $id_dat_phong; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#xoaDichVuModal<?php echo $dv['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <!-- Modal xác nhận xóa dịch vụ -->
                                            <div class="modal fade" id="xoaDichVuModal<?php echo $dv['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Xác nhận xóa</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Bạn có chắc chắn muốn xóa dịch vụ <strong><?php echo htmlspecialchars($dv['ten_dich_vu']); ?></strong> khỏi danh sách dịch vụ đã sử dụng?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <a href="/quanlykhachsan/admin/dich_vu/xoa_dich_vu_su_dung.php?id=<?php echo $dv['id']; ?>&id_dat_phong=<?php echo $id_dat_phong; ?>" class="btn btn-danger">Xóa</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-primary">
                                <td colspan="<?php echo ($dat_phong['trang_thai'] == 'đã nhận phòng') ? '5' : '5'; ?>" class="text-end fw-bold">Tổng tiền dịch vụ:</td>
                                <td class="fw-bold"><?php echo number_format($tong_tien_dich_vu, 0, ',', '.'); ?> VNĐ</td>
                                <?php if ($dat_phong['trang_thai'] == 'đã nhận phòng'): ?><td></td><?php endif; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Chưa có dịch vụ nào được sử dụng
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lịch sử thao tác (phần này có thể thêm sau nếu cần) -->
</div>

<!-- Modal xác nhận hủy đặt phòng -->
<?php if (($dat_phong['trang_thai'] == 'đã đặt' || $dat_phong['trang_thai'] == 'đã nhận phòng') && $_SESSION['role'] == 'quản lý'): ?>
<div class="modal fade" id="huyDatPhongModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận hủy đặt phòng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Cảnh báo: Hành động này không thể hoàn tác!
                </div>
                <p>Bạn có chắc chắn muốn hủy đặt phòng của khách hàng <strong><?php echo htmlspecialchars($dat_phong['ten_khach_hang']); ?></strong>?</p>
                <?php if ($dat_phong['trang_thai'] == 'đã nhận phòng'): ?>
                    <p class="text-danger fw-bold">Khách hàng đã nhận phòng! Việc hủy phòng tại thời điểm này có thể ảnh hưởng đến trải nghiệm của khách hàng.</p>
                <?php endif; ?>
                <?php if ($dat_phong['tien_coc'] > 0): ?>
                    <p>Tiền cọc đã thanh toán: <strong><?php echo number_format($dat_phong['tien_coc'], 0, ',', '.'); ?> VNĐ</strong></p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="hoanTienCoc" checked>
                        <label class="form-check-label" for="hoanTienCoc">
                            Hoàn trả tiền cọc cho khách hàng
                        </label>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="lyDoHuy" class="form-label">Lý do hủy:</label>
                    <textarea class="form-control" id="lyDoHuy" rows="3" placeholder="Nhập lý do hủy đặt phòng..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-danger" id="btnXacNhanHuy">Hủy đặt phòng</button>

<script>
document.getElementById('btnXacNhanHuy').addEventListener('click', function() {
    var lyDo = document.getElementById('lyDoHuy').value;
    var hoanTienCoc = document.getElementById('hoanTienCoc')?.checked ? '1' : '0';
    
    if (!lyDo) {
        alert('Vui lòng nhập lý do hủy đặt phòng!');
        return;
    }
    
    // Chuyển hướng với các tham số
    window.location.href = '/quanlykhachsan/admin/dat_phong/huy_dat_phong.php?id=<?php echo $id_dat_phong; ?>&ly_do=' + encodeURIComponent(lyDo) + '&hoan_tien=' + hoanTienCoc;
});
</script>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include __DIR__ . '/../../includes/footer.php';
?>