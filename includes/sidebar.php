<?php
// Không cần gọi session_start() ở đây vì đã được gọi trong index.php
// session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

// Lấy thông tin người dùng hiện tại
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; // Thay 'user_role' bằng 'role'
$user_name = $_SESSION['fullname']; // Thay 'user_name' bằng 'fullname'

// Xác định trang hiện tại để đánh dấu menu đang active
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = dirname($_SERVER['PHP_SELF']);

// Hàm kiểm tra trang hiện tại để thêm class active
function isActive($page_name, $dir_name = null) {
    global $current_page, $current_dir;
    
    if ($dir_name !== null) {
        return (strpos($current_dir, $dir_name) !== false) ? 'active' : '';
    }
    
    return ($current_page == $page_name) ? 'active' : '';
}
?>
<style>
/* Sidebar */
.sidebar {
    width: 250px;
    height: 100vh;
    background: #2c3e50;
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    padding: 15px;
    overflow-y: auto;
}

.sidebar-header {
    font-size: 20px;
    font-weight: bold;
    text-align: center;
    padding: 10px 0;
    border-bottom: 1px solid #34495e;
}

.sidebar-user {
    padding: 15px;
    border-bottom: 1px solid #34495e;
    text-align: center;
}

.user-info .user-name {
    font-weight: bold;
}

.user-info .user-role {
    font-size: 14px;
    color: #bdc3c7;
}

/* Sidebar navigation */
.sidebar-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-nav ul li {
    padding: 10px;
}

.sidebar-nav ul li a {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-nav ul li a:hover,
.sidebar-nav ul li.active > a {
    background: #34495e;
    padding: 10px;
    border-radius: 5px;
}

/* Dropdown menu */
.sidebar-nav ul .collapse {
    display: none;
    padding-left: 15px;
}

.sidebar-nav ul .collapse.show {
    display: block;
}

.sidebar-nav .dropdown-toggle::after {
    content: " ▼";
    font-size: 12px;
    float: right;
}

.sidebar-nav .dropdown-toggle[aria-expanded="true"]::after {
    content: " ▲";
}
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h3>Quản Lý Khách Sạn</h3>
    </div>
    
    <div class="sidebar-user">
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($user_role); ?></span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <!-- Dashboard -->
            <li class="<?php echo isActive('index'); ?>">
                <a href="/index.php">
                    <i class="fas fa-tachometer-alt"></i> Trang chủ
                </a>
            </li>

            <!-- Quản lý đặt phòng -->
            <li class="<?php echo isActive('', 'dat_phong'); ?>">
                <a href="#datPhongSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-calendar-check"></i> Đặt phòng
                </a>
                <ul class="collapse <?php echo isActive('', 'dat_phong') ? 'show' : ''; ?>" id="datPhongSubmenu">
                    <li class="<?php echo isActive('tim_phong'); ?>">
                        <a href="/dat_phong/tim_phong.php">Tìm phòng trống</a>
                    </li>
                    <li class="<?php echo isActive('dat_phong'); ?>">
                        <a href="/dat_phong/dat_phong.php">Đặt phòng mới</a>
                    </li>
                    <li class="<?php echo isActive('danh_sach'); ?>">
                        <a href="/dat_phong/danh_sach.php">Danh sách đặt phòng</a>
                    </li>
                    <li class="<?php echo isActive('nhan_phong'); ?>">
                        <a href="/dat_phong/nhan_phong.php">Nhận phòng</a>
                    </li>
                    <li class="<?php echo isActive('tra_phong'); ?>">
                        <a href="/dat_phong/tra_phong.php">Trả phòng</a>
                    </li>
                </ul>
            </li>

            <!-- Quản lý khách hàng -->
            <li class="<?php echo isActive('', 'khach_hang'); ?>">
                <a href="#khachHangSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-users"></i> Khách hàng
                </a>
                <ul class="collapse <?php echo isActive('', 'khach_hang') ? 'show' : ''; ?>" id="khachHangSubmenu">
                    <li class="<?php echo isActive('danh_sach'); ?>">
                        <a href="/khach_hang/danh_sach.php">Danh sách khách hàng</a>
                    </li>
                    <li class="<?php echo isActive('them'); ?>">
                        <a href="/khach_hang/them.php">Thêm khách hàng</a>
                    </li>
                    <li class="<?php echo isActive('tim_kiem'); ?>">
                        <a href="/khach_hang/tim_kiem.php">Tìm kiếm khách hàng</a>
                    </li>
                </ul>
            </li>

            <!-- Quản lý phòng -->
            <li class="<?php echo isActive('', 'phong'); ?>">
                <a href="#phongSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-door-closed"></i> Phòng
                </a>
                <ul class="collapse <?php echo isActive('', 'phong') ? 'show' : ''; ?>" id="phongSubmenu">
                    <li class="<?php echo isActive('danh_sach'); ?>">
                        <a href="/phong/danh_sach.php">Danh sách phòng</a>
                    </li>
                    <li class="<?php echo isActive('trang_thai'); ?>">
                        <a href="/phong/trang_thai.php">Trạng thái phòng</a>
                    </li>
                    <?php if ($user_role == 'quản lý'): ?>
                    <li class="<?php echo isActive('them'); ?>">
                        <a href="/phong/them.php">Thêm phòng</a>
                    </li>
                    <li class="<?php echo isActive('sua'); ?>">
                        <a href="/phong/sua.php">Sửa thông tin phòng</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>

            <!-- Quản lý dịch vụ -->
            <li class="<?php echo isActive('', 'dich_vu'); ?>">
                <a href="#dichVuSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-concierge-bell"></i> Dịch vụ
                </a>
                <ul class="collapse <?php echo isActive('', 'dich_vu') ? 'show' : ''; ?>" id="dichVuSubmenu">
                    <li class="<?php echo isActive('danh_sach'); ?>">
                        <a href="/dich_vu/danh_sach.php">Danh sách dịch vụ</a>
                    </li>
                    <li class="<?php echo isActive('dat_dich_vu'); ?>">
                        <a href="/dich_vu/dat_dich_vu.php">Đặt dịch vụ</a>
                    </li>
                    <?php if ($user_role == 'quản lý'): ?>
                    <li class="<?php echo isActive('them'); ?>">
                        <a href="/dich_vu/them.php">Thêm dịch vụ</a>
                    </li>
                    <li class="<?php echo isActive('sua'); ?>">
                        <a href="/dich_vu/sua.php">Sửa dịch vụ</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>

            <!-- Thanh toán -->
            <li class="<?php echo isActive('', 'thanh_toan'); ?>">
                <a href="#thanhToanSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-file-invoice-dollar"></i> Thanh toán
                </a>
                <ul class="collapse <?php echo isActive('', 'thanh_toan') ? 'show' : ''; ?>" id="thanhToanSubmenu">
                    <li class="<?php echo isActive('tao_hoa_don'); ?>">
                        <a href="/thanh_toan/tao_hoa_don.php">Tạo hóa đơn</a>
                    </li>
                    <li class="<?php echo isActive('danh_sach'); ?>">
                        <a href="/thanh_toan/danh_sach.php">Danh sách hóa đơn</a>
                    </li>
                </ul>
            </li>

            <!-- Báo cáo - Chỉ hiển thị cho quản lý -->
            <?php if ($user_role == 'quản lý'): ?>
            <li class="<?php echo isActive('', 'bao_cao'); ?>">
                <a href="#baoCaoSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-chart-bar"></i> Báo cáo
                </a>
                <ul class="collapse <?php echo isActive('', 'bao_cao') ? 'show' : ''; ?>" id="baoCaoSubmenu">
                    <li class="<?php echo isActive('doanh_thu'); ?>">
                        <a href="/bao_cao/doanh_thu.php">Báo cáo doanh thu</a>
                    </li>
                    <li class="<?php echo isActive('phong'); ?>">
                        <a href="/bao_cao/phong.php">Báo cáo tình trạng phòng</a>
                    </li>
                </ul>
            </li>

            <!-- Quản lý nhân viên - Chỉ hiển thị cho quản lý -->
            <li class="<?php echo isActive('', 'admin/nhan_vien'); ?>">
                <a href="#nhanVienSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-user-tie"></i> Nhân viên
                </a>
                <ul class="collapse <?php echo isActive('', 'admin/nhan_vien') ? 'show' : ''; ?>" id="nhanVienSubmenu">
                    <li class="<?php echo isActive('index'); ?>">
                        <a href="/admin/nhan_vien/index.php">Danh sách nhân viên</a>
                    </li>
                    <li class="<?php echo isActive('them'); ?>">
                        <a href="/admin/nhan_vien/them.php">Thêm nhân viên</a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Quản lý tài khoản cá nhân -->
            <li class="<?php echo isActive('', 'admin/tai_khoan'); ?>">
                <a href="#taiKhoanSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                    <i class="fas fa-user-cog"></i> Tài khoản
                </a>
                <ul class="collapse <?php echo isActive('', 'admin/tai_khoan') ? 'show' : ''; ?>" id="taiKhoanSubmenu">
                    <li class="<?php echo isActive('thong_tin'); ?>">
                        <a href="/admin/tai_khoan/thong_tin.php">Thông tin cá nhân</a>
                    </li>
                    <li class="<?php echo isActive('doi_mat_khau'); ?>">
                        <a href="/admin/tai_khoan/doi_mat_khau.php">Đổi mật khẩu</a>
                    </li>
                </ul>
            </li>

            <!-- Đăng xuất -->
            <li>
                <a href="/auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </li>
        </ul>
    </nav>
</div>

<!-- Script để xử lý dropdown menu - sử dụng jQuery nếu đã được include -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if jQuery is loaded
    if (typeof jQuery !== 'undefined') {
        $('.dropdown-toggle').on('click', function() {
            const submenu = $(this).next('.collapse');
            submenu.toggleClass('show');
        });
    } else {
        // Vanilla JS fallback
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(function(dropdown) {
            dropdown.addEventListener('click', function(e) {
                e.preventDefault();
                const submenu = this.nextElementSibling;
                submenu.classList.toggle('show');
            });
        });
    }
});
</script>