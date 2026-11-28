<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pages = [
    'overview' => ['title' => 'Tổng quan', 'kicker' => 'KHÔNG GIAN LÀM VIỆC', 'description' => 'Tổng quan hoạt động nhân sự và chấm công.'],
    'employees' => ['title' => 'Quản lý nhân viên', 'kicker' => 'NHÂN SỰ', 'description' => 'Quản lý thông tin và hồ sơ nhân viên.'],
    'accounts' => ['title' => 'Quản lý tài khoản', 'kicker' => 'TÀI KHOẢN NHÂN VIÊN', 'description' => 'Cấp và quản lý thông tin đăng nhập cho nhân viên.'],
    'schedule' => ['title' => 'Phân ca làm việc', 'kicker' => 'LỊCH LÀM VIỆC', 'description' => 'Tạo và quản lý ca làm việc cho nhân viên theo từng ngày.'],
    'attendance' => ['title' => 'Quản lý chấm công', 'kicker' => 'CHẤM CÔNG', 'description' => 'Theo dõi và quản lý dữ liệu chấm công nhân viên.'],
    'overtime' => ['title' => 'Duyệt tăng ca và nghỉ phép', 'kicker' => 'TĂNG CA & NGHỈ PHÉP', 'description' => 'Xem và xử lý đơn đăng ký tăng ca, nghỉ phép.'],
    'reports' => ['title' => 'Báo cáo thống kê', 'kicker' => 'BÁO CÁO', 'description' => 'Xem thống kê và xuất báo cáo nhân sự.'],
    'settings' => ['title' => 'Cài đặt', 'kicker' => 'HỆ THỐNG', 'description' => 'Cấu hình thông tin và tham số hệ thống.'],
];
$page = $pages[$section] ?? $pages['overview'];
$isOvertime = $section === 'overtime';
$isSchedule = $section === 'schedule';
$attendanceStatusFor = static function (array $employee): string {
    if ((int) $employee['absent'] > 0) {
        return 'absent';
    }
    if ((int) $employee['workingDays'] > 0 && (int) $employee['leave'] === (int) $employee['workingDays']) {
        return 'leave';
    }
    if (
        (int) $employee['workingDays'] > 0
        && (int) $employee['worked'] + (int) $employee['leave'] === (int) $employee['workingDays']
        && (int) $employee['incomplete'] === 0
    ) {
        return 'complete';
    }

    return 'incomplete';
};
$attendanceStatusLabels = [
    'complete' => 'Đủ công',
    'incomplete' => 'Thiếu công',
    'leave' => 'Nghỉ phép',
    'absent' => 'Vắng mặt',
];
$attendanceDepartments = array_values(array_unique(array_filter(array_column($employees, 'department'))));
$attendancePositions = array_values(array_unique(array_filter(array_column($employees, 'position'))));
sort($attendanceDepartments, SORT_NATURAL | SORT_FLAG_CASE);
sort($attendancePositions, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($page['title']) ?> | LumiCine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/hrm.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/hrm.css') ?>" rel="stylesheet">
    <?php if ($isOvertime): ?><link href="assets/css/overtime.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/overtime.css') ?>" rel="stylesheet"><?php endif; ?>
    <?php if ($isSchedule): ?><link href="assets/css/schedule.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/schedule.css') ?>" rel="stylesheet"><?php endif; ?>
</head>
<body<?= $isOvertime ? ' class="overtime-page"' : '' ?>>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <aside class="sidebar" id="sidebar" aria-label="Điều hướng quản trị">
        <a class="brand" href="?action=admin-overview"><span class="brand-mark"><i class="bi bi-buildings-fill"></i></span><span><strong>LumiCine</strong></span></a>
        <p class="nav-caption">KHÔNG GIAN LÀM VIỆC</p>
        <nav class="nav flex-column sidebar-nav">
            <a class="nav-link <?= $section === 'overview' ? 'active' : '' ?>" href="?action=admin-overview"><i class="bi bi-grid-1x2"></i><span>Tổng quan</span></a>
            <a class="nav-link <?= $section === 'employees' ? 'active' : '' ?>" href="?action=admin-employees"><i class="bi bi-people"></i><span>Quản lý nhân viên</span></a>
            <a class="nav-link <?= $section === 'accounts' ? 'active' : '' ?>" href="?action=admin-accounts"><i class="bi bi-person-lock"></i><span>Quản lý tài khoản</span></a>
            <a class="nav-link <?= $section === 'schedule' ? 'active' : '' ?>" href="?action=admin-schedule"><i class="bi bi-calendar-week"></i><span>Phân ca làm việc</span></a>
            <a class="nav-link <?= $section === 'attendance' ? 'active' : '' ?>" href="?action=admin-attendance"><i class="bi bi-calendar2-check"></i><span>Quản lý chấm công</span></a>
            <a class="nav-link <?= $section === 'overtime' ? 'active' : '' ?>" href="?action=admin-overtime"><i class="bi bi-clock-history"></i><span>Duyệt tăng ca & nghỉ phép</span></a>
            <a class="nav-link" href="?action=admin-summary"><i class="bi bi-clipboard-data"></i><span>Tổng hợp công</span></a>
            <a class="nav-link <?= $section === 'reports' ? 'active' : '' ?>" href="?action=admin-reports"><i class="bi bi-bar-chart-line"></i><span>Báo cáo thống kê</span></a>
        </nav>
        <p class="nav-caption nav-caption-settings">HỆ THỐNG</p>
        <nav class="nav flex-column sidebar-nav">
            <a class="nav-link <?= $section === 'settings' ? 'active' : '' ?>" href="?action=admin-settings"><i class="bi bi-gear"></i><span>Cài đặt</span></a>
            <a class="nav-link" href="index.php"><i class="bi bi-person-workspace"></i><span>Trang nhân viên</span></a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-profile">
                <span class="avatar avatar-admin"><?= $escape($currentUser['initials']) ?></span>
                <span class="profile-copy"><strong><?= $escape($currentUser['display_name']) ?></strong><small><?= $escape($roleLabel) ?></small></span>
            </div>
        </div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="mobile-menu-button" type="button" id="sidebar-toggle"><i class="bi bi-list"></i></button><div class="topbar-title"><strong><?= $escape($page['title']) ?></strong><div class="breadcrumbs"><a href="?action=admin-overview">Trang chủ</a><i class="bi bi-chevron-right"></i><span><?= $escape($page['title']) ?></span></div></div></div>
            <div class="topbar-actions">
                <button class="icon-button notification-button" type="button" id="notification-button" data-notification-url="?action=admin-overtime" aria-label="Thông báo"><i class="bi bi-bell"></i><span class="notification-indicator" id="notification-indicator" hidden></span></button>
                <span class="topbar-divider"></span>
                <button class="user-menu" type="button" id="admin-account-menu-button" aria-haspopup="menu" aria-expanded="false"><span class="avatar avatar-admin"><?= $escape($currentUser['initials']) ?></span><span class="user-copy"><strong><?= $escape($currentUser['display_name']) ?></strong><small><?= $escape($roleLabel) ?></small></span><i class="bi bi-chevron-down user-chevron"></i></button>
                <div class="admin-account-menu" id="admin-account-menu" role="menu" hidden>
                    <form action="index.php?action=logout" method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><button type="submit" role="menuitem"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button></form>
                </div>
            </div>
        </header>
        <div class="page-container">
            <section class="page-intro"><div><div class="page-kicker"><span></span> <?= $escape($page['kicker']) ?></div><h1><?= $escape($page['title']) ?></h1><p><?= $escape($page['description']) ?></p></div></section>
            <?php if ($section === 'attendance'): ?>
                <form class="attendance-period-filter" method="get" aria-label="Chọn kỳ chấm công">
                    <input type="hidden" name="action" value="admin-attendance">
                    <label>Tháng
                        <select name="month" aria-label="Tháng chấm công">
                            <?php for ($monthOption = 1; $monthOption <= 12; $monthOption++): ?>
                                <option value="<?= $monthOption ?>" <?= $monthOption === $attendanceMonth ? 'selected' : '' ?>>Tháng <?= $monthOption ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>Năm
                        <select name="year" aria-label="Năm chấm công">
                            <?php for ($yearOption = max(2020, $attendanceYear - 3); $yearOption <= min(2100, $attendanceYear + 1); $yearOption++): ?>
                                <option value="<?= $yearOption ?>" <?= $yearOption === $attendanceYear ? 'selected' : '' ?>><?= $yearOption ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><i class="bi bi-calendar-range"></i> Xem kỳ công</button>
                </form>
            <?php endif; ?>
            <?php if ($isSchedule): ?>
                <section class="schedule-page">
                    <nav class="schedule-mode-switch" aria-label="Chế độ quản lý phân ca">
                        <a class="<?= $scheduleMode === 'manage' ? 'active' : '' ?>" href="?action=admin-schedule">Lịch phân ca</a>
                        <a class="<?= $scheduleMode === 'auto' ? 'active' : '' ?>" href="?action=admin-schedule&amp;mode=auto">Quy tắc & tự động phân ca</a>
                    </nav>
                    <?php if ($scheduleMode === 'auto'): ?>
                        <div class="auto-schedule-layout">
                            <section class="schedule-board auto-schedule-card auto-catalog-card">
                                <header class="schedule-board-header">
                                    <div><h2>Danh mục quy tắc phân ca</h2><p>Quy tắc bắt buộc luôn được kiểm tra; chỉ các quy tắc ưu tiên có thể bật/tắt và sắp thứ tự.</p></div>
                                    <button class="button button-outline" id="auto-add-rule" type="button"><i class="bi bi-plus-lg"></i> Thêm quy tắc ca/phòng ban</button>
                                </header>
                                <div class="auto-rule-stats">
                                    <article><span>Tổng quy tắc</span><strong id="auto-rule-total">12</strong></article>
                                    <article><span>Đang áp dụng</span><strong id="auto-rule-active">0</strong></article>
                                    <article><span>Bắt buộc</span><strong id="auto-rule-mandatory">8</strong></article>
                                    <article><span>Ưu tiên</span><strong id="auto-rule-priority">4</strong></article>
                                </div>
                                <div class="auto-rule-filters">
                                    <label>Tìm kiếm<input id="auto-rule-search" type="search" placeholder="Mã hoặc tên quy tắc"></label>
                                    <label>Loại<select id="auto-rule-type-filter"><option value="">Tất cả</option><option value="mandatory">Bắt buộc</option><option value="priority">Ưu tiên</option></select></label>
                                    <label>Trạng thái<select id="auto-rule-status-filter"><option value="">Tất cả</option><option value="active">Đang áp dụng</option><option value="inactive">Đã tắt</option><option value="not_configured">Chưa cấu hình</option><option value="unavailable">Chưa hỗ trợ dữ liệu</option></select></label>
                                    <label>Phạm vi<select id="auto-rule-scope-filter"><option value="">Tất cả</option><option value="Toàn công ty">Toàn công ty</option><option value="Phòng ban">Phòng ban</option><option value="Chức vụ">Chức vụ</option></select></label>
                                </div>
                                <div class="schedule-table-wrap">
                                    <table class="schedule-table auto-catalog-table">
                                        <thead><tr><th>Mã</th><th>Tên quy tắc</th><th>Loại</th><th>Phạm vi</th><th>Cấu hình</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                        <tbody id="auto-rule-catalog-rows"><tr><td colspan="7">Đang tải quy tắc...</td></tr></tbody>
                                    </table>
                                </div>
                                <footer class="schedule-table-footer auto-catalog-footer">
                                    <span id="auto-rule-page-label">—</span>
                                    <div><button class="button button-light" id="auto-rule-prev" type="button" aria-label="Trang trước"><i class="bi bi-chevron-left"></i></button><button class="button button-light" id="auto-rule-next" type="button" aria-label="Trang sau"><i class="bi bi-chevron-right"></i></button></div>
                                </footer>
                                <div class="auto-rule-details" id="auto-rule-details" hidden></div>
                            </section>

                            <section class="schedule-board auto-schedule-card">
                                <header class="schedule-board-header">
                                    <div><h2>Quy tắc nhân sự theo ca</h2><p>Cấu hình số người tối thiểu/tối đa và chức vụ phù hợp theo từng phòng ban.</p></div>
                                </header>
                                <form class="auto-schedule-rule-form" id="auto-rule-form">
                                    <label>Loại ca<select id="auto-rule-shift" required></select></label>
                                    <label>Phòng ban<select id="auto-rule-department" required></select></label>
                                    <label>Ít nhất<input id="auto-rule-min" type="number" min="0" max="200" value="1" required></label>
                                    <label>Nhiều nhất<input id="auto-rule-max" type="number" min="1" max="200" value="5" required></label>
                                    <fieldset class="auto-position-field"><legend>Chức vụ được phép</legend><div id="auto-rule-positions"><span class="auto-schedule-muted">Đang tải chức vụ...</span></div><small>Không chọn chức vụ nào nghĩa là mọi chức vụ trong phòng ban đều phù hợp.</small></fieldset>
                                    <button class="button button-primary" type="submit"><i class="bi bi-plus-lg"></i> Lưu quy tắc</button>
                                </form>
                                <div class="auto-rule-list" id="auto-rule-list"><p class="auto-schedule-muted">Đang tải quy tắc...</p></div>
                            </section>

                            <section class="schedule-board auto-schedule-card">
                                <header class="schedule-board-header">
                                    <div><h2>Cấu hình tham số quy tắc</h2><p>Chọn “Cấu hình” tại quy tắc tương ứng để chỉ chỉnh các tham số của quy tắc đó.</p></div>
                                </header>
                                <form class="auto-policy-form" id="auto-policy-form">
                                    <div class="auto-policy-rule-fields">
                                        <fieldset class="auto-policy-rule-fields-group" id="auto-policy-qt005" data-policy-rule hidden>
                                            <legend>QT005 · Giới hạn giờ làm</legend>
                                            <label>Giờ tối đa/ngày<input id="auto-max-daily" type="number" min="1" max="24" step="0.5" placeholder="Không giới hạn"></label>
                                            <label>Giờ tối đa/tuần<input id="auto-max-weekly" type="number" min="1" max="168" step="0.5" placeholder="Không giới hạn"></label>
                                        </fieldset>
                                        <fieldset class="auto-policy-rule-fields-group" id="auto-policy-qt006" data-policy-rule hidden>
                                            <legend>QT006 · Thời gian nghỉ tối thiểu</legend>
                                            <label>Nghỉ tối thiểu giữa ca (giờ)<input id="auto-min-rest" type="number" min="0" max="48" step="0.5" value="0"></label>
                                        </fieldset>
                                        <fieldset class="auto-policy-rule-fields-group" id="auto-policy-qt012" data-policy-rule hidden>
                                            <legend>QT012 · Hạn chế ca đêm liên tiếp</legend>
                                            <label>Ca đêm liên tiếp tối đa<input id="auto-max-consecutive-nights" type="number" min="1" max="14" placeholder="Không giới hạn"></label>
                                        </fieldset>
                                    </div>
                                    <button class="button button-outline" id="auto-save-policy" type="submit" disabled>Lưu tham số quy tắc</button>
                                </form>
                                <p class="auto-schedule-policy-note" id="auto-policy-note">Đang tải quy tắc áp dụng...</p>
                            </section>

                            <section class="schedule-board auto-schedule-card">
                                <header class="schedule-board-header">
                                    <div><h2>Tạo lịch dự kiến</h2><p>Chỉ nhân viên Intern được xét phân ca; lịch hiện có được giữ nguyên và hệ thống chỉ bổ sung đến mức nhân sự tối thiểu.</p></div>
                                </header>
                                <form class="auto-generate-form" id="auto-generate-form">
                                    <label>Từ ngày<input id="auto-from-date" type="date" value="<?= $escape($scheduleDate) ?>" required></label>
                                    <label>Đến ngày<input id="auto-to-date" type="date" value="<?= $escape((new DateTimeImmutable($scheduleDate))->modify('+6 days')->format('Y-m-d')) ?>" required></label>
                                    <label>Phòng ban<select id="auto-department"><option value="">Tất cả phòng ban có quy tắc</option></select></label>
                                    <label>Ca làm việc<select id="auto-shifts" multiple required aria-label="Chọn ca làm việc"></select></label>
                                    <label class="auto-location-field">Địa điểm<input id="auto-location" type="text" maxlength="100" required placeholder="Ví dụ: Quầy vé tầng 1"></label>
                                    <button class="button button-primary" id="auto-preview-button" type="submit"><i class="bi bi-magic"></i> Tự động phân ca</button>
                                </form>
                                <p class="auto-schedule-policy-note" id="auto-policy-note">Đang tải quy tắc áp dụng...</p>
                            </section>

                            <section class="schedule-board auto-schedule-card auto-preview-card" id="auto-preview" hidden>
                                <header class="schedule-board-header auto-preview-header">
                                    <div><h2>Kết quả phân ca dự kiến</h2><p id="auto-preview-summary"></p></div>
                                    <div class="auto-preview-actions">
                                        <button class="button button-outline" id="auto-rerun" type="button"><i class="bi bi-arrow-repeat"></i> Chạy lại</button>
                                        <button class="button button-primary" id="auto-confirm" type="button"><i class="bi bi-check2"></i> Xác nhận và lưu</button>
                                    </div>
                                </header>
                                <div class="auto-result-sections">
                                    <section><h3>Lịch dự kiến</h3><div class="schedule-table-wrap"><table class="schedule-table auto-plan-table"><thead><tr><th>Ngày</th><th>Phòng ban</th><th>Ca làm</th><th>Nhân viên</th><th>Trạng thái</th><th></th></tr></thead><tbody id="auto-plan-rows"></tbody></table></div></section>
                                    <section><h3>Ca còn thiếu nhân sự</h3><div class="auto-result-list" id="auto-shortages"></div></section>
                                    <section><h3>Cảnh báo và nhân viên không thể phân</h3><div class="auto-result-list" id="auto-issues"></div></section>
                                </div>
                            </section>
                            <p class="auto-schedule-feedback" id="auto-schedule-feedback" role="status" aria-live="polite"></p>
                        </div>
                    <?php else: ?>
                    <div class="schedule-stats">
                        <article><span class="schedule-stat-icon blue"><i class="bi bi-calendar2-week"></i></span><div><span>Tổng ca trong kỳ</span><strong id="schedule-total"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] !== 'unassigned')) ?></strong></div></article>
                        <article><span class="schedule-stat-icon green"><i class="bi bi-clock-history"></i></span><div><span>Đang diễn ra</span><strong id="schedule-active"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'active')) ?></strong></div></article>
                        <article><span class="schedule-stat-icon orange"><i class="bi bi-hourglass-split"></i></span><div><span>Sắp bắt đầu</span><strong id="schedule-upcoming"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'upcoming')) ?></strong></div></article>
                        <article><span class="schedule-stat-icon purple"><i class="bi bi-hourglass-split"></i></span><div><span>Tổng giờ dự kiến</span><strong id="schedule-hours">0 giờ</strong></div></article>
                    </div>

                    <section class="schedule-board" aria-labelledby="schedule-list-title">
                        <header class="schedule-board-header">
                            <div><h2 id="schedule-list-title">Lịch phân ca</h2><p id="schedule-date-caption">Ca làm việc trong ngày</p><p id="schedule-employee-caption">Chọn nhân viên để xem lịch cá nhân theo ngày, tuần hoặc tháng.</p></div>
                            <div class="schedule-actions">
                                <button class="button button-light" type="button" id="schedule-prev"><i class="bi bi-chevron-left"></i></button>
                                <label class="schedule-date"><i class="bi bi-calendar3"></i><input id="schedule-date" type="date" value="<?= $escape($scheduleDate) ?>" aria-label="Chọn ngày phân ca"></label>
                                <button class="button button-light" type="button" id="schedule-next"><i class="bi bi-chevron-right"></i></button>
                                <select id="schedule-view" aria-label="Khoảng thời gian"><option value="day">Ngày</option><option value="week">Tuần</option><option value="month">Tháng</option></select>
                                <select id="schedule-department-filter" aria-label="Lọc phòng ban"><option value="">Tất cả phòng ban</option><?php foreach ($scheduleDepartments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= $escape($department['name']) ?></option><?php endforeach; ?></select>
                                <select id="schedule-employee-filter" aria-label="Lọc nhân viên"><option value="">Tất cả nhân viên</option><?php foreach ($scheduleFilterEmployees as $employee): ?><option value="<?= (int) $employee['id'] ?>" data-code="<?= $escape($employee['code']) ?>" data-department="<?= $escape($employee['department']) ?>"><?= $escape($employee['name']) ?><?= !empty($employee['code']) ? ' · ' . $escape($employee['code']) : '' ?><?= (int) $employee['active'] !== 1 ? ' (ngừng hoạt động)' : '' ?></option><?php endforeach; ?></select>
                                <select id="schedule-shift-filter" aria-label="Lọc ca làm việc"><option value="">Tất cả ca làm</option><?php foreach ($scheduleShiftFilters as $shift): ?><option value="<?= (int) $shift['id'] ?>"><?= $escape($shift['name']) ?><?= (int) $shift['active'] !== 1 ? ' (ngừng hoạt động)' : '' ?></option><?php endforeach; ?></select>
                                <button class="button button-outline" type="button" id="schedule-manage-shifts"><i class="bi bi-sliders"></i> Quản lý loại ca</button>
                                <button class="button button-primary" type="button" id="schedule-create"><i class="bi bi-plus-lg"></i> Tạo ca mới</button>
                            </div>
                        </header>
                        <div class="schedule-tools">
                            <label class="schedule-search"><i class="bi bi-search"></i><input id="schedule-search" type="search" placeholder="Tìm nhân viên, bộ phận, địa điểm..." aria-label="Tìm ca làm việc"></label>
                            <div class="schedule-filters" role="group" aria-label="Lọc trạng thái">
                                <button type="button" class="active" data-status="all">Tất cả <span id="filter-all"><?= count($scheduleAssignments) ?></span></button>
                                <button type="button" data-status="active">Đang diễn ra <span id="filter-active"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'active')) ?></span></button>
                                <button type="button" data-status="upcoming">Sắp bắt đầu <span id="filter-upcoming"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'upcoming')) ?></span></button>
                                <button type="button" data-status="completed">Hoàn thành <span id="filter-completed"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'completed')) ?></span></button>
                                <button type="button" data-status="unassigned">Chưa phân ca <span id="filter-unassigned"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'unassigned')) ?></span></button>
                            </div>
                        </div>
                        <div class="schedule-table-wrap">
                            <table class="schedule-table">
                                <thead><tr><th>Nhân viên</th><th>Ngày làm</th><th>Bộ phận</th><th>Ca làm</th><th>Thời gian</th><th>Địa điểm</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                <tbody id="schedule-rows">
                                    <?php if ($scheduleAssignments === []): ?>
                                        <tr class="schedule-empty-row"><td colspan="8">Chưa có ca làm việc trong ngày này.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($scheduleAssignments as $index => $assignment): ?>
                                            <tr data-assignment-id="<?= $escape($assignment['id']) ?>">
                                                <td><div class="schedule-employee"><span><?= $escape(lumicineUtf8Substring($assignment['name'], 0, 1)) ?></span><div><strong><?= $escape($assignment['name']) ?></strong><small><?= !empty($assignment['employee_code']) ? 'Mã: ' . $escape($assignment['employee_code']) . ' · ' : '' ?><?= $escape($assignment['role']) ?></small></div></div></td>
                                                <td><?= $escape($assignment['work_date']) ?></td><td><?= $escape($assignment['department']) ?></td><td><strong><?= $escape($assignment['shift_name'] ?? ('Ca ' . ($index + 1))) ?></strong></td>
                                                <td><strong><?= $assignment['start'] !== '' ? $escape(substr($assignment['start'], 0, 5) . ' - ' . substr($assignment['end'], 0, 5)) : '—' ?></strong></td>
                                                <td><?= $escape($assignment['location']) ?></td>
                                                <td><span class="schedule-status <?= $escape($assignment['status']) ?>"><?= $assignment['status'] === 'unassigned' ? 'Chưa phân ca' : 'Đã phân ca · ' . ($assignment['status'] === 'active' ? 'Đang diễn ra' : ($assignment['status'] === 'upcoming' ? 'Sắp bắt đầu' : 'Hoàn thành')) ?></span></td>
                                                <td><div class="schedule-row-actions"><?php if ($assignment['status'] === 'unassigned'): ?><button type="button" data-action="assign" title="Phân ca"><i class="bi bi-plus-lg"></i></button><?php else: ?><button type="button" data-action="edit" title="Chỉnh sửa"><i class="bi bi-pencil"></i></button><button type="button" data-action="cancel" title="Hủy ca"><i class="bi bi-trash3"></i></button><?php endif; ?></div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <footer class="schedule-table-footer"><span id="schedule-result-count">Hiển thị <?= count($scheduleAssignments) ?> ca</span><button class="button button-outline" type="button" id="schedule-export"><i class="bi bi-download"></i> Xuất danh sách</button></footer>
                    </section>
                    <?php endif; ?>
                </section>

                <?php if ($scheduleMode === 'manage'): ?>
                <dialog class="schedule-dialog" id="schedule-dialog">
                    <form id="schedule-form">
                        <header><div><span>THÔNG TIN CA</span><h2 id="schedule-dialog-title">Tạo ca làm việc</h2></div><button type="button" id="schedule-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
                        <div class="schedule-dialog-grid">
                            <label class="full">Nhân viên<select name="employeeId" required><option value="">Chọn nhân viên</option><?php foreach ($scheduleEmployees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= $escape($employee['name']) ?> - <?= $escape($employee['code']) ?></option><?php endforeach; ?></select></label>
                            <label class="full">Loại ca làm việc
                                <select name="shiftId" required>
                                    <option value="">Chọn ca làm việc</option>
                                    <?php foreach ($scheduleShiftTypes as $shift): ?>
                                        <option value="<?= (int) $shift['id'] ?>">
                                            <?= $escape($shift['name']) ?> (<?= $escape(substr($shift['start'], 0, 5)) ?> - <?= $escape(substr($shift['end'], 0, 5)) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="full">Địa điểm làm việc<input name="location" type="text" maxlength="100" required placeholder="Ví dụ: Quầy vé tầng 1"></label>
                        </div>
                        <footer><button type="button" class="button button-light" id="schedule-dialog-cancel">Hủy</button><button type="submit" class="button button-primary" id="schedule-save">Lưu ca làm việc</button></footer>
                    </form>
                </dialog>
                <dialog class="schedule-dialog" id="shift-types-dialog">
                    <form id="shift-types-form" method="dialog">
                        <header><div><span>QUẢN LÝ LOẠI CA</span><h2>Ngừng hoạt động / kích hoạt ca</h2></div><button type="button" id="shift-types-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
                        <div class="shift-types-list" id="shift-types-list"></div>
                        <footer><button type="button" class="button button-light" id="shift-types-cancel">Đóng</button></footer>
                    </form>
                </dialog>
                <?php endif; ?>
            <?php elseif ($isOvertime): ?>
                <section class="overtime-card" id="overtime-requests" aria-labelledby="overtime-requests-title">
                    <div class="overtime-heading"><div><h2 id="overtime-requests-title">Đơn đăng ký tăng ca</h2><p>Xem lý do, thời gian và xử lý các đơn nhân viên gửi.</p></div><div class="overtime-summary"><span>Chờ duyệt</span><strong id="overtime-pending-count">0</strong></div></div>
                    <div class="overtime-feedback" id="overtime-feedback" role="status" aria-live="polite"></div>
                    <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Nhân viên</th><th>Bắt đầu</th><th>Kết thúc</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody id="admin-overtime-rows"><tr><td colspan="6">Đang tải đơn tăng ca...</td></tr></tbody></table></div>
                </section>
                <section class="overtime-card" id="leave-requests" aria-labelledby="leave-requests-title">
                    <div class="overtime-heading"><div><h2 id="leave-requests-title">Đơn đăng ký nghỉ phép</h2><p>Xem lý do, thời gian và xử lý các đơn nghỉ phép.</p></div><div class="overtime-summary"><span>Chờ duyệt</span><strong id="leave-pending-count">0</strong></div></div>
                    <div class="overtime-feedback" id="leave-feedback" role="status" aria-live="polite"></div>
                    <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Nhân viên</th><th>Bắt đầu</th><th>Kết thúc</th><th>Loại nghỉ</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody id="admin-leave-rows"><tr><td colspan="7">Đang tải đơn nghỉ phép...</td></tr></tbody></table></div>
                </section>
            <?php elseif ($section === 'overview'): ?>
                <section class="summary-grid">
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-blue"><i class="bi bi-people-fill"></i></span></div><p class="summary-label">Nhân viên</p><div class="summary-value"><?= count($employees) ?> <span>người</span></div></article>
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-green"><i class="bi bi-calendar2-check-fill"></i></span></div><p class="summary-label">Ngày công</p><div class="summary-value"><?= array_sum(array_column($employees, 'worked')) ?> <span>ngày</span></div></article>
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-orange"><i class="bi bi-clock-history"></i></span></div><p class="summary-label">Đi muộn</p><div class="summary-value"><?= array_sum(array_column($employees, 'late')) ?> <span>lượt</span></div></article>
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-purple"><i class="bi bi-calendar2-heart-fill"></i></span></div><p class="summary-label">Nghỉ phép</p><div class="summary-value"><?= array_sum(array_column($employees, 'leave')) ?> <span>ngày</span></div><div class="summary-foot">Vắng không phép: <?= array_sum(array_column($employees, 'absent')) ?> ngày</div></article>
                </section>
            <?php elseif (in_array($section, ['employees', 'attendance', 'reports'], true)): ?>
                <?php if ($section === 'reports'): ?>
                    <section class="report-filter data-card" aria-label="Bộ lọc báo cáo">
                        <form id="report-filter-form" class="report-filter-grid">
                            <label>Từ ngày<input type="date" id="report-from" required></label>
                            <label>Đến ngày<input type="date" id="report-to" required></label>
                            <label>Phòng ban
                                <select id="report-department">
                                    <option value="">Tất cả phòng ban</option>
                                    <?php foreach ($reportDepartments as $department): ?>
                                        <option value="<?= (int) $department['id'] ?>"><?= $escape($department['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="report-filter-actions">
                                <button class="button button-primary" type="submit"><i class="bi bi-funnel"></i> Lọc dữ liệu</button>
                                <button class="button button-outline" type="button" id="report-reset"><i class="bi bi-arrow-counterclockwise"></i> Đặt lại</button>
                                <button class="button button-light" type="button" id="report-export"><i class="bi bi-file-earmark-spreadsheet"></i> Xuất CSV</button>
                            </div>
                        </form>
                        <p class="report-feedback" id="report-feedback" role="status" aria-live="polite"></p>
                    </section>
                    <section class="summary-grid" id="report-summary">
                        <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-blue"><i class="bi bi-people-fill"></i></span></div><p class="summary-label">Tổng nhân viên</p><div class="summary-value" id="report-total-employees">0</div></article>
                        <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-green"><i class="bi bi-calendar2-check-fill"></i></span></div><p class="summary-label">Tổng ngày công</p><div class="summary-value" id="report-total-work">0</div></article>
                        <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-orange"><i class="bi bi-clock-history"></i></span></div><p class="summary-label">Lượt đi muộn</p><div class="summary-value" id="report-total-late">0</div></article>
                        <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-purple"><i class="bi bi-calendar2-heart-fill"></i></span></div><p class="summary-label">Ngày nghỉ phép</p><div class="summary-value" id="report-total-leave">0</div></article>
                    </section>
                    <section class="report-chart-grid">
                        <article class="report-chart-card report-chart-wide"><header><h2>Chấm công theo ngày</h2><p>Số lượt đúng giờ, đi muộn và vắng mặt</p></header><div class="report-chart-body"><canvas id="attendanceChart"></canvas><p class="report-empty" id="attendance-empty" hidden>Chưa có dữ liệu thống kê trong khoảng thời gian này</p></div></article>
                        <article class="report-chart-card"><header><h2>Nghỉ phép</h2><p>Phân loại đơn nghỉ phép theo trạng thái</p></header><div class="report-chart-body report-doughnut"><canvas id="leaveChart"></canvas><p class="report-empty" id="leave-empty" hidden>Chưa có dữ liệu thống kê trong khoảng thời gian này</p></div></article>
                        <article class="report-chart-card"><header><h2>Ngày công theo phòng ban</h2><p>Tổng ngày công trong khoảng thời gian đã lọc</p></header><div class="report-chart-body"><canvas id="departmentChart"></canvas><p class="report-empty" id="department-empty" hidden>Chưa có dữ liệu thống kê trong khoảng thời gian này</p></div></article>
                        <article class="report-chart-card"><header><h2>Tăng ca</h2><p>Giờ tăng ca theo ngày và trạng thái đơn</p></header><div class="report-chart-body"><canvas id="overtimeChart"></canvas><p class="report-empty" id="overtime-empty" hidden>Chưa có dữ liệu thống kê trong khoảng thời gian này</p></div></article>
                        <article class="report-chart-card report-chart-wide"><header><h2>Top 5 nhân viên có ngày công cao nhất</h2><p>Xếp hạng trong khoảng thời gian đã lọc</p></header><div class="report-chart-body"><canvas id="topChart"></canvas><p class="report-empty" id="top-empty" hidden>Chưa có dữ liệu thống kê trong khoảng thời gian này</p></div></article>
                    </section>
                <?php elseif ($section === 'employees'): ?>
                    <section class="data-card" aria-labelledby="workforce-employees-title">
                        <div class="table-card-header workforce-card-header">
                            <div><h2 id="workforce-employees-title">Danh sách nhân viên</h2><p>Thêm, cập nhật hồ sơ hoặc tạm ngừng nhân viên. Hồ sơ ngừng hoạt động vẫn được giữ để bảo toàn lịch sử.</p></div>
                            <button class="button button-primary" type="button" id="add-workforce-employee"><i class="bi bi-person-plus"></i> Thêm nhân viên</button>
                        </div>
                        <p class="mapping-feedback" id="employee-management-feedback" role="status" aria-live="polite"></p>
                        <div class="table-wrap">
                            <table class="employee-table" id="workforce-employee-table">
                                <thead><tr><th>Mã nhân viên</th><th>Họ và tên</th><th>Phòng ban</th><th>Chức vụ</th><th>Loại nhân viên</th><th>Ngày nghỉ việc</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                <tbody>
                                    <?php if ($managedEmployees === []): ?>
                                        <tr data-empty-row><td colspan="8">Chưa có hồ sơ nhân viên.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($managedEmployees as $employee): ?>
                                            <?php $active = (int) $employee['active'] === 1; ?>
                                            <tr data-employee-row data-id="<?= (int) $employee['id'] ?>" data-code="<?= $escape($employee['code']) ?>" data-name="<?= $escape($employee['name']) ?>" data-position="<?= $escape($employee['position']) ?>" data-employee-type="<?= $escape($employee['employee_type']) ?>" data-department-id="<?= $employee['department_id'] === null ? '' : (int) $employee['department_id'] ?>" data-termination-date="<?= $escape($employee['termination_date'] ?? '') ?>" data-active="<?= $active ? '1' : '0' ?>">
                                                <td><?= $escape($employee['code']) ?></td>
                                                <td><?= $escape($employee['name']) ?></td>
                                                <td><?= $escape($employee['department'] !== '' ? $employee['department'] : 'Chưa phân công') ?></td>
                                                <td><?= $escape($employee['position'] !== '' ? $employee['position'] : '—') ?></td>
                                                <td><?= $employee['employee_type'] === WorkSchedule::INTERN ? 'Intern' : 'Fulltime' ?></td>
                                                <td><?= $escape($employee['termination_date'] ?? '—') ?></td>
                                                <td><span class="status-badge <?= $active ? 'status-complete' : 'status-incomplete' ?>"><?= $active ? 'Đang làm việc' : 'Ngừng hoạt động' ?></span></td>
                                                <td class="workforce-row-actions">
                                                    <button class="button button-outline" type="button" data-edit-employee>Sửa</button>
                                                    <button class="button button-light" type="button" data-toggle-employee><?= $active ? 'Ngừng hoạt động' : 'Kích hoạt lại' ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="data-card" aria-labelledby="workforce-departments-title">
                        <div class="table-card-header workforce-card-header">
                            <div><h2 id="workforce-departments-title">Danh sách phòng ban</h2><p>Quản lý các phòng ban và nhân viên đang được gán.</p></div>
                            <button class="button button-primary" type="button" id="add-workforce-department"><i class="bi bi-diagram-3"></i> Thêm phòng ban</button>
                        </div>
                        <p class="mapping-feedback" id="department-management-feedback" role="status" aria-live="polite"></p>
                        <div class="table-wrap">
                            <table class="employee-table" id="workforce-department-table">
                                <thead><tr><th>Tên phòng ban</th><th>Số hồ sơ nhân viên</th><th>Thao tác</th></tr></thead>
                                <tbody>
                                    <?php if ($departments === []): ?>
                                        <tr data-empty-row><td colspan="3">Chưa có phòng ban.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($departments as $department): ?>
                                            <tr data-department-row data-id="<?= (int) $department['id'] ?>" data-name="<?= $escape($department['name']) ?>" data-count="<?= (int) $department['employee_count'] ?>">
                                                <td><?= $escape($department['name']) ?></td>
                                                <td><?= (int) $department['employee_count'] ?></td>
                                                <td class="workforce-row-actions"><button class="button button-outline" type="button" data-edit-department>Sửa</button><button class="button button-light" type="button" data-delete-department>Xóa</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="data-card">
                        <div class="table-card-header">
                            <div>
                                <h2>Liên kết tài khoản nhân viên</h2>
                                <p>Chọn đúng hồ sơ theo mã nhân viên đã xác minh. Mỗi hồ sơ chỉ liên kết được với một tài khoản.</p>
                            </div>
                        </div>
                        <p class="mapping-feedback" id="mapping-feedback" role="status" aria-live="polite"></p>
                        <div class="table-wrap">
                            <table class="employee-table" id="employee-account-mappings">
                                <thead><tr><th>Tài khoản</th><th>Tên hiển thị</th><th>Vai trò</th><th>Hồ sơ nhân viên</th><th>Thao tác</th></tr></thead>
                                <tbody>
                                    <?php if ($employeeMappings === []): ?>
                                        <tr><td colspan="5">Không có tài khoản đăng nhập.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($employeeMappings as $account): ?>
                                            <tr data-mapping-row data-user-id="<?= (int) $account['id'] ?>">
                                                <td><?= $escape($account['username']) ?></td>
                                                <td><?= $escape($account['display_name']) ?></td>
                                                <td><?= $escape([
                                                    'employee' => 'Nhân viên',
                                                    'accountant' => 'Kế toán',
                                                    'hr' => 'HR',
                                                    'chairman' => 'Chủ tịch',
                                                ][$account['role']] ?? $account['role']) ?></td>
                                                <td>
                                                    <select class="mapping-select" data-employee-code aria-label="Hồ sơ nhân viên cho tài khoản <?= $escape($account['username']) ?>">
                                                        <option value="">Chưa liên kết</option>
                                                        <?php if ($account['employee_code'] !== null && !array_filter($mappingEmployees, static fn ($employee) => $employee['code'] === $account['employee_code'])): ?>
                                                            <option value="<?= $escape($account['employee_code']) ?>" selected>Mã không còn hoạt động: <?= $escape($account['employee_code']) ?></option>
                                                        <?php endif; ?>
                                                        <?php foreach ($mappingEmployees as $employee): ?>
                                                            <option value="<?= $escape($employee['code']) ?>" <?= $employee['code'] === $account['employee_code'] ? 'selected' : '' ?>>
                                                                <?= $escape($employee['code']) ?> — <?= $escape($employee['name']) ?><?= $employee['department'] !== '' ? ' (' . $escape($employee['department']) . ')' : '' ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><button class="button button-primary mapping-save" type="button" data-save-mapping>Lưu liên kết</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="data-card">
                        <div class="table-card-header"><div><h2>Danh sách hồ sơ nhân viên</h2><p>Đối chiếu mã nhân viên trước khi gán cho tài khoản.</p></div></div>
                        <div class="table-wrap"><table class="employee-table"><thead><tr><th>Mã NV</th><th>Nhân viên</th><th>Bộ phận</th><th>Chức vụ</th></tr></thead><tbody>
                            <?php foreach ($mappingEmployees as $employee): ?>
                                <tr><td><?= $escape($employee['code']) ?></td><td><?= $escape($employee['name']) ?></td><td><?= $escape($employee['department']) ?></td><td><?= $escape($employee['position']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                    </section>
                <?php elseif ($section === 'attendance'): ?>
                    <section class="data-card admin-attendance-report" aria-labelledby="admin-attendance-title">
                        <div class="table-card-header attendance-report-header">
                            <div><h2 id="admin-attendance-title">Dữ liệu chấm công</h2><p>Tổng hợp ngày công tháng <?= (int) $attendanceMonth ?>/<?= (int) $attendanceYear ?> theo kỳ lương đã chọn.</p></div>
                            <div class="attendance-report-actions">
                                <button class="button button-outline" id="print-attendance-report" type="button"><i class="bi bi-printer" aria-hidden="true"></i> In báo cáo</button>
                                <button class="button button-primary" id="export-attendance-report" type="button"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Xuất Excel</button>
                            </div>
                        </div>
                        <div class="filter-panel attendance-admin-filters" id="attendance-admin-filters">
                            <label class="search-control">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input id="admin-attendance-search" type="search" placeholder="Tìm mã nhân viên hoặc họ tên..." autocomplete="off" aria-label="Tìm mã nhân viên hoặc họ tên">
                            </label>
                            <label class="filter-select">
                                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                                <select id="admin-attendance-department" aria-label="Lọc theo phòng ban">
                                    <option value="">Tất cả phòng ban</option>
                                    <?php foreach ($attendanceDepartments as $department): ?>
                                        <option value="<?= $escape($department) ?>"><?= $escape($department) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="filter-select">
                                <i class="bi bi-person-badge" aria-hidden="true"></i>
                                <select id="admin-attendance-position" aria-label="Lọc theo chức vụ">
                                    <option value="">Tất cả chức vụ</option>
                                    <?php foreach ($attendancePositions as $position): ?>
                                        <option value="<?= $escape($position) ?>"><?= $escape($position) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="filter-select">
                                <i class="bi bi-funnel" aria-hidden="true"></i>
                                <select id="admin-attendance-status" aria-label="Lọc theo trạng thái công">
                                    <option value="">Tất cả trạng thái</option>
                                    <?php foreach ($attendanceStatusLabels as $statusValue => $statusLabel): ?>
                                        <option value="<?= $escape($statusValue) ?>"><?= $escape($statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="filter-buttons">
                                <button class="button button-primary filter-submit" id="apply-attendance-filters" type="button"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Lọc</button>
                                <button class="button button-light" id="reset-attendance-filters" type="button"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Làm mới</button>
                            </div>
                        </div>
                        <div class="attendance-filter-count" id="attendance-filter-count" role="status" aria-live="polite">
                            Hiển thị <?= count($employees) ?> trong <?= count($employees) ?> nhân viên
                        </div>
                        <div class="table-wrap attendance-admin-table-wrap">
                            <table class="employee-table" id="admin-attendance-table">
                                <thead><tr><th>Mã NV</th><th>Nhân viên</th><th>Bộ phận</th><th>Chức vụ</th><th>Ngày công</th><th>Nghỉ phép</th><th>Vắng không phép</th><th>Đi muộn</th><th>Phút về sớm</th><th>Trạng thái</th></tr></thead>
                                <tbody id="admin-attendance-rows">
                                    <?php foreach ($employees as $employee): ?>
                                        <?php $attendanceStatus = $attendanceStatusFor($employee); ?>
                                        <tr data-attendance-row
                                            data-employee-search="<?= $escape($employee['id'] . ' ' . $employee['name']) ?>"
                                            data-department="<?= $escape($employee['department']) ?>"
                                            data-position="<?= $escape($employee['position']) ?>"
                                            data-status="<?= $escape($attendanceStatus) ?>">
                                            <td><?= $escape($employee['id']) ?></td>
                                            <td><?= $escape($employee['name']) ?></td>
                                            <td><?= $escape($employee['department']) ?></td>
                                            <td><?= $escape($employee['position']) ?></td>
                                            <td><?= (int) $employee['worked'] ?> ngày</td>
                                            <td><?= (int) $employee['leave'] ?> ngày</td>
                                            <td><?= (int) $employee['absent'] ?> ngày</td>
                                            <td><?= (int) $employee['late'] ?> lượt / <?= (int) $employee['lateMinutes'] ?> phút</td>
                                            <td><?= (int) $employee['earlyMinutes'] ?> phút</td>
                                            <td><span class="status-badge status-<?= $escape($attendanceStatus) ?>"><?= $escape($attendanceStatusLabels[$attendanceStatus]) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="empty-state" id="admin-attendance-empty" hidden>
                                <strong>Không tìm thấy dữ liệu chấm công</strong>
                                <span>Thử đổi từ khóa hoặc điều kiện lọc.</span>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            <?php elseif ($section === 'accounts'): ?>
                <section class="data-card account-management" aria-labelledby="employee-accounts-title">
                    <div class="table-card-header workforce-card-header">
                        <div>
                            <h2 id="employee-accounts-title">Tài khoản nhân viên</h2>
                            <p>Mỗi hồ sơ nhân viên chỉ được cấp một tài khoản. Mật khẩu được lưu dạng mã hóa và không thể xem lại.</p>
                        </div>
                        <button class="button button-primary" type="button" id="add-employee-account" <?= $accountEmployees === [] ? 'disabled' : '' ?>><i class="bi bi-person-plus"></i> Thêm tài khoản</button>
                    </div>
                    <?php if ($accountEmployees === []): ?><p class="account-management-feedback">Không có nhân viên đang làm việc chưa được cấp tài khoản.</p><?php endif; ?>
                    <p class="account-management-feedback" id="employee-accounts-feedback" role="status" aria-live="polite"></p>
                    <div class="table-wrap">
                        <table class="employee-table">
                            <thead><tr><th>Mã nhân viên</th><th>Nhân viên</th><th>Tên đăng nhập</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                <?php if ($employeeAccounts === []): ?>
                                    <tr><td colspan="4">Chưa có tài khoản nhân viên.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($employeeAccounts as $account): ?>
                                        <tr data-account-row data-id="<?= (int) $account['id'] ?>" data-username="<?= $escape($account['username']) ?>">
                                            <td><?= $escape($account['code'] ?? '—') ?></td>
                                            <td><?= $escape($account['employee_name']) ?></td>
                                            <td><strong><?= $escape($account['username']) ?></strong></td>
                                            <td class="workforce-row-actions">
                                                <button class="button button-outline" type="button" data-edit-account>Sửa</button>
                                                <button class="button button-light" type="button" data-delete-account>Xóa</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <p class="account-password-note"><i class="bi bi-shield-lock"></i> Khi sửa tài khoản, để trống mật khẩu nếu không muốn thay đổi. Để đổi mật khẩu, nhập mật khẩu mới.</p>
            <?php elseif ($section === 'settings'): ?>
                <section class="data-card"><div class="table-card-header"><div><h2>Cài đặt hệ thống</h2><p>Cấu hình chung cho LumiCine.</p></div></div><div class="settings-grid"><label>Giờ bắt đầu ca<input type="time" value="08:00"></label><label>Giờ kết thúc ca<input type="time" value="17:00"></label><label>Ngày công chuẩn/tháng<input type="number" min="1" max="31" value="22"></label><button class="button button-primary" type="button">Lưu cài đặt</button></div></section>
                <section class="data-card" aria-labelledby="payroll-settings-title">
                    <div class="table-card-header">
                        <div>
                            <h2 id="payroll-settings-title">Kỳ lương và tổng hợp công</h2>
                            <p>Chọn ngày bắt đầu chu kỳ; bảng tổng hợp công sẽ tính từ ngày này đến trước ngày bắt đầu kỳ tiếp theo.</p>
                        </div>
                    </div>
                    <form class="settings-grid payroll-settings-grid" id="payroll-settings-form">
                        <label for="payroll-start-day">Ngày bắt đầu kỳ lương
                            <input id="payroll-start-day" name="payrollStartDay" type="number" min="1" max="31" step="1" value="<?= (int) $payrollStartDay ?>" required>
                        </label>
                        <p class="payroll-settings-help">Nếu tháng không có ngày đã chọn (ví dụ ngày 31), hệ thống dùng ngày cuối tháng. Thay đổi sẽ áp dụng cho lịch và số liệu của bảng tổng hợp công.</p>
                        <button class="button button-primary" id="save-payroll-settings" type="submit">Lưu ngày lương</button>
                    </form>
                    <p class="payroll-settings-feedback" id="payroll-settings-feedback" role="status" aria-live="polite"></p>
                </section>
            <?php else: ?>
                <section class="data-card section-placeholder"><div class="table-card-header"><div><h2><?= $escape($page['title']) ?></h2><p>Chức năng này đã được đặt trên trang riêng.</p></div></div></section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php if ($section === 'employees'): ?>
    <dialog class="holiday-dialog workforce-dialog" id="employee-dialog">
        <form class="holiday-dialog-content workforce-form" id="employee-form">
            <header class="dialog-heading"><div><span class="page-kicker"><span></span> HỒ SƠ NHÂN VIÊN</span><h2 id="employee-dialog-title">Thêm nhân viên</h2><p>Điền thông tin hồ sơ. Mã nhân viên được dùng để liên kết tài khoản đăng nhập.</p></div><button class="dialog-close" type="button" data-close-employee-dialog aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
            <input id="employee-record-id" type="hidden">
            <div class="workforce-form-grid">
                <label class="dialog-field">Mã nhân viên<input id="employee-code" type="text" maxlength="32" autocomplete="off" required></label>
                <label class="dialog-field">Họ và tên<input id="employee-name" type="text" maxlength="100" required></label>
                <label class="dialog-field">Phòng ban<select id="employee-department"><option value="">Chưa phân công</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= $escape($department['name']) ?></option><?php endforeach; ?></select></label>
                <label class="dialog-field">Chức vụ<input id="employee-position" type="text" maxlength="100"></label>
                <label class="dialog-field">Loại nhân viên<select id="employee-type" required><option value="FULLTIME">Fulltime</option><option value="INTERN">Intern</option></select></label>
                <label class="dialog-field">Ngày nghỉ việc<input id="employee-termination-date" type="date"></label>
            </div>
            <p class="workforce-dialog-feedback" id="employee-dialog-feedback" role="status" aria-live="polite"></p>
            <footer class="dialog-actions"><button class="button button-light" type="button" data-close-employee-dialog>Hủy</button><button class="button button-primary" type="submit" id="save-workforce-employee">Lưu hồ sơ</button></footer>
        </form>
    </dialog>
    <dialog class="holiday-dialog workforce-dialog" id="department-dialog">
        <form class="holiday-dialog-content workforce-form" id="department-form">
            <header class="dialog-heading"><div><span class="page-kicker"><span></span> CƠ CẤU NHÂN SỰ</span><h2 id="department-dialog-title">Thêm phòng ban</h2><p>Đặt tên rõ ràng để dễ phân công nhân viên.</p></div><button class="dialog-close" type="button" data-close-department-dialog aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
            <input id="department-record-id" type="hidden">
            <label class="dialog-field workforce-department-field">Tên phòng ban<input id="department-name" type="text" maxlength="100" required></label>
            <p class="workforce-dialog-feedback" id="department-dialog-feedback" role="status" aria-live="polite"></p>
            <footer class="dialog-actions"><button class="button button-light" type="button" data-close-department-dialog>Hủy</button><button class="button button-primary" type="submit" id="save-workforce-department">Lưu phòng ban</button></footer>
        </form>
    </dialog>
<?php endif; ?>
<?php if ($section === 'accounts'): ?>
    <dialog class="holiday-dialog workforce-dialog account-dialog" id="employee-account-dialog">
        <form class="holiday-dialog-content workforce-form" id="employee-account-form">
            <header class="dialog-heading">
                <div><span class="page-kicker"><span></span> TÀI KHOẢN NHÂN VIÊN</span><h2 id="employee-account-dialog-title">Thêm tài khoản</h2><p>Mật khẩu phải có ít nhất 8 ký tự; hệ thống chỉ lưu mật khẩu dạng băm.</p></div>
                <button class="dialog-close" type="button" data-close-account-dialog aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
            </header>
            <input id="employee-account-id" type="hidden">
            <label class="dialog-field" id="account-employee-field">Nhân viên
                <select id="account-employee" required>
                    <option value="">Chọn nhân viên</option>
                    <?php foreach ($accountEmployees as $employee): ?>
                        <option value="<?= (int) $employee['id'] ?>"><?= $escape($employee['code']) ?> — <?= $escape($employee['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="dialog-field">Tên đăng nhập<input id="account-username" type="text" maxlength="190" autocomplete="username" required></label>
            <label class="dialog-field" id="account-password-field">Mật khẩu<input id="account-password" type="password" minlength="8" maxlength="72" autocomplete="new-password"></label>
            <p class="workforce-dialog-feedback" id="employee-account-dialog-feedback" role="status" aria-live="polite"></p>
            <footer class="dialog-actions">
                <button class="button button-light" type="button" data-close-account-dialog>Hủy</button>
                <button class="button button-primary" type="submit" id="save-employee-account">Lưu tài khoản</button>
            </footer>
        </form>
    </dialog>
<?php endif; ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" aria-live="polite"></div>
<?php require PATH_VIEW . 'api-config.php'; ?>
<script src="assets/js/admin-section.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/admin-section.js') ?>" defer></script>
<script src="assets/js/notification-badge.js" defer></script>
<?php if ($isSchedule): ?>
    <?php if ($scheduleMode === 'auto'): ?>
        <script src="assets/js/auto-schedule.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/auto-schedule.js') ?>" defer></script>
    <?php else: ?>
        <script>
            window.scheduleInitialData = <?= json_encode($scheduleAssignments, JSON_UNESCAPED_UNICODE) ?>;
            window.scheduleInitialShiftTypes = <?= json_encode($scheduleShiftTypes, JSON_UNESCAPED_UNICODE) ?>;
        </script>
        <script src="assets/js/schedule.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/schedule.js') ?>" defer></script>
    <?php endif; ?>
<?php elseif ($isOvertime): ?>
    <script src="assets/js/overtime.js" defer></script>
    <script src="assets/js/leave.js" defer></script>
<?php elseif ($section === 'employees'): ?>
    <script src="assets/js/workforce-management.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/workforce-management.js') ?>" defer></script>
<?php elseif ($section === 'accounts'): ?>
    <script src="assets/js/employee-accounts.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/employee-accounts.js') ?>" defer></script>
<?php elseif ($section === 'reports'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
    <script src="assets/js/reports.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/reports.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
