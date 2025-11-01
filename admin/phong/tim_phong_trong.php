<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
include_once __DIR__ . '/../../auth/check_login.php';

// Import file cấu hình và functions
require_once __DIR__ . '/../../config/config.php';
// require_once __DIR__ . '/../../includes/functions.php';

// Đặt giá trị mặc định cho các tham số tìm kiếm
$ngay_nhan = isset($_GET['ngay_nhan']) ? $_GET['ngay_nhan'] : date('Y-m-d');
$ngay_tra = isset($_GET['ngay_tra']) ? $_GET['ngay_tra'] : date('Y-m-d', strtotime('+1 day'));
$loai_phong = isset($_GET['loai_phong']) ? $_GET['loai_phong'] : '';
$gia_min = isset($_GET['gia_min']) ? $_GET['gia_min'] : 0;
$gia_max = isset($_GET['gia_max']) ? $_GET['gia_max'] : 2000000;

// Khởi tạo mảng để lưu danh sách phòng trống
$phong_trong = [];

// Xử lý khi form được submit
if (isset($_GET['search'])) {
    // Xây dựng câu truy vấn tìm phòng trống
    $sql = "
        SELECT p.* 
        FROM phong p
        WHERE p.id NOT IN (
            SELECT dp.id_phong
            FROM dat_phong dp
            WHERE (dp.trang_thai = 'đã đặt' OR dp.trang_thai = 'đã nhận phòng')
            AND (
                (dp.ngay_nhan_phong <= ? AND dp.ngay_tra_phong >= ?) OR
                (dp.ngay_nhan_phong <= ? AND dp.ngay_tra_phong >= ?) OR
                (dp.ngay_nhan_phong >= ? AND dp.ngay_tra_phong <= ?)
            )
        )
        AND p.trang_thai = 'trống'
    ";
    
    // Thêm điều kiện lọc theo loại phòng nếu có
    if (!empty($loai_phong)) {
        $sql .= " AND p.loai_phong = ?";
    }
    
    // Thêm điều kiện lọc theo giá
    $sql .= " AND p.gia_ngay >= ? AND p.gia_ngay <= ?";
    
    // Thêm sắp xếp
    $sql .= " ORDER BY p.loai_phong, p.gia_ngay";
    
    // Chuẩn bị tham số cho truy vấn
    $params = [
        $ngay_tra, $ngay_nhan,
        $ngay_nhan, $ngay_nhan,
        $ngay_nhan, $ngay_tra
    ];
    
    if (!empty($loai_phong)) {
        $params[] = $loai_phong;
    }
    
    $params[] = $gia_min;
    $params[] = $gia_max;
    
    // Thực hiện truy vấn
    $phong_trong = fetchAllRows($sql, $params);
}

// Include header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <h2 class="my-4">Tìm phòng trống</h2>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-search"></i> Tìm kiếm phòng trống
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="ngay_nhan" class="form-label">Ngày nhận phòng</label>
                    <input type="date" class="form-control" id="ngay_nhan" name="ngay_nhan" value="<?php echo $ngay_nhan; ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="ngay_tra" class="form-label">Ngày trả phòng</label>
                    <input type="date" class="form-control" id="ngay_tra" name="ngay_tra" value="<?php echo $ngay_tra; ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="loai_phong" class="form-label">Loại phòng</label>
                    <select class="form-select" id="loai_phong" name="loai_phong">
                        <option value="" <?php echo $loai_phong === '' ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="đơn" <?php echo $loai_phong === 'đơn' ? 'selected' : ''; ?>>Phòng đơn</option>
                        <option value="đôi" <?php echo $loai_phong === 'đôi' ? 'selected' : ''; ?>>Phòng đôi</option>
                        <option value="vip" <?php echo $loai_phong === 'vip' ? 'selected' : ''; ?>>Phòng VIP</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="gia_min" class="form-label">Giá thấp nhất</label>
                    <input type="number" class="form-control" id="gia_min" name="gia_min" value="<?php echo $gia_min; ?>" step="100000">
                </div>
                <div class="col-md-3">
                    <label for="gia_max" class="form-label">Giá cao nhất</label>
                    <input type="number" class="form-control" id="gia_max" name="gia_max" value="<?php echo $gia_max; ?>" step="100000">
                </div>
                <div class="col-12">
                    <button type="submit" name="search" value="1" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['search'])): ?>
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-list"></i> Kết quả tìm kiếm
            </div>
            <div class="card-body">
                <?php if (!empty($phong_trong)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Tìm thấy <?php echo count($phong_trong); ?> phòng trống từ ngày <?php echo date('d/m/Y', strtotime($ngay_nhan)); ?> đến ngày <?php echo date('d/m/Y', strtotime($ngay_tra)); ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Số phòng</th>
                                    <th>Loại phòng</th>
                                    <th>Giá/ngày</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($phong_trong as $phong): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($phong['so_phong']); ?></td>
                                    <td>
                                        <?php
                                        $ten_loai = '';
                                        switch ($phong['loai_phong']) {
                                            case 'đơn':
                                                echo '<span class="badge bg-primary">Phòng đơn</span>';
                                                break;
                                            case 'đôi':
                                                echo '<span class="badge bg-success">Phòng đôi</span>';
                                                break;
                                            case 'vip':
                                                echo '<span class="badge bg-warning text-dark">Phòng VIP</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo number_format($phong['gia_ngay'], 0, ',', '.'); ?> VNĐ</td>
                                    <td>
                                        <?php 
                                        switch ($phong['trang_thai']) {
                                            case 'trống':
                                                echo '<span class="badge bg-success">Trống</span>';
                                                break;
                                            case 'đã đặt':
                                                echo '<span class="badge bg-warning text-dark">Đã đặt</span>';
                                                break;
                                            case 'đang sử dụng':
                                                echo '<span class="badge bg-danger">Đang sử dụng</span>';
                                                break;
                                            case 'bảo trì':
                                                echo '<span class="badge bg-secondary">Bảo trì</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="/quanlykhachsan/admin/dat_phong/them.php?id_phong=<?php echo $phong['id']; ?>&ngay_nhan=<?php echo $ngay_nhan; ?>&ngay_tra=<?php echo $ngay_tra; ?>" class="btn btn-primary btn-sm" title="Đặt phòng">
                                            <i class="fas fa-calendar-check"></i> Đặt
                                        </a>
                                        <a href="/quanlykhachsan/admin/phong/chi_tiet.php?id=<?php echo $phong['id']; ?>" class="btn btn-info btn-sm" title="Chi tiết">
                                            <i class="fas fa-info-circle"></i> Chi tiết
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Không tìm thấy phòng trống nào phù hợp với tiêu chí tìm kiếm.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JS để kiểm tra ngày nhận/trả -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Lấy các trường input
    const ngayNhanInput = document.getElementById('ngay_nhan');
    const ngayTraInput = document.getElementById('ngay_tra');
    
    // Thiết lập ngày tối thiểu cho ngày nhận phòng là ngày hiện tại
    const today = new Date().toISOString().split('T')[0];
    ngayNhanInput.min = today;
    
    // Cập nhật ngày trả phòng tối thiểu khi ngày nhận phòng thay đổi
    ngayNhanInput.addEventListener('change', function() {
        const ngayNhan = new Date(this.value);
        const nextDay = new Date(ngayNhan);
        nextDay.setDate(ngayNhan.getDate() + 1);
        
        ngayTraInput.min = nextDay.toISOString().split('T')[0];
        
        // Nếu ngày trả phòng nhỏ hơn ngày nhận phòng + 1, cập nhật ngày trả phòng
        if (new Date(ngayTraInput.value) <= ngayNhan) {
            ngayTraInput.value = nextDay.toISOString().split('T')[0];
        }
    });
    
    // Kiểm tra khi form được submit
    document.querySelector('form').addEventListener('submit', function(e) {
        const ngayNhan = new Date(ngayNhanInput.value);
        const ngayTra = new Date(ngayTraInput.value);
        
        if (ngayTra <= ngayNhan) {
            e.preventDefault();
            alert('Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 ngày!');
        }
    });
});
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>