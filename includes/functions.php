<?php
// Khai báo để sử dụng session trong toàn bộ hệ thống
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn gốc của hệ thống
if (!defined('BASE_URL')) {
    define('BASE_URL', '/quanlykhachsan');
}

// // Hàm kiểm tra người dùng đã đăng nhập chưa
// function isLoggedIn() {
//     return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
// }

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}


// Hàm kiểm tra quyền quản trị
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['chuc_vu']) && $_SESSION['chuc_vu'] === 'quản lý';
}

// Hàm chuyển hướng người dùng
function redirect($url) {
    header("Location: $url");
    exit();
}

function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}


// Hàm hiển thị thông báo
function setMessage($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

// Hàm hiển thị thông báo và chuyển hướng
function setMessageAndRedirect($message, $url, $type = 'success') {
    setMessage($message, $type);
    redirect($url);
}

// Hàm hiển thị thông báo
function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'success';
        $alertClass = ($type === 'success') ? 'alert-success' : 'alert-danger';
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo $_SESSION['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        // Xóa thông báo sau khi hiển thị
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Hàm làm sạch dữ liệu đầu vào
function sanitizeInput($input) {
    if (is_array($input)) {
        $output = [];
        foreach ($input as $key => $value) {
            $output[$key] = sanitizeInput($value);
        }
        return $output;
    }
    
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Hàm xác thực dữ liệu đầu vào
function validateInput($input, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        // Kiểm tra trường bắt buộc
        if (strpos($rule, 'required') !== false && (empty($input[$field]) && $input[$field] !== '0')) {
            $errors[$field] = 'Trường này không được để trống';
            continue;
        }
        
        // Bỏ qua các trường không bắt buộc nếu không có giá trị
        if (empty($input[$field]) && $input[$field] !== '0') {
            continue;
        }
        
        // Kiểm tra số điện thoại
        if (strpos($rule, 'phone') !== false) {
            if (!preg_match('/^[0-9]{10,11}$/', $input[$field])) {
                $errors[$field] = 'Số điện thoại không hợp lệ';
            }
        }
        
        // Kiểm tra email
        if (strpos($rule, 'email') !== false) {
            if (!filter_var($input[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Email không hợp lệ';
            }
        }
        
        // Kiểm tra số
        if (strpos($rule, 'numeric') !== false) {
            if (!is_numeric($input[$field])) {
                $errors[$field] = 'Giá trị phải là số';
            }
        }
    }
    
    return $errors;
}

// Hàm lấy thông tin người dùng đang đăng nhập
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (isLoggedIn()) {
            $userId = $_SESSION['user_id'];
            $sql = "SELECT * FROM nhan_vien WHERE id = ?";
            return fetchSingleRow($sql, [$userId]);
        }
        return null;
    }
}

function verifyPassword($inputPassword, $hashedPassword) {
    return password_verify($inputPassword, $hashedPassword);
}

function showMessage($message, $type = 'success') {
    echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>";
    echo htmlspecialchars($message);
    echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
    echo "</div>";
}


// Hàm kiểm tra và xác thực đăng nhập
function loginUser($username, $password) {
    $sql = "SELECT * FROM nhan_vien WHERE ten_dang_nhap = ? AND trang_thai = 1";
    $user = fetchSingleRow($sql, [$username]);
    
    if ($user && password_verify($password, $user['mat_khau'])) {
        // Thiết lập session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['ten_dang_nhap'];
        $_SESSION['ho_ten'] = $user['ho_ten'];
        $_SESSION['chuc_vu'] = $user['chuc_vu'];
        return true;
    }
    
    return false;
}

// Hàm đăng xuất
function logoutUser() {
    session_unset();
    session_destroy();
}

// Hàm đổi mật khẩu
function changePassword($userId, $currentPassword, $newPassword) {
    $sql = "SELECT mat_khau FROM nhan_vien WHERE id = ?";
    $user = fetchSingleRow($sql, [$userId]);
    
    if (!$user || !password_verify($currentPassword, $user['mat_khau'])) {
        return false;
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $sql = "UPDATE nhan_vien SET mat_khau = ? WHERE id = ?";
    
    return executeQuery($sql, [$hashedPassword, $userId]);
}

// Hàm định dạng tiền tệ
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

// Hàm định dạng ngày tháng
function formatDate($date) {
    if (!$date) return '';
    $timestamp = strtotime($date);
    return date('d/m/Y', $timestamp);
}

// Hàm tính số ngày giữa hai ngày
function calculateDays($checkIn, $checkOut) {
    $checkInDate = new DateTime($checkIn);
    $checkOutDate = new DateTime($checkOut);
    $interval = $checkInDate->diff($checkOutDate);
    return $interval->days;
}

// Hàm tính tổng tiền phòng
function calculateRoomTotal($roomPrice, $checkIn, $checkOut) {
    $days = calculateDays($checkIn, $checkOut);
    return $roomPrice * $days;
}

// QUẢN LÝ PHÒNG
// Hàm lấy danh sách tất cả các phòng
function getAllRooms() {
    $sql = "SELECT * FROM phong ORDER BY so_phong";
    return fetchAllRows($sql);
}

// Hàm lấy thông tin phòng theo ID
function getRoomById($roomId) {
    $sql = "SELECT * FROM phong WHERE id = ?";
    return fetchSingleRow($sql, [$roomId]);
}

// Hàm lấy phòng theo số phòng
function getRoomByNumber($roomNumber) {
    $sql = "SELECT * FROM phong WHERE so_phong = ?";
    return fetchSingleRow($sql, [$roomNumber]);
}

// Hàm thêm phòng mới
function addRoom($roomNumber, $roomType, $price) {
    $sql = "INSERT INTO phong (so_phong, loai_phong, gia_ngay, trang_thai) VALUES (?, ?, ?, 'trống')";
    return insertAndGetId($sql, [$roomNumber, $roomType, $price]);
}

// Hàm cập nhật thông tin phòng
function updateRoom($roomId, $roomNumber, $roomType, $price, $status) {
    $sql = "UPDATE phong SET so_phong = ?, loai_phong = ?, gia_ngay = ?, trang_thai = ? WHERE id = ?";
    return executeQuery($sql, [$roomNumber, $roomType, $price, $status, $roomId]);
}

// Hàm cập nhật trạng thái phòng
function updateRoomStatus($roomId, $status) {
    $sql = "UPDATE phong SET trang_thai = ? WHERE id = ?";
    return executeQuery($sql, [$status, $roomId]);
}

// Hàm lấy danh sách phòng theo trạng thái
function getRoomsByStatus($status) {
    $sql = "SELECT * FROM phong WHERE trang_thai = ? ORDER BY so_phong";
    return fetchAllRows($sql, [$status]);
}

// Hàm lấy danh sách phòng trống
function getAvailableRooms($checkIn, $checkOut) {
    $sql = "SELECT * FROM phong WHERE trang_thai = 'trống' 
            AND id NOT IN (
                SELECT id_phong FROM dat_phong 
                WHERE ((ngay_nhan_phong <= ? AND ngay_tra_phong >= ?) 
                OR (ngay_nhan_phong <= ? AND ngay_tra_phong >= ?)
                OR (ngay_nhan_phong >= ? AND ngay_tra_phong <= ?))
                AND trang_thai IN ('đã đặt', 'đã nhận phòng')
            )
            ORDER BY so_phong";
            
    return fetchAllRows($sql, [
        $checkOut, $checkIn, 
        $checkIn, $checkIn, 
        $checkIn, $checkOut
    ]);
}

// QUẢN LÝ KHÁCH HÀNG
// Hàm lấy danh sách tất cả khách hàng
function getAllCustomers() {
    $sql = "SELECT * FROM khach_hang ORDER BY ho_ten";
    return fetchAllRows($sql);
}

// Hàm lấy thông tin khách hàng theo ID
function getCustomerById($customerId) {
    $sql = "SELECT * FROM khach_hang WHERE id = ?";
    return fetchSingleRow($sql, [$customerId]);
}

// Hàm tìm kiếm khách hàng
function searchCustomers($keyword) {
    $sql = "SELECT * FROM khach_hang WHERE ho_ten LIKE ? OR so_dien_thoai LIKE ? OR so_cmnd LIKE ?";
    $param = "%$keyword%";
    return fetchAllRows($sql, [$param, $param, $param]);
}

// Hàm thêm khách hàng mới
function addCustomer($hoTen, $soCmnd, $soDienThoai, $diaChi) {
    $sql = "INSERT INTO khach_hang (ho_ten, so_cmnd, so_dien_thoai, dia_chi) VALUES (?, ?, ?, ?)";
    return insertAndGetId($sql, [$hoTen, $soCmnd, $soDienThoai, $diaChi]);
}

// Hàm cập nhật thông tin khách hàng
function updateCustomer($id, $hoTen, $soCmnd, $soDienThoai, $diaChi) {
    $sql = "UPDATE khach_hang SET ho_ten = ?, so_cmnd = ?, so_dien_thoai = ?, dia_chi = ? WHERE id = ?";
    return executeQuery($sql, [$hoTen, $soCmnd, $soDienThoai, $diaChi, $id]);
}

// QUẢN LÝ ĐẶT PHÒNG
// Hàm lấy thông tin đặt phòng theo ID
function getBookingById($bookingId) {
    $sql = "SELECT dp.*, kh.ho_ten as ten_khach_hang, p.so_phong, p.loai_phong, p.gia_ngay 
            FROM dat_phong dp 
            JOIN khach_hang kh ON dp.id_khach_hang = kh.id 
            JOIN phong p ON dp.id_phong = p.id 
            WHERE dp.id = ?";
    return fetchSingleRow($sql, [$bookingId]);
}

// Hàm lấy danh sách tất cả các đặt phòng
function getAllBookings() {
    $sql = "SELECT dp.*, kh.ho_ten as ten_khach_hang, p.so_phong, p.loai_phong, p.gia_ngay 
            FROM dat_phong dp 
            JOIN khach_hang kh ON dp.id_khach_hang = kh.id 
            JOIN phong p ON dp.id_phong = p.id 
            ORDER BY dp.ngay_nhan_phong DESC";
    return fetchAllRows($sql);
}

// Hàm lấy danh sách đặt phòng theo trạng thái
function getBookingsByStatus($status) {
    $sql = "SELECT dp.*, kh.ho_ten as ten_khach_hang, p.so_phong, p.loai_phong, p.gia_ngay 
            FROM dat_phong dp 
            JOIN khach_hang kh ON dp.id_khach_hang = kh.id 
            JOIN phong p ON dp.id_phong = p.id 
            WHERE dp.trang_thai = ?
            ORDER BY dp.ngay_nhan_phong DESC";
    return fetchAllRows($sql, [$status]);
}

// Hàm đặt phòng mới
function createBooking($idKhachHang, $idPhong, $idNhanVien, $ngayNhanPhong, $ngayTraPhong, $tienCoc) {
    $sql = "INSERT INTO dat_phong (id_khach_hang, id_phong, id_nhan_vien, ngay_nhan_phong, ngay_tra_phong, tien_coc, trang_thai) 
            VALUES (?, ?, ?, ?, ?, ?, 'đã đặt')";
    $bookingId = insertAndGetId($sql, [$idKhachHang, $idPhong, $idNhanVien, $ngayNhanPhong, $ngayTraPhong, $tienCoc]);
    
    if ($bookingId) {
        // Cập nhật trạng thái phòng
        $sql = "UPDATE phong SET trang_thai = 'đã đặt' WHERE id = ?";
        executeQuery($sql, [$idPhong]);
    }
    
    return $bookingId;
}
