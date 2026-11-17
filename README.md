# LumiCine HRM

Trang nhân viên hiển thị lịch làm việc tại `views/main.php`. Trang tổng hợp công dành cho quản trị viên nằm riêng tại `views/admin.php`; dữ liệu nhân viên và chấm công trên trang này vẫn là dữ liệu mẫu.

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
│   ├── env.php
│   └── helper.php
├── controllers/
├── models/
│   └── BaseModel.php
├── routes/
├── scripts/
├── views/
│   ├── admin.php
│   ├── attendance.php
│   └── main.php
├── index.php
└── package.json
```

## Chạy bằng XAMPP

1. Chép thư mục `LumiCine` vào `C:\xampp\htdocs\`.
2. Mở XAMPP Control Panel và khởi động Apache.
3. Trang nhân viên: `http://localhost/LumiCine/`.
4. CSDL chỉ cần thiết nếu bạn bật các chức năng PHP có sử dụng model trong dự án.
5. Trang tổng hợp công: `http://localhost/LumiCine/?action=admin`.

Không cần database để xem các trang với dữ liệu mẫu. Bootstrap, Bootstrap Icons và font được tải từ CDN nên cần kết nối Internet để hiển thị đầy đủ kiểu chữ và biểu tượng.

## Chạy bằng PHP tích hợp

Mở PowerShell trong thư mục dự án `LumiCine` và chạy:

```powershell
php -S 127.0.0.1:8000
```

Sau đó mở `http://127.0.0.1:8000/` để vào trang nhân viên hoặc `http://127.0.0.1:8000/?action=admin` để vào trang tổng hợp công. Website chạy bằng PHP; không cần `npm run dev`.

## Tương tác

Trang nhân viên:

- Chuyển ngày, tìm kiếm ca và lọc theo trạng thái.
- Thêm, sửa, xóa ca và đổi trạng thái; dữ liệu mẫu được lưu theo ngày trong trình duyệt.
- Xuất danh sách ca thành CSV.

Trang quản trị:

- Tìm kiếm theo mã nhân viên hoặc họ tên, không phân biệt dấu.
- Lọc theo phòng ban, chức vụ và trạng thái công.
- Chuyển tháng/năm; dữ liệu mẫu và ngày công chuẩn được cập nhật theo kỳ.
- Xem số lượt/phút đi muộn, ngày nghỉ có lương/không lương và lịch chấm công từng ngày.
- Xuất dữ liệu đang lọc thành CSV UTF-8 tương thích Excel; in báo cáo theo bố cục ngang.
- Chọn số dòng và chuyển trang; giao diện hỗ trợ cuộn ngang trên bảng dữ liệu.
