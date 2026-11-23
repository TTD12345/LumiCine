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
    <title>Đổi mật khẩu | LumiCine</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/employee.css" rel="stylesheet">
</head>
<body class="forced-password-page">
    <main class="forced-password-layout">
        <section class="forced-password-panel" aria-labelledby="forced-password-title">
            <div class="employee-brand forced-password-brand">
                <span class="brand-icon"><i class="bi bi-buildings-fill"></i></span>
                <span class="brand-title">LumiCine</span>
            </div>
            <span class="forced-password-icon"><i class="bi bi-shield-lock"></i></span>
            <p class="page-kicker">BẢO MẬT TÀI KHOẢN</p>
            <h1 id="forced-password-title">Cập nhật mật khẩu</h1>
            <p class="forced-password-copy">Xin chào <?= $escape($currentUser['display_name']) ?>. Bạn có thể đổi mật khẩu ngay hoặc giữ nguyên mật khẩu hiện tại. Nếu giữ nguyên mà không chọn “Không hiện lại”, lời nhắc sẽ xuất hiện ở lần đăng nhập tiếp theo.</p>
            <form id="employee-password-form" class="forced-password-form" data-required="1">
                <label for="new-password">Mật khẩu mới
                    <input id="new-password" name="newPassword" type="password" minlength="8" maxlength="72" autocomplete="new-password" required autofocus>
                </label>
                <label for="confirm-password">Xác nhận mật khẩu mới
                    <input id="confirm-password" name="confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                </label>
                <p class="forced-password-hint">Mật khẩu cần có ít nhất 8 ký tự.</p>
                <label class="forced-password-reminder">
                    <input id="dont-show-password-prompt" type="checkbox">
                    <span>Không hiện lại lời nhắc này ở lần đăng nhập sau</span>
                </label>
                <p id="password-feedback" class="forced-password-feedback" role="status" aria-live="polite"></p>
                <div class="forced-password-actions">
                    <button class="secondary-button" type="button" data-keep-password>Giữ mật khẩu hiện tại</button>
                    <button class="primary-button forced-password-submit" type="submit">Đổi mật khẩu</button>
                </div>
            </form>
            <form class="forced-password-logout" action="index.php?action=logout" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button class="employee-logout-button" type="submit"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button>
            </form>
        </section>
    </main>
    <div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script src="assets/js/employee-personal.js" defer></script>
</body>
</html>
