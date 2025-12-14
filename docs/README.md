# 📚 TÀI LIỆU HỆ THỐNG TÍNH DẦU

## 📖 Tổng quan

Hệ thống **TinhDau** là ứng dụng web quản lý và tính toán tiêu thụ dầu cho các tàu vận chuyển xi măng tại Công ty Xi măng Hà Tiên. 

### 🎯 Chức năng chính

- **Tính toán dầu tiêu thụ**: Tự động tính toán lượng dầu dựa trên khoảng cách, hệ số tàu và các thông số vận chuyển
- **Quản lý dầu tồn**: Theo dõi lượng dầu tồn kho, nhập xuất dầu
- **Báo cáo DAUTON**: Báo cáo chi tiết về tình hình sử dụng dầu
- **Quản lý danh mục**: Tàu, điểm đến/đi, khoảng cách, hệ số

### 🏗️ Cấu trúc hệ thống

```
TinhDau/
├── admin/              # Module quản trị
│   └── bao_cao_dau_ton.php
├── ajax/               # API endpoints AJAX
├── api/                # REST API
├── auth/               # Xác thực & phân quyền
├── config/             # Cấu hình hệ thống
├── includes/           # Các file dùng chung
├── models/             # Data models
├── src/                # Source code chính
├── assets/             # CSS, JS, images
├── data/               # Dữ liệu CSV
│   ├── bang_he_so_tau_cu_ly_full_v2.csv
│   └── khoang_duong.csv
└── docs/               # Tài liệu hệ thống
```

### 💻 Công nghệ sử dụng

- **Backend**: PHP
- **Database**: MySQL/MariaDB
- **Frontend**: HTML, CSS, JavaScript
- **Data Format**: CSV cho dữ liệu hệ số và khoảng cách

## 🔧 Lịch sử khắc phục

### ✅ Đồng bộ dữ liệu báo cáo DAUTON và quản lý dầu tồn
- **Ngày:** 20/10/2025
- **Vấn đề:** Khác biệt dữ liệu do logic xác định ngày khác nhau
- **Giải pháp:** Đồng bộ thứ tự ưu tiên xác định ngày: `ngay_do_xong → ngay_den → ngay_di → created_at`
- **File sửa:** `admin/bao_cao_dau_ton.php` (2 vị trí)
- **Kết quả:** Dữ liệu đồng nhất 100%

## 📄 File tài liệu

- `README.md` - Tổng quan hệ thống (file này)
- `FIX_DONG_BO_DU_LIEU.md` - Mô tả chi tiết vấn đề và cách khắc phục đồng bộ dữ liệu
- `BAO_CAO_KIEM_TRA_DONG_BO.md` - Báo cáo kiểm tra sau khắc phục

## 📊 Module chính

### 1. Tính toán dầu (`index.php`)
- Nhập thông tin chuyến đi
- Tính toán tự động lượng dầu tiêu thụ
- Lưu lịch sử tính toán

### 2. Quản lý dầu tồn (`quan_ly_dau_ton.php`)
- Xem tồn kho hiện tại
- Nhập dầu
- Xuất dầu
- Theo dõi biến động

### 3. Lịch sử (`lich_su.php`)
- Xem lịch sử các chuyến đi
- Tra cứu theo nhiều tiêu chí
- Export báo cáo

### 4. Danh mục
- **Tàu** (`danh_sach_tau.php`): Quản lý danh sách tàu và hệ số
- **Điểm đến/đi** (`danh_sach_diem.php`): Quản lý các địa điểm
- **Khoảng cách**: Quản lý khoảng cách giữa các điểm

### 5. Báo cáo Admin (`admin/`)
- Báo cáo dầu tồn tổng hợp
- Thống kê sử dụng dầu
- Phân tích xu hướng

## 🔑 Dữ liệu tham chiếu

### Hệ số tàu
File: `bang_he_so_tau_cu_ly_full_v2.csv`
- Chứa hệ số tiêu thụ dầu của từng tàu
- Phân loại theo tải trọng và cự ly

### Khoảng cách
File: `khoang_duong.csv`
- Ma trận khoảng cách giữa các điểm
- Đơn vị: hải lý (nautical miles)

## 🎯 Tình trạng hiện tại

**✅ HOÀN TẤT** - Hệ thống hoạt động bình thường với dữ liệu đồng nhất.

## 📝 Ghi chú

- Hệ thống sử dụng logic ưu tiên ngày: `ngay_do_xong → ngay_den → ngay_di → created_at`
- Đảm bảo đồng bộ logic này trên tất cả các module để tránh sai lệch dữ liệu
- Backup dữ liệu thường xuyên trước khi thực hiện cập nhật lớn

## 📞 Hỗ trợ

Mọi thắc mắc về hệ thống, vui lòng liên hệ bộ phận IT Xi măng Hà Tiên.