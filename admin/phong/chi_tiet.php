<?php
// Bắt đầu phiên làm việc để sử dụng biến session
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Chuyển hướng đến trang đăng nhập nếu chưa đăng nhập
    header('Location: /quanlykhachsan/auth/login.php');
    exit;
}

// Import file cấu hình và hàm chung
require_once __DIR__ . '/../../config/config.php';

// Kiểm tra ID phòng từ tham số URL
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = 'ID phòng không hợp lệ!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/phong/index.php');
    exit;
}

$phong_id = (int)$_GET['id'];

// Lấy thông tin chi tiết của phòng
$sql_phong = "SELECT * FROM phong WHERE id = ?";
$phong = fetchSingleRow($sql_phong, [$phong_id]);

if (!$phong) {
    $_SESSION['message'] = 'Không tìm thấy thông tin phòng!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/admin/phong/index.php');
    exit;
}

// Lấy lịch sử đặt phòng của phòng này
$sql_dat_phong = "
    SELECT dp.*, kh.ho_ten as ten_khach_hang, nv.ho_ten as ten_nhan_vien
    FROM dat_phong dp
    JOIN khach_hang kh ON dp.id_khach_hang = kh.id
    JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
    WHERE dp.id_phong = ?
    ORDER BY dp.ngay_nhan_phong DESC
";
$lich_su_dat_phong = fetchAllRows($sql_dat_phong, [$phong_id]);

// Lấy thông tin về hóa đơn của các lần đặt phòng
$hoa_don_info = [];
if ($lich_su_dat_phong) {
    foreach ($lich_su_dat_phong as $dat_phong) {
        $sql_hoa_don = "
            SELECT hd.*, 
                  (SELECT SUM(thanh_tien) FROM su_dung_dich_vu WHERE id_dat_phong = hd.id_dat_phong) as tong_dich_vu
            FROM hoa_don hd
            WHERE hd.id_dat_phong = ?
        ";
        $hoa_don = fetchSingleRow($sql_hoa_don, [$dat_phong['id']]);
        if ($hoa_don) {
            $hoa_don_info[$dat_phong['id']] = $hoa_don;
        }
    }
}

// Lấy 5 phòng cùng loại để gợi ý
$sql_phong_tuong_tu = "
    SELECT * FROM phong 
    WHERE loai_phong = ? AND id != ? 
    LIMIT 5
";
$phong_tuong_tu = fetchAllRows($sql_phong_tuong_tu, [$phong['loai_phong'], $phong_id]);

// Include header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/phong/index.php">Danh sách phòng</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết phòng <?php echo htmlspecialchars($phong['so_phong']); ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-door-open me-2"></i>
                        Chi tiết phòng <?php echo htmlspecialchars($phong['so_phong']); ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Số phòng:</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($phong['so_phong']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Loại phòng:</div>
                        <div class="col-md-8">
                            <?php 
                            $loai_phong_text = '';
                            switch($phong['loai_phong']) {
                                case 'đơn':
                                    $loai_phong_text = '<span class="badge bg-info">Phòng đơn</span>';
                                    break;
                                case 'đôi':
                                    $loai_phong_text = '<span class="badge bg-success">Phòng đôi</span>';
                                    break;
                                case 'vip':
                                    $loai_phong_text = '<span class="badge bg-warning">Phòng VIP</span>';
                                    break;
                                default:
                                    $loai_phong_text = '<span class="badge bg-secondary">Không xác định</span>';
                            }
                            echo $loai_phong_text;
                            ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Giá phòng:</div>
                        <div class="col-md-8"><?php echo number_format($phong['gia_ngay'], 0, ',', '.'); ?> VNĐ/ngày</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Trạng thái:</div>
                        <div class="col-md-8">
                            <?php 
                            $trang_thai_text = '';
                            switch($phong['trang_thai']) {
                                case 'trống':
                                    $trang_thai_text = '<span class="badge bg-success">Trống</span>';
                                    break;
                                case 'đã đặt':
                                    $trang_thai_text = '<span class="badge bg-warning">Đã đặt</span>';
                                    break;
                                case 'đang sử dụng':
                                    $trang_thai_text = '<span class="badge bg-danger">Đang sử dụng</span>';
                                    break;
                                case 'bảo trì':
                                    $trang_thai_text = '<span class="badge bg-secondary">Đang bảo trì</span>';
                                    break;
                                default:
                                    $trang_thai_text = '<span class="badge bg-secondary">Không xác định</span>';
                            }
                            echo $trang_thai_text;
                            ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <?php if ($phong['trang_thai'] == 'trống'): ?>
                                <a href="/quanlykhachsan/admin/dat_phong/them.php?id_phong=<?php echo $phong_id; ?>" class="btn btn-success">
                                    <i class="fas fa-calendar-plus me-1"></i> Đặt phòng này
                                </a>
                                <?php endif; ?>
                                
                                <a href="/quanlykhachsan/admin/phong/sua.php?id=<?php echo $phong_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-1"></i> Sửa thông tin
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lịch sử đặt phòng -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-history me-2"></i> Lịch sử đặt phòng</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($lich_su_dat_phong)): ?>
                        <p class="text-muted">Chưa có lịch sử đặt phòng nào.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Mã đặt</th>
                                        <th>Khách hàng</th>
                                        <th>Thời gian</th>
                                        <th>Trạng thái</th>
                                        <th>Tiền cọc</th>
                                        <th>Hóa đơn</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lich_su_dat_phong as $dat_phong): ?>
                                    <tr>
                                        <td><?php echo $dat_phong['id']; ?></td>
                                        <td><?php echo htmlspecialchars($dat_phong['ten_khach_hang']); ?></td>
                                        <td>
                                            <small>
                                                <div><strong>Nhận:</strong> <?php echo date('d/m/Y', strtotime($dat_phong['ngay_nhan_phong'])); ?></div>
                                                <div><strong>Trả:</strong> <?php echo date('d/m/Y', strtotime($dat_phong['ngay_tra_phong'])); ?></div>
                                            </small>
                                        </td>
                                        <td>
                                            <?php 
                                            $trang_thai_dat = '';
                                            switch($dat_phong['trang_thai']) {
                                                case 'đã đặt':
                                                    $trang_thai_dat = '<span class="badge bg-warning">Đã đặt</span>';
                                                    break;
                                                case 'đã nhận phòng':
                                                    $trang_thai_dat = '<span class="badge bg-primary">Đã nhận phòng</span>';
                                                    break;
                                                case 'đã trả phòng':
                                                    $trang_thai_dat = '<span class="badge bg-success">Đã trả phòng</span>';
                                                    break;
                                                case 'đã hủy':
                                                    $trang_thai_dat = '<span class="badge bg-danger">Đã hủy</span>';
                                                    break;
                                                default:
                                                    $trang_thai_dat = '<span class="badge bg-secondary">Không xác định</span>';
                                            }
                                            echo $trang_thai_dat;
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo number_format($dat_phong['tien_coc'], 0, ',', '.'); ?> VNĐ
                                        </td>
                                        <td>
                                            <?php if (isset($hoa_don_info[$dat_phong['id']])): ?>
                                                <span class="badge bg-success">Đã xuất</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Chưa có</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="/quanlykhachsan/admin/dat_phong/chi_tiet.php?id=<?php echo $dat_phong['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Phòng tương tự -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-th-list me-2"></i> Phòng cùng loại</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($phong_tuong_tu)): ?>
                        <p class="text-muted">Không tìm thấy phòng cùng loại.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($phong_tuong_tu as $p): ?>
                                <a href="/quanlykhachsan/admin/phong/chi_tiet.php?id=<?php echo $p['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Phòng <?php echo htmlspecialchars($p['so_phong']); ?></h6>
                                        <?php 
                                        $trang_thai_class = '';
                                        switch($p['trang_thai']) {
                                            case 'trống':
                                                $trang_thai_class = 'bg-success';
                                                break;
                                            case 'đã đặt':
                                                $trang_thai_class = 'bg-warning';
                                                break;
                                            case 'đang sử dụng':
                                                $trang_thai_class = 'bg-danger';
                                                break;
                                            case 'bảo trì':
                                                $trang_thai_class = 'bg-secondary';
                                                break;
                                            default:
                                                $trang_thai_class = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $trang_thai_class; ?>"><?php echo ucfirst($p['trang_thai']); ?></span>
                                    </div>
                                    <small><?php echo number_format($p['gia_ngay'], 0, ',', '.'); ?> VNĐ/ngày</small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thông tin kinh doanh -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Thông tin kinh doanh</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Tính số lượng đặt phòng thành công
                    $so_luong_dat_phong = 0;
                    $tong_doanh_thu = 0;
                    
                    foreach ($lich_su_dat_phong as $dat_phong) {
                        if ($dat_phong['trang_thai'] == 'đã trả phòng') {
                            $so_luong_dat_phong++;
                            
                            if (isset($hoa_don_info[$dat_phong['id']])) {
                                $tong_doanh_thu += $hoa_don_info[$dat_phong['id']]['tong_thanh_toan'];
                            }
                        }
                    }
                    ?>

                    <div class="row">
                        <div class="col-6">
                            <div class="stat-item text-center">
                                <h3><?php echo $so_luong_dat_phong; ?></h3>
                                <p class="text-muted">Lượt đặt phòng</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-item text-center">
                                <h3><?php echo number_format($tong_doanh_thu, 0, ',', '.'); ?></h3>
                                <p class="text-muted">Doanh thu (VNĐ)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>