<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$currentUserName = $escape($currentUser['display_name']);
$currentUserInitials = $escape($currentUser['initials']);

$sections = [
    'dashboard' => ['kicker' => 'TỔNG QUAN', 'title' => 'Bảng điều khiển', 'description' => 'Xem tổng quan lịch làm việc, chấm công và đơn tăng ca.'],
    'shift' => ['kicker' => 'CA LÀM VIỆC', 'title' => 'Ca làm việc', 'description' => 'Danh sách ca làm việc được phân công cho nhân viên.'],
    'overtime' => ['kicker' => 'ĐĂNG KÝ LÀM THÊM', 'title' => 'Đăng ký tăng ca', 'description' => 'Tạo và theo dõi các đơn tăng ca của bạn.'],
    'employees' => ['kicker' => 'DANH SÁCH NHÂN VIÊN', 'title' => 'Nhân viên', 'description' => 'Thông tin nhân viên trong tổ chức.'],
    'reports' => ['kicker' => 'BÁO CÁO', 'title' => 'Báo cáo', 'description' => 'Thống kê và báo cáo hoạt động nhân sự.'],
];
$page = $sections[$section] ?? $sections['dashboard'];
$isOvertime = $section === 'overtime';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($page['title']) ?> | LumiCine</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/employee.css" rel="stylesheet">
    <?php if ($isOvertime): ?><link href="assets/css/overtime.css" rel="stylesheet"><?php endif; ?>
</head>
<body>
<div class="employee-app">
    <aside class="employee-sidebar" id="employee-sidebar" aria-label="Điều hướng chính">
        <a class="employee-brand" href="?action=dashboard"><span class="brand-icon"><i class="bi bi-buildings-fill"></i></span><span class="brand-title">LumiCine<small>QUẢN LÝ NHÂN SỰ</small></span></a>
        <p class="nav-caption">TỔNG QUAN</p>
        <nav class="employee-nav">
            <a class="employee-nav-link <?= $section === 'dashboard' ? 'active' : '' ?>" href="?action=dashboard"><i class="bi bi-grid-1x2"></i><span>Bảng điều khiển</span></a>
            <a class="employee-nav-link <?= $section === 'schedule' ? 'active' : '' ?>" href="?action=schedule"><i class="bi bi-calendar-week"></i><span>Lịch làm việc</span></a>
            <a class="employee-nav-link <?= $section === 'shift' ? 'active' : '' ?>" href="?action=shift"><i class="bi bi-people"></i><span>Ca làm việc</span></a>
            <a class="employee-nav-link <?= $isOvertime ? 'active' : '' ?>" href="?action=overtime"><i class="bi bi-clock-history"></i><span>Đăng ký tăng ca</span></a>
            <a class="employee-nav-link <?= $section === 'attendance' ? 'active' : '' ?>" href="?action=attendance"><i class="bi bi-calendar2-check"></i><span>Chấm công</span></a>
        </nav>
        <?php if ($currentUser['role'] !== 'employee'): ?>
            <p class="nav-caption nav-caption-spaced">QUẢN LÝ</p>
            <nav class="employee-nav">
                <a class="employee-nav-link <?= $section === 'employees' ? 'active' : '' ?>" href="?action=employees"><i class="bi bi-person-vcard"></i><span>Nhân viên</span></a>
                <a class="employee-nav-link <?= $section === 'reports' ? 'active' : '' ?>" href="?action=reports"><i class="bi bi-graph-up"></i><span>Báo cáo</span></a>
            </nav>
        <?php endif; ?>
        <div class="employee-sidebar-bottom">
            <?php if (in_array($currentUser['role'], ['accountant', 'hr', 'chairman'], true)): ?>
                <a class="employee-admin-link" href="admin.php"><i class="bi bi-shield-lock"></i> Trang quản trị HRM</a>
            <?php endif; ?>
            <form class="employee-logout-form" action="index.php?action=logout" method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><button class="employee-logout-button" type="submit"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button></form>
        </div>
    </aside>
    <div class="sidebar-scrim" id="sidebar-scrim"></div>
    <main class="employee-main">
        <header class="employee-topbar">
            <div class="topbar-leading"><button class="mobile-menu" type="button" id="mobile-menu-button" aria-label="Mở menu"><i class="bi bi-list"></i></button><div><strong><?= $escape($page['title']) ?></strong><div class="breadcrumbs"><a href="?action=dashboard">Trang chủ</a><i class="bi bi-chevron-right"></i><span><?= $escape($page['title']) ?></span></div></div></div>
            <div class="topbar-trailing">
                <button class="notification-button" type="button" id="notifications-button" aria-label="Thông báo"><i class="bi bi-bell"></i></button>
                <span class="topbar-divider"></span>
                <div class="topbar-account">
                    <button class="topbar-profile" type="button" id="account-menu-button" aria-haspopup="menu" aria-expanded="false"><span class="profile-avatar"><?= $currentUserInitials ?></span><span class="topbar-profile-copy"><strong><?= $currentUserName ?></strong><small><?= $escape($roleLabel) ?></small></span><i class="bi bi-chevron-down"></i></button>
                    <div class="account-menu" id="account-menu" role="menu" hidden><form action="index.php?action=logout" method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><button type="submit" role="menuitem"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button></form></div>
                </div>
            </div>
        </header>
        <div class="employee-content">
            <section class="page-heading"><div><span class="page-kicker"><?= $escape($page['kicker']) ?></span><h1><?= $escape($page['title']) ?></h1><p><?= $escape($page['description']) ?></p></div></section>
            <?php if ($isOvertime): ?>
                <section class="overtime-card" id="overtime" aria-labelledby="overtime-title">
                    <div class="overtime-heading"><div><h2 id="overtime-title">Đăng ký tăng ca</h2><p>Gửi lý do và thời gian tăng ca để quản trị viên xem xét.</p></div><button class="overtime-button overtime-button-primary" type="button" id="overtime-open-form"><i class="bi bi-plus-lg"></i> Tạo đơn tăng ca</button></div>
                    <p class="overtime-note">Thời gian tăng ca không được trùng giờ hành chính (08:00–17:00).</p>
                    <p class="overtime-note">Đơn đăng ký được lưu trên trình duyệt và hiển thị lại ở tất cả trang nhân viên.</p>
                    <div class="overtime-feedback" id="overtime-feedback" role="status" aria-live="polite"></div>
                    <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Thời gian</th><th>Bộ phận</th><th>Lý do</th><th>Trạng thái</th></tr></thead><tbody id="employee-overtime-rows"><tr><td colspan="4">Đang tải đơn tăng ca...</td></tr></tbody></table></div>
                </section>
            <?php elseif ($section === 'dashboard'): ?>
                <section class="employee-stats">
                    <article class="stat-card"><div class="stat-top"><span>Tổng ca làm việc</span><i class="bi bi-calendar2-week"></i></div><strong>6</strong><small>ca hôm nay</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Đơn tăng ca</span><i class="bi bi-clock-history"></i></div><strong id="dashboard-overtime-count">0</strong><small>đơn đã gửi</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Chấm công</span><i class="bi bi-calendar2-check"></i></div><strong>—</strong><small>trạng thái hôm nay</small></article>
                </section>
            <?php elseif ($section === 'shift'): ?>
                <section class="schedule-card"><div class="schedule-heading"><div><h2>Ca làm việc hôm nay</h2><p>Danh sách ca được phân công cho nhân viên.</p></div></div><div class="employee-table-wrap"><table class="employee-table"><thead><tr><th>Nhân viên</th><th>Bộ phận</th><th>Thời gian</th><th>Địa điểm</th><th>Trạng thái</th></tr></thead><tbody><tr><td>Nguyễn Minh Anh</td><td>Hành chính - Nhân sự</td><td>08:00 – 16:00</td><td>Văn phòng tầng 1</td><td><span class="shift-status active">Đang diễn ra</span></td></tr><tr><td>Trần Quốc Bảo</td><td>Kinh doanh</td><td>09:00 – 17:00</td><td>Văn phòng tầng 2</td><td><span class="shift-status active">Đang diễn ra</span></td></tr><tr><td>Lê Hoàng Yến</td><td>Marketing</td><td>12:00 – 20:00</td><td>Phòng họp A</td><td><span class="shift-status upcoming">Sắp bắt đầu</span></td></tr></tbody></table></div></section>
            <?php elseif ($section === 'employees'): ?>
                <section class="schedule-card"><div class="schedule-heading"><div><h2>Danh sách nhân viên</h2><p>Thông tin nhân viên trong tổ chức.</p></div></div><div class="employee-table-wrap"><table class="employee-table"><thead><tr><th>Mã NV</th><th>Nhân viên</th><th>Bộ phận</th><th>Chức vụ</th></tr></thead><tbody><tr><td>NV001</td><td>Nguyễn Minh Anh</td><td>Hành chính - Nhân sự</td><td>Trưởng phòng nhân sự</td></tr><tr><td>NV002</td><td>Trần Quốc Bảo</td><td>Kinh doanh</td><td>Chuyên viên kinh doanh</td></tr><tr><td>NV003</td><td>Lê Hoàng Yến</td><td>Marketing</td><td>Chuyên viên marketing</td></tr></tbody></table></div></section>
            <?php elseif ($section === 'reports'): ?>
                <section class="employee-stats"><article class="stat-card"><div class="stat-top"><span>Tổng nhân viên</span><i class="bi bi-person-vcard"></i></div><strong>6</strong><small>nhân viên</small></article><article class="stat-card"><div class="stat-top"><span>Ca đang diễn ra</span><i class="bi bi-clock"></i></div><strong>2</strong><small>ca</small></article><article class="stat-card"><div class="stat-top"><span>Đơn tăng ca</span><i class="bi bi-hourglass-split"></i></div><strong>—</strong><small>đơn</small></article></section>
            <?php else: ?>
                <section class="schedule-card section-placeholder"><div class="schedule-heading"><div><h2><?= $escape($page['title']) ?></h2><p>Nội dung chức năng đang sẵn sàng trên trang riêng này.</p></div></div></section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php if ($isOvertime): ?>
<dialog class="shift-dialog overtime-dialog" id="overtime-dialog" aria-labelledby="overtime-dialog-title"><form id="overtime-form"><header class="dialog-heading"><div><span class="page-kicker">ĐĂNG KÝ LÀM THÊM</span><h2 id="overtime-dialog-title">Đơn đăng ký tăng ca</h2><p>Thông tin nhân viên: <strong id="overtime-employee-name"><?= $currentUserName ?></strong></p></div><button class="dialog-close" type="button" id="overtime-close-form" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header><div class="dialog-grid"><label class="dialog-field">Bắt đầu tăng ca<input name="start" type="datetime-local" required></label><label class="dialog-field">Kết thúc tăng ca<input name="end" type="datetime-local" required></label><label class="dialog-field full">Bộ phận<input name="department" type="text" required maxlength="100" placeholder="Ví dụ: Kế toán"></label><label class="dialog-field full">Lý do tăng ca<textarea name="reason" required minlength="5" maxlength="1000" rows="4"></textarea></label></div><p class="overtime-form-feedback" id="overtime-form-feedback" role="alert" aria-live="polite"></p><footer class="dialog-actions"><button class="secondary-button" type="button" id="overtime-cancel-form">Hủy</button><button class="primary-button" type="submit">Gửi đơn</button></footer></form></dialog>
<?php endif; ?>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
<script src="assets/js/employee-section.js" defer></script>
<?php if ($isOvertime): ?><script src="assets/js/overtime.js" defer></script><?php endif; ?>
</body>
</html>
