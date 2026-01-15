# 🚢 TinhDau - Hệ Thống Tính Dầu và Quản Lý Dầu Tồn

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Active-success.svg)](https://github.com/alexmerex/TinhDau)

> Hệ thống quản lý và tính toán nhiên liệu cho đội tàu vận chuyển xi măng Hà Tiên

## 📋 Mục Lục

- [Giới Thiệu](#-giới-thiệu)
- [Tính Năng](#-tính-năng)
- [Yêu Cầu Hệ Thống](#-yêu-cầu-hệ-thống)
- [Cài Đặt](#-cài-đặt)
- [Sử Dụng](#-sử-dụng)
- [Quản Lý Dữ Liệu (CSV)](#-quản-lý-dữ-liệu-csv)
- [Cấu Trúc Dự Án](#-cấu-trúc-dự-án)
- [Tài Liệu Kỹ Thuật](#-tài-liệu-kỹ-thuật)
- [API Documentation](#-api-documentation)
- [Testing](#-testing)
- [Đóng Góp](#-đóng-góp)
- [License](#-license)
- [Liên Hệ](#-liên-hệ)

## 🎯 Giới Thiệu

**TinhDau** là hệ thống quản lý và tính toán nhiên liệu chuyên nghiệp được phát triển cho đội tàu vận chuyển xi măng Hà Tiên. Hệ thống cung cấp các chức năng:

- ✅ Tính toán nhiên liệu tiêu thụ dựa trên tuyến đường và khối lượng vận chuyển
- ✅ Quản lý dầu tồn kho theo từng tàu
- ✅ Xuất báo cáo Excel tự động với template chuyên nghiệp
- ✅ Quản lý thông tin tàu, cây xăng, tuyến đường
- ✅ Theo dõi lịch sử tính toán và báo cáo

## ✨ Tính Năng

### 🔢 Tính Toán Nhiên Liệu
- Tính toán tự động dựa trên hệ số nhiên liệu theo loại tàu
- Hỗ trợ tính toán đa tuyến đường (multi-segment)
- Tự động tra cứu khoảng cách giữa các điểm
- Tính toán chính xác với nhiều loại hàng hóa

### 📊 Quản Lý Dầu Tồn
- Quản lý dầu tồn kho theo từng tàu
- Theo dõi lịch sử nhập/xuất dầu
- Chuyển dầu giữa các tàu
- Báo cáo dầu tồn theo tháng

### 📄 Xuất Báo Cáo
- Xuất báo cáo Excel với template chuyên nghiệp
- Tự động áp dụng header/footer từ template
- Hỗ trợ nhiều loại báo cáo:
  - Báo cáo tháng (BCTHANG)
  - Báo cáo dầu tồn (DAUTON)
  - Báo cáo tính dầu (IN TINH DAU)

### 👥 Quản Lý Người Dùng
- Hệ thống đăng nhập/đăng xuất
- Phân quyền admin/user
- Quản lý mật khẩu

### 🛠️ Quản Trị
- Quản lý thông tin tàu
- Quản lý cây xăng
- Quản lý tuyến đường
- Quản lý loại hàng
- Quản lý điểm đến/đi

## 💻 Yêu Cầu Hệ Thống

- **PHP**: >= 7.4
- **Web Server**: Apache/Nginx
- **Extensions**: 
  - `php-xml`
  - `php-zip`
  - `php-gd`
  - `php-mbstring`
- **Composer**: >= 2.0 (để cài đặt dependencies)

## 🚀 Cài Đặt

### Cài Đặt Với XAMPP (Windows)

1. **Tải và cài đặt XAMPP**
   ```bash
   # Tải từ https://www.apachefriends.org/
   ```

2. **Clone repository**
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/alexmerex/TinhDau.git tinh-dau-2
   cd tinh-dau-2
   ```

3. **Cài đặt dependencies**
   ```bash
   composer install
   ```

   Lưu ý:
   - Nếu `vendor/` đã tồn tại sẵn (đã commit), bạn vẫn nên chạy `composer install` để đảm bảo đủ thư viện.
   - **Không commit `vendor/`**, chỉ commit `composer.lock` để cố định phiên bản thư viện.

4. **Cấu hình quyền truy cập**
   - Đảm bảo thư mục `data/` có quyền ghi
   - Trên Windows: Thường không cần cấu hình thêm

5. **Khởi động Apache từ XAMPP Control Panel**

6. **Truy cập hệ thống**
   ```
   http://localhost/tinh-dau-2/
   ```

### Cài Đặt Với Linux/MacOS

1. **Clone repository**
   ```bash
   git clone https://github.com/alexmerex/TinhDau.git
   cd TinhDau
   ```

2. **Cài đặt dependencies**
   ```bash
   composer install
   ```

3. **Cấu hình quyền**
   ```bash
   chmod -R 755 data/
   chown -R www-data:www-data data/
   ```

4. **Cấu hình web server**
   - Apache: Tạo virtual host hoặc symlink
   - Nginx: Cấu hình root directory

### Tạo Tài Khoản Admin

Sau khi cài đặt, tạo tài khoản admin đầu tiên:

```bash
php create_admin.php
```

Hoặc chỉnh sửa trực tiếp file `data/users.csv`:

```csv
username,password_hash,role,created_at
admin,$2y$10$...,admin,2025-01-01 00:00:00
```

**Mật khẩu mặc định:** `admin123` (nên đổi sau lần đăng nhập đầu)

## 📖 Sử Dụng

### Đăng Nhập

1. Truy cập: `http://localhost/tinh-dau-2/`
2. Đăng nhập với:
   - **Username**: `admin`
   - **Password**: `admin123`

### Tính Toán Nhiên Liệu

1. Chọn **Tàu** từ dropdown
2. Nhập **Số chuyến** hoặc tạo chuyến mới
3. Chọn **Điểm đi** và **Điểm đến**
4. Nhập **Khối lượng vận chuyển** (tấn)
5. Chọn **Loại hàng**
6. Click **Tính toán**
7. Xem kết quả và **Lưu** nếu cần

### Xuất Báo Cáo

1. Vào trang **Lịch Sử** (`lich_su.php`)
2. Chọn **Tháng** và **Năm** cần xuất
3. Click **Xuất Excel**
4. File sẽ được tải về: `BCTHANG_T[MM]_[YYYY].xlsx`

### Quản Lý Dầu Tồn

1. Vào **Quản lý dầu tồn** (`quan_ly_dau_ton.php`)
2. Xem danh sách dầu tồn theo từng tàu
3. Thêm/Xóa/Sửa dầu tồn
4. Chuyển dầu giữa các tàu
5. Xuất báo cáo dầu tồn

## 💾 Quản Lý Dữ Liệu (CSV)

Hệ thống sử dụng các file CSV trong thư mục `data/` để lưu trữ toàn bộ dữ liệu vận hành, bao gồm:
- `users.csv`: Tài khoản người dùng
- `tau_phan_loai.csv`: Thông tin và hệ số của tàu
- `dau_ton.csv`: Dữ liệu dầu tồn
- `tuyen_duong_log.csv`: Lịch sử các tuyến đường
- ... và các file dữ liệu khác.

### 🚨 Lưu ý quan trọng:
- **Đây là dữ liệu production**: Các file này chứa dữ liệu thật của hệ thống.
- **Backup thường xuyên**: Nên backup thư mục `data/` định kỳ để tránh mất mát dữ liệu.
- **Commit cẩn thận**: Khi commit các file trong `data/`, hãy chắc chắn rằng bạn muốn cập nhật dữ liệu đó lên repository. Cân nhắc việc chỉ commit thay đổi cấu trúc hoặc dữ liệu mẫu, không commit dữ liệu nhạy cảm hoặc thay đổi liên tục.

## 📁 Cấu Trúc Dự Án

```
TinhDau/
├── admin/                  # Admin panel
│   ├── bao_cao_dau_ton.php
│   ├── quan_ly_cay_xang.php
│   ├── quan_ly_dau_ton.php
│   ├── quan_ly_tau.php
│   ├── quan_ly_tuyen_duong.php
│   └── quan_ly_user.php
│
├── ajax/                   # AJAX endpoints
│   ├── get_trip_details.php
│   └── get_trips.php
│
├── api/                    # REST API endpoints
│   ├── insert_trip.php
│   ├── reorder_segments.php
│   ├── update_transfer.php
│   └── ...
│
├── assets/                 # Static assets
│   ├── css/
│   ├── js/
│   └── images/
│
├── auth/                   # Authentication
│   ├── login.php
│   ├── logout.php
│   └── check_auth.php
│
├── config/                 # Configuration
│   ├── database.php
│   └── report_header_registry.php
│
├── data/                   # CSV data storage
│   ├── users.csv
│   ├── tau_phan_loai.csv
│   ├── dau_ton.csv
│   └── ...
│
├── docs/                   # Documentation
│   └── README.md           # [Tài liệu kỹ thuật chi tiết](docs/README.md)
│
├── includes/               # Shared includes
│   ├── header.php
│   ├── footer.php
│   ├── excel_export_full.php
│   └── helpers.php
│
├── models/                 # Data models
│   ├── User.php
│   ├── TauPhanLoai.php
│   ├── DauTon.php
│   ├── TinhToanNhienLieu.php
│   └── ...
│
├── src/                    # Source code
│   └── Report/
│       └── HeaderTemplate.php
│
├── template_header/        # Excel templates
│   ├── sample_header_BCTHANG.xlsx
│   ├── sample_header_DAUTON.xlsx
│   └── ...
│
├── vendor/                 # Composer dependencies
│
├── composer.json           # Composer config
├── index.php              # Entry point
├── lich_su.php            # History page
└── README.md              # This file
```

## 📚 Tài Liệu Kỹ Thuật

Để xem tài liệu kỹ thuật chi tiết về kiến trúc, module, và các ghi chú kỹ thuật, vui lòng xem:

👉 **[docs/README.md](docs/README.md)** - Tài liệu kỹ thuật nội bộ

Tài liệu này bao gồm:
- Kiến trúc hệ thống chi tiết
- Mô tả từng module và flow xử lý
- Dữ liệu tham chiếu và cấu trúc CSV
- Lịch sử khắc phục lỗi
- Ghi chú kỹ thuật và best practices

## 🔌 API Documentation

### REST API Endpoints

#### 1. Tạo Chuyến Mới

**POST** `/api/insert_trip.php`

```json
{
  "ten_phuong_tien": "TAU_001",
  "so_chuyen": 1,
  "diem_di": "DIEM_A",
  "diem_den": "DIEM_B",
  "khoi_luong_van_chuyen_t": 1000,
  "ngay_di": "2025-01-15",
  "loai_hang": "XI_MANG"
}
```

**Response:**
```json
{
  "success": true,
  "trip_id": "TRIP_20250115_001",
  "message": "Chuyến đã được tạo thành công"
}
```

#### 2. Sắp Xếp Lại Đoạn Tuyến

**POST** `/api/reorder_segments.php`

```json
{
  "trip_id": "TRIP_20250115_001",
  "segments": [
    {"id": "seg_1", "order": 1},
    {"id": "seg_2", "order": 2}
  ]
}
```

#### 3. Cập Nhật Chuyển Dầu

**POST** `/api/update_transfer.php`

```json
{
  "transfer_id": "TRANSFER_001",
  "tu_tau": "TAU_001",
  "den_tau": "TAU_002",
  "so_luong": 500,
  "ngay_chuyen": "2025-01-15"
}
```

### AJAX Endpoints

#### Lấy Danh Sách Chuyến

**GET** `/ajax/get_trips.php`

**Parameters:**
- `ten_phuong_tien`: Tên tàu
- `month`: Tháng (MM)
- `year`: Năm (YYYY)

**Response:**
```json
{
  "trips": [
    {
      "trip_id": "TRIP_001",
      "so_chuyen": 1,
      "diem_di": "DIEM_A",
      "diem_den": "DIEM_B",
      "khoi_luong": 1000
    }
  ]
}
```

## 🧪 Testing

Hiện tại dự án **không kèm bộ test tự động** trong repo vận hành.
Nếu bạn cần bổ sung PHPUnit/tests sau này, hãy tạo thư mục `tests/` và cấu hình `phpunit.xml` phù hợp.

## 🤝 Đóng Góp

Chúng tôi hoan nghênh mọi đóng góp! Vui lòng làm theo các bước sau:

### Quy Trình Đóng Góp

1. **Fork** repository
2. **Tạo branch** mới (`git checkout -b feature/AmazingFeature`)
3. **Commit** thay đổi (`git commit -m 'Add some AmazingFeature'`)
4. **Push** lên branch (`git push origin feature/AmazingFeature`)
5. **Tạo Pull Request**

### Code Standards

- **PSR-12**: Coding style
- **PHPDoc**: Đầy đủ documentation cho functions/classes
- **Testing**: Viết unit tests cho mọi feature mới
- **Git**: Sử dụng conventional commits

### Báo Cáo Lỗi

Khi báo cáo lỗi, vui lòng cung cấp:
- Mô tả chi tiết vấn đề
- Các bước để reproduce
- Môi trường (PHP version, OS, etc.)
- Screenshots (nếu có)

## 📄 License

Dự án được phân phối dưới giấy phép **MIT License**. Xem file [LICENSE](LICENSE) để biết thêm chi tiết.

```
MIT License

Copyright (c) 2025 TinhDau Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## 📧 Liên Hệ

- **GitHub**: [@alexmerex](https://github.com/alexmerex)
- **Repository**: [https://github.com/alexmerex/TinhDau](https://github.com/alexmerex/TinhDau)
- **Issues**: [GitHub Issues](https://github.com/alexmerex/TinhDau/issues)
- **Email**: khoapham491@gmail.com

## 🙏 Cảm Ơn

Dự án này được xây dựng với sự hỗ trợ của:

- [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) - Excel export library
- [PHPUnit](https://phpunit.de/) - Testing framework
- [Composer](https://getcomposer.org/) - Dependency management

## 📊 Thống Kê Dự Án

- **Ngôn ngữ chính**: PHP (97.4%)
- **Framework**: Vanilla PHP
- **Storage**: CSV files
- **Dependencies**: PhpSpreadsheet, PHPUnit

## 🗺️ Roadmap

### Version 2.0 (Q2 2025)
- [ ] Migration sang MySQL/PostgreSQL
- [ ] REST API đầy đủ với authentication
- [ ] Mobile responsive design
- [ ] Real-time notifications
- [ ] Multi-language support (EN/VI)

### Version 1.5 (Q1 2025)
- [x] PHPUnit test suite
- [x] API endpoints cơ bản
- [x] Documentation đầy đủ
- [ ] Docker support
- [ ] CI/CD pipeline

### Version 1.4 (Hiện tại)
- [x] Excel export với templates
- [x] Page setup tự động
- [x] Quản lý dầu tồn nâng cao
- [x] Chuyển dầu giữa các tàu
- [x] UX enhancements

---

**⭐ Nếu dự án hữu ích, hãy star repository này!**

Made with ❤️ by [WokuShop Team](https://github.com/alexmerex)

