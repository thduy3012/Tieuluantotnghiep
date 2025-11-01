<?php
// Kiểm tra session và quyền truy cập
session_start();

// Include file cấu hình và kết nối database
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// Kiểm tra quyền quản lý
if ($_SESSION['role'] !== 'quản lý') {
    $_SESSION['error'] = "Bạn không có quyền truy cập trang này!";
    header('Location: ../../index.php');
    exit;
}

// Kiểm tra ID nhân viên
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID nhân viên không hợp lệ!";
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];
$conn = getDatabaseConnection();

// Lấy thông tin nhân viên
$sql = "SELECT * FROM nhan_vien WHERE id = ?";
$nhan_vien = fetchSingleRow($sql, [$id]);

if (!$nhan_vien) {
    $_SESSION['error'] = "Không tìm thấy nhân viên!";
    header('Location: index.php');
    exit;
}

// Xử lý khi form được gửi đi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ho_ten = trim($_POST['ho_ten']);
    $ten_dang_nhap = trim($_POST['ten_dang_nhap']);
    $chuc_vu = $_POST['chuc_vu'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    $mat_khau = trim($_POST['mat_khau']);
    
    // Validate dữ liệu
    $errors = [];
    
    if (empty($ho_ten)) {
        $errors[] = "Họ tên không được để trống!";
    }
    
    if (empty($ten_dang_nhap)) {
        $errors[] = "Tên đăng nhập không được để trống!";
    }
    
    if (!in_array($chuc_vu, ['quản lý', 'nhân viên'])) {
        $errors[] = "Chức vụ không hợp lệ!";
    }
    
    // Kiểm tra tên đăng nhập đã tồn tại chưa (nếu thay đổi tên đăng nhập)
    if ($ten_dang_nhap !== $nhan_vien['ten_dang_nhap']) {
        $sql_check = "SELECT id FROM nhan_vien WHERE ten_dang_nhap = ? AND id != ?";
        $exists = fetchSingleRow($sql_check, [$ten_dang_nhap, $id]);
        
        if ($exists) {
            $errors[] = "Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác!";
        }
    }
    
    // Nếu không có lỗi, tiến hành cập nhật
    if (empty($errors)) {
        try {
            // Chuẩn bị câu SQL (có hoặc không có mật khẩu)
            if (!empty($mat_khau)) {
                // Nếu có cập nhật mật khẩu
                $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);
                $sql_update = "UPDATE nhan_vien SET 
                               ho_ten = ?, 
                               ten_dang_nhap = ?, 
                               chuc_vu = ?, 
                               trang_thai = ?,
                               mat_khau = ?
                               WHERE id = ?";
                $params = [$ho_ten, $ten_dang_nhap, $chuc_vu, $trang_thai, $hashed_password, $id];
            } else {
                // Nếu không cập nhật mật khẩu
                $sql_update = "UPDATE nhan_vien SET 
                               ho_ten = ?, 
                               ten_dang_nhap = ?, 
                               chuc_vu = ?, 
                               trang_thai = ?
                               WHERE id = ?";
                $params = [$ho_ten, $ten_dang_nhap, $chuc_vu, $trang_thai, $id];
            }
            
            // Thực hiện cập nhật
            if (executeQuery($sql_update, $params)) {
                $_SESSION['success'] = "Cập nhật thông tin nhân viên thành công!";
                header('Location: index.php');
                exit;
            } else {
                $errors[] = "Có lỗi xảy ra khi cập nhật thông tin!";
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi database: " . $e->getMessage();
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Sửa thông tin nhân viên</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin nhân viên</h6>
                </div>
                <div class="card-body">
                    <form action="sua.php?id=<?php echo $id; ?>" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ho_ten" class="form-label">Họ tên</label>
                                <input type="text" class="form-control" id="ho_ten" name="ho_ten" value="<?php echo htmlspecialchars($nhan_vien['ho_ten']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="ten_dang_nhap" class="form-label">Tên đăng nhập</label>
                                <input type="text" class="form-control" id="ten_dang_nhap" name="ten_dang_nhap" value="<?php echo htmlspecialchars($nhan_vien['ten_dang_nhap']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="chuc_vu" class="form-label">Chức vụ</label>
                                <select class="form-control" id="chuc_vu" name="chuc_vu" required>
                                    <option value="quản lý" <?php echo ($nhan_vien['chuc_vu'] === 'quản lý') ? 'selected' : ''; ?>>Quản lý</option>
                                    <option value="nhân viên" <?php echo ($nhan_vien['chuc_vu'] === 'nhân viên') ? 'selected' : ''; ?>>Nhân viên</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="trang_thai" class="form-label">Trạng thái</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" <?php echo ($nhan_vien['trang_thai'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="trang_thai">Hoạt động</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="mat_khau" class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                                <input type="password" class="form-control" id="mat_khau" name="mat_khau">
                                <small class="text-muted">Để trống nếu không muốn đổi mật khẩu</small>
                            </div>
                            <div class="col-md-6">
                                <label for="xac_nhan_mat_khau" class="form-label">Xác nhận mật khẩu mới</label>
                                <input type="password" class="form-control" id="xac_nhan_mat_khau" name="xac_nhan_mat_khau">
                            </div>
                        </div>
                        
                        <!-- Các nút hành động -->
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Lưu thay đổi
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Kiểm tra mật khẩu xác nhận
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const matKhauInput = document.getElementById('mat_khau');
    const xacNhanMatKhauInput = document.getElementById('xac_nhan_mat_khau');
    
    form.addEventListener('submit', function(event) {
        // Kiểm tra nếu mật khẩu mới được nhập
        if (matKhauInput.value.trim() !== '') {
            // Kiểm tra xác nhận mật khẩu
            if (matKhauInput.value !== xacNhanMatKhauInput.value) {
                event.preventDefault();
                alert('Mật khẩu xác nhận không khớp!');
                xacNhanMatKhauInput.focus();
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>