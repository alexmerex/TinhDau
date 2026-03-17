# TINHDAU

Hệ thống hỗ trợ **tính định mức nhiên liệu**, **quản lý chuyến tàu**, **theo dõi dầu tồn** và **xuất báo cáo** phục vụ công tác vận hành nội bộ.

> Tài liệu này dành cho **người dùng nghiệp vụ** (điều độ, kế toán, quản lý đội tàu).

---

## 1. Mục tiêu hệ thống

TINHDAU giúp đơn vị:
- Tính nhanh nhiên liệu theo tuyến và thông số chuyến.
- Quản lý hành trình và các đoạn trong từng chuyến.
- Theo dõi các lệnh **cấp thêm dầu** (ma nơ, qua cầu, rô đai + vệ sinh...).
- Quản lý **dầu tồn theo tháng** và theo tàu.
- Tổng hợp và xuất báo cáo phục vụ vận hành.

---

## 2. Các màn hình chính

### 2.1 Trang tính toán (trang chủ)
- Chọn tàu và mã chuyến.
- Nhập tuyến, khối lượng, thời gian đi/đến/dỡ xong.
- Thêm tình huống **đổi lệnh** (nhiều điểm nếu phát sinh).
- Nhập thông tin **cấp thêm** nếu có.
- Bấm **Tính Toán Nhiên Liệu** để xem kết quả.
- Bấm **Lưu Kết Quả** để ghi dữ liệu chính thức.

### 2.2 Lịch sử
- Xem và tra cứu dữ liệu đã lưu theo tàu/chuyến/thời gian.
- Hỗ trợ kiểm tra lại thông tin khi cần đối soát.

### 2.3 Quản lý dầu tồn
- Nhập/xem/chỉnh dữ liệu dầu tồn theo tháng.
- Theo dõi biến động dầu tồn theo tàu.

### 2.4 Khu vực quản trị (dành cho quản trị viên)
- Quản lý tàu
- Quản lý tuyến đường
- Quản lý loại hàng
- Quản lý cây xăng
- Quản lý người dùng

---

## 3. Quy trình nghiệp vụ khuyến nghị

1. **Chọn tàu và mã chuyến** cần làm việc.
2. **Nhập tuyến và thông số chuyến** (điểm đi/đến, khối lượng, ngày...).
3. Nếu phát sinh, nhập thêm:
   - **Đổi lệnh**
   - **Cấp thêm**
4. Bấm **Tính Toán Nhiên Liệu** để kiểm tra.
5. Kiểm tra lại thông tin, sau đó bấm **Lưu Kết Quả**.
6. Cuối kỳ, vào báo cáo để **xuất file tổng hợp**.

---

## 4. Lưu ý quan trọng

- Dữ liệu chuyến và dầu tồn là dữ liệu nghiệp vụ quan trọng, cần nhập đúng ngay từ đầu.
- Khi sửa/xóa chuyến, hệ thống có thể ảnh hưởng đến thứ tự chuyến liên quan.
- Nên kiểm tra lại tháng báo cáo trước khi lưu.
- Nên sao lưu dữ liệu định kỳ theo quy định nội bộ đơn vị.

---

## 5. Tài liệu kỹ thuật/API

Danh sách đầy đủ endpoint dùng cho tích hợp nội bộ nằm tại:

- `docs/API.md`

---

## 6. Bản quyền & phạm vi sử dụng

Phần mềm này được cấp phép theo hình thức **nội bộ** cho đơn vị sở hữu và các đơn vị được ủy quyền.

- Không sử dụng như phần mềm mã nguồn mở/public.
- Không chia sẻ mã nguồn hoặc dữ liệu ra ngoài khi chưa có phê duyệt.
- Chi tiết xem tại file `LICENSE`.

---

## 7. Hỗ trợ

Khi có sự cố dữ liệu hoặc cần thay đổi quy trình:
- Liên hệ quản trị hệ thống hoặc bộ phận kỹ thuật phụ trách triển khai.
