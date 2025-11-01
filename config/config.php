<?php
// Cấu hình kết nối database
define('DB_HOST', 'localhost');     // Địa chỉ máy chủ MySQL
define('DB_USERNAME', 'root');       // Tên đăng nhập MySQL
define('DB_PASSWORD', '');           // Mật khẩu MySQL (để trống nếu không có mật khẩu)
define('DB_NAME', 'quanlykhachsan'); // Tên cơ sở dữ liệu

// Biến kết nối toàn cục
$conn = null;

// Hàm kết nối database
function getDatabaseConnection() {
    global $conn;
    if ($conn === null) {
        try {
            // Tạo kết nối PDO
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
            
            // Thiết lập chế độ báo lỗi
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Đảm bảo kết nối sử dụng UTF-8
            $conn->exec("SET NAMES utf8mb4");
        } catch(PDOException $e) {
            // Hiển thị lỗi kết nối (trong môi trường thực tế, nên ghi log thay vì hiển thị trực tiếp)
            die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
        }
    }
    return $conn;
}

// Hàm đóng kết nối
function closeConnection() {
    global $conn;
    $conn = null;
}

// Hàm tiện ích để chạy truy vấn
function executeQuery($sql, $params = []) {
    $conn = getDatabaseConnection();
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        // Xử lý lỗi truy vấn
        error_log("Lỗi truy vấn: " . $e->getMessage());
        return false;
    }
}

// Hàm lấy một dòng dữ liệu
function fetchSingleRow($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
}

// Hàm lấy nhiều dòng dữ liệu
function fetchAllRows($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
}

// Hàm chèn dữ liệu và trả về ID mới
function insertAndGetId($sql, $params = []) {
    $conn = getDatabaseConnection();
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $conn->lastInsertId();
    } catch(PDOException $e) {
        error_log("Lỗi chèn dữ liệu: " . $e->getMessage());
        return false;
    }
}

// Gọi hàm kết nối khi include file
getDatabaseConnection();