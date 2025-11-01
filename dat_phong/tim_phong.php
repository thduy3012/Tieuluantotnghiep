<?php
session_start();
require_once '../config/config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

// Thiết lập thời gian mặc định nếu chưa có tìm kiếm
$ngay_nhan = isset($_GET['ngay_nhan']) ? $_GET['ngay_nhan'] : date('Y-m-d');
$ngay_tra = isset($_GET['ngay_tra']) ? $_GET['ngay_tra'] : date('Y-m-d', strtotime('+1 day'));
$loai_phong = isset($_GET['loai_phong']) ? $_GET['loai_phong'] : '';

// Danh sách phòng trống
$phong_trong = [];

// Nếu form được gửi đi
if (isset($_GET['search'])) {
    // Truy vấn tìm phòng trống
    $sql = "SELECT p.* FROM phong p 
            WHERE p.trang_thai = 'trống' 
            AND p.id NOT IN (
                SELECT dp.id_phong FROM dat_phong dp 
                WHERE ((dp.ngay_nhan_phong <= :ngay_nhan AND dp.ngay_tra_phong > :ngay_nhan)
                OR (dp.ngay_nhan_phong < :ngay_tra AND dp.ngay_tra_phong >= :ngay_tra)
                OR (dp.ngay_nhan_phong >= :ngay_nhan AND dp.ngay_tra_phong <= :ngay_tra))
                AND dp.trang_thai IN ('đã đặt', 'đã nhận phòng')
            )";
    
    // Thêm điều kiện loại phòng nếu có
    if (!empty($loai_phong)) {
        $sql .= " AND p.loai_phong = :loai_phong";
    }
    
    $params = [':ngay_nhan' => $ngay_nhan, ':ngay_tra' => $ngay_tra];
    if (!empty($loai_phong)) {
        $params[':loai_phong'] = $loai_phong;
    }
    
    $phong_trong = fetchAllRows($sql, $params);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm phòng trống - Quản lý khách sạn</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Content -->
        <div class="content">
            <?php include '../includes/header.php'; ?>
            
            <div class="container-fluid">
                <h2 class="mb-4">Tìm phòng trống</h2>
                
                <!-- Form tìm phòng -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="ngay_nhan">Ngày nhận phòng</label>
                                    <input type="date" class="form-control" id="ngay_nhan" name="ngay_nhan" value="<?php echo $ngay_nhan; ?>" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="ngay_tra">Ngày trả phòng</label>
                                    <input type="date" class="form-control" id="ngay_tra" name="ngay_tra" value="<?php echo $ngay_tra; ?>" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="loai_phong">Loại phòng</label>
                                    <select class="form-control" id="loai_phong" name="loai_phong">
                                        <option value="">Tất cả</option>
                                        <option value="đơn" <?php echo ($loai_phong == 'đơn') ? 'selected' : ''; ?>>Phòng đơn</option>
                                        <option value="đôi" <?php echo ($loai_phong == 'đôi') ? 'selected' : ''; ?>>Phòng đôi</option>
                                        <option value="vip" <?php echo ($loai_phong == 'vip') ? 'selected' : ''; ?>>Phòng VIP</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3 d-flex align-items-end">
                                    <button type="submit" name="search" value="1" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Tìm phòng
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Kết quả tìm kiếm -->
                <?php if (isset($_GET['search'])): ?>
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Danh sách phòng trống từ <?php echo date('d/m/Y', strtotime($ngay_nhan)); ?> đến <?php echo date('d/m/Y', strtotime($ngay_tra)); ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($phong_trong)): ?>
                                <div class="alert alert-warning">Không tìm thấy phòng trống phù hợp với yêu cầu.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Số phòng</th>
                                                <th>Loại phòng</th>
                                                <th>Giá (VNĐ/ngày)</th>
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
                                                        switch($phong['loai_phong']) {
                                                            case 'đơn': echo 'Phòng đơn'; break;
                                                            case 'đôi': echo 'Phòng đôi'; break;
                                                            case 'vip': echo 'Phòng VIP'; break;
                                                            default: echo $phong['loai_phong']; break;
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo number_format($phong['gia_ngay'], 0, ',', '.'); ?></td>
                                                    <td>
                                                        <span class="badge badge-success">Trống</span>
                                                    </td>
                                                    <td>
                                                        <a href="/dat_phong/dat_phong.php?id_phong=<?php echo $phong['id']; ?>&ngay_nhan=<?php echo $ngay_nhan; ?>&ngay_tra=<?php echo $ngay_tra; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-calendar-check"></i> Đặt phòng
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
                <?php endif; ?>
            </div>
            
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/assets/js/main.js"></script>
    
    <script>
    // Kiểm tra ngày hợp lệ
    document.querySelector('form').addEventListener('submit', function(e) {
        const ngayNhan = new Date(document.getElementById('ngay_nhan').value);
        const ngayTra = new Date(document.getElementById('ngay_tra').value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (ngayNhan < today) {
            alert('Ngày nhận phòng không thể là ngày trong quá khứ');
            e.preventDefault();
            return false;
        }
        
        if (ngayTra <= ngayNhan) {
            alert('Ngày trả phòng phải sau ngày nhận phòng');
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>
</html>