<?php
// Bắt đầu output buffering
ob_start();

// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Bạn cần đăng nhập để thực hiện chức năng này!";
    $_SESSION['message_type'] = "danger";
    header("Location: /quanlykhachsan/auth/login.php");
    exit();
}

// Import file cấu hình và header
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';

// Hàm cập nhật trạng thái phòng dựa trên trạng thái đặt phòng
function capNhatTrangThaiPhong($idPhong, $trangThaiDatPhong) {
    $trangThaiPhong = 'trống'; // Mặc định

    switch ($trangThaiDatPhong) {
        case 'đã đặt':
            $trangThaiPhong = 'đã đặt';
            break;
        case 'đã nhận phòng':
            $trangThaiPhong = 'đang sử dụng';
            break;
        case 'đã trả phòng':
            $trangThaiPhong = 'trống';
            break;
        case 'đã hủy':
            $trangThaiPhong = 'trống';
            break;
    }

    // Cập nhật trạng thái phòng
    $sql = "UPDATE phong SET trang_thai = :trang_thai WHERE id = :id_phong";
    return executeQuery($sql, [
        ':trang_thai' => $trangThaiPhong,
        ':id_phong' => $idPhong
    ]);
}

// Xử lý cập nhật trạng thái đặt phòng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_dat_phong'], $_POST['trang_thai'])) {
    $idDatPhong = $_POST['id_dat_phong'];
    $trangThai = $_POST['trang_thai'];
    
    try {
        // Lấy thông tin đặt phòng hiện tại để biết id_phong
        $sqlGetInfo = "SELECT id_phong FROM dat_phong WHERE id = :id";
        $datPhong = fetchSingleRow($sqlGetInfo, [':id' => $idDatPhong]);
        
        if (!$datPhong) {
            throw new Exception("Không tìm thấy thông tin đặt phòng!");
        }
        
        // Cập nhật trạng thái đặt phòng
        $sql = "UPDATE dat_phong SET trang_thai = :trang_thai WHERE id = :id";
        $result = executeQuery($sql, [
            ':trang_thai' => $trangThai,
            ':id' => $idDatPhong
        ]);
        
        if ($result) {
            // Cập nhật trạng thái phòng tương ứng
            capNhatTrangThaiPhong($datPhong['id_phong'], $trangThai);
            
            // Tạo hóa đơn nếu trạng thái là "đã trả phòng"
            if ($trangThai === 'đã trả phòng') {
                // Lấy thông tin đặt phòng
                $datPhongInfo = fetchSingleRow("SELECT * FROM dat_phong WHERE id = :id", [':id' => $idDatPhong]);
                
                // Tính số ngày ở
                $ngayNhan = new DateTime($datPhongInfo['ngay_nhan_phong']);
                $ngayTra = new DateTime($datPhongInfo['ngay_tra_phong']);
                $soNgay = $ngayTra->diff($ngayNhan)->days;
                if ($soNgay < 1) $soNgay = 1; // Tối thiểu 1 ngày
                
                // Lấy giá phòng
                $phongInfo = fetchSingleRow("SELECT gia_ngay FROM phong WHERE id = :id", [':id' => $datPhongInfo['id_phong']]);
                $giaPhong = $phongInfo['gia_ngay'];
                
                // Tính tổng tiền phòng
                $tongTienPhong = $giaPhong * $soNgay;
                
                // Tính tổng tiền dịch vụ
                $dichVuInfo = fetchSingleRow(
                    "SELECT SUM(thanh_tien) as tong_dich_vu FROM su_dung_dich_vu WHERE id_dat_phong = :id_dat_phong",
                    [':id_dat_phong' => $idDatPhong]
                );
                $tongTienDichVu = $dichVuInfo['tong_dich_vu'] ?? 0;
                
                // Tính tổng thanh toán
                $tongThanhToan = $tongTienPhong + $tongTienDichVu - $datPhongInfo['tien_coc'];
                
                // Kiểm tra xem đã có hóa đơn chưa
                $hoaDonExist = fetchSingleRow(
                    "SELECT id FROM hoa_don WHERE id_dat_phong = :id_dat_phong",
                    [':id_dat_phong' => $idDatPhong]
                );
                
                if (!$hoaDonExist) {
                    // Tạo mới hóa đơn
                    $sqlHoaDon = "INSERT INTO hoa_don (id_dat_phong, ngay_thanh_toan, tong_tien_phong, tong_tien_dich_vu, tong_thanh_toan) 
                                  VALUES (:id_dat_phong, CURDATE(), :tong_tien_phong, :tong_tien_dich_vu, :tong_thanh_toan)";
                    executeQuery($sqlHoaDon, [
                        ':id_dat_phong' => $idDatPhong,
                        ':tong_tien_phong' => $tongTienPhong,
                        ':tong_tien_dich_vu' => $tongTienDichVu,
                        ':tong_thanh_toan' => $tongThanhToan
                    ]);
                }
            }
            
            $_SESSION['message'] = "Cập nhật trạng thái đặt phòng thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Cập nhật trạng thái thất bại!";
            $_SESSION['message_type'] = "danger";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    // Chuyển hướng về trang danh sách đặt phòng
    header("Location: /quanlykhachsan/admin/dat_phong/index.php");
    exit();
}

// Lấy thông tin đặt phòng cần cập nhật
$datPhongInfo = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idDatPhong = $_GET['id'];
    
    $sql = "SELECT dp.*, kh.ho_ten as ten_khach_hang, p.so_phong, p.loai_phong, p.gia_ngay 
            FROM dat_phong dp
            JOIN khach_hang kh ON dp.id_khach_hang = kh.id
            JOIN phong p ON dp.id_phong = p.id
            WHERE dp.id = :id";
    
    $datPhongInfo = fetchSingleRow($sql, [':id' => $idDatPhong]);
    
    if (!$datPhongInfo) {
        $_SESSION['message'] = "Không tìm thấy thông tin đặt phòng!";
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit();
    }
}
?>

<div class="container">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cập nhật trạng thái đặt phòng</h6>
        </div>
        <div class="card-body">
            <?php if ($datPhongInfo): ?>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Thông tin đặt phòng</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Khách hàng:</th>
                                <td><?php echo htmlspecialchars($datPhongInfo['ten_khach_hang']); ?></td>
                            </tr>
                            <tr>
                                <th>Phòng:</th>
                                <td><?php echo htmlspecialchars($datPhongInfo['so_phong']); ?> (<?php echo htmlspecialchars($datPhongInfo['loai_phong']); ?>)</td>
                            </tr>
                            <tr>
                                <th>Giá phòng:</th>
                                <td><?php echo number_format($datPhongInfo['gia_ngay'], 0, ',', '.'); ?> VNĐ/ngày</td>
                            </tr>
                            <tr>
                                <th>Ngày nhận phòng:</th>
                                <td><?php echo date('d/m/Y', strtotime($datPhongInfo['ngay_nhan_phong'])); ?></td>
                            </tr>
                            <tr>
                                <th>Ngày trả phòng:</th>
                                <td><?php echo date('d/m/Y', strtotime($datPhongInfo['ngay_tra_phong'])); ?></td>
                            </tr>
                            <tr>
                                <th>Tiền cọc:</th>
                                <td><?php echo number_format($datPhongInfo['tien_coc'], 0, ',', '.'); ?> VNĐ</td>
                            </tr>
                            <tr>
                                <th>Trạng thái hiện tại:</th>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch ($datPhongInfo['trang_thai']) {
                                            case 'đã đặt': echo 'warning'; break;
                                            case 'đã nhận phòng': echo 'success'; break;
                                            case 'đã trả phòng': echo 'primary'; break;
                                            case 'đã hủy': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars($datPhongInfo['trang_thai']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Cập nhật trạng thái</h5>
                        <form method="POST" action="">
                            <input type="hidden" name="id_dat_phong" value="<?php echo $datPhongInfo['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="trang_thai" class="form-label">Trạng thái mới</label>
                                <select class="form-select" id="trang_thai" name="trang_thai" required>
                                    <option value="">-- Chọn trạng thái --</option>
                                    <option value="đã đặt" <?php echo $datPhongInfo['trang_thai'] == 'đã đặt' ? 'selected' : ''; ?>>Đã đặt</option>
                                    <option value="đã nhận phòng" <?php echo $datPhongInfo['trang_thai'] == 'đã nhận phòng' ? 'selected' : ''; ?>>Đã nhận phòng</option>
                                    <option value="đã trả phòng" <?php echo $datPhongInfo['trang_thai'] == 'đã trả phòng' ? 'selected' : ''; ?>>Đã trả phòng</option>
                                    <option value="đã hủy" <?php echo $datPhongInfo['trang_thai'] == 'đã hủy' ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Lưu ý:</strong>
                                <ul>
                                    <li>Trạng thái "Đã nhận phòng" sẽ cập nhật trạng thái phòng thành "đang sử dụng"</li>
                                    <li>Trạng thái "Đã trả phòng" sẽ cập nhật trạng thái phòng thành "trống" và tạo hóa đơn</li>
                                    <li>Trạng thái "Đã hủy" sẽ cập nhật trạng thái phòng thành "trống"</li>
                                </ul>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">Quay lại</a>
                                <button type="submit" class="btn btn-primary">Cập nhật</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($datPhongInfo['trang_thai'] == 'đã nhận phòng'): ?>
                <div class="row">
                    <div class="col-12">
                        <h5>Dịch vụ đã sử dụng</h5>
                        <?php
                        $dichVuSuDung = fetchAllRows(
                            "SELECT sdv.*, dv.ten_dich_vu
                            FROM su_dung_dich_vu sdv
                            JOIN dich_vu dv ON sdv.id_dich_vu = dv.id
                            WHERE sdv.id_dat_phong = :id_dat_phong
                            ORDER BY sdv.ngay_su_dung DESC",
                            [':id_dat_phong' => $idDatPhong]
                        );
                        if ($dichVuSuDung && count($dichVuSuDung) > 0) {
                            echo '<table class="table table-bordered table-striped">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Dịch vụ</th>';
                            echo '<th>Số lượng</th>';
                            echo '<th>Ngày sử dụng</th>';
                            echo '<th>Thành tiền</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            
                            $tongTien = 0;
                            foreach ($dichVuSuDung as $dv) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($dv['ten_dich_vu']) . '</td>';
                                echo '<td>' . htmlspecialchars($dv['so_luong']) . '</td>';
                                echo '<td>' . date('d/m/Y', strtotime($dv['ngay_su_dung'])) . '</td>';
                                echo '<td>' . number_format($dv['thanh_tien'], 0, ',', '.') . ' VNĐ</td>';
                                echo '</tr>';
                                
                                $tongTien += $dv['thanh_tien'];
                            }
                            
                            echo '</tbody>';
                            echo '<tfoot>';
                            echo '<tr>';
                            echo '<th colspan="3" class="text-end">Tổng tiền dịch vụ:</th>';
                            echo '<th>' . number_format($tongTien, 0, ',', '.') . ' VNĐ</th>';
                            echo '</tr>';
                            echo '</tfoot>';
                            echo '</table>';
                            
                            // Tính dự kiến hóa đơn
                            $ngayNhan = new DateTime($datPhongInfo['ngay_nhan_phong']);
                            $ngayTra = new DateTime($datPhongInfo['ngay_tra_phong']);
                            $soNgay = $ngayTra->diff($ngayNhan)->days;
                            if ($soNgay < 1) $soNgay = 1; // Tối thiểu 1 ngày
                            
                            $tongTienPhong = $datPhongInfo['gia_ngay'] * $soNgay;
                            $tongThanhToan = $tongTienPhong + $tongTien - $datPhongInfo['tien_coc'];
                            
                            echo '<div class="alert alert-warning">';
                            echo '<h6>Dự kiến thanh toán:</h6>';
                            echo '<p>Tiền phòng: ' . number_format($tongTienPhong, 0, ',', '.') . ' VNĐ (' . $soNgay . ' ngày x ' . number_format($datPhongInfo['gia_ngay'], 0, ',', '.') . ' VNĐ)</p>';
                            echo '<p>Tiền dịch vụ: ' . number_format($tongTien, 0, ',', '.') . ' VNĐ</p>';
                            echo '<p>Tiền cọc: -' . number_format($datPhongInfo['tien_coc'], 0, ',', '.') . ' VNĐ</p>';
                            echo '<p class="fw-bold">Tổng cần thanh toán: ' . number_format($tongThanhToan, 0, ',', '.') . ' VNĐ</p>';
                            echo '</div>';
                            
                            echo '<div class="mt-3 mb-4">';
                            echo '<a href="../dich_vu/su_dung_dich_vu.php?id_dat_phong=' . $idDatPhong . '" class="btn btn-info">Thêm dịch vụ</a>';
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-info">Chưa có dịch vụ nào được sử dụng</div>';
                            echo '<a href="../dich_vu/su_dung_dich_vu.php?id_dat_phong=' . $idDatPhong . '" class="btn btn-info mb-4">Thêm dịch vụ</a>';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-danger">Không tìm thấy thông tin đặt phòng!</div>
                <a href="index.php" class="btn btn-secondary">Quay lại danh sách</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Import footer
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush(); // Xóa bộ nhớ đệm
?>