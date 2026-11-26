# LumiCine HRM

LumiCine dùng MySQL làm nguồn dữ liệu cho hồ sơ nhân viên, phân ca, chấm công, tăng ca và báo cáo. Người dùng phải đăng nhập; chỉ tài khoản có vai trò Kế toán, HR hoặc Chủ tịch mới được mở trang quản trị. Quyền thao tác được kiểm tra ở phía máy chủ.

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

## Cấu hình database hiện có

Ứng dụng mặc định kết nối MySQL `data_employments` trên `localhost:3306` bằng user `root` không mật khẩu, phù hợp cấu hình Laragon hiện tại. Có thể cấu hình lại bằng các biến môi trường `LUMICINE_DB_NAME`, `LUMICINE_DB_HOST`, `LUMICINE_DB_PORT`, `LUMICINE_DB_USERNAME` và `LUMICINE_DB_PASSWORD`. PHP cần bật extension `PDO_MYSQL`.

Không import `database/schema.sql` vào database đang có dữ liệu. Trước khi chạy phiên bản này, sao lưu database rồi áp dụng migration bổ sung (không xóa hoặc sửa nội dung các bản ghi hiện có):

```powershell
Get-Content -Raw .\database\migrations\2026-11-21-add-workforce-storage.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
Get-Content -Raw .\database\migrations\2026-11-21-add-payroll-settings.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
Get-Content -Raw .\database\migrations\2026-11-23-add-password-change-required.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
Get-Content -Raw .\database\migrations\2026-11-24-add-face-attendance.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
Get-Content -Raw .\database\migrations\2026-11-24-create-auto-schedule-rules.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
Get-Content -Raw .\database\migrations\2026-11-24-add-schedule-rule-management.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
```

Các migration thêm `lumicine_users.employee_code`, `phan_ca.dia_diem`, bảng lịch nghỉ `lumicine_holidays` và cấu hình `lumicine_settings` (mặc định ngày bắt đầu kỳ lương là 10). Migration mật khẩu đánh dấu các tài khoản nhân viên hiện có cần đổi mật khẩu một lần; chạy lại migration không đặt lại trạng thái này. Nếu MySQL không nằm ở đường dẫn trên, dùng đường dẫn `mysql.exe` tương ứng với cài đặt của bạn.

Khi nâng cấp luồng tăng ca, chạy thêm migration riêng để cho phép đơn tăng ca lưu trước khi có phân ca:

```powershell
Get-Content -Raw .\database\migrations\2026-11-21-add-overtime-employee-link.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
```

Liên kết tài khoản với hồ sơ nhân viên tại **Quản lý nhân viên** trong trang quản trị: chọn hồ sơ theo đúng mã nhân viên đã xác minh và lưu liên kết. Mã nhân viên có thể được liên kết cho tài khoản nhân viên hoặc tài khoản quản trị (HR, kế toán, chủ tịch) để tài khoản đó cũng chấm công theo hồ sơ của mình. Mỗi mã nhân viên chỉ được dùng cho một tài khoản; ứng dụng không tự ghép theo tên. Có thể cập nhật trực tiếp bằng SQL nếu cần:

```sql
UPDATE lumicine_users
SET employee_code = 'MA_NHAN_VIEN'
WHERE username = 'TEN_TAI_KHOAN';
```

Tài khoản nhân viên chưa được liên kết sẽ không thể xem/chấm công cho hồ sơ của người khác; trang sẽ hướng dẫn quản trị viên bổ sung mapping.
Sau khi cập nhật `employee_code`, đăng xuất và đăng nhập lại để phiên làm việc nhận mapping mới.

Các bảng nghiệp vụ hiện có được dùng trực tiếp:

- Nhân viên và phòng ban: `nhan_vien`, `phong_ban`.
- Ca làm và phân ca: `ca_lam_viec`, `phan_ca`.
- Giờ vào/ra và tổng hợp: `cham_cong`, `tong_hop_cong`.
- Đơn tăng ca: `tang_ca`.
- Ngày nghỉ/làm bù do quản trị viên bổ sung: `lumicine_holidays`.

Lịch sử chấm công, đơn tăng ca và phân ca hiện có được giữ nguyên. Hệ thống không đọc hoặc ghi vào bảng `lumicine_attendance` trong `schema.sql`; chấm công dùng bảng hiện hữu `cham_cong`. Database hiện chưa có bảng/quy tắc nghỉ phép riêng hoặc cấu hình lương, nên báo cáo hiển thị chưa có dữ liệu cho các phần này thay vì tự tạo số liệu.

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

## Dữ liệu nghiệp vụ

Trang nhân viên và quản trị đọc/ghi dữ liệu qua PHP/MySQL; trình duyệt không còn lưu dữ liệu nghiệp vụ trong `localStorage`.

- Quản trị viên phân ca cho nhân viên trong `phan_ca`; ca được lưu theo ngày và liên kết với `ca_lam_viec`. Có thể sửa/hủy ca khi chưa phát sinh chấm công hoặc tăng ca.
- Tự động phân ca dùng quy tắc theo ca và phòng ban trong `lumicine_schedule_requirements`; danh sách chức vụ cho phép được lưu riêng. Hệ thống chỉ tạo bản xem trước, giữ nguyên các ca đã có và chỉ lấp đến số lượng tối thiểu. Quản trị viên có thể đổi hoặc bỏ nhân viên trong bản xem trước; máy chủ kiểm tra lại lịch, nghỉ phép đã duyệt, chức vụ, phòng ban, giờ làm và sức chứa trong transaction trước khi xác nhận lưu.
- **Danh mục quy tắc phân ca** hiển thị 12 mã QT, tìm kiếm/lọc/phân trang và trạng thái cấu hình. Các quy tắc bắt buộc không thể tắt; cấu hình ca/phòng ban được ngừng áp dụng theo trạng thái để giữ lại lịch sử. Có thể bật/tắt và sắp thứ tự các tiêu chí ưu tiên được hỗ trợ.
- Giới hạn tối đa giờ/ngày, giờ/tuần, nghỉ giữa ca và số ca đêm liên tiếp được thiết lập trong **Tự động phân ca**. Migration khởi tạo các giới hạn giờ/ca đêm ở trạng thái chưa áp dụng; chỉ các giới hạn quản trị viên cấu hình mới được dùng. QT012 ưu tiên người có ít ca đêm liên tiếp và, khi bật, chặn vượt số ca đêm tối đa. Nghỉ phép lấy từ `lumicine_leave_requests` với trạng thái đã duyệt. Database hiện chưa có chức năng lưu nguyện vọng ca/ngày không thể làm, nên QT011 được hiển thị là chưa khả dụng và không tác động đến kết quả.
- **Quản lý phân ca** hỗ trợ xem lịch theo ngày, tuần hoặc tháng; lọc theo phòng ban, nhân viên và ca (kể cả loại ca đã ngừng hoạt động); lịch cá nhân hiển thị mã nhân viên, thứ trong tuần, tổng số ca và tổng giờ dự kiến sau khi trừ thời gian nghỉ giữa ca đã cấu hình.
- Khi lưu hoặc sửa ca, máy chủ khóa hồ sơ nhân viên trong transaction và kiểm tra lịch `DA_PHAN` của ngày liền trước, ngày được chọn và ngày liền sau; ca qua đêm, lịch bị sửa và thời gian nghỉ tối thiểu đã cấu hình đều được xử lý. Lịch tự động tiếp tục kiểm tra lịch hiện hữu và các lịch vừa thêm trong cùng transaction; lịch `HUY` không được tính là ca đang có hiệu lực.
- Tại **Quản lý nhân viên**, quản trị viên có thể thêm/sửa hồ sơ nhân viên, phân phòng ban, ngừng hoạt động hoặc kích hoạt lại hồ sơ, đồng thời thêm/sửa/xóa phòng ban. Ngừng hoạt động là khóa mềm để giữ lịch sử phân ca và chấm công; phòng ban đang được gán cho nhân viên không thể xóa. Khi đổi mã nhân viên đã liên kết với tài khoản, mapping được cập nhật cùng giao dịch.
- Tại **Quản lý tài khoản**, quản trị viên có thể tạo, sửa tên đăng nhập, đặt lại mật khẩu và xóa tài khoản nhân viên. Mỗi nhân viên chỉ có một tài khoản; mật khẩu luôn được lưu bằng `password_hash` và không thể xem lại. Xóa tài khoản không xóa hồ sơ nhân viên.
- Nhân viên có thể đổi mật khẩu trong **Hồ sơ cá nhân** bằng cách xác nhận mật khẩu hiện tại. Tài khoản nhân viên mới và các tài khoản đang có tại thời điểm chạy migration được chuyển đến màn hình riêng ngay sau khi đăng nhập; nhân viên có thể đổi mật khẩu hoặc giữ mật khẩu hiện tại. Chọn “Không hiện lại” sẽ lưu lựa chọn bỏ lời nhắc ở các lần đăng nhập sau; giữ mật khẩu mà không chọn thì lời nhắc sẽ xuất hiện lại ở lần đăng nhập kế tiếp.
- Nhân viên chỉ xem lịch của mã nhân viên được liên kết với tài khoản.
- Chấm công chỉ được ghi nhận trong ngày hiện tại khi nhân viên có phân ca. Thời gian lấy từ máy chủ; dữ liệu vào/ra được ghi vào `cham_cong`.
- Chấm công khuôn mặt yêu cầu đăng ký ba descriptor (nhìn thẳng và xoay hai bên) trước lần chấm công đầu tiên; xác thực quét tự động và so khớp với các góc đã đăng ký. Descriptor được lưu trong `lumicine_face_profiles`; ứng dụng không lưu ảnh khuôn mặt riêng. Trình duyệt cần truy cập camera qua `localhost` hoặc HTTPS, và mô hình nhận diện được tải từ CDN.
- Đơn tăng ca được liên kết với bản ghi `cham_cong`, lưu trong `tang_ca`; quản trị viên duyệt hoặc từ chối trực tiếp trên database.
- Ngày bắt đầu kỳ lương có thể thay đổi trong **Cài đặt**; bảng tổng hợp công và lịch chi tiết áp dụng từ ngày này đến trước ngày bắt đầu kỳ tiếp theo. Nếu tháng thiếu ngày đã chọn, hệ thống dùng ngày cuối tháng.
- Nhân viên gửi đơn tăng ca trước, đơn được lưu trong `tang_ca` với hồ sơ nhân viên và chưa cần phân ca. Khi quản trị viên duyệt, hệ thống tự tạo ca tăng ca, phân ca và bản ghi `cham_cong`; đơn bị từ chối thì không tạo phân ca.
- Thời gian tăng ca chỉ được đăng ký ngoài giờ hành chính 08:00–17:00. Đơn có bất kỳ phần nào trùng khoảng 08:00–17:00 sẽ bị từ chối ở máy chủ.
- Báo cáo đọc dữ liệu phân ca, chấm công và tăng ca đã duyệt theo tháng.
- Ngày nghỉ/làm bù tùy chỉnh lưu trong `lumicine_holidays` và được dùng chung giữa các tài khoản quản trị. Lịch Nager.Date vẫn là nguồn tham khảo bên ngoài.
- Xuất báo cáo Excel tương thích định dạng `.xls`, có màu phân biệt các nhóm dữ liệu, và in báo cáo.

Phân quyền thao tác database được xác thực ở máy chủ; các thao tác ghi dùng CSRF token. Trước khi sử dụng thật, hãy kiểm tra mapping tài khoản và sao lưu database định kỳ.

## Không gian nhân viên

Trang Employee có tổng quan cá nhân, chấm công, bảng công theo tháng, đơn nghỉ phép/tăng ca, danh sách đơn, thông báo và hồ sơ. Bảng công và thông báo chỉ lấy dữ liệu theo hồ sơ đã liên kết với tài khoản; tăng ca chỉ cộng khi đơn có trạng thái đã duyệt. Ngày đã phân ca nhưng thiếu giờ vào được ghi nhận là thiếu chấm công hoặc nghỉ không phép theo dữ liệu nghỉ phép đã duyệt; hệ thống không tự áp dụng quy tắc trừ công.

Ứng dụng tự tạo bảng hồ sơ cá nhân khi kết nối database lần đầu. Nếu tài khoản database không có quyền `CREATE TABLE`, hãy áp dụng migration sau trước khi mở trang tổng quan hoặc hồ sơ:

```powershell
Get-Content -Raw .\database\migrations\2026-11-23-create-employee-profiles.sql |
    & 'D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe' --host=localhost --port=3306 --user=root data_employments
```

Nhân viên chỉ tự cập nhật số điện thoại và email. Ngày sinh, ngày vào làm và ảnh đại diện có thể để trống cho đến khi được bổ sung từ quy trình quản lý hồ sơ; các thay đổi chấm công và duyệt đơn vẫn thuộc quyền quản trị/ quản lý.
