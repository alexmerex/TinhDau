# API & AJAX Endpoints

Tài liệu liệt kê đầy đủ các endpoint hiện có trong thư mục `api/` và `ajax/`.

## Quy ước phản hồi

Phần lớn endpoint trả JSON theo dạng:

```json
{
  "success": true,
  "data": {}
}
```

Khi lỗi thường có:

```json
{
  "success": false,
  "error": "..." 
}
```

---

## 1) Nhóm `api/`

## `GET /api/get_distance.php`

Lấy khoảng cách giữa 2 điểm.

- Query params:
  - `diem_dau` (string, bắt buộc)
  - `diem_cuoi` (string, bắt buộc)
- Response thành công:
  - `success`, `distance`

---

## `GET /api/get_tuyen_duong.php`

Lấy toàn bộ tuyến đường.

- Response:
  - `success`
  - `routes`: mảng `{ id, diem_dau, diem_cuoi, khoang_cach_km }`

---

## `GET /api/search_diem.php`

Tìm điểm phục vụ autocomplete.

- Query params:
  - `q` (string, ưu tiên) hoặc `keyword` (string)
  - `diem_dau` (string, tùy chọn)
- Response:
  - `success`
  - `results`: mảng tên điểm (format mới)
  - `data`: mảng object `{ diem, khoang_cach }` (format cũ)

---

## `GET /api/get_ma_chuyen.php`

Lấy mã chuyến cao nhất theo tàu.

- Query params:
  - `ten_tau` (string, bắt buộc)
- Response:
  - `success`
  - `ma_chuyen_cao_nhat`
  - `message`

---

## `GET /api/get_loai_hang.php`

Lấy danh sách loại hàng.

- Response:
  - `success`
  - `data`: danh sách loại hàng

---

## `POST /api/add_loai_hang.php`

Thêm loại hàng mới.

- Body params (`application/x-www-form-urlencoded`):
  - `ten_loai_hang` (string, bắt buộc)
- Response:
  - `success`
  - `data`: bản ghi loại hàng vừa tạo

---

## `GET /api/preview_calculation.php`

Preview tính nhiên liệu theo tuyến (không lưu dữ liệu).

- Query params:
  - `ten_tau` (string, bắt buộc)
  - `diem_di` (string, bắt buộc)
  - `diem_den` (string, bắt buộc)
  - `khoi_luong` (number, tùy chọn, mặc định 0)
- Response:
  - `success`
  - `data`: `{ khoang_cach_km, nhien_lieu_lit, he_so_co_hang, he_so_khong_hang, nhom_cu_ly, sch, skh }`

---

## `POST /api/update_segment.php`

Cập nhật một đoạn/bản ghi theo chỉ số dòng `idx` (`___idx` trong file CSV).

- Body params:
  - `idx` (int, bắt buộc)
  - Nếu là đoạn thường: có thể cập nhật `ten_tau`, `diem_di`, `diem_den`, `khoi_luong_van_chuyen_t`, `loai_hang`, `ghi_chu`, `ngay_di`, `ngay_den`, `ngay_do_xong`
  - Nếu là cấp thêm: `ly_do_cap_them`, `so_luong_cap_them_lit`, `ngay_di`
- Response:
  - `success`, `message`

---

## `POST /api/update_thang_bao_cao.php`

Cập nhật trường tháng báo cáo cho một bản ghi.

- Body params:
  - `idx` (int, bắt buộc)
  - `thang_bao_cao` (string `YYYY-MM`, bắt buộc)
- Response:
  - `success`

---

## `POST /api/insert_trip.php`

Chèn chuyến giữa (renumber các chuyến từ vị trí chỉ định trở đi).

- Body params:
  - `ten_tau` (string, bắt buộc)
  - `insert_position` (int > 0, bắt buộc)
- Response:
  - `success`
  - `message`
  - `affected_count`
  - `insert_position`
  - `renumbered_trips`

---

## `POST /api/delete_trip.php`

Xóa một chuyến và renumber các chuyến phía sau.

- Body params:
  - `ten_tau` (string, bắt buộc)
  - `delete_trip` (int > 0, bắt buộc)
- Response:
  - `success`
  - `message`
  - `deleted_count`
  - `renumbered_count`
  - `renumbered_trips`

---

## `POST /api/move_segment.php`

Di chuyển một đoạn từ chuyến này sang chuyến khác.

- Body params:
  - `ten_tau` (string, bắt buộc)
  - `from_trip` (int, bắt buộc)
  - `to_trip` (int, bắt buộc)
  - `segment_index` (int >= 0, bắt buộc)
- Response:
  - `success`
  - `message`
  - `segment_info`

---

## `POST /api/reorder_segments.php`

Sắp xếp lại thứ tự đoạn trong chuyến.

- Content-Type: `application/json`
- Body JSON:
  - `ten_tau` (string, bắt buộc)
  - `so_chuyen` (int, bắt buộc)
  - `new_order` (array int, bắt buộc) — danh sách `___idx` theo thứ tự mới
- Response:
  - `success`
  - `message`
  - `segments_reordered`

---

## `POST /api/update_tinh_chinh.php`

Cập nhật một lệnh tinh chỉnh dầu tồn.

- Body params:
  - `id` (string, bắt buộc)
  - `ngay` (string, bắt buộc)
  - `so_luong` (number, bắt buộc)
  - `ly_do` (string, tùy chọn)
- Response:
  - `success`
  - `message`

---

## `POST /api/delete_dau_ton.php`

Xóa một bản ghi dầu tồn theo id.

- Body params:
  - `id` (string, bắt buộc)
- Response:
  - `success`
  - `message`

---

## `POST /api/update_cay_xang.php`

Cập nhật cây xăng cho bản ghi dầu tồn.

- Body params:
  - `id` (string, bắt buộc)
  - `cay_xang` (string, có thể rỗng)
- Response:
  - `success`
  - `message`

---

## `POST /api/save_order_overrides.php`

Lưu thứ tự hiển thị/ưu tiên theo tháng và tàu.

- Body params:
  - `month` (string `YYYY-MM`, bắt buộc)
  - `ship` (string, bắt buộc)
  - `order` (array int, bắt buộc)
- Response:
  - `success`

---

## `POST|GET /api/update_transfer.php`

Cập nhật lệnh chuyển dầu (hỗ trợ 2 chế độ).

### Chế độ mới (khuyến nghị) theo cặp chuyển
- `transfer_pair_id` (string, bắt buộc theo mode này)
- `new_source_ship`, `new_dest_ship`, `new_date`, `new_liters`, `reason` (tùy chọn, nếu thiếu sẽ fallback từ dữ liệu cũ)

### Chế độ cũ (legacy)
- `old_source_ship`, `old_dest_ship`, `old_date`, `old_liters` (bắt buộc)
- cùng các trường `new_*` như trên

- Response:
  - `success`

---

## `POST|GET /api/delete_transfer.php`

Xóa lệnh chuyển dầu.

### Chế độ mới
- `transfer_pair_id` (string)

### Chế độ cũ
- `source_ship`, `dest_ship`, `date`, `liters`

- Response:
  - `success`
  - `message`

---

## 2) Nhóm `ajax/`

## `GET /ajax/get_trips.php`

Lấy danh sách chuyến của một tàu.

- Query params:
  - `ten_tau` (string, bắt buộc)
- Response:
  - `success`
  - `trips` (array int)
  - `max_trip`
  - `next_trip`
  - `so_dang_ky`

---

## `GET /ajax/get_trip_details.php`

Lấy chi tiết một chuyến cụ thể.

- Query params:
  - `ten_tau` (string, bắt buộc)
  - `so_chuyen` (int, bắt buộc)
- Response:
  - `success`
  - `segments`: các đoạn thường
  - `cap_them`: các dòng cấp thêm
  - `all_segments`: tất cả dòng của chuyến
  - `last_segment`
  - `has_data`
  - `so_dang_ky`
  - `debug`

---

## 3) Ghi chú sử dụng

- Đa số endpoint ghi dữ liệu yêu cầu `POST`.
- Khi tích hợp phía frontend, luôn kiểm tra `success` trước khi dùng dữ liệu.
- Một số endpoint trả lỗi với HTTP code `400/404/500`, nên xử lý fallback phù hợp ở UI.
