<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pages = [
    'overview' => ['title' => 'Tổng quan', 'kicker' => 'KHÔNG GIAN LÀM VIỆC', 'description' => 'Tổng quan hoạt động nhân sự và chấm công.'],
    'employees' => ['title' => 'Quản lý nhân viên', 'kicker' => 'NHÂN SỰ', 'description' => 'Quản lý thông tin và hồ sơ nhân viên.'],
    'schedule' => ['title' => 'Phân ca làm việc', 'kicker' => 'LỊCH LÀM VIỆC', 'description' => 'Tạo và quản lý ca làm việc cho nhân viên theo từng ngày.'],
    'attendance' => ['title' => 'Quản lý chấm công', 'kicker' => 'CHẤM CÔNG', 'description' => 'Theo dõi và quản lý dữ liệu chấm công nhân viên.'],
    'overtime' => ['title' => 'Duyệt tăng ca', 'kicker' => 'TĂNG CA', 'description' => 'Xem và xử lý các đơn đăng ký tăng ca.'],
    'leave' => ['title' => 'Quản lý nghỉ phép', 'kicker' => 'NGHỈ PHÉP', 'description' => 'Theo dõi và xử lý đơn nghỉ phép.'],
    'reports' => ['title' => 'Báo cáo thống kê', 'kicker' => 'BÁO CÁO', 'description' => 'Xem thống kê và xuất báo cáo nhân sự.'],
    'settings' => ['title' => 'Cài đặt', 'kicker' => 'HỆ THỐNG', 'description' => 'Cấu hình thông tin và tham số hệ thống.'],
];
$page = $pages[$section] ?? $pages['overview'];
$isOvertime = $section === 'overtime';
$isSchedule = $section === 'schedule';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($page['title']) ?> | LumiCine HRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/hrm.css" rel="stylesheet">
    <?php if ($isOvertime): ?><link href="assets/css/overtime.css" rel="stylesheet"><?php endif; ?>
    <?php if ($isSchedule): ?><link href="assets/css/schedule.css" rel="stylesheet"><?php endif; ?>
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <aside class="sidebar" id="sidebar" aria-label="Điều hướng quản trị">
        <a class="brand" href="?action=admin-overview"><span class="brand-mark"><i class="bi bi-buildings-fill"></i></span><span><strong>HRM</strong><small>PEOPLE MANAGEMENT</small></span></a>
        <p class="nav-caption">KHÔNG GIAN LÀM VIỆC</p>
        <nav class="nav flex-column sidebar-nav">
            <a class="nav-link <?= $section === 'overview' ? 'active' : '' ?>" href="?action=admin-overview"><i class="bi bi-grid-1x2"></i><span>Tổng quan</span></a>
            <a class="nav-link <?= $section === 'employees' ? 'active' : '' ?>" href="?action=admin-employees"><i class="bi bi-people"></i><span>Quản lý nhân viên</span></a>
            <a class="nav-link <?= $section === 'schedule' ? 'active' : '' ?>" href="?action=admin-schedule"><i class="bi bi-calendar-week"></i><span>Phân ca làm việc</span></a>
            <a class="nav-link <?= $section === 'attendance' ? 'active' : '' ?>" href="?action=admin-attendance"><i class="bi bi-calendar2-check"></i><span>Quản lý chấm công</span></a>
            <a class="nav-link <?= $section === 'overtime' ? 'active' : '' ?>" href="?action=admin-overtime"><i class="bi bi-clock-history"></i><span>Duyệt tăng ca</span></a>
            <a class="nav-link" href="?action=admin-summary"><i class="bi bi-clipboard-data"></i><span>Tổng hợp công</span></a>
            <a class="nav-link <?= $section === 'leave' ? 'active' : '' ?>" href="?action=admin-leave"><i class="bi bi-calendar2-minus"></i><span>Quản lý nghỉ phép</span></a>
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
                <button class="icon-button notification-button" type="button" id="notification-button" aria-label="Thông báo"><i class="bi bi-bell"></i><span class="notification-indicator" id="notification-indicator" hidden></span></button>
                <span class="topbar-divider"></span>
                <button class="user-menu" type="button" id="admin-account-menu-button" aria-haspopup="menu" aria-expanded="false"><span class="avatar avatar-admin"><?= $escape($currentUser['initials']) ?></span><span class="user-copy"><strong><?= $escape($currentUser['display_name']) ?></strong><small><?= $escape($roleLabel) ?></small></span><i class="bi bi-chevron-down user-chevron"></i></button>
                <div class="admin-account-menu" id="admin-account-menu" role="menu" hidden>
                    <form action="index.php?action=logout" method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><button type="submit" role="menuitem"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button></form>
                </div>
            </div>
        </header>
        <div class="page-container">
            <section class="page-intro"><div><div class="page-kicker"><span></span> <?= $escape($page['kicker']) ?></div><h1><?= $escape($page['title']) ?></h1><p><?= $escape($page['description']) ?></p></div></section>
            <?php if ($isSchedule): ?>
                <section class="schedule-page">
                    <div class="schedule-stats">
                        <article><span class="schedule-stat-icon blue"><i class="bi bi-calendar2-week"></i></span><div><span>Tổng ca trong ngày</span><strong id="schedule-total"><?= count($scheduleAssignments) ?></strong></div></article>
                        <article><span class="schedule-stat-icon green"><i class="bi bi-clock-history"></i></span><div><span>Đang diễn ra</span><strong id="schedule-active"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'active')) ?></strong></div></article>
                        <article><span class="schedule-stat-icon orange"><i class="bi bi-hourglass-split"></i></span><div><span>Sắp bắt đầu</span><strong id="schedule-upcoming"><?= count(array_filter($scheduleAssignments, static fn ($item) => $item['status'] === 'upcoming')) ?></strong></div></article>
                        <article><span class="schedule-stat-icon purple"><i class="bi bi-people"></i></span><div><span>Nhân viên hoạt động</span><strong><?= count($scheduleEmployees) ?></strong></div></article>
                    </div>

                    <section class="schedule-board" aria-labelledby="schedule-list-title">
                        <header class="schedule-board-header">
                            <div><h2 id="schedule-list-title">Lịch phân ca</h2><p id="schedule-date-caption">Ca làm việc trong ngày</p></div>
                            <div class="schedule-actions">
                                <button class="button button-light" type="button" id="schedule-prev"><i class="bi bi-chevron-left"></i></button>
                                <label class="schedule-date"><i class="bi bi-calendar3"></i><input id="schedule-date" type="date" value="<?= $escape($scheduleDate) ?>" aria-label="Chọn ngày phân ca"></label>
                                <button class="button button-light" type="button" id="schedule-next"><i class="bi bi-chevron-right"></i></button>
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
                            </div>
                        </div>
                        <div class="schedule-table-wrap">
                            <table class="schedule-table">
                                <thead><tr><th>Nhân viên</th><th>Bộ phận</th><th>Ca làm</th><th>Thời gian</th><th>Địa điểm</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                <tbody id="schedule-rows">
                                    <?php if ($scheduleAssignments === []): ?>
                                        <tr class="schedule-empty-row"><td colspan="7">Chưa có ca làm việc trong ngày này.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($scheduleAssignments as $index => $assignment): ?>
                                            <tr data-assignment-id="<?= $escape($assignment['id']) ?>">
                                                <td><div class="schedule-employee"><span><?= $escape(mb_substr($assignment['name'], 0, 1, 'UTF-8')) ?></span><div><strong><?= $escape($assignment['name']) ?></strong><small><?= $escape($assignment['role']) ?></small></div></div></td>
                                                <td><?= $escape($assignment['department']) ?></td><td><strong><?= $escape($assignment['shift_name'] ?? ('Ca ' . ($index + 1))) ?></strong></td>
                                                <td><strong><?= $escape(substr($assignment['start'], 0, 5)) ?> - <?= $escape(substr($assignment['end'], 0, 5)) ?></strong></td>
                                                <td><?= $escape($assignment['location']) ?></td>
                                                <td><span class="schedule-status <?= $escape($assignment['status']) ?>"><?= $assignment['status'] === 'active' ? 'Đang diễn ra' : ($assignment['status'] === 'upcoming' ? 'Sắp bắt đầu' : 'Hoàn thành') ?></span></td>
                                                <td><div class="schedule-row-actions"><button type="button" data-action="edit" title="Chỉnh sửa"><i class="bi bi-pencil"></i></button><button type="button" data-action="cancel" title="Hủy ca"><i class="bi bi-trash3"></i></button></div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <footer class="schedule-table-footer"><span id="schedule-result-count">Hiển thị <?= count($scheduleAssignments) ?> ca</span><button class="button button-outline" type="button" id="schedule-export"><i class="bi bi-download"></i> Xuất danh sách</button></footer>
                    </section>
                </section>

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
            <?php elseif ($isOvertime): ?>
                <section class="overtime-card" id="overtime-requests" aria-labelledby="overtime-requests-title">
                    <div class="overtime-heading"><div><h2 id="overtime-requests-title">Đơn đăng ký tăng ca</h2><p>Xem lý do, thời gian và xử lý các đơn nhân viên gửi.</p></div><div class="overtime-summary"><span>Chờ duyệt</span><strong id="overtime-pending-count">0</strong></div></div>
                    <div class="overtime-feedback" id="overtime-feedback" role="status" aria-live="polite"></div>
                    <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Nhân viên</th><th>Bắt đầu</th><th>Kết thúc</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody id="admin-overtime-rows"><tr><td colspan="6">Đang tải đơn tăng ca...</td></tr></tbody></table></div>
                </section>
            <?php elseif ($section === 'overview'): ?>
                <section class="summary-grid">
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-blue"><i class="bi bi-people-fill"></i></span></div><p class="summary-label">Nhân viên</p><div class="summary-value"><?= count($employees) ?> <span>người</span></div></article>
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-green"><i class="bi bi-calendar2-check-fill"></i></span></div><p class="summary-label">Ngày công</p><div class="summary-value"><?= array_sum(array_column($employees, 'worked')) ?> <span>ngày</span></div></article>
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-orange"><i class="bi bi-clock-history"></i></span></div><p class="summary-label">Đi muộn</p><div class="summary-value"><?= array_sum(array_column($employees, 'late')) ?> <span>lượt</span></div></article>
                    <article class="summary-card"><div class="summary-heading"><span class="summary-icon icon-purple"><i class="bi bi-calendar2-heart-fill"></i></span></div><p class="summary-label">Nghỉ phép</p><div class="summary-value">— <span>chưa có bảng dữ liệu</span></div></article>
                </section>
            <?php elseif (in_array($section, ['employees', 'attendance', 'leave', 'reports'], true)): ?>
                <?php if ($section === 'leave'): ?>
                    <section class="data-card"><div class="table-card-header"><div><h2>Danh sách nghỉ phép</h2><p>Database hiện chưa có bảng lưu đơn/ngày nghỉ phép.</p></div></div><div class="table-wrap"><table class="employee-table"><thead><tr><th>Nhân viên</th><th>Bộ phận</th><th>Ngày nghỉ</th><th>Trạng thái</th></tr></thead><tbody><tr><td colspan="4">Chưa có dữ liệu nghỉ phép để hiển thị.</td></tr></tbody></table></div></section>
                <?php elseif ($section === 'reports'): ?>
                    <section class="summary-grid">
                        <article class="summary-card"><p class="summary-label">Tổng nhân viên</p><div class="summary-value"><?= count($employees) ?></div></article>
                        <article class="summary-card"><p class="summary-label">Tổng ngày công</p><div class="summary-value"><?= array_sum(array_column($employees, 'worked')) ?></div></article>
                        <article class="summary-card"><p class="summary-label">Lượt đi muộn</p><div class="summary-value"><?= array_sum(array_column($employees, 'late')) ?></div></article>
                        <article class="summary-card"><p class="summary-label">Ngày nghỉ phép</p><div class="summary-value">—</div></article>
                    </section>
                <?php elseif ($section === 'employees'): ?>
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
                                <thead><tr><th>Tài khoản</th><th>Tên hiển thị</th><th>Hồ sơ nhân viên</th><th>Thao tác</th></tr></thead>
                                <tbody>
                                    <?php if ($employeeMappings === []): ?>
                                        <tr><td colspan="4">Không có tài khoản mang vai trò nhân viên.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($employeeMappings as $account): ?>
                                            <tr data-mapping-row data-user-id="<?= (int) $account['id'] ?>">
                                                <td><?= $escape($account['username']) ?></td>
                                                <td><?= $escape($account['display_name']) ?></td>
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
                <?php else: ?>
                    <section class="data-card"><div class="table-card-header"><div><h2><?= $section === 'attendance' ? 'Dữ liệu chấm công' : 'Danh sách nhân viên' ?></h2><p><?= $section === 'attendance' ? 'Tổng hợp ngày công theo nhân viên.' : 'Thông tin nhân viên trong hệ thống.' ?></p></div></div><div class="table-wrap"><table class="employee-table"><thead><tr><th>Mã NV</th><th>Nhân viên</th><th>Bộ phận</th><th>Chức vụ</th><?php if ($section === 'attendance'): ?><th>Ngày công</th><th>Đi muộn</th><?php endif; ?></tr></thead><tbody><?php foreach ($employees as $employee): ?><tr><td><?= $escape($employee['id']) ?></td><td><?= $escape($employee['name']) ?></td><td><?= $escape($employee['department']) ?></td><td><?= $escape($employee['position']) ?></td><?php if ($section === 'attendance'): ?><td><?= (int) $employee['worked'] ?> ngày</td><td><?= (int) $employee['late'] ?> lượt</td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>
                <?php endif; ?>
            <?php elseif ($section === 'settings'): ?>
                <section class="data-card"><div class="table-card-header"><div><h2>Cài đặt hệ thống</h2><p>Cấu hình chung cho LumiCine HRM.</p></div></div><div class="settings-grid"><label>Giờ bắt đầu ca<input type="time" value="08:00"></label><label>Giờ kết thúc ca<input type="time" value="17:00"></label><label>Ngày công chuẩn/tháng<input type="number" min="1" max="31" value="22"></label><button class="button button-primary" type="button">Lưu cài đặt</button></div></section>
            <?php else: ?>
                <section class="data-card section-placeholder"><div class="table-card-header"><div><h2><?= $escape($page['title']) ?></h2><p>Chức năng này đã được đặt trên trang riêng.</p></div></div></section>
            <?php endif; ?>
        </div>
    </main>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" aria-live="polite"></div>
<script src="assets/js/admin-section.js" defer></script>
<?php if ($isSchedule): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script>
        window.scheduleInitialData = <?= json_encode($scheduleAssignments, JSON_UNESCAPED_UNICODE) ?>;
        window.scheduleInitialShiftTypes = <?= json_encode($scheduleShiftTypes, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="assets/js/schedule.js" defer></script>
<?php elseif ($isOvertime): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script src="assets/js/overtime.js" defer></script>
<?php elseif ($section === 'employees'): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
<?php endif; ?>
</body>
</html>
