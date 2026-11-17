# LumiCine HRM

Trang nhân viên hiển thị lịch làm việc tại `views/main.php`. Trang tổng hợp công dành cho quản trị viên nằm riêng tại `views/admin.php`. Cả hai dùng dữ liệu mẫu, có thể thay bằng truy vấn MySQL khi tích hợp backend.

## Cấu trúc thư mục

```text
LumiCine/
├── assets/
│   ├── css/
│   │   ├── employee.css
│   │   ├── hrm.css
│   │   └── overtime.css
│   ├── js/
│   │   ├── employee.js
│   │   ├── attendance.js
│   │   └── overtime.js
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
- Gửi đơn đăng ký tăng ca với lý do và thời gian bắt đầu/kết thúc; theo dõi trạng thái duyệt.
- Xuất danh sách ca thành CSV.

Trang quản trị:

- Tìm kiếm theo mã nhân viên hoặc họ tên, không phân biệt dấu.
- Lọc theo phòng ban, chức vụ và trạng thái công.
- Chuyển tháng/năm; dữ liệu mẫu và ngày công chuẩn được cập nhật theo kỳ.
- Xem số lượt/phút đi muộn, ngày nghỉ có lương/không lương và lịch chấm công từng ngày.
- Duyệt hoặc từ chối các đơn đăng ký tăng ca của nhân viên.
- Theo dõi ngày lễ đi làm và khoản lương minh họa theo mức 300%; cần thay dữ liệu lương/ngày lễ mẫu bằng cấu hình thực tế trước khi dùng tính lương.
- Lịch ngày lễ gọi API công khai Nager.Date theo năm và quốc gia `VN`; có thể bổ sung hoặc xóa ngày lễ/ngày nghỉ bù theo thông báo chính thức từng năm.
- Có thể bổ sung ngày nghỉ hưởng lương và ngày làm bù. Ngày bổ sung lưu trong `localStorage` của trình duyệt hiện tại, không tự đồng bộ giữa máy/người dùng. Nếu API lỗi, trang báo lỗi và vẫn dùng các ngày bổ sung đã lưu.
- Xuất dữ liệu đang lọc thành CSV UTF-8 tương thích Excel; in báo cáo theo bố cục ngang.
- Chọn số dòng và chuyển trang; giao diện hỗ trợ cuộn ngang trên bảng dữ liệu.

Đơn tăng ca hiện lưu trong `localStorage` của trình duyệt đang dùng để minh họa luồng giữa trang nhân viên và quản trị. Dữ liệu không được chia sẻ qua thiết bị hoặc người dùng khác; cần tích hợp cơ sở dữ liệu và đăng nhập/phân quyền trước khi sử dụng thực tế.

### Luồng tải lịch nghỉ Việt Nam

1. Khi mở trang quản trị hoặc đổi năm, trình duyệt gọi `https://date.nager.at/api/v3/PublicHolidays/{năm}/VN`.
2. Ngày lễ do API trả về được kết hợp với ngày bổ sung đã lưu trên trình duyệt.
3. Danh sách đã kết hợp được dùng để đánh dấu lịch nhân viên và tính ngày công chuẩn (ngày thường trừ ngày nghỉ lễ, cộng ngày làm bù); dữ liệu API được giữ trong bộ nhớ của trang cho đến khi tải lại.
4. Nager.Date hiện trả thiếu ngày Tết, Giỗ Tổ Hùng Vương và lịch nghỉ/làm bù. Mở **Lịch nghỉ Việt Nam**, chọn ngày, nhập tên và chọn loại ngày để bổ sung; có thể xóa ngày đã thêm.
5. Nếu API lỗi, trang báo trạng thái và vẫn sử dụng ngày bổ sung đã lưu; nút **Tải lại API** cho phép thử lại. API là nguồn tham khảo, không nên dùng riêng để chốt bảng lương.
