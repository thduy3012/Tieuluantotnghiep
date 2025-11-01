<?php
// Bắt đầu phiên làm việc nếu chưa bắt đầu
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Import file cấu hình
require_once __DIR__ . '/../config/config.php';

// Kiểm tra xem người dùng đã đăng nhập chưa (sẽ triển khai đầy đủ trong file auth/check_login.php sau)
$logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_role = $logged_in ? ($_SESSION['role'] ?? '') : '';
$user_name = $logged_in ? ($_SESSION['fullname'] ?? '') : '';

// Lấy đường dẫn hiện tại để xác định menu nào đang active
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản lý Khách sạn</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $logged_in && $user_role == 'quản lý' ? '/quanlykhachsan/assets/css/style.css' : '/quan_ly_khach_san/assets/css/style.css'; ?>">
    <style>
        /* Responsive adjustments for header */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .dropdown-menu {
                border: none;
                background-color: transparent;
                padding-left: 1rem;
            }
            
            .dropdown-item {
                color: rgba(255,255,255,.75);
                padding: 0.5rem 0;
            }
            
            .dropdown-item:hover {
                background-color: transparent;
                color: #fff;
            }
            
            .dropdown-divider {
                border-color: rgba(255,255,255,.25);
            }
        }
        
        /* Ensure long menu items don't break layout */
        .nav-link, .dropdown-item {
            white-space: normal;
            word-wrap: break-word;
        }
        
        /* Improve visibility of active items */
        .nav-link.active {
            font-weight: bold;
            background-color: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        
        /* Smooth transitions for dropdown menus */
        .dropdown-menu {
            transition: all 0.3s;
        }
        
        /* Fix navbar at top for small screens */
        @media (max-width: 576px) {
            body {
                padding-top: 56px;
            }
            header.bg-dark {
                position: fixed;
                top: 0;
                width: 100%;
                z-index: 1030;
            }
        }
    </style>
</head>
<body>
    <header class="bg-dark text-white">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/quanlykhachsan/index.php">
                    <i class="fas fa-hotel me-2"></i>Quản lý Khách sạn
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <?php if ($logged_in): ?>
                        <!-- Menu cho người dùng đã đăng nhập -->
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="/quanlykhachsan/index.php">
                                    <i class="fas fa-home me-1"></i> Trang chủ
                                </a>
                            </li>
                            
                            <!-- Menu dành cho quản lý -->
                            <?php if ($user_role == 'quản lý'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo strpos($current_page, 'admin') !== false ? 'active' : ''; ?>" href="/quanlykhachsan/admin/index.php">
                                    <i class="fas fa-cogs me-1"></i> Quản trị
                                </a>
                            </li>
                            
                            <!-- Menu quản lý nhân viên - CHỈ HIỂN THỊ CHO QUẢN LÝ -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo strpos($current_page, 'nhan_vien') !== false ? 'active' : ''; ?>" href="#" id="navbarDropdownNhanVien" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-tie me-1"></i> Nhân viên
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/nhan_vien/index.php">Danh sách nhân viên</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/nhan_vien/them.php">Thêm nhân viên</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/nhan_vien/sua.php">Cập nhật thông tin</a></li>
                                    <!-- <li><a class="dropdown-item" href="/quanlykhachsan/admin/nhan_vien/xoa.php">Vô hiệu hóa tài khoản</a></li> -->
                                </ul>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Menu chung cho tất cả nhân viên -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownPhong" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-door-open me-1"></i> Phòng
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/phong/index.php">Danh sách phòng</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/phong/tim_phong_trong.php">Tìm phòng trống</a></li>
                                </ul>
                            </li>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownDatPhong" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-calendar-check me-1"></i> Đặt phòng
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/dat_phong/index.php">Danh sách đặt phòng</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/dat_phong/them.php">Đặt phòng mới</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/dat_phong/nhan_phong.php">Nhận phòng</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/dat_phong/tra_phong.php">Trả phòng</a></li>
                                </ul>
                            </li>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownDichVu" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-concierge-bell me-1"></i> Dịch vụ
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/dich_vu/index.php">Danh sách dịch vụ</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/dich_vu/su_dung_dich_vu.php">Đặt dịch vụ</a></li>
                                </ul>
                            </li>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownKhachHang" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-users me-1"></i> Khách hàng
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/khach_hang/index.php">Danh sách khách hàng</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/khach_hang/them.php">Thêm khách hàng</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/khach_hang/tim_kiem.php">Tìm kiếm khách hàng</a></li>
                                </ul>
                            </li>
                            
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownHoaDon" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-file-invoice-dollar me-1"></i> Hóa đơn
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/hoa_don/index.php">Danh sách hóa đơn</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/hoa_don/thanh_toan.php">Thanh toán</a></li>
                                </ul>
                            </li>
                            
                            <?php if ($user_role == 'quản lý'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownBaoCao" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-chart-bar me-1"></i> Báo cáo
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/bao_cao/doanh_thu.php">Báo cáo doanh thu</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/bao_cao/tinh_trang_phong.php">Tình trạng phòng</a></li>
                                </ul>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- Thông tin người dùng và đăng xuất -->
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/tai_khoan/thong_tin.php">Thông tin tài khoản</a></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/admin/tai_khoan/doi_mat_khau.php">Đổi mật khẩu</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/quanlykhachsan/auth/logout.php">Đăng xuất</a></li>
                                </ul>
                            </li>
                        </ul>
                    <?php else: ?>
                        <!-- Menu cho người dùng chưa đăng nhập -->
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="/quanlykhachsan/index.php">
                                    <i class="fas fa-home me-1"></i> Trang chủ
                                </a>
                            </li>
                        </ul>
                        <ul class="navbar-nav">
                            <li class="nav-item">
                                <a class="nav-link" href="/quanlykhachsan/auth/login.php">
                                    <i class="fas fa-sign-in-alt me-1"></i> Đăng nhập
                                </a>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
    
    <div class="container mt-4">
        <?php
        // Hiển thị thông báo nếu có
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-' . $_SESSION['message_type'] . ' alert-dismissible fade show" role="alert">';
            echo $_SESSION['message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            
            // Xóa thông báo sau khi hiển thị
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
        ?>