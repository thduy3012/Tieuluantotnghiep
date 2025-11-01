<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Import file cấu hình và các hàm cần thiết
require_once __DIR__ . '/../../config/config.php';

// Xử lý thay đổi trạng thái dịch vụ nếu có
if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
    $id = $_POST['id'];
    $trang_thai = $_POST['trang_thai'] ? 0 : 1; // Đảo ngược trạng thái

    $sql = "UPDATE dich_vu SET trang_thai = :trang_thai WHERE id = :id";
    $result = executeQuery($sql, [':trang_thai' => $trang_thai, ':id' => $id]);

    if ($result) {
        $_SESSION['message'] = "Đã cập nhật trạng thái dịch vụ!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Lỗi khi cập nhật trạng thái dịch vụ!";
        $_SESSION['message_type'] = "danger";
    }

    // Chuyển hướng để tránh việc gửi lại form khi làm mới trang
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Xử lý thêm dịch vụ mới
if (isset($_POST['action']) && $_POST['action'] == 'add_service') {
    $ten_dich_vu = $_POST['ten_dich_vu'];
    $gia = $_POST['gia'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;

    // Kiểm tra dữ liệu đầu vào
    if (empty($ten_dich_vu) || empty($gia)) {
        $_SESSION['message'] = "Vui lòng nhập đầy đủ thông tin dịch vụ!";
        $_SESSION['message_type'] = "danger";
    } else {
        $sql = "INSERT INTO dich_vu (ten_dich_vu, gia, trang_thai) VALUES (:ten_dich_vu, :gia, :trang_thai)";
        $result = executeQuery($sql, [
            ':ten_dich_vu' => $ten_dich_vu, 
            ':gia' => $gia, 
            ':trang_thai' => $trang_thai
        ]);

        if ($result) {
            $_SESSION['message'] = "Thêm dịch vụ mới thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Lỗi khi thêm dịch vụ mới!";
            $_SESSION['message_type'] = "danger";
        }
    }

    // Chuyển hướng để tránh việc gửi lại form khi làm mới trang
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Xử lý cập nhật dịch vụ
if (isset($_POST['action']) && $_POST['action'] == 'update_service') {
    $id = $_POST['id'];
    $ten_dich_vu = $_POST['ten_dich_vu'];
    $gia = $_POST['gia'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;

    // Kiểm tra dữ liệu đầu vào
    if (empty($ten_dich_vu) || empty($gia)) {
        $_SESSION['message'] = "Vui lòng nhập đầy đủ thông tin dịch vụ!";
        $_SESSION['message_type'] = "danger";
    } else {
        $sql = "UPDATE dich_vu SET ten_dich_vu = :ten_dich_vu, gia = :gia, trang_thai = :trang_thai WHERE id = :id";
        $result = executeQuery($sql, [
            ':ten_dich_vu' => $ten_dich_vu, 
            ':gia' => $gia, 
            ':trang_thai' => $trang_thai,
            ':id' => $id
        ]);

        if ($result) {
            $_SESSION['message'] = "Cập nhật dịch vụ thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Lỗi khi cập nhật dịch vụ!";
            $_SESSION['message_type'] = "danger";
        }
    }

    // Chuyển hướng để tránh việc gửi lại form khi làm mới trang
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Xử lý xóa dịch vụ
if (isset($_POST['action']) && $_POST['action'] == 'delete_service') {
    $id = $_POST['id'];

    // Kiểm tra xem dịch vụ đã được sử dụng chưa
    $sql_check = "SELECT COUNT(*) as count FROM su_dung_dich_vu WHERE id_dich_vu = :id";
    $check_result = fetchSingleRow($sql_check, [':id' => $id]);

    if ($check_result && $check_result['count'] > 0) {
        $_SESSION['message'] = "Không thể xóa dịch vụ này vì đã được sử dụng!";
        $_SESSION['message_type'] = "warning";
    } else {
        $sql = "DELETE FROM dich_vu WHERE id = :id";
        $result = executeQuery($sql, [':id' => $id]);

        if ($result) {
            $_SESSION['message'] = "Xóa dịch vụ thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Lỗi khi xóa dịch vụ!";
            $_SESSION['message_type'] = "danger";
        }
    }

    // Chuyển hướng để tránh việc gửi lại form khi làm mới trang
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Lấy danh sách tất cả dịch vụ
$sql = "SELECT * FROM dich_vu ORDER BY id";
$dich_vu_list = fetchAllRows($sql);

// Tiêu đề trang
$page_title = "Quản lý Dịch vụ";

// Import header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-concierge-bell me-2"></i>Danh sách dịch vụ</h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addServiceModal">
            <i class="fas fa-plus me-1"></i> Thêm dịch vụ mới
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($dich_vu_list)): ?>
            <div class="alert alert-info">
                Chưa có dịch vụ nào trong cơ sở dữ liệu.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên dịch vụ</th>
                            <th>Giá</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dich_vu_list as $dich_vu): ?>
                            <tr>
                                <td><?php echo $dich_vu['id']; ?></td>
                                <td><?php echo htmlspecialchars($dich_vu['ten_dich_vu']); ?></td>
                                <td><?php echo number_format($dich_vu['gia'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <form method="post" action="" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $dich_vu['id']; ?>">
                                        <input type="hidden" name="trang_thai" value="<?php echo $dich_vu['trang_thai']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $dich_vu['trang_thai'] ? 'btn-success' : 'btn-secondary'; ?>">
                                            <?php echo $dich_vu['trang_thai'] ? 'Hoạt động' : 'Ngừng cung cấp'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm edit-btn" 
                                            data-id="<?php echo $dich_vu['id']; ?>"
                                            data-ten="<?php echo htmlspecialchars($dich_vu['ten_dich_vu']); ?>"
                                            data-gia="<?php echo $dich_vu['gia']; ?>"
                                            data-trang-thai="<?php echo $dich_vu['trang_thai']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editServiceModal">
                                        <i class="fas fa-edit"></i> Sửa
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm delete-btn"
                                            data-id="<?php echo $dich_vu['id']; ?>"
                                            data-ten="<?php echo htmlspecialchars($dich_vu['ten_dich_vu']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteServiceModal">
                                        <i class="fas fa-trash"></i> Xóa
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Thêm Dịch vụ mới -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm dịch vụ mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_service">
                    <div class="mb-3">
                        <label for="ten_dich_vu" class="form-label">Tên dịch vụ</label>
                        <input type="text" class="form-control" id="ten_dich_vu" name="ten_dich_vu" required>
                    </div>
                    <div class="mb-3">
                        <label for="gia" class="form-label">Giá (VNĐ)</label>
                        <input type="number" class="form-control" id="gia" name="gia" min="0" step="1000" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="trang_thai" name="trang_thai" checked>
                        <label class="form-check-label" for="trang_thai">Hoạt động</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm dịch vụ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Sửa Dịch vụ -->
<div class="modal fade" id="editServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sửa thông tin dịch vụ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_service">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_ten_dich_vu" class="form-label">Tên dịch vụ</label>
                        <input type="text" class="form-control" id="edit_ten_dich_vu" name="ten_dich_vu" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_gia" class="form-label">Giá (VNĐ)</label>
                        <input type="number" class="form-control" id="edit_gia" name="gia" min="0" step="1000" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_trang_thai" name="trang_thai">
                        <label class="form-check-label" for="edit_trang_thai">Hoạt động</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Xóa Dịch vụ -->
<div class="modal fade" id="deleteServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa dịch vụ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa dịch vụ <strong id="delete_service_name"></strong>?</p>
                <p class="text-danger">Lưu ý: Dịch vụ đã được sử dụng sẽ không thể xóa.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="">
                    <input type="hidden" name="action" value="delete_service">
                    <input type="hidden" name="id" id="delete_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Xử lý sự kiện khi nhấn nút Sửa
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const ten = this.getAttribute('data-ten');
            const gia = this.getAttribute('data-gia');
            const trangThai = this.getAttribute('data-trang-thai');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_ten_dich_vu').value = ten;
            document.getElementById('edit_gia').value = gia;
            document.getElementById('edit_trang_thai').checked = (trangThai === '1');
        });
    });
    
    // Xử lý sự kiện khi nhấn nút Xóa
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const ten = this.getAttribute('data-ten');
            
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_service_name').textContent = ten;
        });
    });
</script>

<?php
// Import footer
include_once __DIR__ . '/../../includes/footer.php';
?>