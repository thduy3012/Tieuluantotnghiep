
<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Lưu URL hiện tại để redirect sau khi đăng nhập
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Chuyển hướng đến trang đăng nhập
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Kết nối CSDL
require_once __DIR__ . '/../../config/config.php';

// Xử lý xóa khách hàng nếu có
if (isset($_GET['xoa']) && !empty($_GET['xoa'])) {
    $id_xoa = (int)$_GET['xoa'];
    
    // Kiểm tra xem khách hàng có đặt phòng không
    $sql_check = "SELECT COUNT(*) as count FROM dat_phong WHERE id_khach_hang = ?";
    $result_check = fetchSingleRow($sql_check, [$id_xoa]);
    
    if ($result_check && $result_check['count'] > 0) {
        $_SESSION['message'] = "Không thể xóa khách hàng này vì đã có dữ liệu đặt phòng!";
        $_SESSION['message_type'] = "danger";
    } else {
        // Thực hiện xóa khách hàng
        $sql_delete = "DELETE FROM khach_hang WHERE id = ?";
        $result_delete = executeQuery($sql_delete, [$id_xoa]);
        
        if ($result_delete) {
            $_SESSION['message'] = "Xóa khách hàng thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Có lỗi xảy ra khi xóa khách hàng!";
            $_SESSION['message_type'] = "danger";
        }
    }
    
    // Chuyển hướng để tránh việc submit lại form khi refresh
    header("Location: /quanlykhachsan/admin/khach_hang/index.php");
    exit;
}

// Xử lý tìm kiếm
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Kết nối trực tiếp để debug
$conn = getDatabaseConnection();

// Debug: Hiển thị tất cả khách hàng trước
$debug_all = "SELECT * FROM khach_hang";
$stmt_all = $conn->query($debug_all);
$all_customers = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log tất cả khách hàng
error_log("Tất cả khách hàng: " . print_r($all_customers, true));

// Câu lệnh SQL tìm kiếm
if (!empty($search_term)) {
    $sql = "SELECT * FROM khach_hang WHERE 
           ho_ten LIKE :search OR 
           so_cmnd LIKE :search OR 
           so_dien_thoai LIKE :search OR 
           dia_chi LIKE :search
           ORDER BY id DESC";
    
    try {
        $stmt = $conn->prepare($sql);
        $search_param = "%{$search_term}%";
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log kết quả tìm kiếm
        error_log("Từ khóa tìm kiếm: " . $search_term);
        error_log("Tham số tìm kiếm: " . $search_param);
        error_log("Khách hàng tìm thấy: " . print_r($customers, true));
        
        // Không cần phân trang khi tìm kiếm
        $total_pages = 1;
        $page = 1;
    } catch(PDOException $e) {
        error_log("Lỗi SQL tìm kiếm: " . $e->getMessage());
        $customers = [];
        $total_pages = 1;
        $page = 1;
    }
} else {
    // Phân trang
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10; // Số khách hàng mỗi trang
    $offset = ($page - 1) * $limit;

    // Đếm tổng số khách hàng (phục vụ phân trang)
    $sql_count = "SELECT COUNT(*) as total FROM khach_hang";
    $stmt_count = $conn->query($sql_count);
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_customers = $count_result ? $count_result['total'] : 0;
    $total_pages = ceil($total_customers / $limit);

    // Nếu trang hiện tại lớn hơn tổng số trang và có ít nhất 1 trang
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Lấy danh sách khách hàng
    $sql = "SELECT * FROM khach_hang ORDER BY id DESC LIMIT :limit OFFSET :offset";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log danh sách khách hàng phân trang
        error_log("Khách hàng phân trang: " . print_r($customers, true));
    } catch(PDOException $e) {
        error_log("Lỗi SQL phân trang: " . $e->getMessage());
        $customers = [];
    }
}

// Include header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Danh sách khách hàng</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Quản trị</a></li>
        <li class="breadcrumb-item active">Danh sách khách hàng</li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6">
                    <i class="fas fa-users me-1"></i>
                    Danh sách khách hàng
                </div>
                <div class="col-md-6 text-end">
                    <a href="/quanlykhachsan/admin/khach_hang/them.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Thêm khách hàng
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Form tìm kiếm -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <form action="" method="GET" class="d-flex">
                        <input type="text" name="search" class="form-control me-2" placeholder="Tìm theo tên, CMND, SĐT, địa chỉ..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Tìm
                        </button>
                    </form>
                </div>
                <?php if (!empty($search_term)): ?>
                <div class="col-md-6">
                    <a href="/quanlykhachsan/admin/khach_hang/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i> Hiển thị tất cả
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Hiển thị kết quả tìm kiếm nếu có -->
            <?php if (!empty($search_term)): ?>
            <div class="alert alert-info">
                Kết quả tìm kiếm cho: <strong><?php echo htmlspecialchars($search_term); ?></strong>
                <span class="badge bg-primary ms-2"><?php echo count($customers); ?></span> kết quả
            </div>
            <?php endif; ?>

            <!-- Bảng danh sách khách hàng -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="customersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Họ tên</th>
                            <th>Số CMND</th>
                            <th>Số điện thoại</th>
                            <th>Địa chỉ</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Không có khách hàng nào<?php echo !empty($search_term) ? ' phù hợp với từ khóa tìm kiếm' : ''; ?></td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['id']); ?></td>
                                <td><?php echo htmlspecialchars($customer['ho_ten']); ?></td>
                                <td><?php echo htmlspecialchars($customer['so_cmnd'] ?? 'Chưa cập nhật'); ?></td>
                                <td><?php echo htmlspecialchars($customer['so_dien_thoai'] ?? 'Chưa cập nhật'); ?></td>
                                <td><?php echo htmlspecialchars($customer['dia_chi'] ?? 'Chưa cập nhật'); ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                        <a href="/quanlykhachsan/admin/khach_hang/chi_tiet.php?id=<?php echo $customer['id']; ?>" class="btn btn-info btn-sm" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/quanlykhachsan/admin/khach_hang/sua.php?id=<?php echo $customer['id']; ?>" class="btn btn-primary btn-sm" title="Sửa thông tin">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $customer['id']; ?>)" class="btn btn-danger btn-sm" title="Xóa khách hàng">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <a href="/quanlykhachsan/admin/dat_phong/them.php?id_khach_hang=<?php echo $customer['id']; ?>" class="btn btn-success btn-sm" title="Đặt phòng">
                                            <i class="fas fa-calendar-check"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Phân trang (chỉ hiển thị khi không tìm kiếm) -->
            <?php if (empty($search_term) && $total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>">
                            <i class="fas fa-angle-left"></i> Trước
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo '<li class="page-item '.($i == $page ? 'active' : '').'">
                                <a class="page-link" href="?page='.$i.'">'.$i.'</a>
                              </li>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'">'.$total_pages.'</a></li>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>">
                            Tiếp <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa khách hàng này không?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <a href="#" id="confirmDeleteButton" class="btn btn-danger">Xóa</a>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        var deleteUrl = '/quanlykhachsan/admin/khach_hang/index.php?xoa=' + id;
        document.getElementById('confirmDeleteButton').setAttribute('href', deleteUrl);
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>

<?php
// Include footer
include_once __DIR__ . '/../../includes/footer.php';
?>