<?php
// Kiểm tra session và quyền truy cập
session_start();

// Include file cấu hình và kết nối database
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

// Kiểm tra quyền quản lý (chỉ quản lý mới có quyền vào trang này)
if ($_SESSION['role'] !== 'quản lý') {
    header("Location: ../../index.php");
    exit;
}

// Kết nối database
$conn = getDatabaseConnection();

// Xử lý tìm kiếm
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "WHERE 1=1";

if (!empty($search)) {
    $where_clause .= " AND (ho_ten LIKE :search OR ten_dang_nhap LIKE :search OR chuc_vu LIKE :search)";
}

// Phân trang
$records_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Truy vấn lấy tổng số bản ghi
$count_sql = "SELECT COUNT(*) AS total FROM nhan_vien $where_clause";
$count_stmt = $conn->prepare($count_sql);

if (!empty($search)) {
    $count_stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Truy vấn dữ liệu có phân trang
$sql = "SELECT id, ten_dang_nhap, ho_ten, chuc_vu, trang_thai FROM nhan_vien $where_clause ORDER BY id DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($sql);

if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->execute();
$nhan_vien_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý thay đổi trạng thái
if (isset($_POST['toggle_status']) && isset($_POST['employee_id'])) {
    $employee_id = intval($_POST['employee_id']);
    
    // Kiểm tra ID hợp lệ
    if ($employee_id > 0) {
        // Lấy trạng thái hiện tại
        $check_sql = "SELECT trang_thai FROM nhan_vien WHERE id = :id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
        $check_stmt->execute();
        $current_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_status) {
            // Đảo ngược trạng thái
            $new_status = $current_status['trang_thai'] ? 0 : 1;
            
            // Cập nhật trạng thái mới
            $update_sql = "UPDATE nhan_vien SET trang_thai = :status WHERE id = :id";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bindParam(':status', $new_status, PDO::PARAM_INT);
            $update_stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Đã cập nhật trạng thái nhân viên thành công!";
            } else {
                $_SESSION['error_message'] = "Không thể cập nhật trạng thái nhân viên!";
            }
        }
    }
    
    // Redirect để tránh gửi lại form khi refresh
    header("Location: " . $_SERVER['PHP_SELF'] . (empty($search) ? '' : "?search=$search"));
    exit;
}

// Include header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar (có thể include từ file khác) -->
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Quản lý nhân viên</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="them.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Thêm nhân viên mới
                        </a>
                    </div>
                </div>
            </div>

            <!-- Hiển thị thông báo -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Form tìm kiếm -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Tìm kiếm nhân viên..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Tìm kiếm
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($search)): ?>
                            <div class="col-auto">
                                <a href="index.php" class="btn btn-secondary">Xóa bộ lọc</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Bảng danh sách nhân viên -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Danh sách nhân viên</h6>
                    <span>Tổng số: <?php echo $total_records; ?> nhân viên</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="20%">Họ tên</th>
                                    <th width="15%">Tên đăng nhập</th>
                                    <th width="15%">Chức vụ</th>
                                    <th width="10%">Trạng thái</th>
                                    <th width="15%">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($nhan_vien_list) > 0): ?>
                                    <?php foreach ($nhan_vien_list as $nv): ?>
                                        <tr>
                                            <td><?php echo $nv['id']; ?></td>
                                            <td><?php echo htmlspecialchars($nv['ho_ten']); ?></td>
                                            <td><?php echo htmlspecialchars($nv['ten_dang_nhap']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $nv['chuc_vu'] == 'quản lý' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($nv['chuc_vu']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $nv['trang_thai'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $nv['trang_thai'] ? 'Hoạt động' : 'Đã khóa'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="sua.php?id=<?php echo $nv['id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i> Sửa
                                                    </a>
                                                    
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn thay đổi trạng thái của nhân viên này?');">
                                                        <input type="hidden" name="employee_id" value="<?php echo $nv['id']; ?>">
                                                        <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $nv['trang_thai'] ? 'btn-danger' : 'btn-success'; ?>">
                                                            <i class="fas <?php echo $nv['trang_thai'] ? 'fa-lock' : 'fa-unlock'; ?>"></i>
                                                            <?php echo $nv['trang_thai'] ? 'Khóa' : 'Mở khóa'; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Không tìm thấy dữ liệu nhân viên</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Phân trang -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Tự động ẩn thông báo sau 5 giây
document.addEventListener('DOMContentLoaded', function() {
    // Lấy tất cả thông báo
    var alerts = document.querySelectorAll('.alert');
    
    // Thêm hẹn giờ để ẩn từng thông báo
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>