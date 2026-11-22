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
$isLeave = $section === 'leave';
$isSchedule = $section === 'schedule';
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
    <link href="assets/css/hrm.css" rel="stylesheet">
    <?php if ($isOvertime || $isLeave): ?><link href="assets/css/overtime.css" rel="stylesheet"><?php endif; ?>
    <?php if ($isSchedule): ?><link href="assets/css/schedule.css" rel="stylesheet"><?php endif; ?>
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <aside class="sidebar" id="sidebar" aria-label="Điều hướng quản trị">
        <a class="brand" href="?action=admin-overview"><span class="brand-mark"><i class="bi bi-buildings-fill"></i></span><span><strong>LumiCine</strong></span></a>
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
                                                <td><div class="schedule-employee"><span><?= $escape(lumicineUtf8Substring($assignment['name'], 0, 1)) ?></span><div><strong><?= $escape($assignment['name']) ?></strong><small><?= $escape($assignment['role']) ?></small></div></div></td>
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
                <dialog class="schedule-dialog" id="shift-types-dialog">
                    <form id="shift-types-form" method="dialog">
                        <header><div><span>QUẢN LÝ LOẠI CA</span><h2>Ngừng hoạt động / kích hoạt ca</h2></div><button type="button" id="shift-types-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
                        <div class="shift-types-list" id="shift-types-list"></div>
                        <footer><button type="button" class="button button-light" id="shift-types-cancel">Đóng</button></footer>
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
                    <section class="overtime-card" id="leave-requests" aria-labelledby="leave-requests-title">
                        <div class="overtime-heading"><div><h2 id="leave-requests-title">Đơn đăng ký nghỉ phép</h2><p>Xem lý do, thời gian và xử lý các đơn nghỉ phép.</p></div><div class="overtime-summary"><span>Chờ duyệt</span><strong id="leave-pending-count">0</strong></div></div>
                        <div class="overtime-feedback" id="leave-feedback" role="status" aria-live="polite"></div>
                        <div class="overtime-table-wrap"><table class="overtime-table"><thead><tr><th>Nhân viên</th><th>Bắt đầu</th><th>Kết thúc</th><th>Lý do</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody id="admin-leave-rows"><tr><td colspan="6">Đang tải đơn nghỉ phép...</td></tr></tbody></table></div>
                    </section>
                <?php elseif ($section === 'reports'): ?>
                    <section class="summary-grid">
                        <article class="summary-card"><p class="summary-label">Tổng nhân viên</p><div class="summary-value"><?= count($employees) ?></div></article>
                        <article class="summary-card"><p class="summary-label">Tổng ngày công</p><div class="summary-value"><?= array_sum(array_column($employees, 'worked')) ?></div></article>
                        <article class="summary-card"><p class="summary-label">Lượt đi muộn</p><div class="summary-value"><?= array_sum(array_column($employees, 'late')) ?></div></article>
                        <article class="summary-card"><p class="summary-label">Ngày nghỉ phép</p><div class="summary-value">—</div></article>
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
                                <thead><tr><th>Mã nhân viên</th><th>Họ và tên</th><th>Phòng ban</th><th>Chức vụ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                <tbody>
                                    <?php if ($managedEmployees === []): ?>
                                        <tr data-empty-row><td colspan="6">Chưa có hồ sơ nhân viên.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($managedEmployees as $employee): ?>
                                            <?php $active = (int) $employee['active'] === 1; ?>
                                            <tr data-employee-row data-id="<?= (int) $employee['id'] ?>" data-code="<?= $escape($employee['code']) ?>" data-name="<?= $escape($employee['name']) ?>" data-position="<?= $escape($employee['position']) ?>" data-department-id="<?= $employee['department_id'] === null ? '' : (int) $employee['department_id'] ?>" data-active="<?= $active ? '1' : '0' ?>">
                                                <td><?= $escape($employee['code']) ?></td>
                                                <td><?= $escape($employee['name']) ?></td>
                                                <td><?= $escape($employee['department'] !== '' ? $employee['department'] : 'Chưa phân công') ?></td>
                                                <td><?= $escape($employee['position'] !== '' ? $employee['position'] : '—') ?></td>
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
                <?php else: ?>
                    <section class="data-card"><div class="table-card-header"><div><h2><?= $section === 'attendance' ? 'Dữ liệu chấm công' : 'Danh sách nhân viên' ?></h2><p><?= $section === 'attendance' ? 'Tổng hợp ngày công theo nhân viên.' : 'Thông tin nhân viên trong hệ thống.' ?></p></div></div><div class="table-wrap"><table class="employee-table"><thead><tr><th>Mã NV</th><th>Nhân viên</th><th>Bộ phận</th><th>Chức vụ</th><?php if ($section === 'attendance'): ?><th>Ngày công</th><th>Đi muộn</th><?php endif; ?></tr></thead><tbody><?php foreach ($employees as $employee): ?><tr><td><?= $escape($employee['id']) ?></td><td><?= $escape($employee['name']) ?></td><td><?= $escape($employee['department']) ?></td><td><?= $escape($employee['position']) ?></td><?php if ($section === 'attendance'): ?><td><?= (int) $employee['worked'] ?> ngày</td><td><?= (int) $employee['late'] ?> lượt</td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></section>
                <?php endif; ?>
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
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" aria-live="polite"></div>
<script src="assets/js/admin-section.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/admin-section.js') ?>" defer></script>
<script src="assets/js/admin-section.js?v=20261121" defer></script>
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
<?php elseif ($isLeave): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script src="assets/js/leave.js" defer></script>
<?php elseif ($section === 'employees'): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
    <script src="assets/js/workforce-management.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/workforce-management.js') ?>" defer></script>
<?php elseif ($section === 'settings'): ?>
    <?php require PATH_VIEW . 'api-config.php'; ?>
<?php endif; ?>
</body>
</html>
