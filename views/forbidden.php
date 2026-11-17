<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Không có quyền truy cập | LumiCine</title>
    <link href="assets/css/auth.css" rel="stylesheet">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-heading">
            <span class="auth-kicker">MÃ LỖI 403</span>
            <h1>Không có quyền truy cập</h1>
            <p>Tài khoản <?= $escape($currentUser['display_name']) ?> (<?= $escape($roleLabel) ?>) không được phép vào trang quản trị.</p>
            <p>Chỉ Kế toán, HR và Chủ tịch mới được truy cập trang này.</p>
        </div>
        <div class="auth-actions">
            <a href="index.php">Về trang nhân viên</a>
            <form action="index.php?action=logout" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button type="submit">Đăng xuất</button>
            </form>
        </div>
    </main>
</body>
</html>
