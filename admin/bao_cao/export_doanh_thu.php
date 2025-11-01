<?php
// Bắt đầu phiên làm việc
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Chuyển hướng về trang đăng nhập
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Kiểm tra quyền quản lý
if ($_SESSION['role'] !== 'quản lý') {
    $_SESSION['message'] = "Bạn không có quyền truy cập trang này!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/index.php");
    exit();
}

// Import file cấu hình
require_once __DIR__ . '/../../config/config.php';

// Lấy tham số từ URL
$ngay_bat_dau = isset($_GET['ngay_bat_dau']) ? $_GET['ngay_bat_dau'] : date('Y-m-01');
$ngay_ket_thuc = isset($_GET['ngay_ket_thuc']) ? $_GET['ngay_ket_thuc'] : date('Y-m-t');
$loai_bao_cao = isset($_GET['loai_bao_cao']) ? $_GET['loai_bao_cao'] : "ngay";

// Xây dựng câu truy vấn dựa trên loại báo cáo
$group_by = "";
$date_format = "";
$select_date = "";

switch ($loai_bao_cao) {
    case 'ngay':
        $group_by = "GROUP BY ngay";
        $date_format = "%Y-%m-%d";
        $select_date = "DATE(h.ngay_thanh_toan) AS ngay";
        break;
    case 'thang':
        $group_by = "GROUP BY nam, thang";
        $date_format = "%Y-%m";
        $select_date = "YEAR(h.ngay_thanh_toan) AS nam, MONTH(h.ngay_thanh_toan) AS thang";
        break;
    case 'quy':
        $group_by = "GROUP BY nam, quy";
        $date_format = "%Y-Q%q";
        $select_date = "YEAR(h.ngay_thanh_toan) AS nam, QUARTER(h.ngay_thanh_toan) AS quy";
        break;
    case 'nam':
        $group_by = "GROUP BY nam";
        $date_format = "%Y";
        $select_date = "YEAR(h.ngay_thanh_toan) AS nam";
        break;
}

// Truy vấn báo cáo doanh thu - Sử dụng ABS() để đảm bảo giá trị không âm và thêm tiền cọc
$sql = "
    SELECT 
        $select_date,
        DATE_FORMAT(h.ngay_thanh_toan, '$date_format') AS thoi_gian,
        COUNT(h.id) AS so_luong_hoa_don,
        ABS(SUM(h.tong_tien_phong)) AS tong_tien_phong,
        ABS(SUM(h.tong_tien_dich_vu)) AS tong_tien_dich_vu,
        ABS(SUM(dp.tien_coc)) AS tong_tien_coc,
        ABS(SUM(h.tong_thanh_toan)) AS tong_thanh_toan_hd,
        (ABS(SUM(h.tong_thanh_toan)) + ABS(SUM(dp.tien_coc))) AS tong_doanh_thu
    FROM 
        hoa_don h
    JOIN 
        dat_phong dp ON h.id_dat_phong = dp.id
    WHERE 
        h.ngay_thanh_toan BETWEEN :ngay_bat_dau AND :ngay_ket_thuc
        AND h.trang_thai = 'đã thanh toán'
    $group_by
    ORDER BY 
        h.ngay_thanh_toan
";

$params = [
    ':ngay_bat_dau' => $ngay_bat_dau,
    ':ngay_ket_thuc' => $ngay_ket_thuc
];

$bao_cao_doanh_thu = fetchAllRows($sql, $params);

// Tính tổng doanh thu
$tong_so_hoa_don = 0;
$tong_tien_phong = 0;
$tong_tien_dich_vu = 0;
$tong_tien_coc = 0;
$tong_thanh_toan_hd = 0;
$tong_doanh_thu = 0;

if ($bao_cao_doanh_thu) {
    foreach ($bao_cao_doanh_thu as $item) {
        $tong_so_hoa_don += abs($item['so_luong_hoa_don']);
        $tong_tien_phong += abs($item['tong_tien_phong']);
        $tong_tien_dich_vu += abs($item['tong_tien_dich_vu']);
        $tong_tien_coc += abs($item['tong_tien_coc']);
        $tong_thanh_toan_hd += abs($item['tong_thanh_toan_hd']);
        $tong_doanh_thu += abs($item['tong_doanh_thu']);
    }
}

// Thống kê doanh thu theo loại phòng - Sử dụng ABS() để đảm bảo giá trị không âm và thêm tiền cọc
$sql_theo_loai_phong = "
    SELECT 
        p.loai_phong,
        COUNT(h.id) AS so_luong_hoa_don,
        ABS(SUM(h.tong_tien_phong)) AS tong_tien_phong,
        ABS(SUM(h.tong_tien_dich_vu)) AS tong_tien_dich_vu,
        ABS(SUM(dp.tien_coc)) AS tong_tien_coc,
        ABS(SUM(h.tong_thanh_toan)) AS tong_thanh_toan_hd,
        (ABS(SUM(h.tong_thanh_toan)) + ABS(SUM(dp.tien_coc))) AS tong_doanh_thu
    FROM 
        hoa_don h
    JOIN 
        dat_phong dp ON h.id_dat_phong = dp.id
    JOIN 
        phong p ON dp.id_phong = p.id
    WHERE 
        h.ngay_thanh_toan BETWEEN :ngay_bat_dau AND :ngay_ket_thuc
        AND h.trang_thai = 'đã thanh toán'
    GROUP BY 
        p.loai_phong
    ORDER BY 
        tong_doanh_thu DESC
";

$thong_ke_theo_loai_phong = fetchAllRows($sql_theo_loai_phong, $params);

// Thống kê doanh thu theo dịch vụ - Sử dụng ABS() để đảm bảo giá trị không âm
$sql_theo_dich_vu = "
    SELECT 
        dv.ten_dich_vu,
        COUNT(sddv.id) AS so_luong_su_dung,
        ABS(SUM(sddv.thanh_tien)) AS tong_tien_dich_vu
    FROM 
        su_dung_dich_vu sddv
    JOIN 
        dich_vu dv ON sddv.id_dich_vu = dv.id
    JOIN 
        dat_phong dp ON sddv.id_dat_phong = dp.id
    JOIN 
        hoa_don h ON h.id_dat_phong = dp.id
    WHERE 
        h.ngay_thanh_toan BETWEEN :ngay_bat_dau AND :ngay_ket_thuc
        AND h.trang_thai = 'đã thanh toán'
    GROUP BY 
        dv.id
    ORDER BY 
        tong_tien_dich_vu DESC
";

$thong_ke_theo_dich_vu = fetchAllRows($sql_theo_dich_vu, $params);

// Định dạng tên file CSV
$date_range = date('d-m-Y', strtotime($ngay_bat_dau)) . '_den_' . date('d-m-Y', strtotime($ngay_ket_thuc));
$filename = "bao_cao_doanh_thu_" . $loai_bao_cao . "_" . $date_range . ".csv";

// Thiết lập header cho file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Tạo file handle để ghi trực tiếp vào output
$output = fopen('php://output', 'w');

// Thêm BOM để Excel nhận dạng đúng ký tự UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Thêm tiêu đề báo cáo
fputcsv($output, array('BÁO CÁO DOANH THU KHÁCH SẠN'));
fputcsv($output, array('Loại báo cáo: ' . ucfirst($loai_bao_cao)));
fputcsv($output, array('Từ ngày: ' . date('d/m/Y', strtotime($ngay_bat_dau)) . ' đến ngày: ' . date('d/m/Y', strtotime($ngay_ket_thuc))));
fputcsv($output, array('')); // Thêm dòng trống

// Thêm thông tin tổng quan
fputcsv($output, array('TỔNG QUAN DOANH THU'));
fputcsv($output, array('Tổng số hóa đơn', number_format($tong_so_hoa_don)));
fputcsv($output, array('Tổng tiền phòng', number_format($tong_tien_phong) . ' VNĐ'));
fputcsv($output, array('Tổng tiền dịch vụ', number_format($tong_tien_dich_vu) . ' VNĐ'));
fputcsv($output, array('Tổng tiền cọc', number_format($tong_tien_coc) . ' VNĐ'));
fputcsv($output, array('Tổng thanh toán', number_format($tong_thanh_toan_hd) . ' VNĐ'));
fputcsv($output, array('Tổng doanh thu', number_format($tong_doanh_thu) . ' VNĐ'));
fputcsv($output, array('')); // Thêm dòng trống

// Doanh thu theo thời gian
fputcsv($output, array('DOANH THU THEO THỜI GIAN'));
// Tiêu đề các cột
$header = array('Thời gian', 'Số lượng hóa đơn', 'Tiền phòng (VNĐ)', 'Tiền dịch vụ (VNĐ)', 'Tiền cọc (VNĐ)', 'Tiền thanh toán (VNĐ)', 'Tổng doanh thu (VNĐ)');
fputcsv($output, $header);

// Dữ liệu từng dòng
if (!empty($bao_cao_doanh_thu)) {
    foreach ($bao_cao_doanh_thu as $item) {
        // Định dạng thời gian dựa trên loại báo cáo
        $thoi_gian = '';
        if ($loai_bao_cao == 'thang') {
            $thoi_gian = 'Tháng ' . $item['thang'] . '/' . $item['nam'];
        } elseif ($loai_bao_cao == 'quy') {
            $thoi_gian = 'Quý ' . $item['quy'] . '/' . $item['nam'];
        } elseif ($loai_bao_cao == 'nam') {
            $thoi_gian = 'Năm ' . $item['nam'];
        } else {
            $thoi_gian = date('d/m/Y', strtotime($item['thoi_gian']));
        }

        $row = array(
            $thoi_gian,
            number_format($item['so_luong_hoa_don']),
            number_format($item['tong_tien_phong']),
            number_format($item['tong_tien_dich_vu']),
            number_format($item['tong_tien_coc']),
            number_format($item['tong_thanh_toan_hd']),
            number_format($item['tong_doanh_thu'])
        );
        fputcsv($output, $row);
    }
}

// Thêm dòng tổng cộng
$total_row = array(
    'Tổng cộng',
    number_format($tong_so_hoa_don),
    number_format($tong_tien_phong),
    number_format($tong_tien_dich_vu),
    number_format($tong_tien_coc),
    number_format($tong_thanh_toan_hd),
    number_format($tong_doanh_thu)
);
fputcsv($output, $total_row);
fputcsv($output, array('')); // Thêm dòng trống

// Thống kê theo loại phòng
fputcsv($output, array('THỐNG KÊ DOANH THU THEO LOẠI PHÒNG'));
// Tiêu đề các cột
$header = array('Loại phòng', 'Số lượng hóa đơn', 'Tiền phòng (VNĐ)', 'Tiền dịch vụ (VNĐ)', 'Tiền cọc (VNĐ)', 'Tiền thanh toán (VNĐ)', 'Tổng doanh thu (VNĐ)', 'Tỷ lệ (%)');
fputcsv($output, $header);

// Dữ liệu từng dòng
if (!empty($thong_ke_theo_loai_phong)) {
    foreach ($thong_ke_theo_loai_phong as $item) {
        // Định dạng tên loại phòng
        $loai_phong = '';
        switch ($item['loai_phong']) {
            case 'đơn':
                $loai_phong = 'Phòng đơn';
                break;
            case 'đôi':
                $loai_phong = 'Phòng đôi';
                break;
            case 'vip':
                $loai_phong = 'Phòng VIP';
                break;
            default:
                $loai_phong = $item['loai_phong'];
        }

        $row = array(
            $loai_phong,
            number_format($item['so_luong_hoa_don']),
            number_format($item['tong_tien_phong']),
            number_format($item['tong_tien_dich_vu']),
            number_format($item['tong_tien_coc']),
            number_format($item['tong_thanh_toan_hd']),
            number_format($item['tong_doanh_thu']),
            number_format(($tong_doanh_thu > 0 ? ($item['tong_doanh_thu'] / $tong_doanh_thu) * 100 : 0), 2)
        );
        fputcsv($output, $row);
    }
}
fputcsv($output, array('')); // Thêm dòng trống

// Thống kê theo dịch vụ
fputcsv($output, array('THỐNG KÊ DOANH THU THEO DỊCH VỤ'));
// Tiêu đề các cột
$header = array('Tên dịch vụ', 'Số lần sử dụng', 'Tổng doanh thu (VNĐ)', 'Tỷ lệ (%)');
fputcsv($output, $header);

// Dữ liệu từng dòng
if (!empty($thong_ke_theo_dich_vu)) {
    foreach ($thong_ke_theo_dich_vu as $item) {
        $row = array(
            $item['ten_dich_vu'],
            number_format($item['so_luong_su_dung']),
            number_format($item['tong_tien_dich_vu']),
            number_format(($tong_tien_dich_vu > 0 ? ($item['tong_tien_dich_vu'] / $tong_tien_dich_vu) * 100 : 0), 2)
        );
        fputcsv($output, $row);
    }
}

// Thêm dòng cuối cùng với thông tin thời gian xuất file
fputcsv($output, array('')); // Thêm dòng trống
fputcsv($output, array('Xuất báo cáo vào: ' . date('d/m/Y H:i:s')));

// Lấy thông tin người xuất báo cáo từ phiên đăng nhập (sử dụng user_id thay vì ten_nguoi_dung)
$user_info = "Người xuất báo cáo: Quản lý (ID: " . $_SESSION['user_id'] . ")";
fputcsv($output, array($user_info));

// Đóng file handle
fclose($output);
exit;
?>