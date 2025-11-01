<?php
// Import header
require_once 'includes/header.php';

// Lấy thông tin tổng quan về khách sạn
$total_rooms = fetchSingleRow("SELECT COUNT(*) as total FROM phong")['total'] ?? 0;
$available_rooms = fetchSingleRow("SELECT COUNT(*) as total FROM phong WHERE trang_thai = 'trống'")['total'] ?? 0;
$booked_rooms = fetchSingleRow("SELECT COUNT(*) as total FROM phong WHERE trang_thai = 'đã đặt'")['total'] ?? 0;
$occupied_rooms = fetchSingleRow("SELECT COUNT(*) as total FROM phong WHERE trang_thai = 'đang sử dụng'")['total'] ?? 0;
$maintenance_rooms = fetchSingleRow("SELECT COUNT(*) as total FROM phong WHERE trang_thai = 'bảo trì'")['total'] ?? 0;

// Lấy danh sách phòng cho bảng thông tin nhanh
$rooms = fetchAllRows("SELECT * FROM phong ORDER BY so_phong LIMIT 10");
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="text-primary">Hệ thống Quản lý Khách sạn</h1>
        <p class="lead">Chào mừng đến với hệ thống quản lý khách sạn.</p>
    </div>
</div>

<?php if (isset($_SESSION['user_id'])): ?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-door-open me-2"></i>Tổng số phòng</h5>
                <p class="card-text display-4"><?php echo $total_rooms; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Phòng trống</h5>
                <p class="card-text display-4"><?php echo $available_rooms; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-calendar me-2"></i>Phòng đã đặt</h5>
                <p class="card-text display-4"><?php echo $booked_rooms; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-bed me-2"></i>Phòng đang sử dụng</h5>
                <p class="card-text display-4"><?php echo $occupied_rooms; ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Thông tin phòng</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Số phòng</th>
                                <th>Loại phòng</th>
                                <th>Giá/ngày</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($rooms): ?>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($room['so_phong']); ?></td>
                                        <td>
                                            <?php 
                                            switch($room['loai_phong']) {
                                                case 'đơn':
                                                    echo '<span class="badge bg-info">Phòng đơn</span>';
                                                    break;
                                                case 'đôi':
                                                    echo '<span class="badge bg-primary">Phòng đôi</span>';
                                                    break;
                                                case 'vip':
                                                    echo '<span class="badge bg-warning">Phòng VIP</span>';
                                                    break;
                                                default:
                                                    echo htmlspecialchars($room['loai_phong']);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo number_format($room['gia_ngay'], 0, ',', '.'); ?> VNĐ</td>
                                        <td>
                                            <?php 
                                            switch($room['trang_thai']) {
                                                case 'trống':
                                                    echo '<span class="badge bg-success">Trống</span>';
                                                    break;
                                                case 'đã đặt':
                                                    echo '<span class="badge bg-warning">Đã đặt</span>';
                                                    break;
                                                case 'đang sử dụng':
                                                    echo '<span class="badge bg-danger">Đang sử dụng</span>';
                                                    break;
                                                case 'bảo trì':
                                                    echo '<span class="badge bg-secondary">Bảo trì</span>';
                                                    break;
                                                default:
                                                    echo htmlspecialchars($room['trang_thai']);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($room['trang_thai'] == 'trống'): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/them.php?phong_id=<?php echo $room['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-calendar-plus"></i> Đặt phòng
                                                </a>
                                            <?php elseif ($room['trang_thai'] == 'đã đặt'): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/nhan_phong.php?phong_id=<?php echo $room['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check-circle"></i> Nhận phòng
                                                </a>
                                            <?php elseif ($room['trang_thai'] == 'đang sử dụng'): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/tra_phong.php?phong_id=<?php echo $room['id']; ?>" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-sign-out-alt"></i> Trả phòng
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="fas fa-tools"></i> Đang bảo trì
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Không có dữ liệu phòng.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="/quanlykhachsan/admin/phong/index.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Xem tất cả phòng
                </a>
                <a href="/quanlykhachsan/admin/phong/tim_phong_trong.php" class="btn btn-success">
                    <i class="fas fa-search"></i> Tìm phòng trống
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Đặt phòng gần đây</h5>
            </div>
            <div class="card-body">
                <?php
                $recent_bookings = fetchAllRows("
                    SELECT dp.id, dp.ngay_nhan_phong, dp.ngay_tra_phong, dp.trang_thai,
                           kh.ho_ten AS ten_khach, p.so_phong
                    FROM dat_phong dp
                    JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                    JOIN phong p ON dp.id_phong = p.id
                    ORDER BY dp.id DESC
                    LIMIT 5
                ");
                ?>
                
                <?php if ($recent_bookings): ?>
                    <div class="list-group">
                        <?php foreach ($recent_bookings as $booking): ?>
                            <a href="/quanlykhachsan/admin/dat_phong/chi_tiet.php?id=<?php echo $booking['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">Phòng <?php echo htmlspecialchars($booking['so_phong']); ?></h5>
                                    <?php 
                                    switch($booking['trang_thai']) {
                                        case 'đã đặt':
                                            echo '<span class="badge bg-warning">Đã đặt</span>';
                                            break;
                                        case 'đã nhận phòng':
                                            echo '<span class="badge bg-danger">Đã nhận phòng</span>';
                                            break;
                                        case 'đã trả phòng':
                                            echo '<span class="badge bg-success">Đã trả phòng</span>';
                                            break;
                                        case 'đã hủy':
                                            echo '<span class="badge bg-secondary">Đã hủy</span>';
                                            break;
                                        default:
                                            echo htmlspecialchars($booking['trang_thai']);
                                    }
                                    ?>
                                </div>
                                <p class="mb-1">Khách hàng: <?php echo htmlspecialchars($booking['ten_khach']); ?></p>
                                <small>
                                    Nhận phòng: <?php echo date('d/m/Y', strtotime($booking['ngay_nhan_phong'])); ?> - 
                                    Trả phòng: <?php echo date('d/m/Y', strtotime($booking['ngay_tra_phong'])); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Chưa có đặt phòng nào gần đây.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="/quanlykhachsan/admin/dat_phong/index.php" class="btn btn-info text-white">
                    <i class="fas fa-list"></i> Xem tất cả đặt phòng
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Hóa đơn gần đây</h5>
            </div>
            <div class="card-body">
                <?php
                // Thêm vào phần truy vấn SQL để lấy $recent_invoices
                $sql_recent_invoices = "SELECT hd.id, hd.trang_thai, hd.ngay_thanh_toan,
                                        hd.tong_tien_phong, hd.tong_tien_dich_vu, 
                                        dp.tien_coc, p.so_phong, kh.ho_ten as ten_khach
                                        FROM hoa_don hd
                                        JOIN dat_phong dp ON hd.id_dat_phong = dp.id
                                        JOIN phong p ON dp.id_phong = p.id
                                        JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                                        ORDER BY hd.id DESC LIMIT 5";
                $recent_invoices = fetchAllRows($sql_recent_invoices);
                ?>
                
                <?php if ($recent_invoices): ?>
                <div class="list-group">
                    <?php foreach ($recent_invoices as $invoice): ?>
                        <?php
                        // Đảm bảo các giá trị tồn tại và tính toán tổng tiền chính xác
                        $tien_phong = isset($invoice['tong_tien_phong']) ? abs((float)$invoice['tong_tien_phong']) : 0;
                        $tien_dich_vu = isset($invoice['tong_tien_dich_vu']) ? abs((float)$invoice['tong_tien_dich_vu']) : 0;
                        $tien_coc = isset($invoice['tien_coc']) ? abs((float)$invoice['tien_coc']) : 0;
                        
                        // Tính tổng tiền
                        $tong_thanh_toan = $tien_phong + $tien_dich_vu - $tien_coc;
                        ?>
                        <a href="/quanlykhachsan/admin/hoa_don/chi_tiet.php?id=<?php echo $invoice['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Hóa đơn #<?php echo $invoice['id']; ?></h5>
                                <?php 
                                if ($invoice['trang_thai'] == 'đã thanh toán') {
                                    echo '<span class="badge bg-success">Đã thanh toán</span>';
                                } else {
                                    echo '<span class="badge bg-warning">Chưa thanh toán</span>';
                                }
                                ?>
                            </div>
                            <p class="mb-1">Phòng <?php echo htmlspecialchars($invoice['so_phong']); ?> - Khách hàng: <?php echo htmlspecialchars($invoice['ten_khach']); ?></p>
                            <small>
                                Ngày: <?php echo date('d/m/Y', strtotime($invoice['ngay_thanh_toan'])); ?> - 
                                Tổng tiền: <?php echo number_format($tong_thanh_toan, 0, ',', '.'); ?> VNĐ
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Chưa có hóa đơn nào gần đây.</p>
            <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="/quanlykhachsan/admin/hoa_don/index.php" class="btn btn-success">
                    <i class="fas fa-list"></i> Xem tất cả hóa đơn
                </a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card shadow">
            <div class="card-body text-center p-5">
                <i class="fas fa-hotel text-primary display-1 mb-4"></i>
                <h2 class="card-title">Chào mừng đến với Hệ thống Quản lý Khách sạn</h2>
                <p class="card-text lead">Hệ thống quản lý hiện đại, giúp việc quản lý khách sạn trở nên đơn giản và hiệu quả.</p>
                <hr>
                <p>Vui lòng đăng nhập để sử dụng hệ thống.</p>
                <a href="/quanlykhachsan/auth/login.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i> Đăng nhập
                </a>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
// Import footer
require_once 'includes/footer.php';
?>