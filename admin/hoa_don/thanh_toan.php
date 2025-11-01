<?php
ob_start();
// Bắt đầu phiên làm việc nếu chưa bắt đầu
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import các file cần thiết
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Khai báo các biến tìm kiếm
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$error = '';
$success = '';

// Nếu có ID hóa đơn, xử lý thanh toán
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_hoa_don = $_GET['id'];
    
    try {
        // Bắt đầu giao dịch
        $conn = getDatabaseConnection();
        $conn->beginTransaction();

        // Lấy thông tin hóa đơn
        $hoa_don = fetchSingleRow(
            "SELECT hd.*, dp.id as id_dat_phong FROM hoa_don hd
            JOIN dat_phong dp ON hd.id_dat_phong = dp.id
            WHERE hd.id = ? AND hd.trang_thai = 'chưa thanh toán'",
            [$id_hoa_don]
        );

        if (!$hoa_don) {
            throw new Exception("Không tìm thấy hóa đơn cần thanh toán hoặc hóa đơn đã được thanh toán!");
        }

        // Cập nhật trạng thái hóa đơn
        executeQuery(
            "UPDATE hoa_don SET trang_thai = 'đã thanh toán', ngay_thanh_toan = CURDATE() WHERE id = ?",
            [$id_hoa_don]
        );

        // Cập nhật trạng thái đặt phòng thành 'đã trả phòng'
        executeQuery(
            "UPDATE dat_phong SET trang_thai = 'đã trả phòng' WHERE id = ?",
            [$hoa_don['id_dat_phong']]
        );

        // Cập nhật trạng thái phòng thành 'trống'
        executeQuery(
            "UPDATE phong SET trang_thai = 'trống' WHERE id = (SELECT id_phong FROM dat_phong WHERE id = ?)",
            [$hoa_don['id_dat_phong']]
        );

        // Hoàn tất giao dịch
        $conn->commit();
        $success = "Thanh toán thành công! Mã hóa đơn: " . $id_hoa_don;

        // Đặt thông báo cho trang sau khi chuyển hướng
        $_SESSION['message'] = $success;
        $_SESSION['message_type'] = "success";
        header("Location: /quanlykhachsan/admin/hoa_don/index.php");
        exit;
    } catch (Exception $e) {
        // Nếu có lỗi, hủy bỏ giao dịch
        $conn->rollBack();
        $error = "Lỗi: " . $e->getMessage();
    }
}

// Xây dựng câu truy vấn cơ sở để lấy danh sách hóa đơn chưa thanh toán
$sql = "SELECT hd.*, dp.id_khach_hang, dp.id_phong, dp.ngay_nhan_phong, dp.ngay_tra_phong, 
               dp.tien_coc, kh.ho_ten as ten_khach_hang, p.so_phong
        FROM hoa_don hd
        INNER JOIN dat_phong dp ON hd.id_dat_phong = dp.id
        INNER JOIN khach_hang kh ON dp.id_khach_hang = kh.id
        INNER JOIN phong p ON dp.id_phong = p.id
        WHERE hd.trang_thai = 'chưa thanh toán'";

// Xây dựng các điều kiện tìm kiếm
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (kh.ho_ten LIKE ? OR p.so_phong LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if (!empty($date_from)) {
    $sql .= " AND dp.ngay_tra_phong >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND dp.ngay_tra_phong <= ?";
    $params[] = $date_to;
}

// Sắp xếp kết quả theo ID giảm dần (hóa đơn mới nhất trước)
$sql .= " ORDER BY hd.id DESC";

// Thực thi truy vấn
$hoa_don_list = fetchAllRows($sql, $params);

// Hàm định dạng số tiền - phù hợp với chi_tiet.php
function formatAmount($amount) {
    return number_format(abs($amount), 0, ',', '.') . ' VNĐ';
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Thanh toán hóa đơn</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Trang chủ</a></li>
        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/hoa_don/index.php">Quản lý hóa đơn</a></li>
        <li class="breadcrumb-item active">Thanh toán hóa đơn</li>
    </ol>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Form tìm kiếm -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-search"></i> Tìm kiếm hóa đơn cần thanh toán
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Khách hàng, số phòng..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-4">
                    <label for="date_from" class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-4">
                    <label for="date_to" class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                    <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php" class="btn btn-secondary">
                        <i class="fas fa-sync"></i> Làm mới
                    </a>
                </div>
            </form>
        </div>
    </div>


    <!-- Bảng hiển thị danh sách hóa đơn -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i> Danh sách hóa đơn cần thanh toán
        </div>
        <div class="card-body">
            <?php if ($hoa_don_list && count($hoa_don_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Mã hóa đơn</th>
                                <th>Khách hàng</th>
                                <th>Phòng</th>
                                <th>Ngày nhận phòng</th>
                                <th>Ngày trả phòng</th>
                                <th>Tiền phòng</th>
                                <th>Tiền dịch vụ</th>
                                <th>Tiền cọc</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hoa_don_list as $hoa_don): ?>
                                <?php
                                // Tính toán lại tổng thanh toán như trong chi_tiet.php
                                $tong_thanh_toan = abs($hoa_don['tong_tien_phong']) + abs($hoa_don['tong_tien_dich_vu']) - abs($hoa_don['tien_coc']);
                                ?>
                                <tr>
                                    <td><?php echo $hoa_don['id']; ?></td>
                                    <td><?php echo htmlspecialchars($hoa_don['ten_khach_hang']); ?></td>
                                    <td><?php echo htmlspecialchars($hoa_don['so_phong']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($hoa_don['ngay_nhan_phong'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($hoa_don['ngay_tra_phong'])); ?></td>
                                    <td><?php echo formatAmount($hoa_don['tong_tien_phong']); ?></td>
                                    <td><?php echo formatAmount($hoa_don['tong_tien_dich_vu']); ?></td>
                                    <td>- <?php echo formatAmount($hoa_don['tien_coc']); ?></td>
                                    <td class="fw-bold"><?php echo number_format($tong_thanh_toan, 0, ',', '.'); ?> VNĐ</td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <?php echo htmlspecialchars($hoa_don['trang_thai']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="/quanlykhachsan/admin/hoa_don/chi_tiet.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-info btn-sm me-1" title="Chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-success btn-sm me-1" 
                                               onclick="return confirm('Bạn có chắc chắn muốn thanh toán hóa đơn này?')" title="Thanh toán">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i> Không tìm thấy hóa đơn nào cần thanh toán.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tổng tiền cần thanh toán - Hiển thị ở đầu bảng -->
    <?php if ($hoa_don_list && count($hoa_don_list) > 0): ?>
        <?php
        $total_amount = 0;
        
        foreach ($hoa_don_list as $hoa_don) {
            // Tính toán lại cho mỗi hóa đơn giống như trong chi_tiet.php
            $tong_thanh_toan = abs($hoa_don['tong_tien_phong']) + abs($hoa_don['tong_tien_dich_vu']) - abs($hoa_don['tien_coc']);
            $total_amount += $tong_thanh_toan;
        }
        ?>
        <div class="card mb-4 border-danger">
            <div class="card-body bg-light">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="card-title fw-bold mb-0">Tổng tiền cần thanh toán:</h5>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="h3 text-danger fw-bold mb-0"><?php echo number_format($total_amount, 0, ',', '.'); ?> VNĐ</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Nút quay lại -->
    <div class="mb-4">
        <a href="/quanlykhachsan/admin/hoa_don/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách hóa đơn
        </a>
    </div>
</div>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush(); // Xóa bộ nhớ đệm
?>