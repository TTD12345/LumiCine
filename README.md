# LumiCine HRM

Trang nhân viên hiển thị lịch làm việc tại `views/main.php`. Trang tổng hợp công dành cho quản trị viên nằm riêng tại `views/admin.php`. Cả hai dùng dữ liệu mẫu, có thể thay bằng truy vấn MySQL khi tích hợp backend.

## Cấu trúc thư mục

```text
LumiCine/
├── assets/
│   ├── css/
│   │   ├── employee.css
│   │   └── hrm.css
│   ├── js/
│   │   ├── employee.js
│   │   └── attendance.js
│   └── uploads/
├── configs/
├── controllers/
├── models/
├── routes/
├── scripts/
├── views/
│   ├── admin.php
│   └── main.php
├── index.php
└── package.json
```

## Chạy bằng XAMPP

1. Chép thư mục `LumiCine` vào `C:\xampp\htdocs\`.
2. Mở XAMPP Control Panel và khởi động Apache.
3. Trang nhân viên: `http://localhost/LumiCine/`.
4. Trang quản trị: `http://localhost/LumiCine/?action=admin`.

Không cần tạo database để xem dữ liệu mẫu. Bootstrap, Bootstrap Icons và font được tải từ CDN nên cần kết nối Internet để hiển thị đầy đủ kiểu chữ và biểu tượng.

## Chạy bằng PHP tích hợp

Mở PowerShell trong thư mục dự án `LumiCine` và chạy:

```powershell
php -S 127.0.0.1:8000
```

Sau đó mở `http://127.0.0.1:8000/` để vào trang nhân viên hoặc `http://127.0.0.1:8000/?action=admin` để vào trang quản trị. Nếu muốn dùng lệnh npm có sẵn, chạy `npm run dev` ngay trong thư mục `LumiCine`; lệnh này gọi PHP CLI thông qua `scripts/dev.ps1`.

## Tương tác

Trang nhân viên:

- Chuyển ngày, tìm kiếm ca và lọc theo trạng thái.
- Thêm, sửa, xóa ca và đổi trạng thái; dữ liệu mẫu được lưu theo ngày trong trình duyệt.
- Xuất danh sách ca thành CSV.

Trang quản trị:

- Tìm kiếm theo mã nhân viên hoặc họ tên, không phân biệt dấu.
- Lọc theo phòng ban, chức vụ và trạng thái công.
- Chuyển tháng/năm; dữ liệu mẫu và ngày công chuẩn được cập nhật theo kỳ.
- Xem chi tiết nhân viên cùng lịch chấm công minh họa theo tháng.
- Xuất dữ liệu đang lọc thành CSV UTF-8 tương thích Excel; in báo cáo theo bố cục ngang.
- Chọn số dòng và chuyển trang; giao diện hỗ trợ cuộn ngang trên bảng dữ liệu.
