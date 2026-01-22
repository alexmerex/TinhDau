# 🚢 TinhDau – Hệ thống tính & quản lý nhiên liệu tàu (CSV-first)

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

TinhDau là ứng dụng **Vanilla PHP** phục vụ nghiệp vụ **tính toán nhiên liệu**, **quản lý dầu tồn**, và **xuất báo cáo Excel** cho đội tàu vận hành. Hệ thống lưu trữ dữ liệu bằng **CSV/JSON file** trong thư mục `data/` để dễ triển khai trong môi trường nội bộ (XAMPP/Apache).

---

## 📌 Tổng quan nhanh

- **Entry point chính**: `index.php` (yêu cầu đăng nhập).
- **Dữ liệu vận hành**: `data/*.csv` và một số `data/*.json`.
- **Tính toán nhiên liệu**: `models/TinhToanNhienLieu.php`.
- **Lưu kết quả / lịch sử chuyến**: `models/LuuKetQua.php` → `data/ket_qua_tinh_toan.csv`.
- **Quản lý dầu tồn**: `models/DauTon.php` và UI `quan_ly_dau_ton.php` / `admin/quan_ly_dau_ton.php`.
- **Báo cáo Excel**: `includes/` + templates `template_header/` (dựa trên PhpSpreadsheet).

---

## 📑 Mục lục

- [Yêu cầu hệ thống](#-yêu-cầu-hệ-thống)
- [Cài đặt](#-cài-đặt)
  - [Windows (XAMPP)](#windows-xampp)
  - [Linux/MacOS](#linuxmacos)
- [Cấu hình](#-cấu-hình)
- [Tài khoản & phân quyền](#-tài-khoản--phân-quyền)
- [Dữ liệu (CSV/JSON)](#-dữ-liệu-csvjson)
- [Chức năng chính](#-chức-năng-chính)
- [API & AJAX endpoints](#-api--ajax-endpoints)
- [Debug & Logging](#-debug--logging)
- [Cấu trúc thư mục](#-cấu-trúc-thư-mục)
- [Vận hành & an toàn dữ liệu](#-vận-hành--an-toàn-dữ-liệu)
- [Đóng góp](#-đóng-góp)
- [License](#-license)

---

## 🏢 Tình trạng triển khai thực tế

Hệ thống hiện đang được đưa vào sử dụng trong nghiệp vụ thực tế tại **phòng Kỹ thuật Vật tư** của **VICEM**.

- **Phạm vi**: tính toán nhiên liệu, theo dõi dầu tồn và xuất báo cáo phục vụ vận hành.
- **Lưu ý**: Repo này đã được chuẩn hoá để **không** đẩy dữ liệu vận hành lên GitHub (xem mục [Vận hành & an toàn dữ liệu](#-vận-hành--an-toàn-dữ-liệu)).

---

## ✅ Yêu cầu hệ thống

- **PHP**: >= 7.4
- **Web server**: Apache (khuyến nghị XAMPP trên Windows)
- **Extensions**:
  - `xml`
  - `zip`
  - `gd`
  - `mbstring`
- **Composer**: để cài `phpoffice/phpspreadsheet`

---

## 🚀 Cài đặt

### Windows (XAMPP)

1. Copy/clone dự án vào:

```text
C:\xampp\htdocs\tinh-dau-2
```

2. Cài dependency:

```bash
composer install
```

3. Bật Apache trong XAMPP, truy cập:

```text
http://localhost/tinh-dau-2/
```

### Linux/MacOS

1. Clone + cài dependency:

```bash
git clone https://github.com/alexmerex/TinhDau.git
cd TinhDau
composer install
```

2. Cấp quyền ghi cho `data/`:

```bash
chmod -R 775 data
```

---

## ⚙️ Cấu hình

- **Cấu hình hằng số & đường dẫn file dữ liệu**: `config/database.php`
  - `HE_SO_TAU_FILE`: `bang_he_so_tau_cu_ly_full_v2.csv`
  - `KHOA_CACH_FILE`: `khoang_duong.csv`
  - `KET_QUA_DIR`: `data/`
  - `KET_QUA_FILE`: `data/ket_qua_tinh_toan.csv`
  - `VERSION`: hiện đang là `1.3.8`

- **Cấu hình debug/logging**: `config/debug.php`
  - `DEBUG_MODE` (development/prod)
  - `LOG_LEVEL`
  - `LOG_FILE` mặc định: `data/debug.log`

---

## 👤 Tài khoản & phân quyền

- Hệ thống có module đăng nhập tại `auth/`.
- File dữ liệu user: `data/users.csv`.
- Model quản lý user: `models/User.php`.

### Tạo tài khoản admin lần đầu

Hiện tại dự án **không có** script `create_admin.php`.

Cách đơn giản nhất là thêm trực tiếp vào `data/users.csv` (hoặc dùng UI quản trị nếu đã có admin):

- Password được hash bằng `password_hash()`.
- Các cột theo `models/User.php`:

```text
id,username,password,full_name,role,status,created_at,updated_at
```

---

## 💾 Dữ liệu (CSV/JSON)

Thư mục `data/` là nơi lưu **dữ liệu vận hành**. Một số file chính:

- `users.csv`: người dùng
- `tau_phan_loai.csv`: phân loại tàu
- `cay_xang.csv`: danh mục cây xăng
- `loai_hang.csv`: danh mục loại hàng
- `tuyen_duong_log.csv`: log tuyến đường
- `dau_ton.csv` (+ `dau_ton_2.csv`): dữ liệu dầu tồn
- `ket_qua_tinh_toan.csv` (+ `ket_qua_tinh_toan_2.csv`): lịch sử kết quả tính
- `order_overrides.json`, `transfer_overrides.json`: cấu hình/override phục vụ sắp xếp/chuyển dầu

**Lưu ý quan trọng**:

- `data/` thường chứa dữ liệu thật. Trước khi push/public repo cần rà soát dữ liệu nhạy cảm.
- Nên backup định kỳ `data/`.

---

## ✨ Chức năng chính

- **Tính toán nhiên liệu**
  - UI chính: `index.php`
  - Hỗ trợ tuyến nhiều đoạn (multi-segment), đổi lệnh đa điểm
  - Hỗ trợ nhập ngày theo định dạng VN và parse qua helper `parse_date_vn()`

- **Quản lý dầu tồn**
  - Trang nghiệp vụ: `quan_ly_dau_ton.php`
  - Khu vực admin: `admin/quan_ly_dau_ton.php`

- **Báo cáo Excel**
  - Sử dụng `phpoffice/phpspreadsheet`
  - Template header trong `template_header/`
  - Logic export nằm chủ yếu ở `includes/`

---

## 📝 Cập nhật gần đây

- **Chuẩn hóa báo cáo cho lệnh “Cấp thêm”**
  - Khi xuất báo cáo, dòng `Cấp thêm` ưu tiên lấy đúng giá trị người dùng nhập ở `so_luong_cap_them_lit` (chỉ fallback sang `dau_tinh_toan_lit` nếu trống/0) để tránh lệch số liệu.

- **Quy tắc phân loại dầu ma nơ**
  - Các lệnh `Cấp thêm` có lý do chứa `qua cầu` / `rô đai` / `vệ sinh` (kể cả không dấu: `qua cau`, `ro dai`, `ve sinh`) được tính vào **dầu sử dụng không hàng (KH)**.
  - Quy tắc này được áp dụng nhất quán trong báo cáo tổng hợp (**BC TH**) và báo cáo dầu tồn theo tháng (**DAUTON**).

---

## 🔌 API & AJAX endpoints

Thư mục `api/` và `ajax/` cung cấp các endpoint phục vụ UI.

Một số endpoint tiêu biểu:

- `api/insert_trip.php`: tạo chuyến
- `api/reorder_segments.php`: sắp xếp lại các đoạn tuyến
- `api/update_transfer.php`: cập nhật chuyển dầu
- `api/search_diem.php`: tìm điểm
- `ajax/get_trips.php`: lấy danh sách chuyến theo tàu/tháng/năm
- `ajax/get_trip_details.php`: chi tiết chuyến

> Danh sách đầy đủ xem trong thư mục `api/` và `ajax/` (hiện repo chưa có file `docs/API.md`).

---

## 🧰 Debug & Logging

- Cấu hình tại `config/debug.php`.
- Helper debug nằm trong `includes/helpers.php` (ví dụ: `debug_log()`, `debug_request()`, `debug_exception()`, `ddd()`).

Khuyến nghị:

- **Production**: đặt `DEBUG_MODE=false`, `LOG_LEVEL='ERROR'`.
- Không commit file log/CSV dữ liệu nếu repo public.

---

## 📁 Cấu trúc thư mục

```text
.
├── admin/                # UI quản trị
├── ajax/                 # AJAX endpoints (JSON)
├── api/                  # API endpoints
├── assets/               # CSS/JS/Images
├── auth/                 # đăng nhập/đăng xuất/phân quyền
├── backup/               # (tuỳ môi trường)
├── config/               # database.php, debug.php, ...
├── data/                 # CSV/JSON storage (dữ liệu vận hành)
├── docs/                 # tài liệu nội bộ
├── includes/             # helpers, export excel, layout
├── models/               # các model thao tác CSV
├── src/                  # module phụ trợ (Report/...)
├── template_header/      # Excel templates
├── vendor/               # composer dependencies
├── composer.json
└── index.php
```

---

## 🛡 Vận hành & an toàn dữ liệu

- **Không khuyến nghị commit** dữ liệu thật trong `data/` lên repo public.
- Nếu dùng GitHub để backup nội bộ, cân nhắc:
  - Tách dữ liệu production sang thư mục ngoài repo
  - Hoặc dùng `.gitignore` cho `data/*.csv`, `data/*.log`, `data/*.json` (tuỳ chính sách)

---

## 🤝 Đóng góp

- Codebase là PHP thuần, ưu tiên thay đổi nhỏ và kiểm thử trực tiếp luồng nghiệp vụ.
- Nếu bổ sung test: hiện chưa có `tests/`/`phpunit.xml` trong dự án (file `.phpunit.result.cache` nếu có nên được ignore).

---

## 📜 License

MIT License. Xem [LICENSE](LICENSE).
