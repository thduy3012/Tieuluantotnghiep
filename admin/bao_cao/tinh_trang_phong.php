<?php
// Bắt đầu phiên làm việc nếu chưa bắt đầu
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Import file cấu hình và header
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Kiểm tra phân quyền, chỉ quản lý mới được truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'quản lý') {
    $_SESSION['message'] = 'Bạn không có quyền truy cập trang này!';
    $_SESSION['message_type'] = 'danger';
    header('Location: /quanlykhachsan/index.php');
    exit();
}

// Lấy thống kê số lượng phòng theo trạng thái
$thong_ke_trang_thai = fetchAllRows("
    SELECT trang_thai, COUNT(*) AS so_luong 
    FROM phong 
    GROUP BY trang_thai
");

// Lấy thống kê số lượng phòng theo loại
$thong_ke_loai = fetchAllRows("
    SELECT loai_phong, COUNT(*) AS so_luong 
    FROM phong 
    GROUP BY loai_phong
");

// Lấy danh sách phòng với thông tin chi tiết
$filter_trang_thai = isset($_GET['trang_thai']) ? $_GET['trang_thai'] : '';
$filter_loai = isset($_GET['loai_phong']) ? $_GET['loai_phong'] : '';

$sql_phong = "SELECT p.*, 
    CASE 
        WHEN p.trang_thai = 'đang sử dụng' THEN 
            (SELECT dp.ngay_tra_phong 
             FROM dat_phong dp 
             WHERE dp.id_phong = p.id AND dp.trang_thai = 'đã nhận phòng' 
             ORDER BY dp.ngay_tra_phong 
             LIMIT 1)
        WHEN p.trang_thai = 'đã đặt' THEN 
            (SELECT dp.ngay_nhan_phong 
             FROM dat_phong dp 
             WHERE dp.id_phong = p.id AND dp.trang_thai = 'đã đặt' 
             ORDER BY dp.ngay_nhan_phong 
             LIMIT 1)
        ELSE NULL
    END AS ngay_chuyen_trang_thai
FROM phong p ";

$params = [];
$where_clauses = [];

if (!empty($filter_trang_thai)) {
    $where_clauses[] = "p.trang_thai = ?";
    $params[] = $filter_trang_thai;
}

if (!empty($filter_loai)) {
    $where_clauses[] = "p.loai_phong = ?";
    $params[] = $filter_loai;
}

if (!empty($where_clauses)) {
    $sql_phong .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_phong .= " ORDER BY p.so_phong";

$danh_sach_phong = fetchAllRows($sql_phong, $params);

// Lấy thông tin sử dụng phòng trong tháng hiện tại
$thong_ke_su_dung = fetchSingleRow("
    SELECT 
        COUNT(DISTINCT dp.id_phong) AS so_phong_da_su_dung,
        SUM(DATEDIFF(dp.ngay_tra_phong, dp.ngay_nhan_phong)) AS tong_ngay_su_dung
    FROM dat_phong dp
    WHERE (dp.trang_thai = 'đã nhận phòng' OR dp.trang_thai = 'đã trả phòng')
    AND (
        (MONTH(dp.ngay_nhan_phong) = MONTH(CURRENT_DATE) AND YEAR(dp.ngay_nhan_phong) = YEAR(CURRENT_DATE))
        OR
        (MONTH(dp.ngay_tra_phong) = MONTH(CURRENT_DATE) AND YEAR(dp.ngay_tra_phong) = YEAR(CURRENT_DATE))
    )
");

// Lấy số lượng tổng phòng
$tong_phong = fetchSingleRow("SELECT COUNT(*) AS total FROM phong");

// Tính tỷ lệ sử dụng phòng
$ty_le_su_dung = 0;
if ($thong_ke_su_dung && $tong_phong && $tong_phong['total'] > 0) {
    $ty_le_su_dung = ($thong_ke_su_dung['so_phong_da_su_dung'] / $tong_phong['total']) * 100;
}

// Hàm lấy class CSS tương ứng với trạng thái phòng
function getTrangThaiClass($trang_thai) {
    switch ($trang_thai) {
        case 'trống': return 'success';
        case 'đã đặt': return 'warning';
        case 'đang sử dụng': return 'primary';
        case 'bảo trì': return 'danger';
        default: return 'secondary';
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Báo Cáo Tình Trạng Phòng</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Quản trị</a></li>
        <li class="breadcrumb-item active">Tình trạng phòng</li>
    </ol>
    
    <!-- Thống kê tổng quan -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    Phòng trống:
                    <?php
                    $phong_trong = 0;
                    foreach ($thong_ke_trang_thai as $item) {
                        if ($item['trang_thai'] == 'trống') {
                            $phong_trong = $item['so_luong'];
                            break;
                        }
                    }
                    echo $phong_trong;
                    ?>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    Phòng đã đặt:
                    <?php
                    $phong_da_dat = 0;
                    foreach ($thong_ke_trang_thai as $item) {
                        if ($item['trang_thai'] == 'đã đặt') {
                            $phong_da_dat = $item['so_luong'];
                            break;
                        }
                    }
                    echo $phong_da_dat;
                    ?>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    Phòng đang sử dụng:
                    <?php
                    $phong_dang_su_dung = 0;
                    foreach ($thong_ke_trang_thai as $item) {
                        if ($item['trang_thai'] == 'đang sử dụng') {
                            $phong_dang_su_dung = $item['so_luong'];
                            break;
                        }
                    }
                    echo $phong_dang_su_dung;
                    ?>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    Phòng bảo trì:
                    <?php
                    $phong_bao_tri = 0;
                    foreach ($thong_ke_trang_thai as $item) {
                        if ($item['trang_thai'] == 'bảo trì') {
                            $phong_bao_tri = $item['so_luong'];
                            break;
                        }
                    }
                    echo $phong_bao_tri;
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Thống kê theo loại phòng -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Thống kê theo loại phòng
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Loại phòng</th>
                                <th class="text-center">Số lượng</th>
                                <th class="text-center">Tỷ lệ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tong_so_phong = $tong_phong['total'];
                            foreach ($thong_ke_loai as $loai) {
                                $ty_le = ($tong_so_phong > 0) ? round(($loai['so_luong'] / $tong_so_phong) * 100, 2) : 0;
                                ?>
                                <tr>
                                    <td><?php echo ucfirst($loai['loai_phong']); ?></td>
                                    <td class="text-center"><?php echo $loai['so_luong']; ?></td>
                                    <td class="text-center"><?php echo $ty_le; ?>%</td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Tổng cộng</th>
                                <th class="text-center"><?php echo $tong_so_phong; ?></th>
                                <th class="text-center">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Thống kê sử dụng phòng trong tháng -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    Thống kê sử dụng phòng trong tháng <?php echo date('m/Y'); ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Số phòng đã sử dụng</h5>
                                    <p class="card-text fs-4"><?php echo $thong_ke_su_dung ? $thong_ke_su_dung['so_phong_da_su_dung'] : 0; ?> / <?php echo $tong_so_phong; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Tổng số ngày sử dụng</h5>
                                    <p class="card-text fs-4"><?php echo $thong_ke_su_dung ? $thong_ke_su_dung['tong_ngay_su_dung'] : 0; ?> ngày</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Tỷ lệ sử dụng</h5>
                                    <p class="card-text fs-4"><?php echo number_format($ty_le_su_dung, 2); ?>%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bộ lọc -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Lọc danh sách phòng
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-5">
                    <label for="trang_thai" class="form-label">Trạng thái</label>
                    <select name="trang_thai" id="trang_thai" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="trống" <?php echo $filter_trang_thai === 'trống' ? 'selected' : ''; ?>>Trống</option>
                        <option value="đã đặt" <?php echo $filter_trang_thai === 'đã đặt' ? 'selected' : ''; ?>>Đã đặt</option>
                        <option value="đang sử dụng" <?php echo $filter_trang_thai === 'đang sử dụng' ? 'selected' : ''; ?>>Đang sử dụng</option>
                        <option value="bảo trì" <?php echo $filter_trang_thai === 'bảo trì' ? 'selected' : ''; ?>>Bảo trì</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="loai_phong" class="form-label">Loại phòng</label>
                    <select name="loai_phong" id="loai_phong" class="form-select">
                    <option value="">Tất cả loại phòng</option>
<option value="đơn" <?php echo $filter_loai === 'đơn' ? 'selected' : ''; ?>>Phòng đơn</option>
<option value="đôi" <?php echo $filter_loai === 'đôi' ? 'selected' : ''; ?>>Phòng đôi</option>
<option value="vip" <?php echo $filter_loai === 'vip' ? 'selected' : ''; ?>>Phòng VIP</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bảng danh sách phòng -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Chi tiết tình trạng phòng
        </div>
        <div class="card-body">
            <table id="dataTable" class="table table-bordered">
                <thead>
                    <tr>
                        <th width="10%">Số phòng</th>
                        <th width="15%">Loại phòng</th>
                        <th width="15%">Giá/ngày</th>
                        <th width="15%">Trạng thái</th>
                        <th width="45%">Thông tin bổ sung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($danh_sach_phong as $phong): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($phong['so_phong']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($phong['loai_phong'])); ?></td>
                            <td><?php echo number_format($phong['gia_ngay'], 0, ',', '.'); ?> VNĐ</td>
                            <td>
                                <span class="badge bg-<?php echo getTrangThaiClass($phong['trang_thai']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($phong['trang_thai'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                switch ($phong['trang_thai']) {
                                    case 'đang sử dụng':
                                        $chi_tiet = fetchSingleRow("
                                            SELECT dp.*, kh.ho_ten AS ten_khach
                                            FROM dat_phong dp
                                            JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                                            WHERE dp.id_phong = ? AND dp.trang_thai = 'đã nhận phòng'
                                            ORDER BY dp.ngay_tra_phong
                                            LIMIT 1
                                        ", [$phong['id']]);
                                        
                                        if ($chi_tiet) {
                                            echo "Khách hàng: <strong>" . htmlspecialchars($chi_tiet['ten_khach']) . "</strong><br>";
                                            echo "Nhận phòng: " . date('d/m/Y', strtotime($chi_tiet['ngay_nhan_phong'])) . "<br>";
                                            echo "Trả phòng: " . date('d/m/Y', strtotime($chi_tiet['ngay_tra_phong'])) . "<br>";
                                            echo "Còn lại: " . max(0, floor((strtotime($chi_tiet['ngay_tra_phong']) - time()) / 86400)) . " ngày";
                                        }
                                        break;
                                        
                                    case 'đã đặt':
                                        $chi_tiet = fetchSingleRow("
                                            SELECT dp.*, kh.ho_ten AS ten_khach
                                            FROM dat_phong dp
                                            JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                                            WHERE dp.id_phong = ? AND dp.trang_thai = 'đã đặt'
                                            ORDER BY dp.ngay_nhan_phong
                                            LIMIT 1
                                        ", [$phong['id']]);
                                        
                                        if ($chi_tiet) {
                                            echo "Khách hàng: <strong>" . htmlspecialchars($chi_tiet['ten_khach']) . "</strong><br>";
                                            echo "Nhận phòng: " . date('d/m/Y', strtotime($chi_tiet['ngay_nhan_phong'])) . "<br>";
                                            echo "Đến ngày nhận phòng còn: " . max(0, floor((strtotime($chi_tiet['ngay_nhan_phong']) - time()) / 86400)) . " ngày";
                                        }
                                        break;
                                        
                                    case 'bảo trì':
                                        echo "Phòng đang trong quá trình bảo trì";
                                        break;
                                        
                                    default:
                                        echo "Phòng trống, sẵn sàng đặt phòng";
                                        break;
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the datatable
    if (document.getElementById('dataTable')) {
        $('#dataTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/vi.json'
            }
        });
    }
});
</script>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
?>