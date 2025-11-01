<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    // Nếu chưa đăng nhập, chuyển hướng về trang đăng nhập
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Import file cấu hình và functions
require_once __DIR__ . '/../../config/config.php';

// Xử lý form khi người dùng submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $so_cmnd = trim($_POST['so_cmnd'] ?? '');
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $dia_chi = trim($_POST['dia_chi'] ?? '');
    
    // Kiểm tra dữ liệu đầu vào
    $errors = [];
    
    if (empty($ho_ten)) {
        $errors[] = "Họ tên không được để trống";
    }
    
    // Kiểm tra CMND nếu có thì phải hợp lệ (số và 9-12 ký tự)
    if (!empty($so_cmnd) && !preg_match('/^[0-9]{9,12}$/', $so_cmnd)) {
        $errors[] = "Số CMND/CCCD không hợp lệ (phải là số và có 9-12 ký tự)";
    }
    
    // Kiểm tra số điện thoại nếu có thì phải hợp lệ (số và 10-11 ký tự)
    if (!empty($so_dien_thoai) && !preg_match('/^[0-9]{10,11}$/', $so_dien_thoai)) {
        $errors[] = "Số điện thoại không hợp lệ (phải là số và có 10-11 ký tự)";
    }
    
    // Nếu có lỗi, hiển thị thông báo
    if (!empty($errors)) {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "danger";
    } else {
        // Không có lỗi, tiến hành lưu vào database
        try {
            // Kiểm tra xem khách hàng đã tồn tại chưa (nếu có CMND hoặc SĐT)
            $check_sql = "SELECT id FROM khach_hang WHERE ";
            $check_params = [];
            
            if (!empty($so_cmnd)) {
                $check_sql .= "so_cmnd = ?";
                $check_params[] = $so_cmnd;
                
                if (!empty($so_dien_thoai)) {
                    $check_sql .= " OR so_dien_thoai = ?";
                    $check_params[] = $so_dien_thoai;
                }
            } elseif (!empty($so_dien_thoai)) {
                $check_sql .= "so_dien_thoai = ?";
                $check_params[] = $so_dien_thoai;
            } else {
                // Nếu không có CMND và SĐT, bỏ qua việc kiểm tra trùng lặp
                $check_sql = "";
            }
            
            $exists = false;
            if (!empty($check_sql)) {
                $result = fetchSingleRow($check_sql, $check_params);
                $exists = !empty($result);
            }
            
            if ($exists) {
                $_SESSION['message'] = "Khách hàng với số CMND hoặc số điện thoại này đã tồn tại trong hệ thống!";
                $_SESSION['message_type'] = "warning";
            } else {
                // Thêm khách hàng mới
                $sql = "INSERT INTO khach_hang (ho_ten, so_cmnd, so_dien_thoai, dia_chi) VALUES (?, ?, ?, ?)";
                $params = [$ho_ten, $so_cmnd ?: null, $so_dien_thoai ?: null, $dia_chi ?: null];
                
                $result = insertAndGetId($sql, $params);
                
                if ($result) {
                    $_SESSION['message'] = "Thêm khách hàng thành công!";
                    $_SESSION['message_type'] = "success";
                    
                    // Chuyển hướng để tránh việc gửi lại form khi refresh trang
                    header("Location: index.php");
                    exit();
                } else {
                    $_SESSION['message'] = "Có lỗi xảy ra khi thêm khách hàng!";
                    $_SESSION['message_type'] = "danger";
                }
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Lỗi hệ thống: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    }
}

// Import file header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Thêm khách hàng mới</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/khach_hang/index.php">Khách hàng</a></li>
                        <li class="breadcrumb-item active">Thêm khách hàng</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Thông tin khách hàng</h3>
                    </div>
                    
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="ho_ten">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ho_ten" name="ho_ten" 
                                       placeholder="Nhập họ tên khách hàng" value="<?php echo isset($_POST['ho_ten']) ? htmlspecialchars($_POST['ho_ten']) : ''; ?>" required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập họ tên khách hàng.
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="so_cmnd">Số CMND/CCCD</label>
                                <input type="text" class="form-control" id="so_cmnd" name="so_cmnd" 
                                       placeholder="Nhập số CMND/CCCD" value="<?php echo isset($_POST['so_cmnd']) ? htmlspecialchars($_POST['so_cmnd']) : ''; ?>">
                                <small class="form-text text-muted">Số CMND/CCCD phải có 9-12 ký tự số.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="so_dien_thoai">Số điện thoại</label>
                                <input type="text" class="form-control" id="so_dien_thoai" name="so_dien_thoai" 
                                       placeholder="Nhập số điện thoại" value="<?php echo isset($_POST['so_dien_thoai']) ? htmlspecialchars($_POST['so_dien_thoai']) : ''; ?>">
                                <small class="form-text text-muted">Số điện thoại phải có 10-11 ký tự số.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="dia_chi">Địa chỉ</label>
                                <textarea class="form-control" id="dia_chi" name="dia_chi" rows="3" 
                                          placeholder="Nhập địa chỉ khách hàng"><?php echo isset($_POST['dia_chi']) ? htmlspecialchars($_POST['dia_chi']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Lưu
                            </button>
                            <a href="/quanlykhachsan/admin/khach_hang/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Quay lại
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Kích hoạt validation của Bootstrap
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Kiểm tra định dạng CMND và số điện thoại khi nhập
document.getElementById('so_cmnd').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

document.getElementById('so_dien_thoai').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});
</script>

<?php
// Import file footer
include_once __DIR__ . '/../../includes/footer.php';
?>