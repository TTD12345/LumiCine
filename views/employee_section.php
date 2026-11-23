<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$currentUserName = $escape($currentUser['display_name']);
$currentUserInitials = $escape($currentUser['initials']);

$sections = [
    'dashboard' => ['kicker' => 'TỔNG QUAN', 'title' => 'Tổng quan', 'description' => 'Thông tin ca làm, chấm công và đơn của bạn trong một nơi.'],
    'attendance' => ['kicker' => 'CHẤM CÔNG', 'title' => 'Chấm công', 'description' => 'Ghi nhận giờ vào, giờ ra và xem trạng thái chấm công.'],
    'timesheet' => ['kicker' => 'BẢNG CÔNG CÁ NHÂN', 'title' => 'Bảng công', 'description' => 'Theo dõi giờ làm, ngày công và tăng ca của riêng bạn.'],
    'shift' => ['kicker' => 'CA LÀM VIỆC', 'title' => 'Ca làm việc', 'description' => 'Danh sách ca làm việc được phân công cho nhân viên.'],
    'overtime' => ['kicker' => 'ĐĂNG KÝ LÀM THÊM', 'title' => 'Đăng ký tăng ca', 'description' => 'Tạo và theo dõi các đơn tăng ca của bạn.'],
    'leave' => ['kicker' => 'NGHỈ PHÉP', 'title' => 'Đăng ký nghỉ phép', 'description' => 'Tạo đơn xin nghỉ và theo dõi trạng thái duyệt.'],
    'requests' => ['kicker' => 'YÊU CẦU CỦA TÔI', 'title' => 'Đơn của tôi', 'description' => 'Theo dõi trạng thái xử lý các đơn bạn đã gửi.'],
    'notifications' => ['kicker' => 'CẬP NHẬT CÁ NHÂN', 'title' => 'Thông báo', 'description' => 'Thông tin liên quan đến chấm công và các đơn của bạn.'],
    'profile' => ['kicker' => 'HỒ SƠ CÁ NHÂN', 'title' => 'Hồ sơ cá nhân', 'description' => 'Xem thông tin nhân sự và cập nhật thông tin liên hệ được phép.'],
    'schedule' => ['kicker' => 'LỊCH LÀM VIỆC', 'title' => 'Lịch làm việc', 'description' => 'Xem lịch làm việc cá nhân.'],
    'employees' => ['kicker' => 'DANH SÁCH NHÂN VIÊN', 'title' => 'Nhân viên', 'description' => 'Thông tin nhân viên trong tổ chức.'],
    'reports' => ['kicker' => 'BÁO CÁO', 'title' => 'Báo cáo', 'description' => 'Thống kê và báo cáo hoạt động nhân sự.'],
];
$page = $sections[$section] ?? $sections['dashboard'];
$isOvertime = $section === 'overtime';
$isLeave = $section === 'leave';
$isTimesheet = $section === 'timesheet';
$isRequests = $section === 'requests';
$isProfile = $section === 'profile';
$isNotifications = $section === 'notifications';
$employeeProfile = $employeeProfile ?? [
    'employee_code' => $currentUser['employee_code'] ?? '',
    'name' => $currentUser['display_name'],
    'department' => '',
    'position' => '',
    'birth_date' => null,
    'hire_date' => null,
    'phone' => '',
    'email' => '',
];
$employeeTimesheet = $employeeTimesheet ?? [
    'records' => [],
    'summary' => ['work_days' => 0, 'overtime_minutes' => 0, 'leave_days' => 0],
];
$employeeMonth = $employeeMonth ?? (int) date('n');
$employeeYear = $employeeYear ?? (int) date('Y');
$profileBirthDate = !empty($employeeProfile['birth_date'])
    ? date('d/m/Y', strtotime($employeeProfile['birth_date']))
    : 'Chưa cập nhật';
$profileHireDate = !empty($employeeProfile['hire_date'])
    ? date('d/m/Y', strtotime($employeeProfile['hire_date']))
    : 'Chưa cập nhật';
$employeeCount = count($employeeRecords);
$shiftCount = count($employeeShifts);
$todayWorkMinutes = 0;
if (!empty($attendanceToday['check_in'])) {
    $checkInTime = strtotime(date('Y-m-d') . ' ' . $attendanceToday['check_in']);
    $endTime = !empty($attendanceToday['check_out'])
        ? strtotime(date('Y-m-d') . ' ' . $attendanceToday['check_out'])
        : time();
    if ($endTime <= $checkInTime) {
        $endTime += 86400;
    }
    $todayWorkMinutes = max(0, (int) (($endTime - $checkInTime) / 60));
}
$formatHours = static function (int $minutes): string {
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;
    return $remainingMinutes > 0 ? $hours . ' giờ ' . $remainingMinutes . ' phút' : $hours . ' giờ';
};
$attendanceSummary = !$attendanceToday
    ? 'Chưa liên kết hồ sơ'
    : (!$attendanceToday['has_assignment']
        ? 'Chưa có phân ca'
        : (($attendanceToday['attendance_status'] ?? '') === 'VANG_MAT'
            ? 'Vắng mặt'
            : ($attendanceToday['check_out'] !== ''
                ? (($attendanceToday['late_minutes'] ?? 0) > 0 ? 'Đi muộn · Đã hoàn thành' : 'Đã hoàn thành')
                : ($attendanceToday['check_in'] !== ''
                    ? (($attendanceToday['late_minutes'] ?? 0) > 0 ? 'Đi muộn · Đang làm việc' : 'Đang làm việc')
                    : 'Chưa bắt đầu'))));
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
            <a class="employee-nav-link <?= $section === 'dashboard' ? 'active' : '' ?>" href="?action=dashboard"><i class="bi bi-grid-1x2"></i><span>Tổng quan</span></a>
            <a class="employee-nav-link <?= $section === 'attendance' ? 'active' : '' ?>" href="?action=attendance"><i class="bi bi-fingerprint"></i><span>Chấm công</span></a>
            <a class="employee-nav-link <?= $isTimesheet ? 'active' : '' ?>" href="?action=timesheet"><i class="bi bi-calendar2-week"></i><span>Bảng công</span></a>
            <a class="employee-nav-link <?= $isLeave ? 'active' : '' ?>" href="?action=leave"><i class="bi bi-calendar2-minus"></i><span>Xin nghỉ phép</span></a>
            <a class="employee-nav-link <?= $isOvertime ? 'active' : '' ?>" href="?action=overtime"><i class="bi bi-clock-history"></i><span>Đăng ký tăng ca</span></a>
            <a class="employee-nav-link <?= $isRequests ? 'active' : '' ?>" href="?action=requests"><i class="bi bi-inboxes"></i><span>Đơn của tôi</span></a>
            <a class="employee-nav-link <?= $isNotifications ? 'active' : '' ?>" href="?action=notifications"><i class="bi bi-bell"></i><span>Thông báo</span></a>
            <a class="employee-nav-link <?= $isProfile ? 'active' : '' ?>" href="?action=profile"><i class="bi bi-person-circle"></i><span>Hồ sơ cá nhân</span></a>
            <p class="nav-caption nav-caption-spaced">LỊCH LÀM VIỆC</p>
            <a class="employee-nav-link <?= $section === 'schedule' ? 'active' : '' ?>" href="?action=schedule"><i class="bi bi-calendar-week"></i><span>Lịch làm việc</span></a>
            <a class="employee-nav-link <?= $section === 'shift' ? 'active' : '' ?>" href="?action=shift"><i class="bi bi-clock"></i><span>Ca làm việc</span></a>
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
                    <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Thời gian nghỉ</th><th>Loại nghỉ</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody id="employee-leave-rows"><tr><td colspan="5">Đang tải đơn nghỉ phép...</td></tr></tbody></table></div>
                </section>
            <?php elseif ($section === 'dashboard'): ?>
                <section class="employee-welcome-card">
                    <div class="employee-welcome-copy">
                        <span class="page-kicker">HÔM NAY · <?= $escape(date('d/m/Y')) ?></span>
                        <h2>Xin chào, <?= $currentUserName ?>!</h2>
                        <p><?= $escape($employeeProfile['department'] ?: 'Chưa cập nhật phòng ban') ?> · <?= $escape($employeeProfile['position'] ?: 'Chưa cập nhật chức vụ') ?> · Mã NV <?= $escape($employeeProfile['employee_code']) ?></p>
                    </div>
                    <div class="welcome-status"><span>Trạng thái hôm nay</span><strong><?= $escape($attendanceSummary) ?></strong></div>
                </section>
                <section class="employee-stats">
                    <article class="stat-card"><div class="stat-top"><span>Giờ vào</span><i class="bi bi-box-arrow-in-right"></i></div><strong><?= $escape($attendanceToday['check_in'] ?? '') ?: '—' ?></strong><small>chấm công vào</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Giờ ra</span><i class="bi bi-box-arrow-right"></i></div><strong><?= $escape($attendanceToday['check_out'] ?? '') ?: '—' ?></strong><small>chấm công ra</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Tổng giờ làm hôm nay</span><i class="bi bi-hourglass-split"></i></div><strong><?= $escape($formatHours($todayWorkMinutes)) ?></strong><small>theo dữ liệu chấm công</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Ngày công tháng này</span><i class="bi bi-calendar2-check"></i></div><strong><?= (int) $employeeTimesheet['summary']['work_days'] ?></strong><small>ngày hoàn thành ca</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Tăng ca tháng này</span><i class="bi bi-clock-history"></i></div><strong><?= $escape($formatHours((int) $employeeTimesheet['summary']['overtime_minutes'])) ?></strong><small>chỉ tính đơn đã duyệt</small></article>
                    <article class="stat-card"><div class="stat-top"><span>Ngày nghỉ tháng này</span><i class="bi bi-calendar2-minus"></i></div><strong><?= (int) $employeeTimesheet['summary']['leave_days'] ?></strong><small>ngày nghỉ được duyệt</small></article>
                </section>
                <section class="schedule-card dashboard-attendance-card"><div class="schedule-heading"><div><h2>Chấm công hôm nay</h2><p>Thời gian chấm công theo hồ sơ cá nhân của bạn.</p></div><a class="employee-text-link" href="?action=attendance">Đến chấm công <i class="bi bi-arrow-up-right"></i></a></div><div class="today-attendance-summary"><div><span>Giờ vào</span><strong><?= $escape($attendanceToday['check_in'] ?? '') ?: 'Chưa ghi nhận' ?></strong></div><div><span>Giờ ra</span><strong><?= $escape($attendanceToday['check_out'] ?? '') ?: 'Chưa ghi nhận' ?></strong></div><div><span>Trạng thái</span><strong><?= $escape($attendanceSummary) ?></strong></div></div></section>
            <?php elseif ($isTimesheet): ?>
                <section class="schedule-card personal-timesheet">
                    <div class="schedule-heading"><div><h2>Bảng công cá nhân</h2><p>Chỉ hiển thị lịch làm và dữ liệu chấm công gắn với hồ sơ của bạn.</p></div><label class="month-picker"><span>Tháng</span><input id="timesheet-month" type="month" value="<?= $escape(sprintf('%04d-%02d', $employeeYear, $employeeMonth)) ?>"></label></div>
                    <div class="schedule-toolbar"><div class="status-filters" id="timesheet-status-filters"><button class="filter-chip active" type="button" data-status="all">Tất cả</button><button class="filter-chip" type="button" data-status="complete">Đủ công</button><button class="filter-chip" type="button" data-status="late">Đi muộn</button><button class="filter-chip" type="button" data-status="leave">Nghỉ phép</button><button class="filter-chip" type="button" data-status="absent">Vắng mặt</button><button class="filter-chip" type="button" data-status="incomplete">Thiếu chấm công</button></div></div>
                    <div class="employee-table-wrap"><table class="employee-table timesheet-table"><thead><tr><th>Ngày</th><th>Thứ</th><th>Giờ vào</th><th>Giờ ra</th><th>Tổng giờ làm</th><th>Tăng ca</th><th>Công</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody id="employee-timesheet-rows"><tr><td colspan="9">Đang tải bảng công...</td></tr></tbody></table></div>
                    <footer class="schedule-footer"><span id="timesheet-result-count">Đang tải dữ liệu...</span><span>Thời gian tăng ca chỉ cộng các đơn đã duyệt. Không áp dụng quy tắc trừ công.</span></footer>
                </section>
            <?php elseif ($isRequests): ?>
                <section class="schedule-card personal-requests">
                    <div class="schedule-heading"><div><h2>Đơn đã gửi</h2><p>Đơn nghỉ phép và tăng ca thuộc hồ sơ của bạn.</p></div></div>
                    <div class="schedule-toolbar"><div class="status-filters" id="request-status-filters"><button class="filter-chip active" type="button" data-status="all">Tất cả</button><button class="filter-chip" type="button" data-status="pending">Chờ duyệt</button><button class="filter-chip" type="button" data-status="approved">Đã duyệt</button><button class="filter-chip" type="button" data-status="rejected">Từ chối</button></div></div>
                    <div class="employee-table-wrap"><table class="employee-table requests-table"><thead><tr><th>Mã đơn</th><th>Loại đơn</th><th>Ngày</th><th>Nội dung</th><th>Thời gian gửi</th><th>Người duyệt</th><th>Trạng thái</th></tr></thead><tbody id="employee-request-rows"><tr><td colspan="7">Đang tải danh sách đơn...</td></tr></tbody></table></div>
                </section>
            <?php elseif ($isNotifications): ?>
                <section class="schedule-card"><div class="schedule-heading"><div><h2>Thông báo của bạn</h2><p>Thông báo được tổng hợp từ trạng thái chấm công và các đơn cá nhân.</p></div></div><div id="employee-notification-list" class="personal-notification-list" aria-live="polite"><p>Đang tải thông báo...</p></div></section>
            <?php elseif ($isProfile): ?>
                <section class="schedule-card profile-card">
                    <div class="schedule-heading"><div><h2>Thông tin nhân viên</h2><p>Thông tin hồ sơ liên kết với tài khoản đang đăng nhập.</p></div></div>
                    <div class="profile-content"><div class="profile-avatar-large"><?= $currentUserInitials ?></div><dl class="profile-details">
                        <div><dt>Họ tên</dt><dd><?= $escape($employeeProfile['name']) ?></dd></div><div><dt>Mã nhân viên</dt><dd><?= $escape($employeeProfile['employee_code']) ?></dd></div>
                        <div><dt>Ngày sinh</dt><dd><?= $escape($profileBirthDate) ?></dd></div>
                        <div><dt>Phòng ban</dt><dd><?= $escape($employeeProfile['department'] ?: 'Chưa cập nhật') ?></dd></div><div><dt>Chức vụ</dt><dd><?= $escape($employeeProfile['position'] ?: 'Chưa cập nhật') ?></dd></div><div><dt>Ngày vào làm</dt><dd><?= $escape($profileHireDate) ?></dd></div>
                    </dl></div>
                    <?php if ($currentUser['role'] === 'employee'): ?>
                        <form id="employee-profile-form" class="profile-form"><div><label for="profile-phone">Số điện thoại</label><input id="profile-phone" name="phone" type="tel" maxlength="30" value="<?= $escape($employeeProfile['phone'] ?? '') ?>" autocomplete="tel"></div><div><label for="profile-email">Email</label><input id="profile-email" name="email" type="email" maxlength="190" value="<?= $escape($employeeProfile['email'] ?? '') ?>" autocomplete="email"></div><p class="profile-form-note">Bạn chỉ có thể tự cập nhật số điện thoại và email. Họ tên, mã nhân viên, phòng ban, chức vụ và ngày công do quản lý hồ sơ cập nhật.</p><div class="profile-form-actions"><span id="profile-feedback" role="status" aria-live="polite"></span><button class="primary-button" type="submit">Lưu thông tin liên hệ</button></div></form>
                    <?php else: ?>
                        <p class="profile-form-note profile-readonly-note">Thông tin hồ sơ chỉ đọc trong không gian này.</p>
                    <?php endif; ?>
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
<dialog class="shift-dialog overtime-dialog" id="overtime-dialog" aria-labelledby="overtime-dialog-title"><form id="overtime-form"><header class="dialog-heading"><div><span class="page-kicker">ĐĂNG KÝ LÀM THÊM</span><h2 id="overtime-dialog-title">Đơn đăng ký tăng ca</h2><p>Thông tin nhân viên: <strong id="overtime-employee-name"><?= $currentUserName ?></strong></p><p>Chỉ đăng ký ngoài giờ hành chính 08:00 - 17:00. Ca tăng ca chỉ được tạo sau khi quản trị viên duyệt.</p></div><button class="dialog-close" type="button" id="overtime-close-form" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header><div class="dialog-grid"><label class="dialog-field">Bắt đầu tăng ca<input name="start" type="datetime-local" required></label><label class="dialog-field">Kết thúc tăng ca<input name="end" type="datetime-local" required></label><div class="duration-preview full">Tổng thời gian đăng ký: <strong id="overtime-duration">—</strong></div><label class="dialog-field full">Lý do tăng ca<textarea name="reason" required minlength="5" maxlength="800" rows="3"></textarea></label><label class="dialog-field full">Ghi chú (không bắt buộc)<textarea name="note" maxlength="180" rows="2"></textarea></label></div><footer class="dialog-actions"><button class="secondary-button" type="button" id="overtime-cancel-form">Hủy</button><button class="primary-button" type="submit">Gửi đơn</button></footer></form></dialog>
<?php endif; ?>
<?php if ($isLeave && $canSubmitOvertime): ?>
<dialog class="shift-dialog overtime-dialog" id="leave-dialog" aria-labelledby="leave-dialog-title">
    <form id="leave-form">
        <header class="dialog-heading"><div><span class="page-kicker">NGHỈ PHÉP</span><h2 id="leave-dialog-title">Đơn đăng ký nghỉ phép</h2><p>Thông tin nhân viên: <strong><?= $currentUserName ?></strong></p></div><button class="dialog-close" type="button" id="leave-close-form" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
        <div class="dialog-grid">
            <label class="dialog-field">Bắt đầu nghỉ<input name="start" type="datetime-local" required></label>
            <label class="dialog-field">Kết thúc nghỉ<input name="end" type="datetime-local" required></label>
            <div class="duration-preview full">Khoảng nghỉ theo ngày lịch: <strong id="leave-duration">—</strong><small>Ngày công được xác định theo lịch phân ca và quy định của công ty.</small></div>
            <label class="dialog-field">Loại nghỉ
                <select name="leaveType" required>
                    <option value="PHEP_NAM">Phép năm</option>
                    <option value="NGHI_OM">Nghỉ ốm</option>
                    <option value="KHONG_LUONG">Nghỉ không lương</option>
                    <option value="VIEC_RIENG">Việc riêng</option>
                    <option value="KHAC">Khác</option>
                </select>
            </label>
            <label class="dialog-field full">Lý do nghỉ phép<textarea name="reason" required minlength="5" maxlength="1000" rows="4" placeholder="Ví dụ: Việc gia đình, khám bệnh..."></textarea></label>
        </div>
        <footer class="dialog-actions"><button class="secondary-button" type="button" id="leave-cancel-form">Hủy</button><button class="primary-button" type="submit">Gửi đơn</button></footer>
    </form>
</dialog>
<?php endif; ?>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
<script src="assets/js/employee-section.js" defer></script>
<?php if ($isTimesheet || $isRequests || $isNotifications || $isProfile): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script src="assets/js/employee-personal.js" defer></script>
<?php endif; ?>
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
