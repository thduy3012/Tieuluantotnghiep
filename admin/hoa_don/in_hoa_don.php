<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này.";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    $_SESSION['message'] = "Không tìm thấy hóa đơn.";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/admin/hoa_don/index.php");
    exit();
}

// Truy vấn chi tiết hóa đơn với nhiều thông tin hơn
$sql = "SELECT 
            hd.id, 
            hd.ngay_thanh_toan, 
            hd.tong_tien_phong, 
            hd.tong_tien_dich_vu, 
            hd.tong_thanh_toan, 
            hd.trang_thai,
            dp.ngay_nhan_phong,
            dp.ngay_tra_phong,
            dp.tien_coc,
            p.so_phong,
            p.loai_phong,
            p.gia_ngay,
            kh.ho_ten as ten_khach_hang,
            kh.so_dien_thoai,
            kh.so_cmnd,
            kh.dia_chi,
            nv.ho_ten as ten_nhan_vien
        FROM hoa_don hd
        JOIN dat_phong dp ON hd.id_dat_phong = dp.id
        JOIN phong p ON dp.id_phong = p.id
        JOIN khach_hang kh ON dp.id_khach_hang = kh.id
        JOIN nhan_vien nv ON dp.id_nhan_vien = nv.id
        WHERE hd.id = :invoice_id";

$invoice = fetchSingleRow($sql, ['invoice_id' => $invoice_id]);

if (!$invoice) {
    $_SESSION['message'] = "Không tìm thấy chi tiết hóa đơn.";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/admin/hoa_don/index.php");
    exit();
}

// Đảm bảo giá trị tiền không âm
$invoice['tong_tien_phong'] = abs($invoice['tong_tien_phong']);
$invoice['tong_tien_dich_vu'] = abs($invoice['tong_tien_dich_vu']);
$invoice['tong_thanh_toan'] = abs($invoice['tong_thanh_toan']);
$invoice['tien_coc'] = abs($invoice['tien_coc']);
$invoice['gia_ngay'] = abs($invoice['gia_ngay']);

// Lấy chi tiết dịch vụ
$service_sql = "SELECT 
                dv.ten_dich_vu, 
                sdv.so_luong, 
                dv.gia, 
                sdv.thanh_tien,
                sdv.ngay_su_dung
                FROM su_dung_dich_vu sdv
                JOIN dich_vu dv ON sdv.id_dich_vu = dv.id
                WHERE sdv.id_dat_phong = (SELECT id_dat_phong FROM hoa_don WHERE id = :invoice_id)";
$services = fetchAllRows($service_sql, ['invoice_id' => $invoice_id]);

// Đảm bảo không có giá trị âm trong dịch vụ
foreach ($services as &$service) {
    $service['so_luong'] = abs($service['so_luong']);
    $service['gia'] = abs($service['gia']);
    $service['thanh_tien'] = abs($service['thanh_tien']);
}
unset($service);

// Tính số ngày thuê phòng
$check_in = new DateTime($invoice['ngay_nhan_phong']);
$check_out = new DateTime($invoice['ngay_tra_phong']);
$nights = $check_in->diff($check_out)->days;

// Đảm bảo số đêm không âm
$nights = abs($nights);

// Tính tổng tiền trước khi trừ tiền cọc
$tong_tien_truoc_coc = $invoice['tong_tien_phong'] + $invoice['tong_tien_dich_vu'];

// Tính số tiền còn lại phải thanh toán
$so_tien_con_lai = $tong_tien_truoc_coc - $invoice['tien_coc'];

// Hàm chuyển đổi số thành chữ
function readThreeDigits($number) {
    $units = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    $tens = ['', 'mười', 'hai mươi', 'ba mươi', 'bốn mươi', 'năm mươi', 'sáu mươi', 'bảy mươi', 'tám mươi', 'chín mươi'];
    
    $result = '';
    
    // Hàng trăm
    $hundred = floor($number / 100);
    if ($hundred > 0) {
        $result .= $units[$hundred] . ' trăm ';
        $number -= $hundred * 100;
    }
    
    // Hàng chục và đơn vị
    if ($number > 0) {
        if ($hundred > 0 && $number < 10) {
            $result .= 'lẻ ';
        }
        
        $ten = floor($number / 10);
        $unit = $number % 10;
        
        if ($ten > 0) {
            $result .= $tens[$ten];
            if ($unit > 0) {
                if ($ten == 1) {
                    $result .= ' ' . ($unit == 5 ? 'lăm' : $units[$unit]);
                } else {
                    $result .= ' ' . ($unit == 1 ? 'mốt' : ($unit == 5 ? 'lăm' : $units[$unit]));
                }
            }
        } else {
            $result .= $units[$unit];
        }
    }
    
    return trim($result);
}

function convert_number_to_words($number) {
    // Đảm bảo số không âm khi chuyển đổi
    $number = abs(intval($number));
    
    if ($number == 0) {
        return 'Không đồng';
    }
    
    $result = '';
    
    // Xử lý hàng tỷ
    $billions = floor($number / 1000000000);
    if ($billions > 0) {
        $result .= readThreeDigits($billions) . ' tỷ ';
        $number -= $billions * 1000000000;
    }
    
    // Xử lý hàng triệu
    $millions = floor($number / 1000000);
    if ($millions > 0) {
        $result .= readThreeDigits($millions) . ' triệu ';
        $number -= $millions * 1000000;
    }
    
    // Xử lý hàng nghìn
    $thousands = floor($number / 1000);
    if ($thousands > 0) {
        $result .= readThreeDigits($thousands) . ' nghìn ';
        $number -= $thousands * 1000;
    }
    
    // Xử lý hàng trăm còn lại
    if ($number > 0) {
        $result .= readThreeDigits($number);
    }
    
    return ucfirst(trim($result) . ' đồng');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa Đơn Chi Tiết - Khách Sạn</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }
        
        .invoice-container {
            background-color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .invoice-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .invoice-header h2 {
            color: #007bff;
            font-weight: 700;
        }
        
        .invoice-details {
            margin-bottom: 20px;
        }
        
        .table-services {
            margin-top: 20px;
        }
        
        .total-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .deposit-highlight {
            background-color: #e8f4ff;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            margin: 10px 0;
        }
        
        .final-amount {
            font-size: 1.2em;
            color: #dc3545;
            font-weight: bold;
        }
        
        .print-section {
            margin-top: 20px;
        }
        
        @media print {
            body {
                background-color: white !important;
            }
            .no-print { 
                display: none !important; 
            }
            .invoice-container {
                box-shadow: none;
                margin-top: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="invoice-container">
            <div class="invoice-header text-center">
                <h2>
                    <i class="fas fa-file-invoice me-2"></i>HÓA ĐƠN KHÁCH SẠN
                </h2>
                <p class="text-muted">Mã hóa đơn: #<?php echo $invoice['id']; ?> | Ngày: <?php echo date('d/m/Y', strtotime($invoice['ngay_thanh_toan'])); ?></p>
            </div>
            
            <div class="row invoice-details">
                <div class="col-md-6">
                    <h4 class="text-primary"><i class="fas fa-user me-2"></i>Thông Tin Khách Hàng</h4>
                    <p>
                        <strong>Tên:</strong> <?php echo htmlspecialchars($invoice['ten_khach_hang']); ?><br>
                        <strong>Số điện thoại:</strong> <?php echo htmlspecialchars($invoice['so_dien_thoai']); ?><br>
                        <strong>CMND/CCCD:</strong> <?php echo htmlspecialchars($invoice['so_cmnd'] ?: 'Chưa cung cấp'); ?><br>
                        <strong>Địa chỉ:</strong> <?php echo htmlspecialchars($invoice['dia_chi'] ?: 'Chưa cung cấp'); ?>
                    </p>
                </div>
                
                <div class="col-md-6 text-end">
                    <h4 class="text-primary"><i class="fas fa-bed me-2"></i>Chi Tiết Phòng</h4>
                    <p>
                        <strong>Phòng:</strong> <?php echo htmlspecialchars($invoice['so_phong'] . ' - ' . $invoice['loai_phong']); ?><br>
                        <strong>Ngày nhận phòng:</strong> <?php echo date('d/m/Y', strtotime($invoice['ngay_nhan_phong'])); ?><br>
                        <strong>Ngày trả phòng:</strong> <?php echo date('d/m/Y', strtotime($invoice['ngay_tra_phong'])); ?><br>
                        <strong>Số đêm:</strong> <?php echo $nights; ?>
                    </p>
                </div>
            </div>
            
            <div class="table-responsive table-services">
                <table class="table table-striped">
                    <thead class="table-primary">
                        <tr>
                            <th>Dịch Vụ</th>
                            <th>Ngày Sử Dụng</th>
                            <th>Số Lượng</th>
                            <th>Đơn Giá</th>
                            <th>Thành Tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Tiền Phòng (<?php echo $invoice['loai_phong']; ?>)</td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['ngay_nhan_phong'])); ?> - <?php echo date('d/m/Y', strtotime($invoice['ngay_tra_phong'])); ?></td>
                            <td><?php echo $nights; ?> đêm</td>
                            <td><?php echo number_format($invoice['gia_ngay'], 0, ',', '.'); ?> đ</td>
                            <td><?php echo number_format($invoice['tong_tien_phong'], 0, ',', '.'); ?> đ</td>
                        </tr>
                        <?php foreach($services as $service): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['ten_dich_vu']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($service['ngay_su_dung'])); ?></td>
                            <td><?php echo $service['so_luong']; ?></td>
                            <td><?php echo number_format($service['gia'], 0, ',', '.'); ?> đ</td>
                            <td><?php echo number_format($service['thanh_tien'], 0, ',', '.'); ?> đ</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="total-section">
                <div class="row">
                    <div class="col-md-6">
                        <h4><i class="fas fa-calculator me-2"></i>Bằng Chữ</h4>
                        <p><?php echo convert_number_to_words($so_tien_con_lai); ?></p>
                        
                        <div class="deposit-highlight mt-3">
                            <h5><i class="fas fa-info-circle me-2"></i>Thông Tin Thanh Toán</h5>
                            <p>Phương thức thanh toán: Tiền mặt</p>
                            <p>Thu ngân: <?php echo htmlspecialchars($invoice['ten_nhan_vien']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <h4>Tổng Kết</h4>
                        <p><strong>Tổng tiền phòng:</strong> <?php echo number_format($invoice['tong_tien_phong'], 0, ',', '.'); ?> đ</p>
                        <p><strong>Tổng tiền dịch vụ:</strong> <?php echo number_format($invoice['tong_tien_dich_vu'], 0, ',', '.'); ?> đ</p>
                        <p><strong>Thành tiền:</strong> <?php echo number_format($tong_tien_truoc_coc, 0, ',', '.'); ?> đ</p>
                        
                        <div class="deposit-highlight">
                            <p><strong>Đã cọc trước:</strong> <?php echo number_format($invoice['tien_coc'], 0, ',', '.'); ?> đ</p>
                        </div>
                        
                        <h3 class="text-primary mt-3">
                            <strong>Số tiền cần thanh toán:</strong> 
                            <span class="final-amount"><?php echo number_format($so_tien_con_lai, 0, ',', '.'); ?> đ</span>
                        </h3>
                    </div>
                </div>
            </div>
            
            <div class="text-center print-section no-print">
                <hr>
                <button onclick="window.print()" class="btn btn-primary me-2">
                    <i class="fas fa-print me-2"></i>In Hóa Đơn
                </button>
                <a href="/quanlykhachsan/admin/hoa_don/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Quay Lại
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>