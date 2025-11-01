<?php
// Bắt đầu session
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Import config
require_once __DIR__ . '/../../config/config.php';

// Kiểm tra có id được truyền vào không
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID parameter']);
    exit;
}

$id_dat_phong = $_GET['id'];

// Lấy thông tin đặt phòng
$sql_dat_phong = "SELECT dp.*, 
                kh.id as id_khach_hang,
                kh.ho_ten,
                kh.so_cmnd,
                kh.so_dien_thoai,
                kh.dia_chi,
                p.id as id_phong,
                p.so_phong,
                p.loai_phong,
                p.gia_ngay
                FROM dat_phong dp 
                JOIN khach_hang kh ON dp.id_khach_hang = kh.id 
                JOIN phong p ON dp.id_phong = p.id 
                WHERE dp.id = :id_dat_phong";

$dat_phong = fetchSingleRow($sql_dat_phong, [':id_dat_phong' => $id_dat_phong]);

if (!$dat_phong) {
    http_response_code(404);
    echo json_encode(['error' => 'Booking not found']);
    exit;
}

// Lấy thông tin dịch vụ đã sử dụng
$sql_dich_vu = "SELECT sddv.*, 
                dv.ten_dich_vu,
                dv.gia
                FROM su_dung_dich_vu sddv 
                JOIN dich_vu dv ON sddv.id_dich_vu = dv.id 
                WHERE sddv.id_dat_phong = :id_dat_phong
                ORDER BY sddv.ngay_su_dung";

$dich_vu = fetchAllRows($sql_dich_vu, [':id_dat_phong' => $id_dat_phong]);

// Chuẩn bị dữ liệu trả về
$result = [
    'dat_phong' => [
        'id' => $dat_phong['id'],
        'ngay_nhan_phong' => $dat_phong['ngay_nhan_phong'],
        'ngay_tra_phong' => $dat_phong['ngay_tra_phong'],
        'tien_coc' => $dat_phong['tien_coc'],
        'trang_thai' => $dat_phong['trang_thai']
    ],
    'khach_hang' => [
        'id' => $dat_phong['id_khach_hang'],
        'ho_ten' => $dat_phong['ho_ten'],
        'so_cmnd' => $dat_phong['so_cmnd'],
        'so_dien_thoai' => $dat_phong['so_dien_thoai'],
        'dia_chi' => $dat_phong['dia_chi']
    ],
    'phong' => [
        'id' => $dat_phong['id_phong'],
        'so_phong' => $dat_phong['so_phong'],
        'loai_phong' => $dat_phong['loai_phong'],
        'gia_ngay' => $dat_phong['gia_ngay']
    ],
    'dich_vu' => $dich_vu
];

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode($result);
exit;