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
    'leave' => ['kicker' => 'NGHỈ PHÉP', 'title' => 'Đăng ký nghỉ phép', 'description' => 'Tạo đơn xin nghỉ và theo dõi trạng thái duyệt.'],
    'employees' => ['kicker' => 'DANH SÁCH NHÂN VIÊN', 'title' => 'Nhân viên', 'description' => 'Thông tin nhân viên trong tổ chức.'],
    'reports' => ['kicker' => 'BÁO CÁO', 'title' => 'Báo cáo', 'description' => 'Thống kê và báo cáo hoạt động nhân sự.'],
];
$page = $sections[$section] ?? $sections['dashboard'];
$isOvertime = $section === 'overtime';
$isLeave = $section === 'leave';
$employeeCount = count($employeeRecords);
$shiftCount = count($employeeShifts);
$attendanceSummary = !$attendanceToday
    ? 'Chưa liên kết hồ sơ'
    : (!$attendanceToday['has_assignment']
        ? 'Chưa có phân ca'
        : (($attendanceToday['attendance_status'] ?? '') === 'VANG_MAT'
            ? 'Vắng mặt'
            : ($attendanceToday['check_out'] !== ''
                ? 'Đã hoàn thành'
                : ($attendanceToday['check_in'] !== '' ? 'Đang làm việc' : 'Chưa bắt đầu'))));
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
    <?php if ($isOvertime || $isLeave): ?><link href="assets/css/overtime.css" rel="stylesheet"><?php endif; ?>
</head>
<body>
<div class="employee-app">
    <aside class="employee-sidebar" id="employee-sidebar" aria-label="Điều hướng chính">
        <a class="employee-brand" href="?action=dashboard"><span class="brand-icon"><i class="bi bi-buildings-fill"></i></span><span class="brand-title">LumiCine</span></a>
        <p class="nav-caption">TỔNG QUAN</p>
        <nav class="employee-nav">
            <a class="employee-nav-link <?= $section === 'dashboard' ? 'active' : '' ?>" href="?action=dashboard"><i class="bi bi-grid-1x2"></i><span>Bảng điều khiển</span></a>
            <a class="employee-nav-link <?= $section === 'schedule' ? 'active' : '' ?>" href="?action=schedule"><i class="bi bi-calendar-week"></i><span>Lịch làm việc</span></a>
            <a class="employee-nav-link <?= $section === 'shift' ? 'active' : '' ?>" href="?action=shift"><i class="bi bi-people"></i><span>Ca làm việc</span></a>
            <a class="employee-nav-link <?= $isOvertime ? 'active' : '' ?>" href="?action=overtime"><i class="bi bi-clock-history"></i><span>Đăng ký tăng ca</span></a>
            <a class="employee-nav-link <?= $isLeave ? 'active' : '' ?>" href="?action=leave"><i class="bi bi-calendar2-minus"></i><span>Đăng ký nghỉ phép</span></a>
            <a class="employee-nav-link <?= $section === 'attendance' ? 'active' : '' ?>" href="?action=attendance"><i class="bi bi-calendar2-check"></i><span>Chấm công</span></a>
        </nav>
        <div class="employee-sidebar-bottom">
            <?php if (in_array($currentUser['role'], ['accountant', 'hr', 'chairman'], true)): ?>
                <a class="employee-admin-link" href="admin.php"><i class="bi bi-shield-lock"></i> Trang quản trị LumiCine</a>
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
                    <div class="overtime-heading"><div><h2 id="overtime-title">Đăng ký tăng ca</h2><p>Gửi lý do và thời gian tăng ca để quản trị viên xem xét.</p></div><?php if ($canSubmitOvertime): ?><button class="overtime-button overtime-button-primary" type="button" id="overtime-open-form"><i class="bi bi-plus-lg"></i> Tạo đơn tăng ca</button><?php endif; ?></div>
                    <p class="overtime-note">Đơn đăng ký và trạng thái duyệt được lưu tập trung trong database.</p>
                    <div class="overtime-feedback" id="overtime-feedback" role="status" aria-live="polite"></div>
                    <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Thời gian</th><th>Lý do</th><th>Trạng thái</th></tr></thead><tbody id="employee-overtime-rows"><tr><td colspan="3">Đang tải đơn tăng ca...</td></tr></tbody></table></div>
                </section>
            <?php elseif ($isLeave): ?>
                <section class="overtime-card" id="leave" aria-labelledby="leave-title">
                    <div class="overtime-heading"><div><h2 id="leave-title">Đăng ký nghỉ phép</h2><p>Gửi thời gian và lý do nghỉ để quản trị viên xem xét.</p></div><?php if ($canSubmitOvertime): ?><button class="overtime-button overtime-button-primary" type="button" id="leave-open-form"><i class="bi bi-plus-lg"></i> Tạo đơn nghỉ phép</button><?php endif; ?></div>
                    <p class="overtime-note">Chỉ được hủy đơn khi đơn vẫn đang chờ duyệt.</p>
                    <div class="overtime-feedback" id="leave-feedback" role="status" aria-live="polite"></div>
                    <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Thời gian nghỉ</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody id="employee-leave-rows"><tr><td colspan="4">Đang tải đơn nghỉ phép...</td></tr></tbody></table></div>
                </section>
            <?php elseif ($section === 'dashboard'): ?>
                <section class="employee-stats">
                    <article class="stat-card"><div class="stat-top"><span>Tổng ca làm việc</span><i class="bi bi-calendar2-week"></i></div><strong><?= $shiftCount ?></strong><small>ca hôm nay</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Đơn tăng ca</span><i class="bi bi-clock-history"></i></div><strong><?= $overtimeCount ?></strong><small>đơn đã gửi</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Đơn nghỉ phép</span><i class="bi bi-calendar2-minus"></i></div><strong><?= $leaveCount ?></strong><small>đơn đã gửi</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Chấm công</span><i class="bi bi-calendar2-check"></i></div><strong><?= $escape($attendanceSummary) ?></strong><small>trạng thái hôm nay</small></article>
                </section>
            <?php elseif ($section === 'shift'): ?>
                <section class="schedule-card"><div class="schedule-heading"><div><h2>Ca làm việc hôm nay</h2><p>Danh sách ca được phân công cho nhân viên.</p></div></div><div class="employee-table-wrap"><table class="employee-table"><thead><tr><th>Nhân viên</th><th>Bộ phận</th><th>Thời gian</th><th>Địa điểm</th><th>Trạng thái</th></tr></thead><tbody><?php foreach ($employeeShifts as $shift): ?><tr><td><?= $escape($shift['name']) ?></td><td><?= $escape($shift['department']) ?></td><td><?= $escape(substr($shift['start'], 0, 5)) ?> – <?= $escape(substr($shift['end'], 0, 5)) ?></td><td><?= $escape($shift['location']) ?></td><td><span class="shift-status <?= $escape($shift['status']) ?>"><?= $escape(['active' => 'Đang diễn ra', 'upcoming' => 'Sắp bắt đầu', 'completed' => 'Hoàn thành'][$shift['status']]) ?></span></td></tr><?php endforeach; ?><?php if (!$employeeShifts): ?><tr><td colspan="5">Chưa có ca làm việc được phân công hôm nay.</td></tr><?php endif; ?></tbody></table></div></section>
            <?php elseif ($section === 'employees'): ?>
                <section class="schedule-card"><div class="schedule-heading"><div><h2>Danh sách nhân viên</h2><p>Thông tin nhân viên trong tổ chức.</p></div></div><div class="employee-table-wrap"><table class="employee-table"><thead><tr><th>Mã NV</th><th>Nhân viên</th><th>Bộ phận</th><th>Chức vụ</th></tr></thead><tbody><?php foreach ($employeeRecords as $employee): ?><tr><td><?= $escape($employee['code']) ?></td><td><?= $escape($employee['name']) ?></td><td><?= $escape($employee['department']) ?></td><td><?= $escape($employee['position']) ?></td></tr><?php endforeach; ?><?php if (!$employeeRecords): ?><tr><td colspan="4">Chưa có nhân viên đang hoạt động.</td></tr><?php endif; ?></tbody></table></div></section>
            <?php elseif ($section === 'reports'): ?>
                <section class="employee-stats"><article class="stat-card"><div class="stat-top"><span>Tổng nhân viên</span><i class="bi bi-person-vcard"></i></div><strong><?= $employeeCount ?></strong><small>nhân viên</small></article><article class="stat-card"><div class="stat-top"><span>Ca hôm nay</span><i class="bi bi-clock"></i></div><strong><?= $shiftCount ?></strong><small>ca đã phân công</small></article><article class="stat-card"><div class="stat-top"><span>Đơn tăng ca</span><i class="bi bi-hourglass-split"></i></div><strong><?= $overtimeCount ?></strong><small>đơn</small></article></section>
            <?php else: ?>
                <section class="schedule-card section-placeholder"><div class="schedule-heading"><div><h2><?= $escape($page['title']) ?></h2><p>Nội dung chức năng đang sẵn sàng trên trang riêng này.</p></div></div></section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php if ($isOvertime && $canSubmitOvertime): ?>
<dialog class="shift-dialog overtime-dialog" id="overtime-dialog" aria-labelledby="overtime-dialog-title"><form id="overtime-form"><header class="dialog-heading"><div><span class="page-kicker">ĐĂNG KÝ LÀM THÊM</span><h2 id="overtime-dialog-title">Đơn đăng ký tăng ca</h2><p>Thông tin nhân viên: <strong id="overtime-employee-name"><?= $currentUserName ?></strong></p><p>Chỉ đăng ký ngoài giờ hành chính 08:00 - 17:00. Ca tăng ca chỉ được tạo sau khi quản trị viên duyệt.</p></div><button class="dialog-close" type="button" id="overtime-close-form" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header><div class="dialog-grid"><label class="dialog-field">Bắt đầu tăng ca<input name="start" type="datetime-local" required></label><label class="dialog-field">Kết thúc tăng ca<input name="end" type="datetime-local" required></label><label class="dialog-field full">Lý do tăng ca<textarea name="reason" required minlength="5" maxlength="1000" rows="4"></textarea></label></div><footer class="dialog-actions"><button class="secondary-button" type="button" id="overtime-cancel-form">Hủy</button><button class="primary-button" type="submit">Gửi đơn</button></footer></form></dialog>
<?php endif; ?>
<?php if ($isLeave && $canSubmitOvertime): ?>
<dialog class="shift-dialog overtime-dialog" id="leave-dialog" aria-labelledby="leave-dialog-title">
    <form id="leave-form">
        <header class="dialog-heading"><div><span class="page-kicker">NGHỈ PHÉP</span><h2 id="leave-dialog-title">Đơn đăng ký nghỉ phép</h2><p>Thông tin nhân viên: <strong><?= $currentUserName ?></strong></p></div><button class="dialog-close" type="button" id="leave-close-form" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
        <div class="dialog-grid">
            <label class="dialog-field">Bắt đầu nghỉ<input name="start" type="datetime-local" required></label>
            <label class="dialog-field">Kết thúc nghỉ<input name="end" type="datetime-local" required></label>
            <label class="dialog-field full">Lý do nghỉ phép<textarea name="reason" required minlength="5" maxlength="1000" rows="4" placeholder="Ví dụ: Việc gia đình, khám bệnh..."></textarea></label>
        </div>
        <footer class="dialog-actions"><button class="secondary-button" type="button" id="leave-cancel-form">Hủy</button><button class="primary-button" type="submit">Gửi đơn</button></footer>
    </form>
</dialog>
<?php endif; ?>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
<script src="assets/js/employee-section.js" defer></script>
<?php if ($isOvertime): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script src="assets/js/overtime.js" defer></script>
<?php endif; ?>
<?php if ($isLeave): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script src="assets/js/leave.js" defer></script>
<?php endif; ?>
</body>
</html>
