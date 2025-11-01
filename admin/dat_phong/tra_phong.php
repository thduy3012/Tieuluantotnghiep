<?php
// Bắt đầu session
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Vui lòng đăng nhập để tiếp tục!";
    $_SESSION['message_type'] = "warning";
    header("Location: /quanlykhachsan/auth/login.php");
    exit;
}

// Import các file cần thiết
require_once __DIR__ . '/../../config/config.php';

// Xử lý yêu cầu trả phòng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tra_phong'])) {
    $id_dat_phong = $_POST['id_dat_phong'];
    $tong_tien_phong = $_POST['tong_tien_phong'];
    $tong_tien_dich_vu = $_POST['tong_tien_dich_vu'];
    $tong_thanh_toan = $_POST['tong_thanh_toan'];
    $tien_coc = $_POST['tien_coc'];
    $can_thanh_toan = $tong_thanh_toan - $tien_coc;
    $id_phong = $_POST['id_phong'];
    
    // Bắt đầu giao dịch
    $conn = getDatabaseConnection();
    try {
        $conn->beginTransaction();
        
        // 1. Cập nhật trạng thái đặt phòng thành 'đã trả phòng'
        $sql_update_dat_phong = "UPDATE dat_phong SET trang_thai = 'đã trả phòng' WHERE id = :id_dat_phong";
        executeQuery($sql_update_dat_phong, [':id_dat_phong' => $id_dat_phong]);
        
        // 2. Cập nhật trạng thái phòng thành 'trống'
        $sql_update_phong = "UPDATE phong SET trang_thai = 'trống' WHERE id = :id_phong";
        executeQuery($sql_update_phong, [':id_phong' => $id_phong]);
        
        // 3. Tạo hoặc cập nhật hóa đơn
        $sql_check_hoa_don = "SELECT id FROM hoa_don WHERE id_dat_phong = :id_dat_phong";
        $hoa_don = fetchSingleRow($sql_check_hoa_don, [':id_dat_phong' => $id_dat_phong]);
        
        if ($hoa_don) {
            // Cập nhật hóa đơn hiện có
            $sql_update_hoa_don = "UPDATE hoa_don SET 
                ngay_thanh_toan = CURDATE(), 
                tong_tien_phong = :tong_tien_phong, 
                tong_tien_dich_vu = :tong_tien_dich_vu, 
                tong_thanh_toan = :tong_thanh_toan 
                WHERE id_dat_phong = :id_dat_phong";
                
            executeQuery($sql_update_hoa_don, [
                ':tong_tien_phong' => $tong_tien_phong,
                ':tong_tien_dich_vu' => $tong_tien_dich_vu,
                ':tong_thanh_toan' => $tong_thanh_toan,
                ':id_dat_phong' => $id_dat_phong
            ]);
        } else {
            // Tạo hóa đơn mới
            $sql_insert_hoa_don = "INSERT INTO hoa_don (id_dat_phong, ngay_thanh_toan, tong_tien_phong, tong_tien_dich_vu, tong_thanh_toan) 
                VALUES (:id_dat_phong, CURDATE(), :tong_tien_phong, :tong_tien_dich_vu, :tong_thanh_toan)";
                
            executeQuery($sql_insert_hoa_don, [
                ':id_dat_phong' => $id_dat_phong,
                ':tong_tien_phong' => $tong_tien_phong,
                ':tong_tien_dich_vu' => $tong_tien_dich_vu,
                ':tong_thanh_toan' => $tong_thanh_toan
            ]);
        }
        
        // Hoàn tất giao dịch
        $conn->commit();
        
        $_SESSION['message'] = "Trả phòng thành công. Số tiền cần thanh toán: " . number_format($can_thanh_toan, 0, ',', '.') . " VNĐ";
        $_SESSION['message_type'] = "success";
        header("Location: /quanlykhachsan/admin/dat_phong/index.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $conn->rollBack();
        $_SESSION['message'] = "Lỗi khi trả phòng: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

// Lấy danh sách đặt phòng có trạng thái 'đã nhận phòng'
$sql_dat_phong = "SELECT dp.*, 
                kh.ho_ten as ten_khach_hang, 
                kh.so_dien_thoai, 
                p.so_phong, 
                p.loai_phong, 
                p.gia_ngay,
                p.id as id_phong
                FROM dat_phong dp 
                JOIN khach_hang kh ON dp.id_khach_hang = kh.id 
                JOIN phong p ON dp.id_phong = p.id 
                WHERE dp.trang_thai = 'đã nhận phòng'
                ORDER BY dp.ngay_nhan_phong DESC";
$list_dat_phong = fetchAllRows($sql_dat_phong);

// Include header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4>Trả phòng</h4>
            </div>
            <div class="card-body">
                <?php if (empty($list_dat_phong)): ?>
                    <div class="alert alert-info">Hiện không có phòng nào đang được sử dụng.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Khách hàng</th>
                                    <th>Phòng</th>
                                    <th>Ngày nhận</th>
                                    <th>Ngày trả dự kiến</th>
                                    <th>Tiền cọc</th>
                                    <th>Thông tin</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_dat_phong as $dat_phong): ?>
                                    <tr>
                                        <td><?= $dat_phong['id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($dat_phong['ten_khach_hang']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($dat_phong['so_dien_thoai']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($dat_phong['so_phong']) ?><br>
                                            <span class="badge bg-info"><?= htmlspecialchars($dat_phong['loai_phong']) ?></span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($dat_phong['ngay_nhan_phong'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($dat_phong['ngay_tra_phong'])) ?></td>
                                        <td><?= number_format($dat_phong['tien_coc'], 0, ',', '.') ?> VNĐ</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info" 
                                                    onclick="tinhTienPhong(<?= $dat_phong['id'] ?>, <?= $dat_phong['gia_ngay'] ?>, 
                                                    '<?= $dat_phong['ngay_nhan_phong'] ?>', '<?= $dat_phong['ngay_tra_phong'] ?>', 
                                                    <?= $dat_phong['tien_coc'] ?>, <?= $dat_phong['id_phong'] ?>)">
                                                Tính tiền
                                            </button>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" id="btn-tra-phong-<?= $dat_phong['id'] ?>" 
                                                    data-bs-toggle="modal" data-bs-target="#traPhongModal" disabled>
                                                Trả phòng
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Trả Phòng -->
<div class="modal fade" id="traPhongModal" tabindex="-1" aria-labelledby="traPhongModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="traPhongModalLabel">Xác nhận trả phòng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-tra-phong" method="POST" action="">
                    <input type="hidden" name="id_dat_phong" id="id_dat_phong">
                    <input type="hidden" name="id_phong" id="id_phong">
                    <input type="hidden" name="tien_coc" id="tien_coc">
                    <input type="hidden" name="tong_tien_phong" id="tong_tien_phong_input">
                    <input type="hidden" name="tong_tien_dich_vu" id="tong_tien_dich_vu_input">
                    <input type="hidden" name="tong_thanh_toan" id="tong_thanh_toan_input">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Khách hàng:</label>
                                <p id="ten_khach_hang" class="form-control-static"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Số phòng:</label>
                                <p id="so_phong" class="form-control-static"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Ngày nhận phòng:</label>
                                <p id="ngay_nhan_phong" class="form-control-static"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Ngày trả phòng:</label>
                                <p id="ngay_tra_phong" class="form-control-static"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Số ngày ở:</label>
                                <p id="so_ngay" class="form-control-static"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Giá phòng/ngày:</label>
                                <p id="gia_ngay" class="form-control-static"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Chi tiết dịch vụ</h5>
                            <div id="dich_vu_container"></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5>Tổng kết hóa đơn</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">Tổng tiền phòng:</div>
                                        <div class="col-md-6 text-end" id="tong_tien_phong"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">Tổng tiền dịch vụ:</div>
                                        <div class="col-md-6 text-end" id="tong_tien_dich_vu"></div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">Tổng thanh toán:</div>
                                        <div class="col-md-6 text-end" id="tong_thanh_toan"></div>
                                    </div>
                                    <div class="row">
                                    <div class="col-md-6">Tiền cọc đã nhận:</div>
                                    <div class="col-md-6 text-end" id="hien_thi_tien_coc"></div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6"><strong>Còn phải thanh toán:</strong></div>
                                    <div class="col-md-6 text-end"><strong id="can_thanh_toan"></strong></div>
                                </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <button type="submit" name="tra_phong" class="btn btn-primary">Xác nhận trả phòng</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

<script>
// Hàm tính tiền phòng và hiển thị thông tin
function tinhTienPhong(idDatPhong, giaPhong, ngayNhan, ngayTraDuKien, tienCoc, idPhong) {
    // Lấy ngày hiện tại làm ngày trả phòng thực tế
    const ngayHienTai = new Date();
    const ngayTraThucTe = ngayHienTai.toISOString().split('T')[0];
    const ngayNhanDate = new Date(ngayNhan);
    const ngayTraDate = new Date(ngayTraThucTe);
    
    // Tính số ngày ở (làm tròn lên nếu có phần thập phân)
    const soMiligiay = ngayTraDate - ngayNhanDate;
    const soNgay = Math.ceil(soMiligiay / (1000 * 60 * 60 * 24));
    
    // Tính tiền phòng
    const tongTienPhong = soNgay * giaPhong;
    
    // Lấy thông tin khách hàng, phòng và dịch vụ
    fetch(`get_chi_tiet_dat_phong.php?id=${idDatPhong}`)
        .then(response => response.json())
        .then(data => {
            // Hiển thị thông tin cơ bản
            document.getElementById('id_dat_phong').value = idDatPhong;
            document.getElementById('id_phong').value = idPhong;
            document.getElementById('tien_coc').value = tienCoc;
            document.getElementById('ten_khach_hang').textContent = data.khach_hang.ho_ten;
            document.getElementById('so_phong').textContent = `${data.phong.so_phong} (${data.phong.loai_phong})`;
            document.getElementById('ngay_nhan_phong').textContent = formatDate(ngayNhan);
            document.getElementById('ngay_tra_phong').textContent = formatDate(ngayTraThucTe);
            document.getElementById('so_ngay').textContent = soNgay + ' ngày';
            document.getElementById('gia_ngay').textContent = formatCurrency(giaPhong);
            
            // Tính và hiển thị chi tiết dịch vụ
            let tongTienDichVu = 0;
            let dichVuHtml = '<table class="table table-striped">';
            dichVuHtml += '<thead><tr><th>Tên dịch vụ</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>';
            dichVuHtml += '<tbody>';
            
            if (data.dich_vu && data.dich_vu.length > 0) {
                data.dich_vu.forEach(dv => {
                    dichVuHtml += `<tr>
                        <td>${dv.ten_dich_vu}</td>
                        <td>${dv.so_luong}</td>
                        <td>${formatCurrency(dv.gia)}</td>
                        <td>${formatCurrency(dv.thanh_tien)}</td>
                    </tr>`;
                    tongTienDichVu += parseFloat(dv.thanh_tien);
                });
            } else {
                dichVuHtml += '<tr><td colspan="4" class="text-center">Không có dịch vụ nào được sử dụng</td></tr>';
            }
            dichVuHtml += '</tbody></table>';
            document.getElementById('dich_vu_container').innerHTML = dichVuHtml;
            
            // Tính và hiển thị tổng tiền
            const tongThanhToan = tongTienPhong + tongTienDichVu;
            const canThanhToan = tongThanhToan - tienCoc;
            
            document.getElementById('tong_tien_phong').textContent = formatCurrency(tongTienPhong);
            document.getElementById('tong_tien_dich_vu').textContent = formatCurrency(tongTienDichVu);
            document.getElementById('tong_thanh_toan').textContent = formatCurrency(tongThanhToan);
            document.getElementById('hien_thi_tien_coc').textContent = formatCurrency(tienCoc);
            document.getElementById('can_thanh_toan').textContent = formatCurrency(canThanhToan);
            
            // Cập nhật input hidden để gửi đi khi submit form
            document.getElementById('tong_tien_phong_input').value = tongTienPhong;
            document.getElementById('tong_tien_dich_vu_input').value = tongTienDichVu;
            document.getElementById('tong_thanh_toan_input').value = tongThanhToan;
            
            // Enable nút trả phòng
            document.getElementById(`btn-tra-phong-${idDatPhong}`).disabled = false;

            // Hiển thị modal
            const traPhongModal = new bootstrap.Modal(document.getElementById('traPhongModal'));
            traPhongModal.show();
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert('Có lỗi xảy ra khi lấy thông tin. Vui lòng thử lại!');
        });
}

// Hàm định dạng tiền tệ
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

// Hàm định dạng ngày
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';