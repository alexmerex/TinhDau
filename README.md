# TINHDAU v1.4.0

Hệ thống **tính định mức nhiên liệu**, **quản lý chuyến tàu**, **theo dõi dầu tồn** và **xuất báo cáo** phục vụ công tác vận hành nội bộ — Công ty Cổ phần Logistics VICEM.

---

## Mục lục

1. [Tổng quan](#1-tổng-quan)
2. [Kiến trúc & công nghệ](#2-kiến-trúc--công-nghệ)
3. [Cấu trúc thư mục](#3-cấu-trúc-thư-mục)
4. [Cài đặt & triển khai](#4-cài-đặt--triển-khai)
5. [Các màn hình chính](#5-các-màn-hình-chính)
6. [Hệ thống phân quyền](#6-hệ-thống-phân-quyền)
7. [Models & dữ liệu](#7-models--dữ-liệu)
8. [API Endpoints](#8-api-endpoints)
9. [Quy trình nghiệp vụ](#9-quy-trình-nghiệp-vụ)
10. [Lưu ý quan trọng](#10-lưu-ý-quan-trọng)
11. [Lịch sử thay đổi](#11-lịch-sử-thay-đổi)
12. [Bản quyền](#12-bản-quyền)

---

## 1. Tổng quan

TINHDAU giúp đơn vị:

- **Tính nhanh nhiên liệu** theo tuyến đường, hệ số tàu và thông số chuyến.
- **Quản lý hành trình** — các đoạn trong từng chuyến, sắp xếp và di chuyển đoạn giữa các chuyến.
- **Xử lý tình huống phát sinh** — đổi lệnh (nhiều điểm), cấp thêm dầu (ma nơ, qua cầu, rô đai + vệ sinh, bơm nước...).
- **Quản lý dầu tồn** theo tháng, theo tàu, bao gồm điều chuyển giữa các tàu.
- **Xuất báo cáo Excel** — báo cáo tổng hợp, báo cáo tháng, báo cáo dầu tồn, in tính dầu (có header template tùy chỉnh).

---

## 2. Kiến trúc & công nghệ

| Thành phần | Chi tiết |
|---|---|
| **Ngôn ngữ** | PHP >= 7.4 |
| **Lưu trữ** | File CSV + JSON (không sử dụng SQL database) |
| **Thư viện PHP** | [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) ^1.29 (xuất Excel) |
| **Frontend** | Bootstrap 5.3, Font Awesome 6, Flatpickr (date picker), Google Fonts Inter |
| **Xác thực** | PHP Session, dữ liệu user lưu trong CSV |
| **Web server** | Apache (XAMPP) hoặc tương đương |
| **Autoload** | Composer PSR-4 (`App\Models\` → `models/`) |

---

## 3. Cấu trúc thư mục

```
tinh-dau-2/
├── index.php                  # Trang tính toán nhiên liệu (trang chủ)
├── lich_su.php                # Lịch sử & xuất báo cáo
├── quan_ly_dau_ton.php        # Quản lý dầu tồn (user)
├── danh_sach_diem.php         # Danh sách điểm & tuyến đường
├── danh_sach_tau.php          # Danh sách tàu & hệ số
│
├── auth/                      # Xác thực
│   ├── login.php              # Đăng nhập
│   ├── logout.php             # Đăng xuất
│   ├── change_password.php    # Đổi mật khẩu
│   ├── check_auth.php         # Middleware: yêu cầu đăng nhập
│   ├── check_admin.php        # Middleware: yêu cầu quyền admin
│   └── auth_helper.php        # Session helper (isLoggedIn, isAdmin...)
│
├── admin/                     # Khu vực quản trị
│   ├── index.php              # Dashboard thống kê
│   ├── quan_ly_tau.php        # CRUD tàu & hệ số
│   ├── quan_ly_tuyen_duong.php# CRUD tuyến đường
│   ├── quan_ly_loai_hang.php  # CRUD loại hàng
│   ├── quan_ly_cay_xang.php   # CRUD cây xăng
│   ├── quan_ly_dau_ton.php    # Quản lý dầu tồn (admin)
│   ├── bao_cao_dau_ton.php    # Báo cáo dầu tồn + xuất Excel
│   └── quan_ly_user.php       # Quản lý người dùng (admin-only)
│
├── api/                       # REST API (JSON)
│   ├── get_distance.php       # Lấy khoảng cách 2 điểm
│   ├── get_tuyen_duong.php    # Danh sách tuyến đường
│   ├── search_diem.php        # Tìm kiếm điểm
│   ├── get_ma_chuyen.php      # Lấy mã chuyến theo tàu
│   ├── get_loai_hang.php      # Danh sách loại hàng
│   ├── add_loai_hang.php      # Thêm loại hàng mới
│   ├── preview_calculation.php# Xem trước kết quả tính toán
│   ├── update_segment.php     # Cập nhật đoạn chuyến
│   ├── update_thang_bao_cao.php # Cập nhật tháng báo cáo
│   ├── insert_trip.php        # Thêm chuyến mới
│   ├── delete_trip.php        # Xóa chuyến
│   ├── move_segment.php       # Di chuyển đoạn giữa chuyến
│   ├── reorder_segments.php   # Sắp xếp lại thứ tự đoạn
│   ├── update_tinh_chinh.php  # Cập nhật tinh chỉnh
│   ├── delete_dau_ton.php     # Xóa bản ghi dầu tồn
│   ├── update_cay_xang.php    # Cập nhật cây xăng
│   ├── save_order_overrides.php # Lưu thứ tự tùy chỉnh
│   ├── update_transfer.php    # Cập nhật điều chuyển dầu
│   └── delete_transfer.php    # Xóa điều chuyển dầu
│
├── ajax/                      # AJAX helper
│   ├── get_trips.php          # Danh sách chuyến theo tàu
│   └── get_trip_details.php   # Chi tiết đoạn của chuyến
│
├── models/                    # Business logic
│   ├── TinhToanNhienLieu.php  # Công thức tính nhiên liệu
│   ├── LuuKetQua.php          # Đọc/ghi kết quả tính toán
│   ├── KhoangCach.php         # Khoảng cách giữa các điểm
│   ├── HeSoTau.php            # Hệ số nhiên liệu theo tàu
│   ├── TauPhanLoai.php        # Phân loại tàu (công ty/thuê ngoài)
│   ├── DauTon.php             # Dầu tồn, tiêu thụ, điều chuyển
│   ├── CayXang.php            # Danh sách cây xăng
│   ├── LoaiHang.php           # Danh sách loại hàng
│   ├── User.php               # Người dùng & xác thực
│   └── Logger.php             # Ghi log hệ thống
│
├── includes/                  # Shared components
│   ├── header.php / footer.php# Layout chung (navbar, scripts)
│   ├── helpers.php            # Hàm tiện ích (ngày, chuỗi...)
│   ├── excel_export_full.php  # Xuất Excel đầy đủ (PhpSpreadsheet)
│   ├── excel_export_wrapper.php
│   ├── excel_helper.php
│   ├── excel_xml_helper.php
│   ├── add_header_to_sheet.php
│   ├── add_logo_to_excel.php
│   └── header_template_applier.php
│
├── config/
│   ├── database.php           # Đường dẫn CSV, hằng số hệ thống
│   ├── debug.php              # Cấu hình debug & logging
│   └── report_header_registry.php # Mapping header Excel theo loại báo cáo
│
├── src/Report/
│   └── HeaderTemplate.php     # PSR-4: App\Report — resolve header template
│
├── assets/
│   ├── ux-enhancements.js     # Client-side validation & UX
│   └── ux-enhancements.css    # CSS bổ sung
│
├── data/                      # Dữ liệu CSV/JSON runtime
│   ├── *.sample.csv           # Template CSV mẫu (tracked)
│   ├── *.sample.json          # Template JSON mẫu (tracked)
│   └── (*.csv, *.json, *.log) # Dữ liệu vận hành — gitignored
├── docs/
│   └── API.md                 # Tài liệu API chi tiết
├── template_header/           # Template header Excel (.xlsx)
├── composer.json
├── LICENSE
└── vendor/                    # Composer dependencies
```

---

## 4. Cài đặt & triển khai

### Yêu cầu

- PHP >= 7.4 (khuyến nghị 8.0+)
- Apache với `mod_rewrite` (XAMPP hoặc tương đương)
- Composer

### Các bước

```bash
# 1. Clone repository
git clone <repo-url> /path/to/htdocs/tinh-dau-2

# 2. Cài đặt dependencies
cd /path/to/htdocs/tinh-dau-2
composer install

# 3. Đảm bảo thư mục data/ có quyền ghi
chmod -R 775 data/

# 4. Truy cập qua trình duyệt
# http://localhost/tinh-dau-2/
```

### Cấu hình

- **Hệ thống**: `config/database.php` — đường dẫn CSV, tên site, phiên bản, phân trang.
- **Debug**: `config/debug.php` — bật/tắt debug mode, cấp độ log, xoay log file.
- **Báo cáo Excel**: `config/report_header_registry.php` — ánh xạ loại báo cáo → file template header.

---

## 5. Các màn hình chính

### 5.1 Trang tính toán (`index.php`) — Trang chủ

- Chọn tàu, mã chuyến, tháng báo cáo.
- Nhập tuyến đường (điểm đi/đến), khối lượng, ngày đi/đến/dỡ xong.
- Xử lý **đổi lệnh** (nhiều điểm trung gian khi phát sinh).
- Nhập **cấp thêm dầu** (bơm nước, ma nơ, qua cầu, rô đai + vệ sinh).
- **Nhiều lệnh Ma nơ trong 1 chuyến**: thêm/xóa động các lệnh cấp thêm bổ sung tại nhiều địa điểm khác nhau, lưu cùng mã chuyến.
- **Gợi ý chọn nhanh địa điểm Ma nơ** khi chuyến đã có từ 2 địa điểm Ma nơ trở lên.
- Tính toán → xem kết quả → lưu kết quả.
- Xem/quản lý các đoạn trong chuyến hiện tại.

### 5.2 Lịch sử (`lich_su.php`)

- Tra cứu dữ liệu đã lưu theo tàu, chuyến, thời gian.
- Xuất báo cáo Excel (tổng hợp, theo tháng, in tính dầu).
- Hỗ trợ đối soát và kiểm tra lại thông tin.

### 5.3 Quản lý dầu tồn (`quan_ly_dau_ton.php`)

- Nhập/xem/chỉnh dữ liệu dầu tồn theo tháng, theo tàu.
- Quản lý điều chuyển dầu giữa các tàu.
- Theo dõi biến động tồn kho.

### 5.4 Danh sách điểm (`danh_sach_diem.php`)

- Tra cứu tất cả điểm và tuyến đường trong hệ thống.
- Xem khoảng cách giữa các cặp điểm.

### 5.5 Danh sách tàu (`danh_sach_tau.php`)

- Xem danh sách tàu cùng hệ số nhiên liệu.
- Phân loại tàu: công ty / thuê ngoài.

### 5.6 Khu vực quản trị (`admin/`)

| Trang | Chức năng |
|---|---|
| `admin/index.php` | Dashboard — thống kê tổng quan (số tàu, điểm, tuyến, hệ số trung bình) |
| `admin/quan_ly_tau.php` | Thêm/sửa/xóa tàu và hệ số nhiên liệu |
| `admin/quan_ly_tuyen_duong.php` | Thêm/sửa/xóa tuyến đường và khoảng cách |
| `admin/quan_ly_loai_hang.php` | Quản lý danh mục loại hàng |
| `admin/quan_ly_cay_xang.php` | Quản lý danh sách cây xăng |
| `admin/quan_ly_dau_ton.php` | Quản lý dầu tồn (phía admin) |
| `admin/bao_cao_dau_ton.php` | Báo cáo dầu tồn theo tháng + xuất Excel |
| `admin/quan_ly_user.php` | Quản lý người dùng (**chỉ admin**) |

---

## 6. Hệ thống phân quyền

| Vai trò | Quyền truy cập |
|---|---|
| **User** | Trang tính toán, lịch sử, quản lý dầu tồn, danh sách điểm/tàu, đổi mật khẩu |
| **Admin** | Tất cả quyền user + toàn bộ khu vực quản trị (CRUD tàu, tuyến, hàng, cây xăng, dầu tồn, người dùng) |

- Xác thực qua **PHP Session**, dữ liệu người dùng lưu trong `data/users.csv`.
- Middleware `auth/check_auth.php` bảo vệ tất cả trang (yêu cầu đăng nhập).
- Middleware `auth/check_admin.php` bảo vệ trang quản lý người dùng (yêu cầu role = admin).

---

## 7. Models & dữ liệu

| Model | File dữ liệu | Mô tả |
|---|---|---|
| `TinhToanNhienLieu` | — | Công thức tính nhiên liệu: `Q = (Sch + Skh) × Kkh`, kết hợp hệ số tàu và khoảng cách |
| `LuuKetQua` | `data/ket_qua_tinh_toan.csv` | Lưu/đọc kết quả tính toán, quản lý đoạn chuyến, đánh index |
| `KhoangCach` | `khoang_duong.csv` | Tra cứu khoảng cách giữa các cặp điểm, chuẩn hóa tên |
| `HeSoTau` | `bang_he_so_tau_cu_ly_full_v2.csv` | Hệ số nhiên liệu theo tàu và dải cự ly |
| `TauPhanLoai` | `data/tau_phan_loai.csv` | Phân loại tàu: công ty / thuê ngoài, số đăng ký |
| `DauTon` | `data/dau_ton.csv` | Dầu tồn theo tháng, tiêu thụ, điều chuyển, điều chỉnh |
| `CayXang` | `data/cay_xang.csv` | Danh sách cây xăng (nơi cấp dầu) |
| `LoaiHang` | `data/loai_hang.csv` | Danh mục loại hàng hóa |
| `User` | `data/users.csv` | Tài khoản người dùng, mật khẩu, phân quyền |
| `Logger` | `data/logs/` | Ghi log theo cấp độ, xoay file tự động |

---

## 8. API Endpoints

Tất cả endpoint trả JSON theo chuẩn `{ "success": true/false, "data": ... }` hoặc `{ "success": false, "error": "..." }`.

| Phương thức | Endpoint | Chức năng |
|---|---|---|
| GET | `/api/get_distance.php` | Lấy khoảng cách giữa 2 điểm |
| GET | `/api/get_tuyen_duong.php` | Danh sách toàn bộ tuyến đường |
| GET | `/api/search_diem.php` | Tìm kiếm điểm theo từ khóa |
| GET | `/api/get_ma_chuyen.php` | Lấy mã chuyến cao nhất theo tàu |
| GET | `/api/get_loai_hang.php` | Danh sách loại hàng |
| POST | `/api/add_loai_hang.php` | Thêm loại hàng mới |
| POST | `/api/preview_calculation.php` | Xem trước kết quả tính toán |
| POST | `/api/update_segment.php` | Cập nhật thông tin đoạn chuyến |
| POST | `/api/update_thang_bao_cao.php` | Cập nhật tháng báo cáo cho đoạn |
| POST | `/api/insert_trip.php` | Tạo chuyến mới |
| POST | `/api/delete_trip.php` | Xóa chuyến / đoạn |
| POST | `/api/move_segment.php` | Di chuyển đoạn sang chuyến khác |
| POST | `/api/reorder_segments.php` | Sắp xếp lại thứ tự đoạn |
| POST | `/api/update_tinh_chinh.php` | Cập nhật tinh chỉnh |
| POST | `/api/delete_dau_ton.php` | Xóa bản ghi dầu tồn |
| POST | `/api/update_cay_xang.php` | Cập nhật thông tin cây xăng |
| POST | `/api/save_order_overrides.php` | Lưu thứ tự hiển thị tùy chỉnh |
| POST | `/api/update_transfer.php` | Cập nhật điều chuyển dầu |
| POST | `/api/delete_transfer.php` | Xóa điều chuyển dầu |

> Tài liệu API chi tiết (tham số, ví dụ): xem [`docs/API.md`](docs/API.md)

---

## 9. Quy trình nghiệp vụ

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Chọn tàu &     │────▶│  Nhập tuyến,     │────▶│  Xử lý phát    │
│  mã chuyến      │     │  khối lượng,     │     │  sinh (đổi lệnh │
│                 │     │  ngày tháng      │     │  / cấp thêm)    │
└─────────────────┘     └──────────────────┘     └────────┬────────┘
                                                          │
                        ┌──────────────────┐              │
                        │  Kiểm tra &      │◀─────────────┘
                        │  Lưu kết quả     │
                        └────────┬─────────┘
                                 │
                ┌────────────────┴────────────────┐
                ▼                                 ▼
   ┌─────────────────┐              ┌─────────────────────┐
   │  Quản lý dầu    │              │  Xuất báo cáo Excel │
   │  tồn theo tháng │              │  cuối kỳ            │
   └─────────────────┘              └─────────────────────┘
```

1. **Chọn tàu và mã chuyến** cần làm việc.
2. **Nhập tuyến và thông số chuyến** (điểm đi/đến, khối lượng, ngày đi/đến/dỡ xong).
3. Nếu phát sinh, nhập thêm **đổi lệnh** hoặc **cấp thêm dầu**.
4. Bấm **Tính Toán Nhiên Liệu** để kiểm tra kết quả.
5. Kiểm tra lại thông tin, bấm **Lưu Kết Quả** để ghi chính thức.
6. Quản lý **dầu tồn** theo tháng để theo dõi tiêu thụ thực tế.
7. Cuối kỳ, vào lịch sử hoặc báo cáo để **xuất file Excel tổng hợp**.

---

## 10. Lưu ý quan trọng

- Dữ liệu chuyến và dầu tồn là dữ liệu nghiệp vụ quan trọng — cần nhập chính xác ngay từ đầu.
- Khi sửa/xóa/di chuyển đoạn, hệ thống có thể ảnh hưởng đến thứ tự các đoạn liên quan.
- Luôn kiểm tra **tháng báo cáo** trước khi lưu.
- Dữ liệu được lưu dưới dạng file CSV/JSON — cần **sao lưu thư mục `data/`** định kỳ.
- Các file CSV gốc (`khoang_duong.csv`, `bang_he_so_tau_cu_ly_full_v2.csv`) chứa dữ liệu nền tảng — chỉ chỉnh sửa qua giao diện admin.

---

## 11. Lịch sử thay đổi

### v1.4.0 (2026-04-09)

**Tính năng mới:**
- **Nhiều lệnh Ma nơ trong 1 chuyến** — cho phép thêm/xóa động nhiều lệnh cấp thêm dầu (ma nơ, qua cầu, rô đai + vệ sinh, khác) tại nhiều địa điểm khác nhau trong cùng mã chuyến.
- **Gợi ý chọn nhanh địa điểm Ma nơ** — khi chuyến hiện tại đã có từ 2 địa điểm Ma nơ trở lên, hiển thị nút chọn nhanh để nhập nhanh hơn.
- **Validation nâng cao** — kiểm tra tính hợp lệ của tất cả lệnh Ma nơ bổ sung trước khi lưu (địa điểm không rỗng, số lượng > 0).

**Dọn dẹp dự án:**
- Xóa script demo (`generate_diem_csv_demo.php`) và file output demo (`data/diem_generated.csv`).
- Untrack dữ liệu runtime JSON (`order_overrides.json`, `transfer_overrides.json`) — thêm file sample thay thế.
- Xóa file log debug/vận hành và PHPUnit cache.
- Cập nhật `.gitignore`: bảo vệ `*.sample.json` tương tự `*.sample.csv`.
- Xóa file CSV trùng lặp/backup (`dau_ton_2.csv`, `ket_qua_tinh_toan_2.csv`).

### v1.3.8 (2026-04)

- Cập nhật README toàn diện với cấu trúc thư mục, API docs, quy trình nghiệp vụ.
- Fix dropdown tháng báo cáo bị trùng lặp.

### v1.3.x

- Hỗ trợ rô đai + vệ sinh trong cấp thêm dầu.
- Chuẩn hóa báo cáo cấp thêm và rule dầu ma nơ.
- Giữ lại địa điểm cấp thêm sau khi tính toán.
- Quản lý template header Excel theo loại báo cáo.

---

## 12. Bản quyền

Phần mềm thuộc sở hữu của **Công ty Cổ phần Logistics VICEM** (Copyright 2026).

- Chỉ sử dụng cho mục đích **nghiệp vụ nội bộ**.
- Không sao chép, phát tán, thương mại hóa khi chưa được phê duyệt.
- Mã nguồn và dữ liệu được xem là **thông tin mật nội bộ**.
- Chi tiết: xem file [`LICENSE`](LICENSE).

---

## Hỗ trợ

Khi có sự cố dữ liệu hoặc cần thay đổi quy trình, liên hệ **quản trị hệ thống** hoặc bộ phận kỹ thuật phụ trách triển khai.
