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

// Import file cấu hình và header
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Xử lý lọc dữ liệu
$ngay_bat_dau = date('Y-m-01'); // Mặc định là ngày đầu tháng hiện tại
$ngay_ket_thuc = date('Y-m-t'); // Mặc định là ngày cuối tháng hiện tại
$loai_bao_cao = "ngay"; // Mặc định báo cáo theo ngày

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ngay_bat_dau = isset($_POST['ngay_bat_dau']) ? $_POST['ngay_bat_dau'] : $ngay_bat_dau;
    $ngay_ket_thuc = isset($_POST['ngay_ket_thuc']) ? $_POST['ngay_ket_thuc'] : $ngay_ket_thuc;
    $loai_bao_cao = isset($_POST['loai_bao_cao']) ? $_POST['loai_bao_cao'] : $loai_bao_cao;
}

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
        $tong_so_hoa_don += abs($item['so_luong_hoa_don']); // Đảm bảo không âm
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
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Báo cáo doanh thu</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/quanlykhachsan/admin/index.php">Trang quản trị</a></li>
        <li class="breadcrumb-item active">Báo cáo doanh thu</li>
    </ol>

    <!-- Form lọc dữ liệu -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Lọc dữ liệu
        </div>
        <div class="card-body">
            <form method="post" action="" id="form-filter" class="row g-3">
                <div class="col-md-3">
                    <label for="loai_bao_cao" class="form-label">Loại báo cáo</label>
                    <select class="form-select" name="loai_bao_cao" id="loai_bao_cao">
                        <option value="ngay" <?php echo $loai_bao_cao == 'ngay' ? 'selected' : ''; ?>>Theo ngày</option>
                        <option value="thang" <?php echo $loai_bao_cao == 'thang' ? 'selected' : ''; ?>>Theo tháng</option>
                        <option value="quy" <?php echo $loai_bao_cao == 'quy' ? 'selected' : ''; ?>>Theo quý</option>
                        <option value="nam" <?php echo $loai_bao_cao == 'nam' ? 'selected' : ''; ?>>Theo năm</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ngay_bat_dau" class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" id="ngay_bat_dau" name="ngay_bat_dau" value="<?php echo $ngay_bat_dau; ?>">
                </div>
                <div class="col-md-3">
                    <label for="ngay_ket_thuc" class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc" value="<?php echo $ngay_ket_thuc; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Lọc dữ liệu
                    </button>
                    <button type="button" id="btn-export" class="btn btn-success">
                        <i class="fas fa-file-export me-1"></i> Xuất Excel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tổng quan doanh thu -->
    <div class="row">
        <div class="col-xl-2 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format(abs($tong_so_hoa_don)); ?></h4>
                    <div>Tổng số hóa đơn</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format(abs($tong_tien_phong)); ?> VNĐ</h4>
                    <div>Tổng tiền phòng</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format(abs($tong_tien_dich_vu)); ?> VNĐ</h4>
                    <div>Tổng tiền dịch vụ</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format(abs($tong_tien_coc)); ?> VNĐ</h4>
                    <div>Tổng tiền cọc</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-6">
            <div class="card bg-secondary text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format(abs($tong_thanh_toan_hd)); ?> VNĐ</h4>
                    <div>Tổng thanh toán</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <h4><?php echo number_format(abs($tong_doanh_thu)); ?> VNĐ</h4>
                    <div>Tổng doanh thu</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Báo cáo doanh thu theo thời gian -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-line me-1"></i>
            Doanh thu theo thời gian
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTable">
                    <thead> 
                        <tr>
                            <th>Thời gian</th>
                            <th>Số lượng hóa đơn</th>
                            <th>Tiền phòng</th>
                            <th>Tiền dịch vụ</th>
                            <th>Tiền cọc</th>
                            <th>Tiền thanh toán</th>
                            <th>Tổng doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bao_cao_doanh_thu)): ?>
                            <?php foreach ($bao_cao_doanh_thu as $item): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        if ($loai_bao_cao == 'thang') {
                                            echo 'Tháng ' . $item['thang'] . '/' . $item['nam'];
                                        } elseif ($loai_bao_cao == 'quy') {
                                            echo 'Quý ' . $item['quy'] . '/' . $item['nam'];
                                        } elseif ($loai_bao_cao == 'nam') {
                                            echo 'Năm ' . $item['nam'];
                                        } else {
                                            echo date('d/m/Y', strtotime($item['thoi_gian']));
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo number_format(abs($item['so_luong_hoa_don'])); ?></td>
                                    <td><?php echo number_format(abs($item['tong_tien_phong'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_tien_dich_vu'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_tien_coc'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_thanh_toan_hd'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_doanh_thu'])); ?> VNĐ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Không có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Tổng cộng</td>
                            <td><?php echo number_format(abs($tong_so_hoa_don)); ?></td>
                            <td><?php echo number_format(abs($tong_tien_phong)); ?> VNĐ</td>
                            <td><?php echo number_format(abs($tong_tien_dich_vu)); ?> VNĐ</td>
                            <td><?php echo number_format(abs($tong_tien_coc)); ?> VNĐ</td>
                            <td><?php echo number_format(abs($tong_thanh_toan_hd)); ?> VNĐ</td>
                            <td><?php echo number_format(abs($tong_doanh_thu)); ?> VNĐ</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Biểu đồ doanh thu -->
    <div class="row">
        <!-- Biểu đồ doanh thu theo thời gian -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    Biểu đồ doanh thu
                </div>
                <div class="card-body">
                    <canvas id="myBarChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>

        <!-- Biểu đồ tỷ lệ doanh thu theo loại phòng -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Tỷ lệ doanh thu theo loại phòng
                </div>
                <div class="card-body">
                    <canvas id="myPieChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Thống kê theo loại phòng -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-bed me-1"></i>
            Thống kê doanh thu theo loại phòng
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Loại phòng</th>
                            <th>Số lượng hóa đơn</th>
                            <th>Tiền phòng</th>
                            <th>Tiền dịch vụ</th>
                            <th>Tiền cọc</th>
                            <th>Tiền thanh toán</th>
                            <th>Tổng doanh thu</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($thong_ke_theo_loai_phong)): ?>
                            <?php foreach ($thong_ke_theo_loai_phong as $item): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        switch ($item['loai_phong']) {
                                            case 'đơn':
                                                echo 'Phòng đơn';
                                                break;
                                            case 'đôi':
                                                echo 'Phòng đôi';
                                                break;
                                            case 'vip':
                                                echo 'Phòng VIP';
                                                break;
                                            default:
                                                echo $item['loai_phong'];
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo number_format(abs($item['so_luong_hoa_don'])); ?></td>
                                    <td><?php echo number_format(abs($item['tong_tien_phong'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_tien_dich_vu'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_tien_coc'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_thanh_toan_hd'])); ?> VNĐ</td>
                                    <td><?php echo number_format(abs($item['tong_doanh_thu'])); ?> VNĐ</td>
                                    <td>
                                        <?php echo $tong_doanh_thu > 0 ? number_format((abs($item['tong_doanh_thu']) / abs($tong_doanh_thu)) * 100, 2) : 0; ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Không có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Thống kê doanh thu theo dịch vụ -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-concierge-bell me-1"></i>
            Thống kê doanh thu theo dịch vụ
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Tên dịch vụ</th>
                            <th>Số lần sử dụng</th>
                            <th>Tổng doanh thu</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($thong_ke_theo_dich_vu)): ?>
                            <?php foreach ($thong_ke_theo_dich_vu as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['ten_dich_vu']); ?></td>
                                    <td><?php echo number_format(abs($item['so_luong_su_dung'])); ?></td>
                                    <td><?php echo number_format(abs($item['tong_tien_dich_vu'])); ?> VNĐ</td>
                                    <td>
                                        <?php echo $tong_tien_dich_vu > 0 ? number_format((abs($item['tong_tien_dich_vu']) / abs($tong_tien_dich_vu)) * 100, 2) : 0; ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Không có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.8.0/chart.min.js"></script>
<script>
// Khởi tạo dữ liệu cho biểu đồ
document.addEventListener("DOMContentLoaded", function() {
    // Dữ liệu cho biểu đồ cột
    const barChartData = {
        labels: [
            <?php 
            if (!empty($bao_cao_doanh_thu)) {
                foreach ($bao_cao_doanh_thu as $item) {
                    if ($loai_bao_cao == 'thang') {
                        echo "\"Tháng " . $item['thang'] . "/" . $item['nam'] . "\", ";
                    } elseif ($loai_bao_cao == 'quy') {
                        echo "\"Quý " . $item['quy'] . "/" . $item['nam'] . "\", ";
                    } elseif ($loai_bao_cao == 'nam') {
                        echo "\"Năm " . $item['nam'] . "\", ";
                    } else {
                        echo "\"" . date('d/m/Y', strtotime($item['thoi_gian'])) . "\", ";
                    }
                }
            }
            ?>
        ],
        datasets: [
            {
                label: "Tiền phòng",
                backgroundColor: "rgba(0, 123, 255, 0.7)",
                borderColor: "rgba(0, 123, 255, 1)",
                data: [
                    <?php 
                    if (!empty($bao_cao_doanh_thu)) {
                        foreach ($bao_cao_doanh_thu as $item) {
                            echo abs($item['tong_tien_phong']) . ", ";
                        }
                    }
                    ?>
                ]
            },
            {
                label: "Tiền dịch vụ",
                backgroundColor: "rgba(255, 193, 7, 0.7)",
                borderColor: "rgba(255, 193, 7, 1)",
                data: [
                    <?php 
                    if (!empty($bao_cao_doanh_thu)) {
                        foreach ($bao_cao_doanh_thu as $item) {
                            echo abs($item['tong_tien_dich_vu']) . ", ";
                        }
                    }
                    ?>
                ]
            },
            {
                label: "Tiền cọc",
                backgroundColor: "rgba(23, 162, 184, 0.7)",
                borderColor: "rgba(23, 162, 184, 1)",
                data: [
                    <?php 
                    if (!empty($bao_cao_doanh_thu)) {
                        foreach ($bao_cao_doanh_thu as $item) {
                            echo abs($item['tong_tien_coc']) . ", ";
                        }
                    }
                    ?>
                ]
            }
        ]
    };

    // Dữ liệu cho biểu đồ tròn
    const pieChartData = {
        labels: [
            <?php 
            if (!empty($thong_ke_theo_loai_phong)) {
                foreach ($thong_ke_theo_loai_phong as $item) {
                    switch ($item['loai_phong']) {
                        case 'đơn':
                            echo "\"Phòng đơn\", ";
                            break;
                        case 'đôi':
                            echo "\"Phòng đôi\", ";
                            break;
                        case 'vip':
                            echo "\"Phòng VIP\", ";
                            break;
                        default:
                            echo "\"" . $item['loai_phong'] . "\", ";
                    }
                }
            }
            ?>
        ],
        datasets: [
            {
                backgroundColor: ["#007bff", "#28a745", "#dc3545", "#ffc107", "#17a2b8"],
                data: [
                    <?php 
                    if (!empty($thong_ke_theo_loai_phong)) {
                        foreach ($thong_ke_theo_loai_phong as $item) {
                            echo abs($item['tong_doanh_thu']) . ", ";
                        }
                    }
                    ?>
                ]
            }
        ]
    };

    // Khởi tạo biểu đồ cột
    var barChartElement = document.getElementById("myBarChart");
    if (barChartElement) {
        new Chart(barChartElement, {
            type: "bar",
            data: barChartData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(context.raw) + ' đ';
                            }
                        }
                    }
                }
            }
        });
    }

    // Khởi tạo biểu đồ tròn
    var pieChartElement = document.getElementById("myPieChart");
    if (pieChartElement) {
        new Chart(pieChartElement, {
            type: "pie",
            data: pieChartData,
            options: {
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = Math.abs(context.raw); // Đảm bảo giá trị dương
                                const percentage = ((value / <?php echo max(1, abs($tong_doanh_thu)); ?>) * 100).toFixed(2);
                                return context.label + ': ' + new Intl.NumberFormat('vi-VN').format(value) + ' đ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Xử lý xuất Excel
    document.getElementById("btn-export").addEventListener("click", function() {
        window.location.href = "export_doanh_thu.php?ngay_bat_dau=" + document.getElementById("ngay_bat_dau").value + 
                              "&ngay_ket_thuc=" + document.getElementById("ngay_ket_thuc").value + 
                              "&loai_bao_cao=" + document.getElementById("loai_bao_cao").value;
    });


    // Thêm đoạn mã này ngay sau đoạn xử lý xuất Excel trong đoạn script hiện có
    // Cập nhật ngày bắt đầu và kết thúc dựa trên loại báo cáo
    document.getElementById("loai_bao_cao").addEventListener("change", function() {
        const loaiBaoCao = this.value;
        const today = new Date();
        let ngayBatDau = document.getElementById("ngay_bat_dau");
        let ngayKetThuc = document.getElementById("ngay_ket_thuc");
        
        switch(loaiBaoCao) {
            case 'ngay':
                // Mặc định: Từ đầu tháng đến cuối tháng hiện tại
                ngayBatDau.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                ngayKetThuc.value = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
                break;
                
            case 'thang':
                // Từ đầu năm đến cuối năm hiện tại
                ngayBatDau.value = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                ngayKetThuc.value = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
                break;
                
            case 'quy':
                // Từ đầu năm đến cuối năm hiện tại
                ngayBatDau.value = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                ngayKetThuc.value = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
                break;
                
            case 'nam':
                // Từ đầu năm trước đến cuối năm hiện tại
                ngayBatDau.value = new Date(today.getFullYear() - 1, 0, 1).toISOString().split('T')[0];
                ngayKetThuc.value = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
                break;
        }
    });
    });
</script>

<?php
// Import file footer
require_once __DIR__ . '/../../includes/footer.php';
?>