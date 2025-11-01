<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    // Nếu chưa đăng nhập, chuyển hướng về trang đăng nhập
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Import file cấu hình và kết nối database
require_once __DIR__ . '/../../config/config.php';

// Lấy thông tin người dùng hiện tại
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM nhan_vien WHERE id = ?";
$nhan_vien = fetchSingleRow($sql, [$user_id]);

if (!$nhan_vien) {
    $_SESSION['message'] = "Không tìm thấy thông tin nhân viên!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/admin/index.php");
    exit();
}

// Biến lưu trữ thông báo lỗi và kết quả
$errors = [];
$success = false;

// Xử lý khi form được gửi đi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu từ form
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Kiểm tra mật khẩu hiện tại
    if (empty($current_password)) {
        $errors[] = "Vui lòng nhập mật khẩu hiện tại";
    } elseif (!password_verify($current_password, $nhan_vien['mat_khau'])) {
        $errors[] = "Mật khẩu hiện tại không đúng";
    }
    
    // Kiểm tra mật khẩu mới
    if (empty($new_password)) {
        $errors[] = "Vui lòng nhập mật khẩu mới";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Mật khẩu mới phải có ít nhất 6 ký tự";
    }
    
    // Kiểm tra xác nhận mật khẩu
    if (empty($confirm_password)) {
        $errors[] = "Vui lòng xác nhận mật khẩu mới";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Xác nhận mật khẩu không khớp với mật khẩu mới";
    }
    
    // Nếu không có lỗi, tiến hành đổi mật khẩu
    if (empty($errors)) {
        // Mã hóa mật khẩu mới
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Cập nhật mật khẩu trong cơ sở dữ liệu
        $sql = "UPDATE nhan_vien SET mat_khau = ? WHERE id = ?";
        $result = executeQuery($sql, [$hashed_password, $user_id]);
        
        if ($result) {
            $success = true;
            $_SESSION['message'] = "Đổi mật khẩu thành công!";
            $_SESSION['message_type'] = "success";
            header("Location: /quanlykhachsan/admin/tai_khoan/doi_mat_khau.php");
            exit();
        } else {
            $errors[] = "Đã xảy ra lỗi khi cập nhật mật khẩu. Vui lòng thử lại sau.";
        }
    }
}

// Hiển thị giao diện
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-key me-2"></i>Đổi Mật Khẩu
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            Đổi mật khẩu thành công!
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       required minlength="6">
                            </div>
                            <div class="form-text">Mật khẩu phải có ít nhất 6 ký tự</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/quanlykhachsan/admin/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Thông tin hữu ích
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-0">
                        <h6><i class="fas fa-shield-alt me-2"></i>Lưu ý về bảo mật:</h6>
                        <ul class="mb-0">
                            <li>Nên đặt mật khẩu có ít nhất 8 ký tự</li>
                            <li>Nên kết hợp chữ hoa, chữ thường, số và ký tự đặc biệt</li>
                            <li>Không nên sử dụng thông tin cá nhân dễ đoán trong mật khẩu</li>
                            <li>Không nên sử dụng cùng một mật khẩu cho nhiều tài khoản khác nhau</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>