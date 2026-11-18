# LumiCine HRM

Trang nhân viên và trang quản trị là hai trang riêng. Người dùng phải đăng nhập; chỉ tài khoản có vai trò Kế toán, HR hoặc Chủ tịch mới được mở trang quản trị. Việc phân quyền được kiểm tra ở phía máy chủ. Các màn hình nghiệp vụ hiện vẫn dùng dữ liệu mẫu/lưu trên trình duyệt, chưa phải hệ thống dữ liệu nhân sự production.

## Cấu trúc thư mục

```text
LumiCine/
├── assets/
│   ├── css/
│   │   ├── employee.css
│   │   ├── hrm.css
│   │   ├── auth.css
│   │   └── overtime.css
│   ├── js/
│   │   ├── employee.js
│   │   ├── attendance.js
│   │   └── overtime.js
│   └── uploads/
├── configs/
├── controllers/
├── database/
│   └── schema.sql
├── models/
├── routes/
├── scripts/
├── views/
│   ├── admin.php
│   ├── login.php
│   └── main.php
├── index.php
├── admin.php
└── package.json
```

## Chạy bằng XAMPP

1. Chép thư mục `LumiCine` vào `C:\xampp\htdocs\`.
2. Mở XAMPP Control Panel và khởi động Apache.
3. Trang nhân viên: `http://localhost/LumiCine/`.
4. Trang quản trị: `http://localhost/LumiCine/admin.php`.

Tạo database `data_employments` (hoặc chọn tên khác), rồi import `database/schema.sql` để tạo bảng tài khoản. PHP phải bật extension `PDO_MYSQL` để kết nối MySQL. Nếu dùng tên database khác hoặc thông tin kết nối khác, cấu hình các biến môi trường `LUMICINE_DB_NAME`, `LUMICINE_DB_HOST`, `LUMICINE_DB_PORT`, `LUMICINE_DB_USERNAME` và `LUMICINE_DB_PASSWORD` trước khi chạy PHP. Các giá trị mặc định dùng MySQL cục bộ, user `root`, mật khẩu trống.

Nếu database đã có bảng `nhan_vien`, chạy `database/migrations/2026-11-18-add-nhan-vien-chuc-vu.sql` để bổ sung cột chức vụ `chuc_vu`.

## Tạo tài khoản đăng nhập

Tạo password hash trên máy chủ cục bộ bằng PHP CLI (thay chuỗi mẫu bằng mật khẩu do bạn chọn), sau đó chèn tài khoản vào MySQL. Không lưu mật khẩu rõ vào database hoặc mã nguồn:

```powershell
php -r "echo password_hash('THAY_BANG_MAT_KHAU_MOI', PASSWORD_DEFAULT), PHP_EOL;"
```

Chép hash vừa tạo vào `password_hash` trong câu lệnh sau và chọn đúng vai trò:

```sql
INSERT INTO lumicine_users (username, display_name, password_hash, role)
VALUES ('lananh', 'Lan Anh', '<HASH_VUA_TAO>', 'hr');
```

Vai trò hợp lệ: `employee`, `accountant`, `hr`, `chairman`. Tài khoản `employee` chỉ vào trang nhân viên; ba vai trò còn lại được phép vào trang quản trị. Để khóa tài khoản, đặt `is_active = 0`.

## Chạy bằng PHP tích hợp

Mở PowerShell trong thư mục dự án `LumiCine` và chạy:

```powershell
php -S 127.0.0.1:8000
```

Sau đó mở `http://127.0.0.1:8000/` để vào trang nhân viên hoặc `http://127.0.0.1:8000/admin.php` để vào trang quản trị. Cả hai yêu cầu đăng nhập; đường dẫn cũ `?action=admin` vẫn được bảo vệ quyền như trang quản trị. Nếu dùng `npm run dev`, `scripts/dev.ps1` sẽ tự nạp `php_pdo_mysql.dll` từ thư mục extension của PHP nếu driver chưa được bật.

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

Đơn tăng ca, ca làm việc và chấm công hiện vẫn lưu trong `localStorage` để minh họa; đăng nhập và quyền admin không biến các dữ liệu mẫu này thành dữ liệu nghiệp vụ an toàn hoặc đồng bộ. Cần chuyển dữ liệu nghiệp vụ sang backend/MySQL và kiểm thử triển khai trước khi sử dụng thực tế.

### Luồng tải lịch nghỉ Việt Nam

1. Khi mở trang quản trị hoặc đổi năm, trình duyệt gọi `https://date.nager.at/api/v3/PublicHolidays/{năm}/VN`.
2. Ngày lễ do API trả về được kết hợp với ngày bổ sung đã lưu trên trình duyệt.
3. Danh sách đã kết hợp được dùng để đánh dấu lịch nhân viên và tính ngày công chuẩn (ngày thường trừ ngày nghỉ lễ, cộng ngày làm bù); dữ liệu API được giữ trong bộ nhớ của trang cho đến khi tải lại.
4. Nager.Date hiện trả thiếu ngày Tết, Giỗ Tổ Hùng Vương và lịch nghỉ/làm bù. Mở **Lịch nghỉ Việt Nam**, chọn ngày, nhập tên và chọn loại ngày để bổ sung; có thể xóa ngày đã thêm.
5. Nếu API lỗi, trang báo trạng thái và vẫn sử dụng ngày bổ sung đã lưu; nút **Tải lại API** cho phép thử lại. API là nguồn tham khảo, không nên dùng riêng để chốt bảng lương.
