<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

// Include file cấu hình và kết nối
require_once '../config/config.php';

// Thiết lập tiêu đề trang
$page_title = "Danh sách đặt phòng";

// Xác định số lượng bản ghi hiển thị trên một trang
$records_per_page = 10;

// Lấy trang hiện tại từ tham số URL, mặc định là trang 1
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Tính toán OFFSET cho truy vấn SQL
$offset = ($current_page - 1) * $records_per_page;

// Chuẩn bị tham số tìm kiếm
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Xây dựng câu truy vấn SQL với bộ lọc
$sql_conditions = [];
$sql_params = [];

if (!empty($search_query)) {
    $sql_conditions[] = "(p.so_phong LIKE ? OR kh.ho_ten LIKE ? OR kh.so_dien_thoai LIKE ?)";
    $sql_params[] = "%$search_query%";
    $sql_params[] = "%$search_query%";
    $sql_params[] = "%$search_query%";
}

if (!empty($status_filter)) {
    $sql_conditions[] = "dp.trang_thai = ?";
    $sql_params[] = $status_filter;
}

if (!empty($date_filter)) {
    $sql_conditions[] = "(dp.ngay_nhan_phong <= ? AND dp.ngay_tra_phong >= ?)";
    $sql_params[] = $date_filter;
    $sql_params[] = $date_filter;
}

// Tạo WHERE clause nếu có điều kiện
$where_clause = !empty($sql_conditions) ? " WHERE " . implode(" AND ", $sql_conditions) : "";

// Truy vấn SQL để lấy danh sách đặt phòng với phân trang
$sql = "SELECT dp.*, p.so_phong, p.loai_phong, p.gia_ngay, 
        kh.ho_ten AS ten_khach_hang, kh.so_dien_thoai, 
        nv.ho_ten AS ten_nhan_vien 
        FROM dat_phong dp
        JOIN phong p ON dp.id_phong = p.id
        JOIN khach_hang kh ON dp.id_khach_hang = kh.id
        JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
        $where_clause
        ORDER BY dp.ngay_nhan_phong DESC
        LIMIT ? OFFSET ?";

// Thêm tham số phân trang vào mảng tham số
$sql_params[] = $records_per_page;
$sql_params[] = $offset;

// Thực thi truy vấn
$result = fetchAllRows($sql, $sql_params);

// Đếm tổng số bản ghi để tính tổng số trang
$count_sql = "SELECT COUNT(*) as total FROM dat_phong dp
              JOIN phong p ON dp.id_phong = p.id
              JOIN khach_hang kh ON dp.id_khach_hang = kh.id
              JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
              $where_clause";

// Xóa tham số LIMIT và OFFSET
array_pop($sql_params);
array_pop($sql_params);

$count_result = fetchSingleRow($count_sql, $sql_params);
$total_records = $count_result['total'];
$total_pages = ceil($total_records / $records_per_page);

// Xử lý các hành động xóa hoặc cập nhật (nếu có)
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id_to_delete = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // Kiểm tra quyền hạn (chỉ quản lý có thể xóa)
    if ($_SESSION['role'] == 'quản lý') {
        // Kiểm tra xem đơn đặt phòng có tồn tại và chưa nhận phòng
        $check_sql = "SELECT trang_thai FROM dat_phong WHERE id = ?";
        $check_result = fetchSingleRow($check_sql, [$id_to_delete]);
        
        if ($check_result && ($check_result['trang_thai'] == 'đã đặt' || $check_result['trang_thai'] == 'đã hủy')) {
            // Xóa đơn đặt phòng
            $delete_sql = "DELETE FROM dat_phong WHERE id = ?";
            $delete_result = executeQuery($delete_sql, [$id_to_delete]);
            
            if ($delete_result) {
                $message = "Đã xóa đơn đặt phòng thành công.";
                $message_type = "success";
            } else {
                $message = "Có lỗi xảy ra khi xóa đơn đặt phòng.";
                $message_type = "danger";
            }
        } else {
            $message = "Không thể xóa đơn đặt phòng đã nhận phòng hoặc đã trả phòng.";
            $message_type = "warning";
        }
    } else {
        $message = "Bạn không có quyền xóa đơn đặt phòng.";
        $message_type = "danger";
    }
}

// Xử lý hành động hủy đặt phòng
if (isset($_POST['action']) && $_POST['action'] == 'cancel') {
    $id_to_cancel = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // Kiểm tra xem đơn đặt phòng có tồn tại và đang ở trạng thái "đã đặt"
    $check_sql = "SELECT trang_thai FROM dat_phong WHERE id = ?";
    $check_result = fetchSingleRow($check_sql, [$id_to_cancel]);
    
    if ($check_result && $check_result['trang_thai'] == 'đã đặt') {
        // Cập nhật trạng thái đơn đặt phòng thành "đã hủy"
        $update_sql = "UPDATE dat_phong SET trang_thai = 'đã hủy' WHERE id = ?";
        $update_result = executeQuery($update_sql, [$id_to_cancel]);
        
        // Cập nhật trạng thái phòng thành "trống"
        $update_room_sql = "UPDATE phong SET trang_thai = 'trống' WHERE id = (SELECT id_phong FROM dat_phong WHERE id = ?)";
        $update_room_result = executeQuery($update_room_sql, [$id_to_cancel]);
        
        if ($update_result && $update_room_result) {
            $message = "Đã hủy đơn đặt phòng thành công.";
            $message_type = "success";
        } else {
            $message = "Có lỗi xảy ra khi hủy đơn đặt phòng.";
            $message_type = "danger";
        }
    } else {
        $message = "Không thể hủy đơn đặt phòng đã nhận phòng hoặc đã trả phòng.";
        $message_type = "warning";
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="wrapper">
    <!-- Sidebar -->
    <?php include_once '../includes/sidebar.php'; ?>

    <!-- Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo $page_title; ?></h4>
                            <p class="card-category">Quản lý tất cả các đơn đặt phòng của khách sạn</p>
                        </div>
                        <div class="card-body">
                            <?php if (isset($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <?php endif; ?>

                            <!-- Bộ lọc tìm kiếm -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <form method="get" action="" class="form-inline">
                                        <div class="form-group mx-1">
                                            <input type="text" class="form-control" name="search" placeholder="Tìm theo phòng, khách hàng..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        </div>
                                        <div class="form-group mx-1">
                                            <select class="form-control" name="status">
                                                <option value="">-- Tất cả trạng thái --</option>
                                                <option value="đã đặt" <?php echo $status_filter == 'đã đặt' ? 'selected' : ''; ?>>Đã đặt</option>
                                                <option value="đã nhận phòng" <?php echo $status_filter == 'đã nhận phòng' ? 'selected' : ''; ?>>Đã nhận phòng</option>
                                                <option value="đã trả phòng" <?php echo $status_filter == 'đã trả phòng' ? 'selected' : ''; ?>>Đã trả phòng</option>
                                                <option value="đã hủy" <?php echo $status_filter == 'đã hủy' ? 'selected' : ''; ?>>Đã hủy</option>
                                            </select>
                                        </div>
                                        <div class="form-group mx-1">
                                            <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>" placeholder="Lọc theo ngày">
                                        </div>
                                        <button type="submit" class="btn btn-primary mx-1">Lọc</button>
                                        <a href="danh_sach.php" class="btn btn-secondary mx-1">Làm mới</a>
                                        <a href="dat_phong.php" class="btn btn-success mx-1">Đặt phòng mới</a>
                                    </form>
                                </div>
                            </div>

                            <!-- Bảng hiển thị danh sách đặt phòng -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Phòng</th>
                                            <th>Khách hàng</th>
                                            <th>Ngày nhận</th>
                                            <th>Ngày trả</th>
                                            <th>Tiền cọc</th>
                                            <th>Trạng thái</th>
                                            <th>Nhân viên đặt</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result && count($result) > 0): ?>
                                            <?php foreach ($result as $row): ?>
                                                <tr>
                                                    <td><?php echo $row['id']; ?></td>
                                                    <td>
                                                        <?php echo $row['so_phong']; ?> 
                                                        <span class="badge badge-info"><?php echo $row['loai_phong']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($row['ten_khach_hang']); ?>
                                                        <br>
                                                        <small><?php echo htmlspecialchars($row['so_dien_thoai']); ?></small>
                                                    </td>
                                                    <td><?php echo date('d/m/Y', strtotime($row['ngay_nhan_phong'])); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($row['ngay_tra_phong'])); ?></td>
                                                    <td><?php echo number_format($row['tien_coc'], 0, ',', '.'); ?> VNĐ</td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        switch ($row['trang_thai']) {
                                                            case 'đã đặt':
                                                                $status_class = 'badge-primary';
                                                                break;
                                                            case 'đã nhận phòng':
                                                                $status_class = 'badge-success';
                                                                break;
                                                            case 'đã trả phòng':
                                                                $status_class = 'badge-info';
                                                                break;
                                                            case 'đã hủy':
                                                                $status_class = 'badge-danger';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo $row['trang_thai']; ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['ten_nhan_vien']); ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="chi_tiet.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i> Chi tiết
                                                            </a>
                                                            <!-- Nút Nhận phòng chỉ hiển thị khi trạng thái là "đã đặt" -->
                                                            <?php if ($row['trang_thai'] == 'đã đặt'): ?>
                                                            <a href="nhan_phong.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check-circle"></i> Nhận phòng
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Nút Trả phòng chỉ hiển thị khi trạng thái là "đã nhận phòng" -->
                                                            <?php if ($row['trang_thai'] == 'đã nhận phòng'): ?>
                                                            <a href="tra_phong.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-undo"></i> Trả phòng
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Các nút khác -->
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                    <i class="fas fa-ellipsis-v"></i>
                                                                </button>
                                                                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                                    <!-- Nút Sửa chỉ hiển thị khi trạng thái là "đã đặt" -->
                                                                    <?php if ($row['trang_thai'] == 'đã đặt'): ?>
                                                                    <a class="dropdown-item" href="sua.php?id=<?php echo $row['id']; ?>">
                                                                        <i class="fas fa-edit"></i> Sửa
                                                                    </a>
                                                                    <?php endif; ?>
                                                                    
                                                                    <!-- Nút Thanh toán chỉ hiển thị khi đã nhận phòng -->
                                                                    <?php if ($row['trang_thai'] == 'đã nhận phòng'): ?>
                                                                    <a class="dropdown-item" href="/thanh_toan/tao_hoa_don.php?id_dat_phong=<?php echo $row['id']; ?>">
                                                                        <i class="fas fa-file-invoice-dollar"></i> Thanh toán
                                                                    </a>
                                                                    <?php endif; ?>
                                                                    
                                                                    <!-- Nút Hủy chỉ hiển thị khi trạng thái là "đã đặt" -->
                                                                    <?php if ($row['trang_thai'] == 'đã đặt'): ?>
                                                                    <button class="dropdown-item cancel-booking" data-id="<?php echo $row['id']; ?>">
                                                                        <i class="fas fa-ban"></i> Hủy đặt phòng
                                                                    </button>
                                                                    <?php endif; ?>
                                                                    
                                                                    <!-- Nút Xóa chỉ hiển thị cho quản lý và khi trạng thái là "đã đặt" hoặc "đã hủy" -->
                                                                    <?php if ($_SESSION['role'] == 'quản lý' && ($row['trang_thai'] == 'đã đặt' || $row['trang_thai'] == 'đã hủy')): ?>
                                                                    <button class="dropdown-item delete-booking text-danger" data-id="<?php echo $row['id']; ?>">
                                                                        <i class="fas fa-trash"></i> Xóa
                                                                    </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center">Không có dữ liệu đặt phòng.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Hiển thị phân trang -->
                            <?php if ($total_pages > 1): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <p>Hiển thị <?php echo count($result); ?> trên tổng số <?php echo $total_records; ?> đơn đặt phòng</p>
                                </div>
                                <div class="col-md-6">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-end">
                                            <?php
                                            // Tạo URL phân trang với các tham số tìm kiếm
                                            $url_params = [];
                                            if (!empty($search_query)) $url_params[] = "search=" . urlencode($search_query);
                                            if (!empty($status_filter)) $url_params[] = "status=" . urlencode($status_filter);
                                            if (!empty($date_filter)) $url_params[] = "date=" . urlencode($date_filter);
                                            $url_params_str = !empty($url_params) ? "&" . implode("&", $url_params) : "";
                                            ?>
                                            
                                            <!-- Nút Previous -->
                                            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo $url_params_str; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <!-- Các nút trang -->
                                            <?php
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++):
                                            ?>
                                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $url_params_str; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <!-- Nút Next -->
                                            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo $url_params_str; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form ẩn để xóa đặt phòng -->
<form id="delete-form" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete-id">
</form>

<!-- Form ẩn để hủy đặt phòng -->
<form id="cancel-form" method="post" style="display: none;">
    <input type="hidden" name="action" value="cancel">
    <input type="hidden" name="id" id="cancel-id">
</form>

<!-- Script xử lý xóa và hủy đặt phòng -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý sự kiện xóa đặt phòng
    const deleteButtons = document.querySelectorAll('.delete-booking');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            if (confirm('Bạn có chắc chắn muốn xóa đơn đặt phòng này?')) {
                document.getElementById('delete-id').value = bookingId;
                document.getElementById('delete-form').submit();
            }
        });
    });
    
    // Xử lý sự kiện hủy đặt phòng
    const cancelButtons = document.querySelectorAll('.cancel-booking');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            if (confirm('Bạn có chắc chắn muốn hủy đơn đặt phòng này?')) {
                document.getElementById('cancel-id').value = bookingId;
                document.getElementById('cancel-form').submit();
            }
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
                                