<?php
// Khởi tạo session
session_start();

// Nếu đã đăng nhập thì chuyển hướng đến trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Include file cấu hình và kết nối database
require_once '../config/config.php';
require_once '../includes/functions.php';

$error_message = '';
$registered = isset($_GET['registered']) && $_GET['registered'] == 1;

// Xử lý form đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten_dang_nhap = trim($_POST['ten_dang_nhap'] ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');

    // Kiểm tra các trường dữ liệu
    if (empty($ten_dang_nhap) || empty($mat_khau)) {
        $error_message = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } else {
        // Tìm người dùng trong CSDL
        $sql = "SELECT id, ten_dang_nhap, mat_khau, ho_ten, chuc_vu FROM nhan_vien 
                WHERE ten_dang_nhap = ? AND trang_thai = 1";
        $user = fetchSingleRow($sql, [$ten_dang_nhap]);

        if ($user && password_verify($mat_khau, $user['mat_khau'])) {
            // Đăng nhập thành công, tạo session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['ten_dang_nhap'];
            $_SESSION['role'] = $user['chuc_vu'];
            $_SESSION['fullname'] = $user['ho_ten'];

            // Cập nhật thời gian đăng nhập (nếu bạn muốn thêm tính năng này)
            // executeQuery("UPDATE nhan_vien SET last_login = NOW() WHERE id = ?", [$user['id']]);

            // Chuyển hướng đến trang chủ
            header('Location: ../index.php');
            exit();
        } else {
            $error_message = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 rounded-lg mt-5">
                <div class="card-header">
                    <h3 class="text-center font-weight-light my-4">Đăng nhập</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($registered): ?>
                        <div class="alert alert-success">Đăng ký tài khoản thành công! Vui lòng đăng nhập.</div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group mb-3">
                            <label for="ten_dang_nhap" class="small mb-1">Tên đăng nhập</label>
                            <input class="form-control" id="ten_dang_nhap" name="ten_dang_nhap" type="text" placeholder="Nhập tên đăng nhập" />
                        </div>
                        <div class="form-group mb-3">
                            <label for="mat_khau" class="small mb-1">Mật khẩu</label>
                            <input class="form-control" id="mat_khau" name="mat_khau" type="password" placeholder="Nhập mật khẩu" />
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" id="remember" name="remember" type="checkbox" />
                            <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                            <a class="small" href="#">Quên mật khẩu?</a>
                            <button type="submit" class="btn btn-primary">Đăng nhập</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small"><a href="register.php">Chưa có tài khoản? Đăng ký ngay!</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>