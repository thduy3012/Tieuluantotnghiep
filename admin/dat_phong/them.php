<?php
// Bắt đầu phiên làm việc
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Bạn cần đăng nhập để sử dụng chức năng này';
    $_SESSION['message_type'] = 'warning';
    header('Location: /quanlykhachsan/auth/login.php');
    exit();
}

// Import file cấu hình và các hàm cần thiết
require_once __DIR__ . '/../../config/config.php';

// Xử lý khi form được gửi đi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $id_khach_hang = $_POST['id_khach_hang'] ?? 0;
    $id_phong = $_POST['id_phong'] ?? 0;
    $ngay_nhan_phong = $_POST['ngay_nhan_phong'] ?? '';
    $ngay_tra_phong = $_POST['ngay_tra_phong'] ?? '';
    $tien_coc = $_POST['tien_coc'] ?? 0;
    
    // Validate dữ liệu
    $errors = [];
    
    if (empty($id_khach_hang)) {
        $errors[] = "Vui lòng chọn khách hàng";
    }
    
    if (empty($id_phong)) {
        $errors[] = "Vui lòng chọn phòng";
    }
    
    if (empty($ngay_nhan_phong)) {
        $errors[] = "Vui lòng chọn ngày nhận phòng";
    } else {
        // $ngay_nhan = new DateTime($ngay_nhan_phong);
        // $ngay_hien_tai = new DateTime();
        
        // if ($ngay_nhan < $ngay_hien_tai) {
        //     $errors[] = "Ngày nhận phòng không thể trước ngày hiện tại";
        // }
        $ngay_nhan = new DateTime($ngay_nhan_phong);
        $ngay_hien_tai = new DateTime();
        $ngay_hien_tai->setTime(0, 0, 0); // Đặt giờ về 00:00:00 để so sánh theo ngày

        if ($ngay_nhan < $ngay_hien_tai) {
            $errors[] = "Ngày nhận phòng không thể trước ngày hiện tại";
        }
    }
    
    if (empty($ngay_tra_phong)) {
        $errors[] = "Vui lòng chọn ngày trả phòng";
    } else {
        $ngay_tra = new DateTime($ngay_tra_phong);
        $ngay_nhan = new DateTime($ngay_nhan_phong);
        
        if ($ngay_tra <= $ngay_nhan) {
            $errors[] = "Ngày trả phòng phải sau ngày nhận phòng";
        }
    }
    
    // Kiểm tra tính khả dụng của phòng trong khoảng thời gian đã chọn
    if (!empty($id_phong) && !empty($ngay_nhan_phong) && !empty($ngay_tra_phong)) {
        $sql = "SELECT * FROM dat_phong 
                WHERE id_phong = ? 
                AND trang_thai NOT IN ('đã trả phòng', 'đã hủy') 
                AND ((ngay_nhan_phong <= ? AND ngay_tra_phong >= ?) 
                OR (ngay_nhan_phong <= ? AND ngay_tra_phong >= ?) 
                OR (ngay_nhan_phong >= ? AND ngay_tra_phong <= ?))";
        
        $bookings = fetchAllRows($sql, [
            $id_phong, 
            $ngay_tra_phong, $ngay_nhan_phong, 
            $ngay_nhan_phong, $ngay_nhan_phong, 
            $ngay_nhan_phong, $ngay_tra_phong
        ]);
        
        if ($bookings && count($bookings) > 0) {
            $errors[] = "Phòng này đã được đặt trong khoảng thời gian bạn chọn";
        }
    }
    
    // Nếu không có lỗi, thực hiện đặt phòng
    if (empty($errors)) {
        $id_nhan_vien = $_SESSION['user_id'];
        
        $sql = "INSERT INTO dat_phong (id_khach_hang, id_phong, id_nhan_vien, ngay_nhan_phong, ngay_tra_phong, tien_coc, trang_thai) 
                VALUES (?, ?, ?, ?, ?, ?, 'đã đặt')";
        
        $booking_id = insertAndGetId($sql, [
            $id_khach_hang, 
            $id_phong, 
            $id_nhan_vien, 
            $ngay_nhan_phong, 
            $ngay_tra_phong, 
            $tien_coc
        ]);
        
        if ($booking_id) {
            // Cập nhật trạng thái phòng
            $sql = "UPDATE phong SET trang_thai = 'đã đặt' WHERE id = ?";
            executeQuery($sql, [$id_phong]);
            
            $_SESSION['message'] = 'Đặt phòng thành công';
            $_SESSION['message_type'] = 'success';
            header('Location: /quanlykhachsan/admin/dat_phong/index.php');
            exit();
        } else {
            $errors[] = "Có lỗi xảy ra khi đặt phòng, vui lòng thử lại";
        }
    }
}

// Lấy danh sách khách hàng
$khach_hang = fetchAllRows("SELECT id, ho_ten, so_cmnd, so_dien_thoai FROM khach_hang ORDER BY ho_ten");

// Lấy danh sách phòng trống
$phong_trong = fetchAllRows("
    SELECT p.id, p.so_phong, p.loai_phong, p.gia_ngay 
    FROM phong p 
    WHERE p.trang_thai = 'trống' 
    ORDER BY p.so_phong
");

// Tải header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2><i class="fas fa-calendar-plus me-2"></i>Đặt phòng mới</h2>
    </div>
</div>

<?php if (isset($errors) && !empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Bắt buộc đặt head trước khi đóng thẻ </head> trong header.php -->
<!-- Nếu không thể sửa file header.php, hãy đặt ở đầu trang -->
<style>
.search-box {
    position: relative;
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    padding: 8px 12px;
    padding-right: 30px;
    border-radius: 4px;
    border: 1px solid #ced4da;
}

.search-box .clear-search {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #6c757d;
}

.dropdown-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ced4da;
    border-radius: 4px;
    margin-top: 5px;
    background-color: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.15);
    display: none;
    position: absolute;
    width: 100%;
    z-index: 1000;
}

.dropdown-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item.selected {
    background-color: #e9ecef;
}

.custom-select-container {
    position: relative;
    width: 100%;
}

.dropdown-item span.highlight {
    background-color: #ffeeba;
    font-weight: bold;
}
</style>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Thông tin đặt phòng</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="id_khach_hang" class="form-label">Khách hàng <span class="text-danger">*</span></label>
                        <div class="d-flex">
                            <div class="custom-select-container">
                                <div class="search-box">
                                    <input type="text" id="search_khach_hang" class="form-control" placeholder="Tìm kiếm khách hàng..." autocomplete="off">
                                    <span class="clear-search">&times;</span>
                                </div>
                                <div class="dropdown-list" id="khach_hang_list">
                                    <?php foreach ($khach_hang as $kh): ?>
                                        <div class="dropdown-item" data-value="<?php echo $kh['id']; ?>" data-display="<?php echo htmlspecialchars($kh['ho_ten'] . ' - ' . $kh['so_cmnd'] . ' - ' . $kh['so_dien_thoai']); ?>">
                                            <?php echo htmlspecialchars($kh['ho_ten'] . ' - ' . $kh['so_cmnd'] . ' - ' . $kh['so_dien_thoai']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <select class="form-select d-none" id="id_khach_hang" name="id_khach_hang" required>
                                    <option value="">-- Chọn khách hàng --</option>
                                    <?php foreach ($khach_hang as $kh): ?>
                                        <option value="<?php echo $kh['id']; ?>" <?php echo (isset($_POST['id_khach_hang']) && $_POST['id_khach_hang'] == $kh['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kh['ho_ten'] . ' - ' . $kh['so_cmnd'] . ' - ' . $kh['so_dien_thoai']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <a href="/quanlykhachsan/admin/khach_hang/them.php" class="btn btn-outline-primary ms-2" title="Thêm khách hàng mới">
                                <i class="fas fa-plus"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_phong" class="form-label">Phòng <span class="text-danger">*</span></label>
                        <div class="custom-select-container">
                            <div class="search-box">
                                <input type="text" id="search_phong" class="form-control" placeholder="Tìm kiếm phòng..." autocomplete="off">
                                <span class="clear-search">&times;</span>
                            </div>
                            <div class="dropdown-list" id="phong_list">
                                <?php foreach ($phong_trong as $phong): ?>
                                    <div class="dropdown-item" data-value="<?php echo $phong['id']; ?>" data-display="<?php echo htmlspecialchars($phong['so_phong'] . ' - ' . $phong['loai_phong'] . ' - ' . number_format($phong['gia_ngay'], 0, ',', '.') . ' VNĐ/ngày'); ?>" data-gia="<?php echo $phong['gia_ngay']; ?>">
                                        <?php echo htmlspecialchars($phong['so_phong'] . ' - ' . $phong['loai_phong'] . ' - ' . number_format($phong['gia_ngay'], 0, ',', '.') . ' VNĐ/ngày'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <select class="form-select d-none" id="id_phong" name="id_phong" required>
                                <option value="">-- Chọn phòng --</option>
                                <?php foreach ($phong_trong as $phong): ?>
                                    <option value="<?php echo $phong['id']; ?>" data-gia="<?php echo $phong['gia_ngay']; ?>" <?php echo (isset($_POST['id_phong']) && $_POST['id_phong'] == $phong['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($phong['so_phong'] . ' - ' . $phong['loai_phong'] . ' - ' . number_format($phong['gia_ngay'], 0, ',', '.') . ' VNĐ/ngày'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="ngay_nhan_phong" class="form-label">Ngày nhận phòng <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="ngay_nhan_phong" name="ngay_nhan_phong" 
                               value="<?php echo isset($_POST['ngay_nhan_phong']) ? $_POST['ngay_nhan_phong'] : date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ngay_tra_phong" class="form-label">Ngày trả phòng <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="ngay_tra_phong" name="ngay_tra_phong" 
                               value="<?php echo isset($_POST['ngay_tra_phong']) ? $_POST['ngay_tra_phong'] : date('Y-m-d', strtotime('+1 day')); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="so_ngay" class="form-label">Số ngày lưu trú</label>
                        <input type="text" class="form-control" id="so_ngay" readonly>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tong_tien" class="form-label">Tổng tiền dự kiến</label>
                        <input type="text" class="form-control" id="tong_tien" readonly>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tien_coc" class="form-label">Tiền cọc</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="tien_coc" name="tien_coc" 
                                   value="<?php echo isset($_POST['tien_coc']) ? $_POST['tien_coc'] : '0'; ?>" min="0">
                            <span class="input-group-text">VNĐ</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <a href="/quanlykhachsan/admin/dat_phong/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Đặt phòng
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hàm tìm kiếm và highlight text
    function highlightText(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<span class="highlight">$1</span>');
    }
    
    // Khởi tạo tìm kiếm cho khách hàng
    function setupSearchSelect(searchInputId, listId, selectId, itemSelector) {
        const searchInput = document.getElementById(searchInputId);
        const dropdownList = document.getElementById(listId);
        const select = document.getElementById(selectId);
        const clearButton = searchInput.nextElementSibling;
        let items = dropdownList.querySelectorAll(itemSelector);
        
        // Thiết lập giá trị ban đầu nếu đã chọn
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            searchInput.value = selectedOption.text;
        }
        
        // Hiển thị dropdown khi focus vào input
        searchInput.addEventListener('focus', function() {
            dropdownList.style.display = 'block';
        });
        
        // Ẩn dropdown khi click ra ngoài
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdownList.contains(e.target)) {
                dropdownList.style.display = 'none';
            }
        });
        
        // Xử lý tìm kiếm
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            // Reset dropdown list
            items.forEach(item => {
                const text = item.getAttribute('data-display');
                if (query === '' || text.toLowerCase().includes(query)) {
                    item.style.display = 'block';
                    item.innerHTML = highlightText(text, query);
                } else {
                    item.style.display = 'none';
                }
            });
            
            dropdownList.style.display = 'block';
            
            // Hiển thị nút clear nếu có text
            if (this.value) {
                clearButton.style.display = 'block';
            } else {
                clearButton.style.display = 'none';
            }
        });
        
        // Xử lý khi chọn item
        items.forEach(item => {
            item.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                const displayText = this.getAttribute('data-display');
                
                select.value = value;
                searchInput.value = displayText;
                dropdownList.style.display = 'none';
                
                // Kích hoạt sự kiện change để cập nhật tính toán
                const event = new Event('change');
                select.dispatchEvent(event);
                
                // Nếu là phòng, tính toán lại
                if (selectId === 'id_phong') {
                    tinhSoNgay();
                }
            });
        });
        
        // Xử lý nút clear
        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            select.value = '';
            this.style.display = 'none';
            
            // Hiển thị tất cả các item
            items.forEach(item => {
                item.style.display = 'block';
                item.textContent = item.getAttribute('data-display');
            });
            
            // Kích hoạt sự kiện change
            const event = new Event('change');
            select.dispatchEvent(event);
            
            // Nếu là phòng, tính toán lại
            if (selectId === 'id_phong') {
                tinhSoNgay();
            }
            
            searchInput.focus();
        });
        
        // Ẩn nút clear ban đầu
        clearButton.style.display = searchInput.value ? 'block' : 'none';
    }
    
    // Thiết lập tìm kiếm cho khách hàng và phòng
    setupSearchSelect('search_khach_hang', 'khach_hang_list', 'id_khach_hang', '.dropdown-item');
    setupSearchSelect('search_phong', 'phong_list', 'id_phong', '.dropdown-item');
    
    // Hàm tính số ngày giữa hai ngày
    function tinhSoNgay() {
        const ngayNhan = new Date(document.getElementById('ngay_nhan_phong').value);
        const ngayTra = new Date(document.getElementById('ngay_tra_phong').value);
        
        if (ngayNhan && ngayTra && ngayTra >= ngayNhan) {
            // Tính số mili giây giữa hai ngày và chuyển đổi sang ngày
            const diffTime = Math.abs(ngayTra - ngayNhan);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            document.getElementById('so_ngay').value = diffDays;
            
            // Tính tổng tiền dự kiến
            tinhTongTien(diffDays);
        } else {
            document.getElementById('so_ngay').value = '';
            document.getElementById('tong_tien').value = '';
        }
    }
    
    // Hàm tính tổng tiền dự kiến
    function tinhTongTien(soNgay) {
        const selectPhong = document.getElementById('id_phong');
        if (selectPhong.value) {
            const selectedOption = selectPhong.options[selectPhong.selectedIndex];
            const giaPhong = parseFloat(selectedOption.getAttribute('data-gia'));
            
            const tongTien = giaPhong * soNgay;
            document.getElementById('tong_tien').value = tongTien.toLocaleString('vi-VN') + ' VNĐ';
            
            // Đề xuất tiền cọc (50% tổng tiền)
            const tienCoc = Math.round(tongTien * 0.5);
            document.getElementById('tien_coc').value = tienCoc;
        }
    }
    
    // Gắn sự kiện change cho các trường input
    document.getElementById('ngay_nhan_phong').addEventListener('change', tinhSoNgay);
    document.getElementById('ngay_tra_phong').addEventListener('change', tinhSoNgay);
    document.getElementById('id_phong').addEventListener('change', tinhSoNgay);
    
    // Tính toán ban đầu
    tinhSoNgay();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>