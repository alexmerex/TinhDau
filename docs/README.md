# 📚 Tài Liệu Kỹ Thuật - TinhDau

> **Lưu ý**: Đây là tài liệu kỹ thuật nội bộ. Để xem hướng dẫn cài đặt và sử dụng, vui lòng xem [README.md](../README.md) ở thư mục gốc.

## 📖 Mục Lục

- [Tổng Quan Kỹ Thuật](#-tổng-quan-kỹ-thuật)
- [Kiến Trúc Hệ Thống](#-kiến-trúc-hệ-thống)
- [Module Chi Tiết](#-module-chi-tiết)
- [Dữ Liệu Tham Chiếu](#-dữ-liệu-tham-chiếu)
- [Lịch Sử Khắc Phục](#-lịch-sử-khắc-phục)
- [Ghi Chú Kỹ Thuật](#-ghi-chú-kỹ-thuật)

## 🏗️ Tổng Quan Kỹ Thuật

### Công Nghệ Sử Dụng

- **Backend**: PHP 7.4+ (Vanilla PHP, không framework)
- **Storage**: CSV files (không sử dụng database)
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla + jQuery)
- **Export**: PhpSpreadsheet (Excel export)
- **Testing**: PHPUnit
- **Dependency Management**: Composer

### Kiến Trúc

Hệ thống sử dụng kiến trúc MVC đơn giản:

```
┌─────────────┐
│   View      │  (HTML/PHP templates)
│  (Frontend) │
└──────┬──────┘
       │
┌──────▼──────┐
│ Controller  │  (PHP files: index.php, lich_su.php, etc.)
│  (Logic)    │
└──────┬──────┘
       │
┌──────▼──────┐
│   Model     │  (models/*.php)
│  (Data)     │
└──────┬──────┘
       │
┌──────▼──────┐
│   Storage   │  (CSV files in data/)
│   (CSV)     │
└─────────────┘
```

## 🏛️ Kiến Trúc Hệ Thống

### Cấu Trúc Thư Mục Chi Tiết

```
TinhDau/
├── admin/                  # Admin panel - Quản trị hệ thống
│   ├── bao_cao_dau_ton.php      # Báo cáo dầu tồn tổng hợp
│   ├── quan_ly_cay_xang.php     # Quản lý cây xăng
│   ├── quan_ly_dau_ton.php      # Quản lý dầu tồn
│   ├── quan_ly_tau.php          # Quản lý thông tin tàu
│   ├── quan_ly_tuyen_duong.php  # Quản lý tuyến đường
│   └── quan_ly_user.php         # Quản lý người dùng
│
├── ajax/                   # AJAX endpoints - Xử lý request không đồng bộ
│   ├── get_trip_details.php     # Lấy chi tiết chuyến đi
│   └── get_trips.php            # Lấy danh sách chuyến
│
├── api/                    # REST API endpoints
│   ├── insert_trip.php          # Tạo chuyến mới
│   ├── reorder_segments.php     # Sắp xếp lại đoạn tuyến
│   ├── update_transfer.php      # Cập nhật chuyển dầu
│   └── ...                      # Các API khác
│
├── auth/                   # Authentication & Authorization
│   ├── login.php                # Đăng nhập
│   ├── logout.php               # Đăng xuất
│   ├── check_auth.php           # Kiểm tra quyền truy cập
│   └── check_admin.php           # Kiểm tra quyền admin
│
├── config/                 # Configuration files
│   ├── database.php             # Cấu hình kết nối (CSV)
│   └── report_header_registry.php  # Registry cho Excel templates
│
├── data/                   # CSV data storage
│   ├── users.csv                # Người dùng
│   ├── tau_phan_loai.csv         # Phân loại tàu
│   ├── dau_ton.csv               # Dầu tồn
│   ├── ket_qua_tinh_toan.csv     # Kết quả tính toán
│   └── ...                       # Các file CSV khác
│
├── includes/               # Shared includes
│   ├── header.php               # Header chung
│   ├── footer.php               # Footer chung
│   ├── helpers.php              # Helper functions
│   └── excel_export_full.php    # Excel export logic
│
├── models/                 # Data models (Business Logic)
│   ├── User.php                 # Model người dùng
│   ├── TauPhanLoai.php          # Model phân loại tàu
│   ├── DauTon.php               # Model dầu tồn
│   ├── TinhToanNhienLieu.php    # Logic tính toán nhiên liệu
│   └── ...                      # Các model khác
│
├── src/                    # Source code chính
│   └── Report/
│       └── HeaderTemplate.php   # Template cho Excel headers
│
└── template_header/        # Excel templates
    ├── sample_header_BCTHANG.xlsx
    ├── sample_header_DAUTON.xlsx
    └── ...
```

## 📊 Module Chi Tiết

### 1. Module Tính Toán Nhiên Liệu (`index.php`)

**Chức năng:**
- Nhập thông tin chuyến đi (tàu, điểm đi/đến, khối lượng)
- Tính toán tự động lượng dầu tiêu thụ
- Hỗ trợ đa tuyến đường (multi-segment)
- Lưu kết quả vào CSV

**Models liên quan:**
- `TinhToanNhienLieu.php` - Logic tính toán
- `TauPhanLoai.php` - Hệ số tàu
- `KhoangCach.php` - Khoảng cách
- `LuuKetQua.php` - Lưu kết quả

**Flow:**
```
User Input → Validate → Calculate → Save → Display Result
```

### 2. Module Quản Lý Dầu Tồn (`quan_ly_dau_ton.php`)

**Chức năng:**
- Xem tồn kho hiện tại theo từng tàu
- Nhập dầu mới
- Xuất dầu
- Chuyển dầu giữa các tàu
- Theo dõi lịch sử biến động

**Models liên quan:**
- `DauTon.php` - CRUD dầu tồn
- `CayXang.php` - Quản lý cây xăng

**Logic xác định ngày:**
```
Ưu tiên: ngay_do_xong → ngay_den → ngay_di → created_at
```

### 3. Module Lịch Sử (`lich_su.php`)

**Chức năng:**
- Xem lịch sử các chuyến đi
- Tra cứu theo nhiều tiêu chí (tháng, tàu, điểm)
- Export báo cáo Excel
- Filter và search

**Export:**
- Sử dụng PhpSpreadsheet
- Áp dụng template từ `template_header/`
- Tự động format và styling

### 4. Module Quản Trị (`admin/`)

**Báo cáo dầu tồn** (`bao_cao_dau_ton.php`):
- Tổng hợp dầu tồn theo tháng
- So sánh với dữ liệu quản lý dầu tồn
- Đảm bảo đồng bộ dữ liệu

**Quản lý tàu** (`quan_ly_tau.php`):
- CRUD thông tin tàu
- Quản lý hệ số nhiên liệu
- Phân loại tàu

**Quản lý tuyến đường** (`quan_ly_tuyen_duong.php`):
- Quản lý điểm đến/đi
- Quản lý khoảng cách
- Log thay đổi

## 🔑 Dữ Liệu Tham Chiếu

### Hệ Số Tàu

**File:** `bang_he_so_tau_cu_ly_full_v2.csv`

**Cấu trúc:**
- Phân loại theo tên tàu
- Hệ số theo tải trọng (tấn)
- Hệ số theo cự ly (hải lý)

**Sử dụng:**
- Model `HeSoTau.php` đọc và cache dữ liệu
- Tra cứu nhanh bằng array lookup

### Khoảng Cách

**File:** `khoang_duong.csv`

**Cấu trúc:**
- Ma trận khoảng cách giữa các điểm
- Đơn vị: hải lý (nautical miles)
- Format: CSV với điểm đi và điểm đến

**Sử dụng:**
- Model `KhoangCach.php` xử lý
- Tự động tính khoảng cách cho tuyến đường

### Dữ Liệu CSV Khác

- `data/users.csv` - Người dùng hệ thống
- `data/tau_phan_loai.csv` - Phân loại tàu
- `data/dau_ton.csv` - Dầu tồn kho
- `data/ket_qua_tinh_toan.csv` - Lịch sử tính toán
- `data/cay_xang.csv` - Danh sách cây xăng
- `data/loai_hang.csv` - Loại hàng hóa

## 🔧 Lịch Sử Khắc Phục

### ✅ Đồng Bộ Dữ Liệu Báo Cáo DAUTON và Quản Lý Dầu Tồn

**Ngày:** 20/10/2025

**Vấn đề:**
- Dữ liệu báo cáo DAUTON (`admin/bao_cao_dau_ton.php`) khác với dữ liệu quản lý dầu tồn (`quan_ly_dau_ton.php`)
- Nguyên nhân: Logic xác định ngày khác nhau giữa 2 module

**Giải pháp:**
- Đồng bộ thứ tự ưu tiên xác định ngày trên tất cả các module:
  ```
  ngay_do_xong → ngay_den → ngay_di → created_at
  ```

**File sửa:**
- `admin/bao_cao_dau_ton.php` (2 vị trí)
- `quan_ly_dau_ton.php` (đã có logic đúng)

**Kết quả:**
- ✅ Dữ liệu đồng nhất 100% giữa 2 module
- ✅ Báo cáo chính xác
- ✅ Không còn sai lệch

**Tài liệu liên quan:**
- `FIX_DONG_BO_DU_LIEU.md` - Mô tả chi tiết vấn đề và cách khắc phục
- `BAO_CAO_KIEM_TRA_DONG_BO.md` - Báo cáo kiểm tra sau khắc phục

## 📝 Ghi Chú Kỹ Thuật

### Logic Ưu Tiên Ngày

**Quy tắc:**
```
1. ngay_do_xong (nếu có)
2. ngay_den (nếu không có ngay_do_xong)
3. ngay_di (nếu không có cả 2 trên)
4. created_at (fallback cuối cùng)
```

**Áp dụng tại:**
- Module quản lý dầu tồn
- Module báo cáo dầu tồn
- Module lịch sử
- Tất cả các module liên quan đến ngày tháng

### Xử Lý CSV

**Best Practices:**
- Luôn lock file khi ghi (flock)
- Backup trước khi thay đổi lớn
- Validate dữ liệu trước khi ghi
- Handle encoding UTF-8

**Performance:**
- Cache dữ liệu đọc nhiều lần
- Sử dụng array lookup thay vì loop
- Lazy loading cho dữ liệu lớn

### Excel Export

**Template System:**
- Templates lưu trong `template_header/`
- Registry trong `config/report_header_registry.php`
- Tự động apply header/footer
- Support multiple sheet types

**PhpSpreadsheet:**
- Version: ^1.29
- Format: XLSX
- Auto-sizing columns
- Custom styling

### Security

**Authentication:**
- Session-based
- Password hashing: bcrypt
- CSRF protection (nên thêm)
- Input validation

**File Access:**
- Chỉ cho phép đọc/ghi trong `data/`
- Validate file paths
- Không cho phép directory traversal

### Performance

**Optimization:**
- Cache CSV data trong memory
- Minimize file I/O
- Use indexes cho lookup
- Lazy load models

**Monitoring:**
- Log errors vào file
- Track execution time
- Monitor file sizes

## 🎯 Tình Trạng Hiện Tại

**✅ HOÀN TẤT** - Hệ thống hoạt động bình thường với dữ liệu đồng nhất.

**Các tính năng đã hoàn thành:**
- ✅ Tính toán nhiên liệu
- ✅ Quản lý dầu tồn
- ✅ Xuất báo cáo Excel
- ✅ Đồng bộ dữ liệu
- ✅ Quản lý người dùng
- ✅ Admin panel

**Cần cải thiện:**
- [ ] Migration sang database (MySQL/PostgreSQL)
- [ ] API authentication
- [ ] Unit tests đầy đủ
- [ ] Docker support
- [ ] CI/CD pipeline

## 📞 Hỗ Trợ Kỹ Thuật

Mọi thắc mắc về kỹ thuật, vui lòng:
- Tạo issue trên GitHub
- Liên hệ bộ phận IT Xi măng Hà Tiên
- Email: khoapham491@gmail.com

---

**Xem thêm:**
- [README.md chính](../README.md) - Hướng dẫn cài đặt và sử dụng
- [API Documentation](../README.md#-api-documentation) - Tài liệu API
- [Contributing Guide](../README.md#-đóng-góp) - Hướng dẫn đóng góp
