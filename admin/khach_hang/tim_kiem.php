<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import các file cần thiết
require_once __DIR__ . '/../../config/config.php';
include_once __DIR__ . '/../../includes/header.php';

// Khởi tạo biến tìm kiếm
$search_term = '';
$search_field = 'ho_ten';
$results = [];
$has_search = false;

// Xử lý form tìm kiếm nếu được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_term = trim($_POST['search_term'] ?? '');
    $search_field = $_POST['search_field'] ?? 'ho_ten';
    
    // Chỉ tìm kiếm nếu có dữ liệu nhập vào
    if (!empty($search_term)) {
        $has_search = true;
        
        // Xây dựng câu truy vấn dựa trên trường tìm kiếm
        $sql = "SELECT * FROM khach_hang WHERE ";
        $params = [];
        
        switch ($search_field) {
            case 'id':
                $sql .= "id = ?";
                $params[] = $search_term;
                break;
            case 'so_cmnd':
                $sql .= "so_cmnd LIKE ?";
                $params[] = "%$search_term%";
                break;
            case 'so_dien_thoai':
                $sql .= "so_dien_thoai LIKE ?";
                $params[] = "%$search_term%";
                break;
            case 'dia_chi':
                $sql .= "dia_chi LIKE ?";
                $params[] = "%$search_term%";
                break;
            case 'ho_ten':
            default:
                $sql .= "ho_ten LIKE ?";
                $params[] = "%$search_term%";
                break;
        }
        
        $sql .= " ORDER BY id DESC";
        
        // Thực hiện truy vấn
        $results = fetchAllRows($sql, $params);
    }
}
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><i class="fas fa-search me-2"></i>Tìm kiếm khách hàng</h1>
    <a href="/quanlykhachsan/admin/khach_hang/them.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Thêm khách hàng mới
    </a>
</div>

<!-- Form tìm kiếm -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0"><i class="fas fa-search me-2"></i>Tìm kiếm</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="search_term" class="form-label">Từ khóa tìm kiếm:</label>
                    <input type="text" class="form-control" id="search_term" name="search_term" 
                           value="<?php echo htmlspecialchars($search_term); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="search_field" class="form-label">Tìm theo:</label>
                    <select class="form-select" id="search_field" name="search_field">
                        <option value="ho_ten" <?php echo $search_field == 'ho_ten' ? 'selected' : ''; ?>>Họ tên</option>
                        <option value="id" <?php echo $search_field == 'id' ? 'selected' : ''; ?>>Mã khách hàng</option>
                        <option value="so_cmnd" <?php echo $search_field == 'so_cmnd' ? 'selected' : ''; ?>>Số CMND/CCCD</option>
                        <option value="so_dien_thoai" <?php echo $search_field == 'so_dien_thoai' ? 'selected' : ''; ?>>Số điện thoại</option>
                        <option value="dia_chi" <?php echo $search_field == 'dia_chi' ? 'selected' : ''; ?>>Địa chỉ</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Tìm kiếm
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hiển thị kết quả tìm kiếm -->
<?php if ($has_search): ?>
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>Kết quả tìm kiếm <?php echo !empty($results) ? "(" . count($results) . ")" : ""; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($results)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Mã KH</th>
                                <th>Họ và tên</th>
                                <th>Số CMND/CCCD</th>
                                <th>Số điện thoại</th>
                                <th>Địa chỉ</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['id']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['ho_ten']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['so_cmnd'] ?? 'Chưa cập nhật'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['so_dien_thoai'] ?? 'Chưa cập nhật'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['dia_chi'] ?? 'Chưa cập nhật'); ?></td>
                                    <td>
                                        <a href="/quanlykhachsan/admin/khach_hang/sua.php?id=<?php echo $customer['id']; ?>" 
                                           class="btn btn-sm btn-primary me-1" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/quanlykhachsan/admin/dat_phong/them.php?id_khach_hang=<?php echo $customer['id']; ?>" 
                                           class="btn btn-sm btn-success me-1" title="Đặt phòng">
                                            <i class="fas fa-bed"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="xemLichSuDatPhong(<?php echo $customer['id']; ?>)" title="Lịch sử đặt phòng">
                                            <i class="fas fa-history"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Không tìm thấy khách hàng nào phù hợp với từ khóa "<?php echo htmlspecialchars($search_term); ?>"
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal xem lịch sử đặt phòng -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="historyModalLabel">Lịch sử đặt phòng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="historyContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                        <p class="mt-2">Đang tải dữ liệu...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Vui lòng nhập từ khóa tìm kiếm để bắt đầu tìm kiếm khách hàng.
    </div>
<?php endif; ?>

<script>
// Hàm xem lịch sử đặt phòng
function xemLichSuDatPhong(customerId) {
    // Hiển thị modal
    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
    modal.show();
    
    // Gọi AJAX để lấy dữ liệu lịch sử đặt phòng
    fetch(`/quanlykhachsan/admin/khach_hang/lich_su_dat_phong.php?id=${customerId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('historyContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('historyContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Đã xảy ra lỗi khi tải dữ liệu: ${error.message}
                </div>
            `;
        });
}
</script>

<?php
// Import footer
include_once __DIR__ . '/../../includes/footer.php';
?>