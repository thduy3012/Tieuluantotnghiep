<?php
// Kiểm tra session và quyền truy cập
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Nếu đã đăng nhập và không phải quản lý thì chuyển hướng
if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'quản lý') {
    header('Location: ../index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Xử lý form đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten_dang_nhap = trim($_POST['ten_dang_nhap'] ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');
    $xac_nhan_mat_khau = trim($_POST['xac_nhan_mat_khau'] ?? '');
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $chuc_vu = trim($_POST['chuc_vu'] ?? '');

    // Kiểm tra các trường dữ liệu
    if (empty($ten_dang_nhap) || empty($mat_khau) || empty($xac_nhan_mat_khau) || empty($ho_ten) || empty($chuc_vu)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin.';
    } elseif ($mat_khau !== $xac_nhan_mat_khau) {
        $error_message = 'Mật khẩu và xác nhận mật khẩu không khớp.';
    } elseif (strlen($mat_khau) < 6) {
        $error_message = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } else {
        // Kiểm tra tên đăng nhập đã tồn tại chưa
        $sql_check = "SELECT id FROM nhan_vien WHERE ten_dang_nhap = ?";
        $result = fetchSingleRow($sql_check, [$ten_dang_nhap]);

        if ($result) {
            $error_message = 'Tên đăng nhập đã tồn tại, vui lòng chọn tên khác.';
        } else {
            // Mã hóa mật khẩu
            $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);

            // Thêm nhân viên mới
            $sql_insert = "INSERT INTO nhan_vien (ten_dang_nhap, mat_khau, ho_ten, chuc_vu, trang_thai) 
                          VALUES (?, ?, ?, ?, 1)";
            $result = executeQuery($sql_insert, [$ten_dang_nhap, $hashed_password, $ho_ten, $chuc_vu]);

            if ($result) {
                $success_message = 'Đăng ký tài khoản thành công!';
                // Nếu không đăng nhập thì chuyển đến trang đăng nhập
                if (!isset($_SESSION['user_id'])) {
                    header('Location: login.php?registered=1');
                    exit();
                }
            } else {
                $error_message = 'Có lỗi xảy ra, vui lòng thử lại sau.';
            }
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-lg border-0 rounded-lg mt-5">
                <div class="card-header">
                    <h3 class="text-center font-weight-light my-4">Đăng ký tài khoản</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group mb-3">
                            <label for="ten_dang_nhap" class="small mb-1">Tên đăng nhập</label>
                            <input class="form-control" id="ten_dang_nhap" name="ten_dang_nhap" type="text" placeholder="Nhập tên đăng nhập" required />
                        </div>
                        <div class="form-group mb-3">
                            <label for="ho_ten" class="small mb-1">Họ và tên</label>
                            <input class="form-control" id="ho_ten" name="ho_ten" type="text" placeholder="Nhập họ và tên" required />
                        </div>
                        <div class="form-group mb-3">
                            <label for="chuc_vu" class="small mb-1">Chức vụ</label>
                            <select class="form-control" id="chuc_vu" name="chuc_vu" required>
                                <option value="">-- Chọn chức vụ --</option>
                                <option value="nhân viên">Nhân viên</option>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'quản lý'): ?>
                                <option value="quản lý">Quản lý</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="mat_khau" class="small mb-1">Mật khẩu</label>
                            <input class="form-control" id="mat_khau" name="mat_khau" type="password" placeholder="Nhập mật khẩu" required />
                        </div>
                        <div class="form-group mb-3">
                            <label for="xac_nhan_mat_khau" class="small mb-1">Xác nhận mật khẩu</label>
                            <input class="form-control" id="xac_nhan_mat_khau" name="xac_nhan_mat_khau" type="password" placeholder="Xác nhận mật khẩu" required />
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                            <button type="submit" class="btn btn-primary">Đăng ký</button>
                            <?php if (!isset($_SESSION['user_id'])): ?>
                            <a class="btn btn-link" href="login.php">Đã có tài khoản? Đăng nhập</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'quản lý'): ?>
                            <a href="../admin/nhan_vien/index.php">Quay lại danh sách nhân viên</a>
                        <?php else: ?>
                            <a href="../index.php">Quay lại trang chủ</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>