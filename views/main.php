<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$currentUserName = $escape($currentUser['display_name']);
$currentUserInitials = $escape($currentUser['initials']);
$statusLabels = [
    'active' => 'Đang diễn ra',
    'upcoming' => 'Sắp bắt đầu',
    'completed' => 'Hoàn thành',
];
$counts = ['active' => 0, 'upcoming' => 0, 'completed' => 0];
foreach ($shifts as $shift) {
    $counts[$shift['status']]++;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f7fb">
    <title>Lịch làm việc | LumiCine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/employee.css" rel="stylesheet">
</head>
<body>
<div class="employee-app">
    <aside class="employee-sidebar" id="employee-sidebar" aria-label="Điều hướng chính">
        <a class="employee-brand" href="index.php?action=dashboard">
            <span class="brand-icon"><i class="bi bi-buildings-fill" aria-hidden="true"></i></span>
            <span class="brand-title">LumiCine</span>
        </a>
        <p class="nav-caption">TỔNG QUAN</p>
        <nav class="employee-nav">
            <a class="employee-nav-link" href="index.php?action=dashboard"><i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Bảng điều khiển</span></a>
            <a class="employee-nav-link active" href="index.php?action=schedule" aria-current="page"><i class="bi bi-calendar-week" aria-hidden="true"></i><span>Lịch làm việc</span></a>
            <a class="employee-nav-link" href="index.php?action=shift"><i class="bi bi-people" aria-hidden="true"></i><span>Ca làm việc</span></a>
            <a class="employee-nav-link" href="index.php?action=overtime"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Đăng ký tăng ca</span></a>
            <a class="employee-nav-link" href="index.php?action=attendance"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>Chấm công</span></a>
        </nav>
        <div class="employee-sidebar-bottom">
            <?php if (in_array($currentUser['role'], ['accountant', 'hr', 'chairman'], true)): ?>
                <a class="employee-admin-link" href="admin.php"><i class="bi bi-shield-lock" aria-hidden="true"></i> Trang quản trị LumiCine</a>
            <?php endif; ?>
            <form class="employee-logout-form" action="index.php?action=logout" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button class="employee-logout-button" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Đăng xuất</button>
            </form>
        </div>
    </aside>
    <div class="sidebar-scrim" id="sidebar-scrim"></div>

    <main class="employee-main">
        <header class="employee-topbar">
            <div class="topbar-leading">
                <button class="mobile-menu" type="button" id="mobile-menu-button" aria-label="Mở menu"><i class="bi bi-list" aria-hidden="true"></i></button>
                <div><strong>Lịch làm việc</strong><div class="breadcrumbs"><a href="?action=dashboard">Trang chủ</a><i class="bi bi-chevron-right"></i><span>Lịch làm việc</span></div></div>
            </div>
            <div class="topbar-trailing">
                <button class="notification-button" type="button" id="notifications-button" aria-label="Thông báo"><i class="bi bi-bell" aria-hidden="true"></i><span id="notification-indicator" hidden></span></button>
                <span class="topbar-divider"></span>
                <div class="topbar-account">
                    <button class="topbar-profile" type="button" id="account-menu-button" aria-haspopup="menu" aria-expanded="false" aria-label="Tùy chọn tài khoản">
                        <span class="profile-avatar"><?= $currentUserInitials ?></span>
                        <span class="topbar-profile-copy">
                            <strong id="top-account-name"><?= $currentUserName ?></strong>
                            <small><?= $escape($roleLabel) ?></small>
                        </span>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="account-menu" id="account-menu" role="menu" hidden>
                        <form action="index.php?action=logout" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <button type="submit" role="menuitem"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Đăng xuất</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <div class="employee-content">
            <section class="page-heading" id="overview">
                <div>
                    <span class="page-kicker">LỊCH LÀM VIỆC &amp; PHÂN CA</span>
                    <h1>Lịch làm việc</h1>
                    <p>Theo dõi và sắp xếp lịch làm việc của đội ngũ theo từng ngày.</p>
                </div>
                <?php if ($canManageShifts): ?><button class="primary-button" type="button" id="add-shift"><i class="bi bi-plus-lg" aria-hidden="true"></i>Tạo ca mới</button><?php endif; ?>
            </section>

            <section class="employee-stats" aria-label="Tổng quan ca làm việc">
                <article class="stat-card">
                    <div class="stat-top"><span>Tổng số ca hôm nay</span><i class="bi bi-calendar2-week"></i></div>
                    <strong id="summary-total"><?= count($shifts) ?></strong><small>ca làm việc</small>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Đang diễn ra</span><i class="bi bi-clock"></i></div>
                    <strong id="summary-active"><?= $counts['active'] ?></strong><small>ca hoạt động</small>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Sắp bắt đầu</span><i class="bi bi-hourglass-split"></i></div>
                    <strong id="summary-upcoming"><?= $counts['upcoming'] ?></strong><small>ca sắp tới</small>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Đã hoàn thành</span><i class="bi bi-check2-circle"></i></div>
                    <strong id="summary-completed"><?= $counts['completed'] ?></strong><small>ca kết thúc</small>
                </article>
            </section>

            <section class="schedule-card" id="schedule">
                <div class="schedule-heading">
                    <div><h2>Danh sách ca làm</h2><p>Chi tiết lịch phân công nhân viên theo từng ca.</p></div>
                    <div class="date-control">
                        <button type="button" id="previous-day" aria-label="Ngày trước"><i class="bi bi-chevron-left"></i></button>
                        <label><i class="bi bi-calendar3"></i><input id="shift-date" type="date" value="<?= $escape($initialDate) ?>" aria-label="Ngày làm việc"></label>
                        <button type="button" id="next-day" aria-label="Ngày tiếp theo"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>

                <div class="schedule-toolbar">
                    <div class="status-filters" role="group" aria-label="Lọc trạng thái ca">
                        <button class="filter-chip active" type="button" data-filter="all">Tất cả <span data-count="all"><?= count($shifts) ?></span></button>
                        <button class="filter-chip" type="button" data-filter="active">Đang diễn ra <span data-count="active"><?= $counts['active'] ?></span></button>
                        <button class="filter-chip" type="button" data-filter="upcoming">Sắp bắt đầu <span data-count="upcoming"><?= $counts['upcoming'] ?></span></button>
                        <button class="filter-chip" type="button" data-filter="completed">Hoàn thành <span data-count="completed"><?= $counts['completed'] ?></span></button>
                    </div>
                    <label class="employee-search"><i class="bi bi-search" aria-hidden="true"></i><input id="shift-search" type="search" placeholder="Tìm nhân viên, vị trí..." aria-label="Tìm nhân viên, vị trí"></label>
                </div>

                <div class="employee-table-wrap">
                    <table class="employee-table">
                        <thead>
                            <tr><th>Nhân viên</th><th>Bộ phận</th><th>Thời gian</th><th>Địa điểm</th><th>Trạng thái</th><th></th></tr>
                        </thead>
                        <tbody id="shift-list">
                            <?php foreach ($shifts as $shift): ?>
                                <?php $firstInitial = preg_match('/^./u', $shift['name'], $initialMatch) === 1 ? $initialMatch[0] : '?'; ?>
                                <tr>
                                    <td><div class="employee-cell"><span class="employee-avatar <?= ['violet', 'blue', 'pink', 'orange', 'green', 'teal'][$index % 6] ?>"><?= $escape($firstInitial) ?></span><span class="employee-identity"><strong><?= $escape($shift['name']) ?></strong><small><?= $escape($shift['role']) ?></small></span></div></td>
                                    <td><?= $escape($shift['department']) ?></td>
                                    <td><strong class="shift-time"><?= $escape($shift['start']) ?> – <?= $escape($shift['end']) ?></strong><small class="shift-duration">8 giờ</small></td>
                                    <td><?= $escape($shift['location']) ?></td>
                                    <td><span class="shift-status <?= $escape($shift['status']) ?>"><?= $escape($statusLabels[$shift['status']]) ?></span></td>
                                    <td><button class="row-menu" type="button" aria-label="Tùy chọn ca của <?= $escape($shift['name']) ?>"><i class="bi bi-three-dots"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="empty-state" id="empty-state" hidden><i class="bi bi-search"></i><strong>Không tìm thấy ca làm việc</strong><span>Hãy thử đổi từ khóa hoặc trạng thái lọc.</span></div>
                </div>
                <footer class="schedule-footer">
                    <span id="result-count">Hiển thị <?= $shifts ? 1 : 0 ?>–<?= min(6, count($shifts)) ?> trong <?= count($shifts) ?> ca</span>
                    <div class="pagination" id="pagination" aria-label="Phân trang"></div>
                </footer>
            </section>
            <footer class="employee-footer"><span>© <?= date('Y') ?> LumiCine · Quản lý nhân sự</span><button type="button" id="export-report"><i class="bi bi-download"></i> Xuất báo cáo</button></footer>
        </div>
    </main>
</div>

<dialog class="shift-dialog" id="shift-dialog">
    <form id="shift-form" method="dialog">
        <header class="dialog-heading"><div><span class="page-kicker">QUẢN LÝ LỊCH</span><h2 id="dialog-title">Tạo ca làm việc</h2><p>Thêm thông tin ca làm việc cho nhân viên.</p></div><button class="dialog-close" type="button" id="close-dialog" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
        <div class="dialog-grid">
            <label class="dialog-field full">Nhân viên<select name="employeeId" required><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= $escape($employee['name']) ?> · <?= $escape($employee['code']) ?></option><?php endforeach; ?></select></label>
            <label class="dialog-field">Bắt đầu<input name="start" type="time" value="08:00" required></label>
            <label class="dialog-field">Kết thúc<input name="end" type="time" value="16:00" required></label>
            <label class="dialog-field full">Địa điểm<input name="location" required maxlength="100" placeholder="Ví dụ: Văn phòng tầng 1"></label>
        </div>
        <footer class="dialog-actions"><button class="secondary-button" type="button" id="cancel-dialog">Hủy</button><button class="primary-button" type="submit" id="save-shift">Lưu ca làm việc</button></footer>
    </form>
</dialog>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
<script>window.employeeScheduleConfig = { initialDate: <?= json_encode($initialDate) ?>, canManage: <?= $canManageShifts ? 'true' : 'false' ?> };</script>
<?php require PATH_VIEW . 'api-config.php'; ?>
<script src="assets/js/employee.js" defer></script>
</body>
</html>
