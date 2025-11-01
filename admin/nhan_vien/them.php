<?php
// Kiểm tra session và quyền truy cập
session_start();

// Include file cấu hình và kết nối database
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Kiểm tra đăng nhập và quyền quản lý
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'quản lý') {
    header('Location: ../../auth/login.php');
    exit;
}

// Biến thông báo
$success_msg = '';
$error_msg = '';

// Xử lý form khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ten_dang_nhap = trim($_POST['ten_dang_nhap']);
    $mat_khau = trim($_POST['mat_khau']);
    $xac_nhan_mat_khau = trim($_POST['xac_nhan_mat_khau']);
    $ho_ten = trim($_POST['ho_ten']);
    $chuc_vu = $_POST['chuc_vu'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;

    // Validate dữ liệu
    if (empty($ten_dang_nhap) || empty($mat_khau) || empty($ho_ten)) {
        $error_msg = "Vui lòng điền đầy đủ thông tin bắt buộc!";
    } elseif ($mat_khau !== $xac_nhan_mat_khau) {
        $error_msg = "Mật khẩu xác nhận không khớp!";
    } elseif (strlen($mat_khau) < 6) {
        $error_msg = "Mật khẩu phải có ít nhất 6 ký tự!";
    } else {
        // Kiểm tra tên đăng nhập đã tồn tại chưa
        $check_sql = "SELECT COUNT(*) as count FROM nhan_vien WHERE ten_dang_nhap = ?";
        $check_result = fetchSingleRow($check_sql, [$ten_dang_nhap]);
        
        if ($check_result['count'] > 0) {
            $error_msg = "Tên đăng nhập đã tồn tại, vui lòng chọn tên đăng nhập khác!";
        } else {
            // Mã hóa mật khẩu
            $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);
            
            // Thêm vào database
            $sql = "INSERT INTO nhan_vien (ten_dang_nhap, mat_khau, ho_ten, chuc_vu, trang_thai) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $result = executeQuery($sql, [
                $ten_dang_nhap,
                $hashed_password,
                $ho_ten,
                $chuc_vu,
                $trang_thai
            ]);
            
            if ($result) {
                $success_msg = "Thêm nhân viên thành công!";
                // Reset form sau khi thêm thành công
                $ten_dang_nhap = '';
                $ho_ten = '';
                $chuc_vu = 'nhân viên';
                $trang_thai = 1;
            } else {
                $error_msg = "Có lỗi xảy ra, vui lòng thử lại sau!";
            }
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
                <h1 class="h2">Thêm Nhân Viên Mới</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại danh sách
                    </a>
                </div>
            </div>
            
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Thông tin nhân viên</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ten_dang_nhap" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ten_dang_nhap" name="ten_dang_nhap" value="<?php echo isset($ten_dang_nhap) ? htmlspecialchars($ten_dang_nhap) : ''; ?>" required>
                                <small class="text-muted">Tên đăng nhập không được trùng với nhân viên khác</small>
                            </div>
                            <div class="col-md-6">
                                <label for="ho_ten" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ho_ten" name="ho_ten" value="<?php echo isset($ho_ten) ? htmlspecialchars($ho_ten) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="mat_khau" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="mat_khau" name="mat_khau" required minlength="6">
                                <small class="text-muted">Mật khẩu tối thiểu 6 ký tự</small>
                            </div>
                            <div class="col-md-6">
                                <label for="xac_nhan_mat_khau" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="xac_nhan_mat_khau" name="xac_nhan_mat_khau" required minlength="6">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="chuc_vu" class="form-label">Chức vụ</label>
                                <select class="form-select" id="chuc_vu" name="chuc_vu">
                                    <option value="nhân viên" <?php echo (isset($chuc_vu) && $chuc_vu === 'nhân viên') ? 'selected' : ''; ?>>Nhân viên</option>
                                    <option value="quản lý" <?php echo (isset($chuc_vu) && $chuc_vu === 'quản lý') ? 'selected' : ''; ?>>Quản lý</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" <?php echo (!isset($trang_thai) || $trang_thai == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="trang_thai">
                                        Kích hoạt tài khoản
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu nhân viên
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Làm mới
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Kiểm tra mật khẩu trùng khớp
document.getElementById('xac_nhan_mat_khau').addEventListener('input', function() {
    const matKhau = document.getElementById('mat_khau').value;
    const xacNhanMatKhau = this.value;
    
    if(matKhau !== xacNhanMatKhau) {
        this.setCustomValidity('Mật khẩu xác nhận không khớp');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('mat_khau').addEventListener('input', function() {
    const matKhau = this.value;
    const xacNhanMatKhau = document.getElementById('xac_nhan_mat_khau').value;
    
    if(xacNhanMatKhau && matKhau !== xacNhanMatKhau) {
        document.getElementById('xac_nhan_mat_khau').setCustomValidity('Mật khẩu xác nhận không khớp');
    } else if(xacNhanMatKhau) {
        document.getElementById('xac_nhan_mat_khau').setCustomValidity('');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>