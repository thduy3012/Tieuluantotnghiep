<?php
ob_start();

// Bắt đầu phiên làm việc
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    // Chuyển hướng đến trang đăng nhập nếu chưa đăng nhập
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Import các file cần thiết
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Cài đặt phân trang
$items_per_page = 10; // Số hóa đơn trên mỗi trang
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Trang hiện tại
if ($current_page < 1) $current_page = 1;

// Khai báo các biến tìm kiếm
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Xây dựng câu truy vấn cơ sở và các tham số tìm kiếm
$params = [];
$where_clause = "WHERE 1=1";

if (!empty($search_query)) {
    $where_clause .= " AND (kh.ho_ten LIKE ? OR p.so_phong LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if (!empty($status_filter)) {
    $where_clause .= " AND hd.trang_thai = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_clause .= " AND hd.ngay_thanh_toan >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_clause .= " AND hd.ngay_thanh_toan <= ?";
    $params[] = $date_to;
}

// Xây dựng câu truy vấn đếm tổng số hóa đơn
$count_sql = "SELECT COUNT(*) as total
              FROM hoa_don hd
              INNER JOIN dat_phong dp ON hd.id_dat_phong = dp.id
              INNER JOIN khach_hang kh ON dp.id_khach_hang = kh.id
              INNER JOIN phong p ON dp.id_phong = p.id
              $where_clause";

// Thực thi truy vấn đếm
$count_result = fetchSingleRow($count_sql, $params);
$total_items = $count_result['total'];

// Tính toán tổng số trang
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Tính vị trí bắt đầu lấy dữ liệu
$offset = ($current_page - 1) * $items_per_page;

// Câu truy vấn lấy dữ liệu với phân trang
$sql = "SELECT hd.*, dp.id_khach_hang, dp.id_phong, dp.ngay_nhan_phong, dp.ngay_tra_phong, dp.tien_coc,
               kh.ho_ten as ten_khach_hang, p.so_phong
        FROM hoa_don hd
        INNER JOIN dat_phong dp ON hd.id_dat_phong = dp.id
        INNER JOIN khach_hang kh ON dp.id_khach_hang = kh.id
        INNER JOIN phong p ON dp.id_phong = p.id
        $where_clause
        ORDER BY hd.id DESC
        LIMIT $offset, $items_per_page";

// Thực thi truy vấn lấy dữ liệu
$hoa_don_list = fetchAllRows($sql, $params);

// Xử lý yêu cầu xóa hóa đơn (chỉ mang tính tham khảo, thực tế không nên xóa hóa đơn)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Kiểm tra quyền quản lý
    if ($_SESSION['role'] != 'quản lý') {
        $_SESSION['message'] = "Bạn không có quyền xóa hóa đơn!";
        $_SESSION['message_type'] = "danger";
        header("Location: /quanlykhachsan/admin/hoa_don/index.php");
        exit();
    }

    // Kiểm tra trạng thái hóa đơn
    $check_sql = "SELECT trang_thai FROM hoa_don WHERE id = ?";
    $hoa_don = fetchSingleRow($check_sql, [$id]);
    
    if ($hoa_don && $hoa_don['trang_thai'] == 'đã thanh toán') {
        $_SESSION['message'] = "Không thể xóa hóa đơn đã thanh toán!";
        $_SESSION['message_type'] = "danger";
    } else {
        // Thực hiện xóa (thực tế nên đánh dấu là đã xóa thay vì xóa thật)
        $delete_sql = "DELETE FROM hoa_don WHERE id = ? AND trang_thai = 'chưa thanh toán'";
        $result = executeQuery($delete_sql, [$id]);
        
        if ($result) {
            $_SESSION['message'] = "Xóa hóa đơn thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Xóa hóa đơn thất bại!";
            $_SESSION['message_type'] = "danger";
        }
    }
    
    header("Location: /quanlykhachsan/admin/hoa_don/index.php");
    exit();
}

// Hàm xây dựng URL phân trang với các tham số tìm kiếm hiện tại
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Tính tổng tiền tất cả các trang
$total_sql = "SELECT 
              SUM(hd.tong_tien_phong) as total_tien_phong,
              SUM(hd.tong_tien_dich_vu) as total_tien_dich_vu,
              SUM(dp.tien_coc) as total_tien_coc,
              SUM(CASE WHEN hd.trang_thai = 'đã thanh toán' THEN (hd.tong_tien_phong + hd.tong_tien_dich_vu - dp.tien_coc) ELSE 0 END) as total_paid,
              SUM(CASE WHEN hd.trang_thai = 'chưa thanh toán' THEN (hd.tong_tien_phong + hd.tong_tien_dich_vu - dp.tien_coc) ELSE 0 END) as total_unpaid
              FROM hoa_don hd
              INNER JOIN dat_phong dp ON hd.id_dat_phong = dp.id
              INNER JOIN khach_hang kh ON dp.id_khach_hang = kh.id
              INNER JOIN phong p ON dp.id_phong = p.id
              $where_clause";

$totals = fetchSingleRow($total_sql, $params);

// Tính tổng tiền
$total_amount = abs($totals['total_tien_phong']) + abs($totals['total_tien_dich_vu']) - abs($totals['total_tien_coc']);
$total_paid = abs($totals['total_paid']);
$total_unpaid = abs($totals['total_unpaid']);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Quản lý hóa đơn</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Trang chủ</a></li>
        <li class="breadcrumb-item active">Danh sách hóa đơn</li>
    </ol>

    <!-- Hiển thị thông báo nếu có -->
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    endif; 
    ?>

    <!-- Form tìm kiếm -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-search"></i> Tìm kiếm hóa đơn
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Khách hàng, số phòng..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tất cả</option>
                        <option value="chưa thanh toán" <?php echo $status_filter == 'chưa thanh toán' ? 'selected' : ''; ?>>Chưa thanh toán</option>
                        <option value="đã thanh toán" <?php echo $status_filter == 'đã thanh toán' ? 'selected' : ''; ?>>Đã thanh toán</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </button>
                    <a href="/quanlykhachsan/admin/hoa_don/index.php" class="btn btn-secondary">
                        <i class="fas fa-sync"></i> Làm mới
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Thông tin về kết quả tìm kiếm và phân trang -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <strong>Tổng số: <?php echo $total_items; ?> hóa đơn</strong>
        </div>
        <div>
            <span>Trang <?php echo $current_page; ?> / <?php echo $total_pages > 0 ? $total_pages : 1; ?></span>
        </div>
    </div>

    <!-- Bảng hiển thị danh sách hóa đơn -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i> Danh sách hóa đơn
        </div>
        <div class="card-body">
            <?php if (!empty($hoa_don_list)): ?>
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
                                <th>Ngày thanh toán</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hoa_don_list as $hoa_don): ?>
                                <?php
                                // Tính toán lại tổng tiền chính xác
                                $tong_thanh_toan = abs($hoa_don['tong_tien_phong']) + abs($hoa_don['tong_tien_dich_vu']) - abs($hoa_don['tien_coc']);
                                ?>
                                <tr>
                                    <td><?php echo $hoa_don['id']; ?></td>
                                    <td><?php echo htmlspecialchars($hoa_don['ten_khach_hang']); ?></td>
                                    <td><?php echo htmlspecialchars($hoa_don['so_phong']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($hoa_don['ngay_nhan_phong'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($hoa_don['ngay_tra_phong'])); ?></td>
                                    <td><?php echo number_format(abs($hoa_don['tong_tien_phong']), 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td><?php echo number_format(abs($hoa_don['tong_tien_dich_vu']), 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td><?php echo number_format(abs($hoa_don['tien_coc']), 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td class="fw-bold"><?php echo number_format($tong_thanh_toan, 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td><?php echo $hoa_don['ngay_thanh_toan'] ? date('d/m/Y', strtotime($hoa_don['ngay_thanh_toan'])) : 'Chưa thanh toán'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $hoa_don['trang_thai'] == 'đã thanh toán' ? 'success' : 'warning'; ?>">
                                            <?php echo htmlspecialchars($hoa_don['trang_thai']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="/quanlykhachsan/admin/hoa_don/chi_tiet.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-info btn-sm me-1" title="Chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($hoa_don['trang_thai'] == 'chưa thanh toán'): ?>
                                                <a href="/quanlykhachsan/admin/hoa_don/thanh_toan.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-success btn-sm me-1" title="Thanh toán">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </a>
                                                <?php if ($_SESSION['role'] == 'quản lý'): ?>
                                                    <a href="/quanlykhachsan/admin/hoa_don/index.php?action=delete&id=<?php echo $hoa_don['id']; ?>&page=<?php echo $current_page; ?>" class="btn btn-danger btn-sm" 
                                                       onclick="return confirm('Bạn có chắc chắn muốn xóa hóa đơn này?')" title="Xóa">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <a href="/quanlykhachsan/admin/hoa_don/in_hoa_don.php?id=<?php echo $hoa_don['id']; ?>" class="btn btn-primary btn-sm me-1" title="In hóa đơn">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Điều hướng phân trang -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Điều hướng phân trang">
                    <ul class="pagination justify-content-center">
                        <!-- Nút về trang đầu -->
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildPaginationUrl(1); ?>" aria-label="Đầu tiên">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Nút trang trước -->
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildPaginationUrl($current_page - 1); ?>" aria-label="Trước">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Hiển thị các trang -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Hiển thị trang đầu nếu cần
                        if ($start_page > 1) {
                            echo '<li class="page-item">
                                    <a class="page-link" href="' . buildPaginationUrl(1) . '">1</a>
                                  </li>';
                            
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled">
                                        <a class="page-link" href="#">...</a>
                                      </li>';
                            }
                        }
                        
                        // Hiển thị các trang giữa
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            echo '<li class="page-item ' . (($i == $current_page) ? 'active' : '') . '">
                                    <a class="page-link" href="' . buildPaginationUrl($i) . '">' . $i . '</a>
                                  </li>';
                        }
                        
                        // Hiển thị trang cuối nếu cần
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled">
                                        <a class="page-link" href="#">...</a>
                                      </li>';
                            }
                            
                            echo '<li class="page-item">
                                    <a class="page-link" href="' . buildPaginationUrl($total_pages) . '">' . $total_pages . '</a>
                                  </li>';
                        }
                        ?>
                        
                        <!-- Nút trang tiếp theo -->
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildPaginationUrl($current_page + 1); ?>" aria-label="Tiếp">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        
                        <!-- Nút trang cuối -->
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildPaginationUrl($total_pages); ?>" aria-label="Cuối">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <!-- Hiển thị tổng số tiền -->
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Tổng doanh thu hiển thị</h5>
                                <p class="card-text text-primary fw-bold h4"><?php echo number_format($total_amount, 0, ',', '.') . ' VNĐ'; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Đã thanh toán</h5>
                                <p class="card-text text-success fw-bold h4"><?php echo number_format($total_paid, 0, ',', '.') . ' VNĐ'; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Chưa thanh toán</h5>
                                <p class="card-text text-warning fw-bold h4"><?php echo number_format($total_unpaid, 0, ',', '.') . ' VNĐ'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i> Không tìm thấy hóa đơn nào.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Nút thêm và các thao tác khác -->
    <div class="mb-4">
        <a href="/quanlykhachsan/admin/dat_phong/them.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Đặt phòng mới
        </a>
        <?php if ($_SESSION['role'] == 'quản lý'): ?>
        <a href="/quanlykhachsan/admin/bao_cao/doanh_thu.php" class="btn btn-success">
            <i class="fas fa-chart-line"></i> Báo cáo doanh thu
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Mã JavaScript để xử lý các chức năng tìm kiếm và lọc nâng cao -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format các ô số tiền nếu cần
    const currencyFormat = function(number) {
        return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(number);
    };
    
    // Tự động ẩn thông báo sau 5 giây
    let alertElement = document.querySelector('.alert');
    if (alertElement) {
        setTimeout(function() {
            let closeButton = alertElement.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            }
        }, 5000);
    }
});
</script>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush(); // Xóa bộ nhớ đệm
?>