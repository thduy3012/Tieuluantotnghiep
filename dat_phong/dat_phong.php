<?php
// Bắt đầu phiên session
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

// Include file cấu hình và kết nối CSDL
require_once '../config/config.php';

// Khai báo biến thông báo
$message = '';
$error = '';

// Xử lý form đặt phòng khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $id_khach_hang = isset($_POST['id_khach_hang']) ? intval($_POST['id_khach_hang']) : 0;
    $id_phong = isset($_POST['id_phong']) ? intval($_POST['id_phong']) : 0;
    $ngay_nhan_phong = isset($_POST['ngay_nhan_phong']) ? $_POST['ngay_nhan_phong'] : '';
    $ngay_tra_phong = isset($_POST['ngay_tra_phong']) ? $_POST['ngay_tra_phong'] : '';
    $tien_coc = isset($_POST['tien_coc']) ? floatval($_POST['tien_coc']) : 0;
    
    // Kiểm tra dữ liệu đầu vào
    if ($id_khach_hang <= 0) {
        $error = "Vui lòng chọn khách hàng!";
    } elseif ($id_phong <= 0) {
        $error = "Vui lòng chọn phòng!";
    } elseif (empty($ngay_nhan_phong) || empty($ngay_tra_phong)) {
        $error = "Vui lòng chọn ngày nhận và trả phòng!";
    } elseif (strtotime($ngay_nhan_phong) > strtotime($ngay_tra_phong)) {
        $error = "Ngày trả phòng phải sau ngày nhận phòng!";
    } elseif (strtotime($ngay_nhan_phong) < strtotime(date('Y-m-d'))) {
        $error = "Ngày nhận phòng không thể là ngày trong quá khứ!";
    } else {
        // Kiểm tra phòng có sẵn không
        $check_sql = "SELECT id FROM dat_phong WHERE id_phong = ? AND trang_thai IN ('đã đặt', 'đã nhận phòng') AND 
                     ((ngay_nhan_phong <= ? AND ngay_tra_phong >= ?) OR 
                      (ngay_nhan_phong <= ? AND ngay_tra_phong >= ?) OR 
                      (ngay_nhan_phong >= ? AND ngay_tra_phong <= ?))";
        
        $check_params = [
            $id_phong, 
            $ngay_tra_phong, $ngay_nhan_phong, // Kiểm tra trường hợp 1
            $ngay_nhan_phong, $ngay_nhan_phong, // Kiểm tra trường hợp 2
            $ngay_nhan_phong, $ngay_tra_phong   // Kiểm tra trường hợp 3
        ];
        
        $check_result = fetchAllRows($check_sql, $check_params);
        
        if ($check_result && count($check_result) > 0) {
            $error = "Phòng đã được đặt trong khoảng thời gian này. Vui lòng chọn phòng khác hoặc thay đổi ngày!";
        } else {
            // Lấy ID của nhân viên đăng nhập
            $id_nhan_vien = $_SESSION['user_id'];
            
            // Thực hiện đặt phòng
            $sql = "INSERT INTO dat_phong (id_khach_hang, id_phong, id_nhan_vien, ngay_nhan_phong, ngay_tra_phong, tien_coc, trang_thai) 
                    VALUES (?, ?, ?, ?, ?, ?, 'đã đặt')";
            
            $params = [$id_khach_hang, $id_phong, $id_nhan_vien, $ngay_nhan_phong, $ngay_tra_phong, $tien_coc];
            
            if (executeQuery($sql, $params)) {
                // Cập nhật trạng thái phòng
                $update_sql = "UPDATE phong SET trang_thai = 'đã đặt' WHERE id = ?";
                executeQuery($update_sql, [$id_phong]);
                
                $message = "Đặt phòng thành công!";
                
                // Chuyển hướng đến trang danh sách đặt phòng sau khi đặt thành công
                header("Location: /dat_phong/danh_sach.php?success=1");
                exit();
            } else {
                $error = "Lỗi khi đặt phòng. Vui lòng thử lại!";
            }
        }
    }
}

// Lấy danh sách khách hàng
$khach_hang_list = fetchAllRows("SELECT id, ho_ten, so_cmnd, so_dien_thoai FROM khach_hang ORDER BY ho_ten");

// Lấy danh sách phòng có trạng thái 'trống'
$phong_list = fetchAllRows("SELECT id, so_phong, loai_phong, gia_ngay FROM phong WHERE trang_thai = 'trống' ORDER BY so_phong");

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Đặt phòng mới</h1>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="id_khach_hang" class="form-label">Khách hàng</label>
                                <select class="form-select" id="id_khach_hang" name="id_khach_hang" required>
                                    <option value="">-- Chọn khách hàng --</option>
                                    <?php if ($khach_hang_list): ?>
                                        <?php foreach ($khach_hang_list as $khach_hang): ?>
                                            <option value="<?php echo $khach_hang['id']; ?>">
                                                <?php echo htmlspecialchars($khach_hang['ho_ten']); ?> - 
                                                <?php echo htmlspecialchars($khach_hang['so_cmnd']); ?> - 
                                                <?php echo htmlspecialchars($khach_hang['so_dien_thoai']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="mt-2">
                                    <a href="/khach_hang/them.php" class="btn btn-sm btn-outline-primary">+ Thêm khách hàng mới</a>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="id_phong" class="form-label">Phòng</label>
                                <select class="form-select" id="id_phong" name="id_phong" required>
                                    <option value="">-- Chọn phòng --</option>
                                    <?php if ($phong_list): ?>
                                        <?php foreach ($phong_list as $phong): ?>
                                            <option value="<?php echo $phong['id']; ?>" data-gia="<?php echo $phong['gia_ngay']; ?>">
                                                Phòng <?php echo htmlspecialchars($phong['so_phong']); ?> - 
                                                Loại: <?php echo htmlspecialchars($phong['loai_phong']); ?> - 
                                                Giá: <?php echo number_format($phong['gia_ngay'], 0, ',', '.'); ?> VNĐ/ngày
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="mt-2">
                                    <a href="/phong/trang_thai.php" class="btn btn-sm btn-outline-info">Xem tất cả phòng</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ngay_nhan_phong" class="form-label">Ngày nhận phòng</label>
                                <input type="date" class="form-control" id="ngay_nhan_phong" name="ngay_nhan_phong" required 
                                       value="<?php echo isset($_POST['ngay_nhan_phong']) ? $_POST['ngay_nhan_phong'] : date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="ngay_tra_phong" class="form-label">Ngày trả phòng</label>
                                <input type="date" class="form-control" id="ngay_tra_phong" name="ngay_tra_phong" required
                                       value="<?php echo isset($_POST['ngay_tra_phong']) ? $_POST['ngay_tra_phong'] : date('Y-m-d', strtotime('+1 day')); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="so_ngay" class="form-label">Số ngày ở</label>
                                <input type="text" class="form-control" id="so_ngay" readonly>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="tong_tien" class="form-label">Tổng tiền dự kiến</label>
                                <input type="text" class="form-control" id="tong_tien" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tien_coc" class="form-label">Tiền cọc</label>
                                <input type="number" class="form-control" id="tien_coc" name="tien_coc" required min="0">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="/dat_phong/danh_sach.php" class="btn btn-secondary me-md-2">Hủy</a>
                            <button type="submit" class="btn btn-primary">Đặt phòng</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- JavaScript để tính số ngày và tổng tiền -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hàm tính số ngày và tổng tiền
    function calculateDaysAndTotal() {
        // Lấy ngày nhận và trả phòng
        const ngayNhan = document.getElementById('ngay_nhan_phong').value;
        const ngayTra = document.getElementById('ngay_tra_phong').value;
        
        // Kiểm tra nếu đã chọn đủ ngày
        if (ngayNhan && ngayTra) {
            // Tính số ngày
            const dateNhan = new Date(ngayNhan);
            const dateTra = new Date(ngayTra);
            const diffTime = dateTra - dateNhan;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            // Hiển thị số ngày
            document.getElementById('so_ngay').value = diffDays > 0 ? diffDays : 0;
            
            // Tính tổng tiền nếu đã chọn phòng
            const phongSelect = document.getElementById('id_phong');
            if (phongSelect.value) {
                const selectedOption = phongSelect.options[phongSelect.selectedIndex];
                const giaPhong = selectedOption.getAttribute('data-gia');
                
                // Tính tổng tiền
                const tongTien = diffDays > 0 ? diffDays * giaPhong : 0;
                
                // Hiển thị tổng tiền
                document.getElementById('tong_tien').value = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(tongTien);
                
                // Đề xuất tiền cọc (50% tổng tiền)
                document.getElementById('tien_coc').value = Math.round(tongTien * 0.5);
            }
        }
    }
    
    // Gắn sự kiện cho các trường input
    document.getElementById('ngay_nhan_phong').addEventListener('change', calculateDaysAndTotal);
    document.getElementById('ngay_tra_phong').addEventListener('change', calculateDaysAndTotal);
    document.getElementById('id_phong').addEventListener('change', calculateDaysAndTotal);
    
    // Tính toán ban đầu
    calculateDaysAndTotal();
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>