<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chưa liên kết hồ sơ | LumiCine</title>
</head>
<body>
    <main>
        <h1>Tài khoản chưa liên kết hồ sơ nhân viên</h1>
        <p>Vui lòng nhờ quản trị viên cập nhật mã nhân viên tương ứng trong cột <code>lumicine_users.employee_code</code> của database.</p>
        <form action="index.php?action=logout" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Đăng xuất</button>
        </form>
    </main>
</body>
</html>
