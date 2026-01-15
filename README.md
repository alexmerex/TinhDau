# 🚢 **TinhDau** – Hệ Thống Tính Và Quản Lý Nhiên Liệu Tàu

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Project Status](https://img.shields.io/badge/status-v1.3.8-success.svg)](#%EF%B8%8F-phi%C3%AAn-b%E1%BA%A3n-hi%E1%BB%87n-t%E1%BA%A1i)

> Hệ thống **TinhDau** hỗ trợ quản lý, theo dõi, và tính toán nhiên liệu tiêu thụ cho đội tàu vận chuyển xi măng _Hà Tiên_. Ứng dụng được xây dựng trên **Vanilla PHP** (không framework) với kiến trúc module hoá, sử dụng **CSV** làm kho lưu trữ dữ liệu nhằm đơn giản hoá triển khai.

---

## 📑 Mục Lục

- [Giới Thiệu](#-giới-thiệu)
- [Tính Năng Chính](#-tính-năng-chính)
- [Công Nghệ & Phụ Thuộc](#-công-nghệ--phụ-thuộc)
- [Yêu Cầu Hệ Thống](#-yêu-cầu-hệ-thống)
- [Hướng Dẫn Cài Đặt](#-hướng-dẫn-cài-đặt)
  - [Windows (XAMPP)](#windows-xampp)
  - [Linux / MacOS](#linux--macos)
- [Sử Dụng Nhanh](#-sử-dụng-nhanh)
- [Cấu Trúc Dự Án](#-cấu-trúc-dự-án)
- [API](#-api)
- [Quản Lý Dữ Liệu CSV](#-quản-lý-dữ-liệu-csv)
- [Đóng Góp](#-đóng-góp)
- [Roadmap](#-roadmap)
- [License](#-license)

---

## 🎯 Giới Thiệu

TinhDau nhằm thay thế tính toán thủ công bằng Excel, cung cấp quy trình **minh bạch** và **chính xác** cho:

- Theo dõi dầu tồn kho từng tàu ⛽️
- Tính toán nhiên liệu tiêu thụ theo quãng đường & khối lượng 📈
- Xuất báo cáo Excel với template chuẩn hoá 📄
- Quản lý người dùng, phân quyền & nhật ký hoạt động 🔐

Phiên bản hiện tại **v1.3.8** đang triển khai thực tế và được bảo trì thường xuyên.

## ✨ Tính Năng Chính

- **Tính toán nhiên liệu tự động** theo hệ số tàu, quãng đường (đa segment) & loại hàng.
- **Quản lý dầu tồn**: nhập – xuất – chuyển dầu giữa các tàu và thống kê theo thời gian.
- **Báo cáo Excel** (PhpSpreadsheet) với header/footer tuỳ biến.
- **Phân quyền**: *admin* / *user*; hỗ trợ đổi mật khẩu.
- **REST API & AJAX** cho frontend và bên thứ ba.
- **Lịch sử truy vết**: ghi lại mọi phép tính & thao tác dữ liệu.

## 🛠 Công Nghệ & Phụ Thuộc

| Thành phần | Phiên bản |
|------------|-----------|
| PHP        | >= 7.4    |
| Composer   | >= 2.0    |
| PhpSpreadsheet | ^1.29 |
| PHPUnit *(tùy chọn)* | ^10 |

> Lưu ý: Dữ liệu được lưu dưới dạng **CSV** nên **không cần** máy chủ CSDL; tuy nhiên Roadmap 2.0 sẽ chuyển sang MySQL/PostgreSQL.

## 💻 Yêu Cầu Hệ Thống

- Apache/Nginx hoặc XAMPP/Laragon (Windows)
- Tiện ích mở rộng PHP bắt buộc: `xml`, `zip`, `gd`, `mbstring`
- Quyền ghi thư mục `data/` (để lưu *.csv* & file Excel sinh ra)

## 🚀 Hướng Dẫn Cài Đặt

### Windows (XAMPP)

```bash
# 1. Cài XAMPP ≥ 7.4 (https://www.apachefriends.org/)
# 2. Clone source vào htdocs
cd C:\xampp\htdocs
git clone https://github.com/<your-org>/tinh-dau.git tinh-dau-2
cd tinh-dau-2
# 3. Cài dependecies
composer install
```

Mở **XAMPP Control Panel** → bật _Apache_. Truy cập: <http://localhost/tinh-dau-2>

### Linux / MacOS

```bash
# Clone & cài đặt
git clone https://github.com/<your-org>/tinh-dau.git
cd tinh-dau
composer install
# Cấp quyền ghi cho data/
chmod -R 775 data
```

Thiết lập **VirtualHost** (Apache) hoặc **server block** (Nginx) trỏ tới thư mục gốc dự án.

---

## ⚡️ Sử Dụng Nhanh

1. **Đăng nhập** với tài khoản `admin / admin123` (tạo lần đầu trong `data/users.csv`).
2. Vào **Tính Nhiên Liệu** → chọn tàu, điểm đi/đến, khối lượng, click **Tính**.
3. Vào **Báo Cáo** để **Xuất Excel** (`BCTHANG_*` hoặc `DAUTON_*`).

> Thao tác chi tiết hơn xem tại [docs/README.md](docs/README.md).

---

## 📁 Cấu Trúc Dự Án

```text
├── admin/          # Trang quản trị (UI PHP thuần)
├── ajax/           # AJAX endpoints (JSON)
├── api/            # REST API (POST/GET)
├── assets/         # JS/CSS/Images tĩnh
├── auth/           # Xác thực & phân quyền
├── config/         # Hằng số & file cấu hình
├── data/           # ***CSV production data***
├── docs/           # Tài liệu kỹ thuật nội bộ
├── includes/       # Helper chung & template export
├── models/         # Lớp PHP mô phỏng DB
├── src/            # Mã nguồn thuần PHP khác
├── template_header/# Excel templates (xlsx)
└── tests/          # (tuỳ chọn) Unit tests
```

---

## 🔌 API

Ví dụ **Insert Trip** (`POST /api/insert_trip.php`):

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

_Toàn bộ danh sách endpoint xem tại [docs/API.md](docs/API.md) (đang cập nhật)._ 

---

## 💾 Quản Lý Dữ Liệu CSV

- **data/** chứa **tất cả** dữ liệu sản xuất. Mỗi file đại diện 1 bảng.
- Sao lưu định kỳ; tránh commit dữ liệu nhạy cảm.
- Khi thay đổi **schema CSV** phải cập nhật `models/` & docs.

---

## 🤝 Đóng Góp

1. Fork → Branch (`feat/<tên>`) → Commit (conventional) → PR.
2. Code style **PSR-12**, comment PHPDoc.
3. Viết unit test (nếu thêm logic) và chạy `composer test`.
4. Thảo luận qua **GitHub Issues** / Discussions.

---

## 🗺 Roadmap

| Phiên bản | Trạng thái | Nội dung |
|-----------|-----------|----------|
| 1.4       | _current_ | Excel export, quản lý dầu tồn nâng cao |
| 1.5       | 🚧        | Docker, CI/CD, test suite |
| 2.0       | 🧭        | Database SQL, đa ngôn ngữ, responsive UI |

---

## 📜 License

TinhDau được phát hành dưới giấy phép **MIT**. Xem chi tiết trong [LICENSE](LICENSE).

> Made with ❤️ by **WokuShop Team**