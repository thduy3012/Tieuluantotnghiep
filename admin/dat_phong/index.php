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
require_once __DIR__ . '/../../includes/functions.php';

// Lấy danh sách đặt phòng
$query = "SELECT dp.*, kh.ho_ten AS ten_khach_hang, p.so_phong, nv.ho_ten AS ten_nhan_vien,
          DATEDIFF(dp.ngay_tra_phong, dp.ngay_nhan_phong) AS so_ngay,
          p.gia_ngay, p.loai_phong
          FROM dat_phong dp
          JOIN khach_hang kh ON dp.id_khach_hang = kh.id
          JOIN phong p ON dp.id_phong = p.id
          JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
          ORDER BY dp.ngay_nhan_phong DESC";

$bookings = fetchAllRows($query);

// Xử lý tìm kiếm
$search_query = "";
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
    if (!empty($search_term)) {
        $search_query = " WHERE kh.ho_ten LIKE :search OR p.so_phong LIKE :search OR dp.trang_thai LIKE :search";
        $query = "SELECT dp.*, kh.ho_ten AS ten_khach_hang, p.so_phong, nv.ho_ten AS ten_nhan_vien,
                DATEDIFF(dp.ngay_tra_phong, dp.ngay_nhan_phong) AS so_ngay,
                p.gia_ngay, p.loai_phong
                FROM dat_phong dp
                JOIN khach_hang kh ON dp.id_khach_hang = kh.id
                JOIN phong p ON dp.id_phong = p.id
                JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id" . $search_query . "
                ORDER BY dp.ngay_nhan_phong DESC";
        
        $bookings = fetchAllRows($query, [':search' => '%' . $search_term . '%']);
    }
}

// Xử lý lọc theo trạng thái
if (isset($_GET['status']) && $_GET['status'] != 'all') {
    $status = $_GET['status'];
    $query = "SELECT dp.*, kh.ho_ten AS ten_khach_hang, p.so_phong, nv.ho_ten AS ten_nhan_vien,
            DATEDIFF(dp.ngay_tra_phong, dp.ngay_nhan_phong) AS so_ngay,
            p.gia_ngay, p.loai_phong
            FROM dat_phong dp
            JOIN khach_hang kh ON dp.id_khach_hang = kh.id
            JOIN phong p ON dp.id_phong = p.id
            JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
            WHERE dp.trang_thai = :status
            ORDER BY dp.ngay_nhan_phong DESC";
    
    $bookings = fetchAllRows($query, [':status' => $status]);
}

// Xử lý xóa đặt phòng
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $booking_id = $_GET['delete'];
    
    // Kiểm tra xem đặt phòng có thể xóa không (chỉ những đặt phòng chưa nhận phòng)
    $check_query = "SELECT trang_thai FROM dat_phong WHERE id = :id";
    $booking_status = fetchSingleRow($check_query, [':id' => $booking_id]);
    
    if ($booking_status && $booking_status['trang_thai'] == 'đã đặt') {
        // Xóa đặt phòng
        $delete_query = "DELETE FROM dat_phong WHERE id = :id";
        $result = executeQuery($delete_query, [':id' => $booking_id]);
        
        if ($result) {
            $_SESSION['message'] = "Đã xóa thông tin đặt phòng thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Có lỗi xảy ra khi xóa đặt phòng!";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Không thể xóa đặt phòng đã nhận phòng hoặc đã trả phòng!";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit;
}

// Xử lý hủy đặt phòng
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = $_GET['cancel'];
    
    // Kiểm tra xem đặt phòng có thể hủy không (chỉ những đặt phòng chưa nhận phòng)
    $check_query = "SELECT trang_thai, id_phong FROM dat_phong WHERE id = :id";
    $booking = fetchSingleRow($check_query, [':id' => $booking_id]);
    
    if ($booking && $booking['trang_thai'] == 'đã đặt') {
        // Cập nhật trạng thái đặt phòng thành đã hủy
        $update_booking_query = "UPDATE dat_phong SET trang_thai = 'đã hủy' WHERE id = :id";
        $result1 = executeQuery($update_booking_query, [':id' => $booking_id]);
        
        // Cập nhật trạng thái phòng thành trống
        $update_room_query = "UPDATE phong SET trang_thai = 'trống' WHERE id = :id";
        $result2 = executeQuery($update_room_query, [':id' => $booking['id_phong']]);
        
        if ($result1 && $result2) {
            $_SESSION['message'] = "Đã hủy đặt phòng thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Có lỗi xảy ra khi hủy đặt phòng!";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Không thể hủy đặt phòng đã nhận phòng hoặc đã trả phòng!";
        $_SESSION['message_type'] = "warning";
    }
    
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit;
}

// Hiển thị trang
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check"></i> Quản lý đặt phòng</h2>
        <a href="/quanlykhachsan/admin/dat_phong/them.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Đặt phòng mới
        </a>
    </div>

    <!-- Tìm kiếm và lọc -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <form action="" method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Tìm kiếm theo tên khách, số phòng..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                            <a href="/quanlykhachsan/admin/dat_phong/index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync-alt"></i> Làm mới
                            </a>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <form action="" method="GET" class="mb-3">
                        <div class="input-group">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                                <option value="đã đặt" <?php echo (isset($_GET['status']) && $_GET['status'] == 'đã đặt') ? 'selected' : ''; ?>>Đã đặt</option>
                                <option value="đã nhận phòng" <?php echo (isset($_GET['status']) && $_GET['status'] == 'đã nhận phòng') ? 'selected' : ''; ?>>Đã nhận phòng</option>
                                <option value="đã trả phòng" <?php echo (isset($_GET['status']) && $_GET['status'] == 'đã trả phòng') ? 'selected' : ''; ?>>Đã trả phòng</option>
                                <option value="đã hủy" <?php echo (isset($_GET['status']) && $_GET['status'] == 'đã hủy') ? 'selected' : ''; ?>>Đã hủy</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng danh sách đặt phòng -->
    <div class="card">
        <div class="card-body">
            <?php if ($bookings && count($bookings) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Khách hàng</th>
                                <th>Phòng</th>
                                <th>Ngày nhận</th>
                                <th>Ngày trả</th>
                                <th>Số ngày</th>
                                <th>Tiền cọc</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['ten_khach_hang']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['so_phong']); ?>
                                        <span class="badge bg-info"><?php echo ucfirst($booking['loai_phong']); ?></span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['ngay_nhan_phong'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['ngay_tra_phong'])); ?></td>
                                    <td><?php echo $booking['so_ngay']; ?></td>
                                    <td><?php echo number_format($booking['tien_coc'], 0, ',', '.'); ?> đ</td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($booking['trang_thai']) {
                                            case 'đã đặt':
                                                $status_class = 'bg-warning';
                                                break;
                                            case 'đã nhận phòng':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'đã trả phòng':
                                                $status_class = 'bg-primary';
                                                break;
                                            case 'đã hủy':
                                                $status_class = 'bg-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($booking['trang_thai']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/quanlykhachsan/admin/dat_phong/chi_tiet.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info" title="Chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($booking['trang_thai'] == 'đã đặt'): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/sua.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary" title="Sửa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="/quanlykhachsan/admin/dat_phong/nhan_phong.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-success" title="Nhận phòng">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                                <a href="javascript:void(0);" onclick="confirmCancel(<?php echo $booking['id']; ?>)" class="btn btn-sm btn-warning" title="Hủy đặt phòng">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $booking['id']; ?>)" class="btn btn-sm btn-danger" title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php elseif ($booking['trang_thai'] == 'đã nhận phòng'): ?>
                                                <a href="/quanlykhachsan/admin/dat_phong/tra_phong.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary" title="Trả phòng">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </a>
                                                <a href="/quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php?id_dat_phong=<?php echo $booking['id']; ?>" class="btn btn-sm btn-success" title="Thêm dịch vụ">
                                                    <i class="fas fa-concierge-bell"></i>
                                                </a>
                                            <?php elseif ($booking['trang_thai'] == 'đã trả phòng'): ?>
                                                <a href="/quanlykhachsan/admin/hoa_don/xem.php?id_dat_phong=<?php echo $booking['id']; ?>" class="btn btn-sm btn-success" title="Xem hóa đơn">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Không tìm thấy thông tin đặt phòng nào.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa thông tin đặt phòng này không? 
                Hành động này không thể hoàn tác.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <a href="#" id="confirmDeleteButton" class="btn btn-danger">Xóa</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal xác nhận hủy đặt phòng -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Xác nhận hủy đặt phòng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn hủy đặt phòng này không? 
                Phòng sẽ được đưa về trạng thái trống và có thể được đặt lại.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Không</button>
                <a href="#" id="confirmCancelButton" class="btn btn-warning">Hủy đặt phòng</a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript cho modal -->
<script>
    function confirmDelete(id) {
        document.getElementById('confirmDeleteButton').href = '/quanlykhachsan/admin/dat_phong/index.php?delete=' + id;
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }

    function confirmCancel(id) {
        document.getElementById('confirmCancelButton').href = '/quanlykhachsan/admin/dat_phong/index.php?cancel=' + id;
        var cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
        cancelModal.show();
    }
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';