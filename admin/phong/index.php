<?php
ob_start(); // Bật bộ nhớ đệm
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import header
require_once __DIR__ . '/../../includes/header.php';

// Thiết lập các thông số phân trang
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10; // Số phòng hiển thị trên mỗi trang

// Lấy tổng số phòng để tính số trang
$count_sql = "SELECT COUNT(*) as total FROM phong";
$total_result = fetchAllRows($count_sql);
$total_rooms = $total_result[0]['total'];
$total_pages = ceil($total_rooms / $items_per_page);

// Điều chỉnh trang hiện tại nếu vượt quá giới hạn
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = 1;
}

// Tính toán offset
$offset = ($current_page - 1) * $items_per_page;

// Lấy danh sách phòng từ database với LIMIT và OFFSET
$sql = "SELECT * FROM phong ORDER BY so_phong ASC LIMIT $items_per_page OFFSET $offset";
$phong_list = fetchAllRows($sql);

// Xử lý thay đổi trạng thái phòng nếu có
if (isset($_POST['update_status']) && isset($_POST['room_id']) && isset($_POST['new_status'])) {
    $room_id = $_POST['room_id'];
    $new_status = $_POST['new_status'];
    
    $update_sql = "UPDATE phong SET trang_thai = ? WHERE id = ?";
    $result = executeQuery($update_sql, [$new_status, $room_id]);
    
    if ($result) {
        $_SESSION['message'] = "Cập nhật trạng thái phòng thành công!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Có lỗi xảy ra khi cập nhật trạng thái phòng!";
        $_SESSION['message_type'] = "danger";
    }
    
    // Tải lại trang để hiển thị thông báo và dữ liệu mới
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . $current_page);
    exit;
}
?>

<div class="container-fluid">
    <h2 class="mb-4">Quản lý phòng</h2>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách phòng</h5>
            <a href="them.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Thêm phòng mới
            </a>
        </div>
        <div class="card-body">
            <!-- Bộ lọc phòng -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm phòng...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <select id="statusFilter" class="form-select me-2" style="max-width: 200px;">
                            <option value="">Tất cả trạng thái</option>
                            <option value="trống">Trống</option>
                            <option value="đã đặt">Đã đặt</option>
                            <option value="đang sử dụng">Đang sử dụng</option>
                            <option value="bảo trì">Bảo trì</option>
                        </select>
                        <select id="typeFilter" class="form-select" style="max-width: 200px;">
                            <option value="">Tất cả loại phòng</option>
                            <option value="đơn">Phòng đơn</option>
                            <option value="đôi">Phòng đôi</option>
                            <option value="vip">Phòng VIP</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Bảng danh sách phòng -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="roomTable">
                    <thead class="table-light">
                        <tr>
                            <th>Số phòng</th>
                            <th>Loại phòng</th>
                            <th>Giá (VNĐ/ngày)</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($phong_list && count($phong_list) > 0): ?>
                            <?php foreach ($phong_list as $phong): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($phong['so_phong']); ?></td>
                                    <td>
                                        <?php 
                                            switch($phong['loai_phong']) {
                                                case 'đơn':
                                                    echo '<span class="badge bg-primary">Phòng đơn</span>';
                                                    break;
                                                case 'đôi':
                                                    echo '<span class="badge bg-success">Phòng đôi</span>';
                                                    break;
                                                case 'vip':
                                                    echo '<span class="badge bg-warning">Phòng VIP</span>';
                                                    break;
                                                default:
                                                    echo htmlspecialchars($phong['loai_phong']);
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo number_format($phong['gia_ngay'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php 
                                            switch($phong['trang_thai']) {
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
                                                    echo htmlspecialchars($phong['trang_thai']);
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changeStatusModal<?php echo $phong['id']; ?>">
                                                <i class="fas fa-exchange-alt"></i> Đổi trạng thái
                                            </button>
                                            <a href="sua.php?id=<?php echo $phong['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-edit"></i> Sửa
                                            </a>
                                            <?php if ($user_role == 'quản lý'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $phong['id']; ?>">
                                                <i class="fas fa-trash-alt"></i> Xóa
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Modal đổi trạng thái -->
                                        <div class="modal fade" id="changeStatusModal<?php echo $phong['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Đổi trạng thái phòng <?php echo htmlspecialchars($phong['so_phong']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="room_id" value="<?php echo $phong['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="new_status" class="form-label">Trạng thái mới:</label>
                                                                <select name="new_status" class="form-select" required>
                                                                    <option value="trống" <?php echo $phong['trang_thai'] == 'trống' ? 'selected' : ''; ?>>Trống</option>
                                                                    <option value="đã đặt" <?php echo $phong['trang_thai'] == 'đã đặt' ? 'selected' : ''; ?>>Đã đặt</option>
                                                                    <option value="đang sử dụng" <?php echo $phong['trang_thai'] == 'đang sử dụng' ? 'selected' : ''; ?>>Đang sử dụng</option>
                                                                    <option value="bảo trì" <?php echo $phong['trang_thai'] == 'bảo trì' ? 'selected' : ''; ?>>Bảo trì</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                            <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Modal xóa phòng (chỉ quản lý mới có) -->
                                        <?php if ($user_role == 'quản lý'): ?>
                                        <div class="modal fade" id="deleteModal<?php echo $phong['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Xác nhận xóa</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Bạn có chắc chắn muốn xóa phòng <strong><?php echo htmlspecialchars($phong['so_phong']); ?></strong> không?
                                                        <p class="text-danger mt-2">Lưu ý: Hành động này không thể hoàn tác và có thể ảnh hưởng đến dữ liệu đặt phòng liên quan!</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                                                        <a href="xoa.php?id=<?php echo $phong['id']; ?>" class="btn btn-danger">Xóa</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Không có dữ liệu phòng nào.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Thêm phân trang -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Phân trang danh sách phòng">
                    <ul class="pagination">
                        <!-- Nút Previous -->
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($current_page > 1) ? '?page='.($current_page-1) : '#'; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <!-- Các nút số trang -->
                        <?php 
                        // Hiển thị tối đa 5 nút số trang
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $start_page + 4);
                        
                        if ($end_page - $start_page < 4) {
                            $start_page = max(1, $end_page - 4);
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Nút Next -->
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($current_page < $total_pages) ? '?page='.($current_page+1) : '#'; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Thêm thông tin về số lượng hiển thị -->
        <div class="card-footer text-muted">
            <div class="row">
                <div class="col-md-6">
                    Hiển thị <?php echo count($phong_list); ?> phòng trên trang <?php echo $current_page; ?>/<?php echo $total_pages; ?>
                </div>
                <div class="col-md-6 text-end">
                    Tổng số: <?php echo $total_rooms; ?> phòng
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Script lọc và tìm kiếm phòng
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const table = document.getElementById('roomTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const status = statusFilter.value.toLowerCase();
        const type = typeFilter.value.toLowerCase();
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            
            if (cells.length === 0) continue; // Skip if no cells (like in "no data" row)
            
            const roomNumber = cells[0].textContent.toLowerCase();
            const roomType = cells[1].textContent.toLowerCase();
            const roomStatus = cells[3].textContent.toLowerCase();
            
            const matchesSearch = roomNumber.includes(searchTerm);
            const matchesStatus = status === '' || roomStatus.includes(status);
            const matchesType = type === '' || roomType.includes(type);
            
            if (matchesSearch && matchesStatus && matchesType) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
    
    searchInput.addEventListener('keyup', filterTable);
    statusFilter.addEventListener('change', filterTable);
    typeFilter.addEventListener('change', filterTable);
    
    // Sửa chức năng filter để phù hợp với phân trang
    // Khi người dùng filter, hãy xử lý trực tiếp trên client-side để không làm mất phân trang
    // Nếu muốn filter server-side, bạn có thể thêm form để submit lại các điều kiện lọc
});
</script>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush(); // Xóa bộ nhớ đệm
?>