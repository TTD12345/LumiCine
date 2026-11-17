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
    <meta name="theme-color" content="#f6f7fb">
    <title>Đăng nhập | LumiCine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/auth.css" rel="stylesheet">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand">
            <span class="auth-brand-icon"><i class="bi bi-buildings-fill" aria-hidden="true"></i></span>
            <span><strong>LumiCine</strong><small>QUẢN LÝ NHÂN SỰ</small></span>
        </div>
        <div class="auth-heading">
            <span class="auth-kicker">KHÔNG GIAN LÀM VIỆC</span>
            <h1>Đăng nhập</h1>
            <p>Đăng nhập bằng tài khoản nhân viên của bạn.</p>
        </div>
        <?php if (isset($_GET['logged_out'])): ?>
            <p class="auth-success" role="status">Bạn đã đăng xuất thành công.</p>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <p class="auth-error" role="alert"><?= $escape($errorMessage) ?></p>
        <?php endif; ?>
        <form class="auth-form" action="index.php?action=login" method="post">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>
                Tên đăng nhập
                <input type="text" name="username" autocomplete="username" maxlength="190" required autofocus value="<?= $escape($loginUsername) ?>">
            </label>
            <label>
                Mật khẩu
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Đăng nhập</button>
        </form>
        <p class="auth-note">Quyền truy cập trang quản trị dành cho Kế toán, HR và Chủ tịch.</p>
    </main>
</body>
</html>
