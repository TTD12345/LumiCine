<?php
$initialDate = date('Y-m-d');
$shifts = [
    [
        'name' => 'Nguyễn Minh Anh',
        'role' => 'Trưởng phòng nhân sự',
        'department' => 'Hành chính - Nhân sự',
        'start' => '08:00',
        'end' => '16:00',
        'location' => 'Văn phòng tầng 1',
        'status' => 'active',
        'tone' => 'violet',
    ],
    [
        'name' => 'Trần Quốc Bảo',
        'role' => 'Chuyên viên kinh doanh',
        'department' => 'Kinh doanh',
        'start' => '09:00',
        'end' => '17:00',
        'location' => 'Văn phòng tầng 2',
        'status' => 'active',
        'tone' => 'blue',
    ],
    [
        'name' => 'Lê Hoàng Yến',
        'role' => 'Chuyên viên marketing',
        'department' => 'Marketing',
        'start' => '12:00',
        'end' => '20:00',
        'location' => 'Phòng họp A',
        'status' => 'upcoming',
        'tone' => 'pink',
    ],
    [
        'name' => 'Phạm Đức Long',
        'role' => 'Kế toán viên',
        'department' => 'Kế toán',
        'start' => '13:00',
        'end' => '21:00',
        'location' => 'Văn phòng tầng 3',
        'status' => 'upcoming',
        'tone' => 'orange',
    ],
    [
        'name' => 'Võ Ngọc Mai',
        'role' => 'Nhân viên hành chính',
        'department' => 'Hành chính - Nhân sự',
        'start' => '07:00',
        'end' => '15:00',
        'location' => 'Khu vực lễ tân',
        'status' => 'completed',
        'tone' => 'green',
    ],
    [
        'name' => 'Đặng Tuấn Kiệt',
        'role' => 'Chuyên viên công nghệ thông tin',
        'department' => 'Công nghệ thông tin',
        'start' => '15:00',
        'end' => '23:00',
        'location' => 'Phòng IT',
        'status' => 'upcoming',
        'tone' => 'teal',
    ],
];

foreach ($shifts as $index => &$shift) {
    $shift['id'] = 'sample-' . ($index + 1);
}
unset($shift);

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
        <a class="employee-brand" href="#overview">
            <span class="brand-icon"><i class="bi bi-buildings-fill" aria-hidden="true"></i></span>
            <span class="brand-title">LumiCine<small>QUẢN LÝ NHÂN SỰ</small></span>
        </a>
        <p class="nav-caption">TỔNG QUAN</p>
        <nav class="employee-nav">
            <a class="employee-nav-link" href="#overview"><i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Bảng điều khiển</span></a>
            <a class="employee-nav-link active" href="#schedule"><i class="bi bi-calendar-week" aria-hidden="true"></i><span>Lịch làm việc</span></a>
            <a class="employee-nav-link" href="#schedule"><i class="bi bi-people" aria-hidden="true"></i><span>Ca làm việc</span></a>
            <a class="employee-nav-link" href="#schedule"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>Chấm công</span></a>
        </nav>
        <p class="nav-caption nav-caption-spaced">QUẢN LÝ</p>
        <nav class="employee-nav">
            <a class="employee-nav-link" href="#schedule"><i class="bi bi-person-vcard" aria-hidden="true"></i><span>Nhân viên</span></a>
            <a class="employee-nav-link" href="#schedule"><i class="bi bi-graph-up" aria-hidden="true"></i><span>Báo cáo</span></a>
        </nav>
        <div class="employee-sidebar-bottom">
            <a class="employee-admin-link" href="?action=admin"><i class="bi bi-shield-lock" aria-hidden="true"></i> Trang quản trị HRM</a>
            <div class="employee-profile">
                <span class="profile-avatar">LA</span>
                <span class="profile-text"><strong id="sidebar-account-name">Lan Anh</strong><small>Nhân viên</small></span>
                <button class="profile-more" type="button" id="account-menu-button" aria-label="Tùy chọn tài khoản"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                <div class="account-menu" id="account-menu" hidden>
                    <button type="button" id="edit-account">Đổi tên hiển thị</button>
                    <button type="button" id="reset-sample-data">Khôi phục dữ liệu mẫu</button>
                </div>
            </div>
        </div>
    </aside>
    <div class="sidebar-scrim" id="sidebar-scrim"></div>

    <main class="employee-main">
        <header class="employee-topbar">
            <div class="topbar-leading">
                <button class="mobile-menu" type="button" id="mobile-menu-button" aria-label="Mở menu"><i class="bi bi-list" aria-hidden="true"></i></button>
                <div><strong>Lịch làm việc</strong><div class="breadcrumbs"><a href="#overview">Trang chủ</a><i class="bi bi-chevron-right"></i><span>Lịch làm việc</span></div></div>
            </div>
            <div class="topbar-trailing">
                <button class="notification-button" type="button" id="notifications-button" aria-label="Thông báo"><i class="bi bi-bell" aria-hidden="true"></i><span></span></button>
                <span class="topbar-divider"></span>
                <span class="topbar-profile"><span class="profile-avatar">LA</span><strong id="top-account-name">Lan Anh</strong><i class="bi bi-chevron-down"></i></span>
            </div>
        </header>

        <div class="employee-content">
            <section class="page-heading" id="overview">
                <div>
                    <span class="page-kicker">LỊCH LÀM VIỆC &amp; PHÂN CA</span>
                    <h1>Lịch làm việc</h1>
                    <p>Theo dõi và sắp xếp lịch làm việc của đội ngũ theo từng ngày.</p>
                </div>
                <button class="primary-button" type="button" id="add-shift"><i class="bi bi-plus-lg" aria-hidden="true"></i>Tạo ca mới</button>
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
                                    <td><div class="employee-cell"><span class="employee-avatar <?= $escape($shift['tone']) ?>"><?= $escape($firstInitial) ?></span><span class="employee-identity"><strong><?= $escape($shift['name']) ?></strong><small><?= $escape($shift['role']) ?></small></span></div></td>
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
                    <span id="result-count">Hiển thị 1–6 trong 6 ca</span>
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
            <label class="dialog-field full">Tên nhân viên<input name="name" required maxlength="80" placeholder="Ví dụ: Nguyễn Minh Anh"></label>
            <label class="dialog-field">Bộ phận<input name="department" required maxlength="50" placeholder="Ví dụ: Kế toán"></label>
            <label class="dialog-field">Vị trí<input name="role" required maxlength="60" placeholder="Ví dụ: Kế toán viên"></label>
            <label class="dialog-field">Bắt đầu<input name="start" type="time" value="08:00" required></label>
            <label class="dialog-field">Kết thúc<input name="end" type="time" value="16:00" required></label>
            <label class="dialog-field full">Địa điểm<input name="location" required maxlength="60" placeholder="Ví dụ: Văn phòng tầng 1"></label>
            <label class="dialog-field full">Trạng thái<select name="status"><option value="upcoming">Sắp bắt đầu</option><option value="active">Đang diễn ra</option><option value="completed">Hoàn thành</option></select></label>
        </div>
        <footer class="dialog-actions"><button class="secondary-button" type="button" id="cancel-dialog">Hủy</button><button class="primary-button" type="submit" id="save-shift">Lưu ca làm việc</button></footer>
    </form>
</dialog>
<dialog class="shift-dialog" id="account-dialog">
    <form id="account-form" method="dialog">
        <header class="dialog-heading"><div><span class="page-kicker">TÀI KHOẢN</span><h2>Thông tin hiển thị</h2><p>Thay đổi tên được hiển thị trên trang.</p></div><button class="dialog-close" type="button" id="close-account-dialog" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></header>
        <label class="dialog-field">Tên hiển thị<input id="account-name" name="name" required maxlength="60"></label>
        <footer class="dialog-actions"><button class="secondary-button" type="button" id="cancel-account-dialog">Hủy</button><button class="primary-button" type="submit">Lưu thay đổi</button></footer>
    </form>
</dialog>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
<script id="shift-data" type="application/json"><?= json_encode($shifts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>window.employeeScheduleConfig = { initialDate: <?= json_encode($initialDate) ?> };</script>
<script src="assets/js/employee.js" defer></script>
</body>
</html>
