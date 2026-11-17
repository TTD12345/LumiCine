<?php
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$today = date('Y-m-d');
$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$currentUserName = $escape($currentUser['display_name']);
$currentUserInitials = $escape($currentUser['initials']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f7fb">
    <title>Chấm công của tôi | LumiCine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/employee.css" rel="stylesheet">
</head>
<body>
<div class="employee-app">
    <aside class="employee-sidebar" id="employee-sidebar" aria-label="Điều hướng chính">
        <a class="employee-brand" href="?action=/">
            <span class="brand-icon"><i class="bi bi-buildings-fill" aria-hidden="true"></i></span>
            <span class="brand-title">LumiCine<small>QUẢN LÝ NHÂN SỰ</small></span>
        </a>
        <p class="nav-caption">TỔNG QUAN</p>
        <nav class="employee-nav">
            <a class="employee-nav-link" href="?action=/#overview"><i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Bảng điều khiển</span></a>
            <a class="employee-nav-link" href="?action=/#schedule"><i class="bi bi-calendar-week" aria-hidden="true"></i><span>Lịch làm việc</span></a>
            <a class="employee-nav-link" href="?action=/#schedule"><i class="bi bi-people" aria-hidden="true"></i><span>Ca làm việc</span></a>
            <a class="employee-nav-link active" href="?action=attendance" aria-current="page"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>Chấm công</span></a>
        </nav>
        <p class="nav-caption nav-caption-spaced">QUẢN LÝ</p>
        <nav class="employee-nav">
            <a class="employee-nav-link" href="?action=/#schedule"><i class="bi bi-person-vcard" aria-hidden="true"></i><span>Nhân viên</span></a>
            <a class="employee-nav-link" href="?action=/#schedule"><i class="bi bi-graph-up" aria-hidden="true"></i><span>Báo cáo</span></a>
        </nav>
        <div class="employee-sidebar-bottom">
            <?php if (in_array($currentUser['role'], ['accountant', 'hr', 'chairman'], true)): ?>
                <a class="employee-admin-link" href="admin.php"><i class="bi bi-shield-lock" aria-hidden="true"></i> Trang quản trị HRM</a>
            <?php endif; ?>
            <form class="employee-logout-form" action="index.php?action=logout" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button class="employee-logout-button" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Đăng xuất</button>
            </form>
            <div class="employee-profile">
                <span class="profile-avatar"><?= $currentUserInitials ?></span>
                <span class="profile-text"><strong id="sidebar-account-name"><?= $currentUserName ?></strong><small><?= $escape($roleLabel) ?></small></span>
            </div>
        </div>
    </aside>
    <div class="sidebar-scrim" id="sidebar-scrim"></div>

    <main class="employee-main">
        <header class="employee-topbar">
            <div class="topbar-leading">
                <button class="mobile-menu" type="button" id="mobile-menu-button" aria-label="Mở menu"><i class="bi bi-list" aria-hidden="true"></i></button>
                <div><strong>Chấm công</strong><div class="breadcrumbs"><a href="?action=/">Trang chủ</a><i class="bi bi-chevron-right" aria-hidden="true"></i><span>Chấm công</span></div></div>
            </div>
            <div class="topbar-trailing">
                <button class="notification-button" type="button" id="notifications-button" aria-label="Thông báo"><i class="bi bi-bell" aria-hidden="true"></i><span></span></button>
                <span class="topbar-divider"></span>
                <span class="topbar-profile"><span class="profile-avatar"><?= $currentUserInitials ?></span><strong id="top-account-name"><?= $currentUserName ?></strong><i class="bi bi-chevron-down"></i></span>
            </div>
        </header>

        <div class="employee-content">
            <section class="page-heading" id="overview">
                <div>
                    <span class="page-kicker">KHÔNG GIAN NHÂN VIÊN</span>
                    <h1>Chấm công của tôi</h1>
                    <p>Ghi nhận giờ vào, giờ ra và xem lại các lần chấm công gần đây.</p>
                </div>
                <label class="attendance-date-picker">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <span class="visually-hidden">Ngày chấm công</span>
                    <input id="attendance-date" type="date" value="<?= $escape($today) ?>" aria-label="Ngày chấm công">
                </label>
            </section>

            <section class="personal-attendance-card" id="attendance" aria-labelledby="attendance-title">
                <div class="attendance-card-top">
                    <div>
                        <span class="attendance-kicker"><span class="attendance-live-dot"></span> CHẤM CÔNG CỦA TÔI</span>
                        <h2 id="attendance-title">Xin chào, <span id="attendance-name"><?= $currentUserName ?></span></h2>
                        <p>Ghi nhận giờ làm việc cho ca hành chính của bạn.</p>
                        <h2 id="attendance-title">Xin chào, <span id="attendance-name">Lan Anh</span></h2>
                        <p>Chấm công vào, ra linh hoạt trong ngày; giờ vào sớm nhất và giờ ra muộn nhất sẽ được lưu.</p>
                    </div>
                </div>
                <div class="attendance-card-body">
                    <div class="attendance-details">
                        <div class="attendance-employee">
                            <span class="attendance-employee-avatar"><?= $currentUserInitials ?></span>
                            <div><strong id="attendance-profile-name"><?= $currentUserName ?></strong><span><?= $escape($roleLabel) ?> · LumiCine</span></div>
                            <span class="attendance-status" id="attendance-status">Chưa bắt đầu</span>
                        </div>
                        <div class="attendance-shift-info">
                            <span><i class="bi bi-clock" aria-hidden="true"></i> Ca hành chính</span>
                            <strong>08:00 <span>—</span> 17:00</strong>
                            <span><i class="bi bi-geo-alt" aria-hidden="true"></i> Văn phòng LumiCine</span>
                        </div>
                        <div class="attendance-events">
                            <div class="attendance-event">
                                <span class="attendance-event-icon checkin-icon"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i></span>
                                <span><strong>Giờ vào đầu tiên</strong><small id="attendance-check-in">Chưa ghi nhận</small></span>
                                <span class="attendance-event-state" id="attendance-check-in-state">Chờ chấm công</span>
                            </div>
                            <div class="attendance-event">
                                <span class="attendance-event-icon checkout-icon"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></span>
                                <span><strong>Giờ ra cuối cùng</strong><small id="attendance-check-out">Chưa ghi nhận</small></span>
                                <span class="attendance-event-state" id="attendance-check-out-state">Chờ chấm công</span>
                            </div>
                        </div>
                    </div>
                    <div class="attendance-clock">
                        <span class="attendance-clock-label">THỜI GIAN HIỆN TẠI</span>
                        <strong id="attendance-live-clock">--:--:--</strong>
                        <span id="attendance-live-date"></span>
                        <button class="attendance-punch-button" id="attendance-punch" type="button"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Chấm công vào</span></button>
                    </div>
                </div>
                <div class="attendance-card-foot"><i class="bi bi-info-circle" aria-hidden="true"></i> Bản xem thử · Ghi nhận được lưu trên trình duyệt này.</div>
            </section>

            <section class="employee-stats attendance-overview" aria-label="Tổng quan chấm công">
                <article class="stat-card">
                    <div class="stat-top"><span>Ngày có ghi nhận</span><i class="bi bi-calendar2-check"></i></div>
                    <strong id="attendance-days-count">0</strong><small>ngày</small>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Giờ làm đã hoàn thành</span><i class="bi bi-hourglass-split"></i></div>
                    <strong id="attendance-hours-total">0</strong><small>giờ</small>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Trạng thái hôm nay</span><i class="bi bi-person-check"></i></div>
                    <strong class="attendance-status-summary" id="attendance-status-summary">Chưa bắt đầu</strong>
                </article>
            </section>

            <section class="schedule-card attendance-history-card" id="attendance-history-section">
                <div class="schedule-heading">
                    <div><h2>Lịch sử chấm công</h2><p>Các lần ghi nhận gần đây của bạn.</p></div>
                    <span class="history-range-label">7 lần gần nhất</span>
                </div>
                <div class="employee-table-wrap">
                    <table class="employee-table attendance-history-table">
                        <thead><tr><th>Ngày</th><th>Giờ vào</th><th>Giờ ra</th><th>Tổng thời gian</th><th>Trạng thái</th></tr></thead>
                        <tbody id="attendance-history"></tbody>
                    </table>
                    <div class="empty-state" id="attendance-history-empty" hidden><i class="bi bi-calendar2-x" aria-hidden="true"></i><strong>Chưa có lịch sử chấm công</strong><span>Các ngày đã chấm công sẽ được hiển thị tại đây.</span></div>
                </div>
                <footer class="schedule-footer"><span id="attendance-history-count">Chưa có dữ liệu chấm công</span><span class="history-storage-note">Dữ liệu bản thử nghiệm lưu trong trình duyệt.</span></footer>
            </section>
            <footer class="employee-footer"><span>© <?= date('Y') ?> LumiCine · Quản lý nhân sự</span><a href="?action=/#schedule">Quay lại lịch làm việc</a></footer>
        </div>
    </main>
</div>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
<script src="assets/js/employee-attendance.js" defer></script>
</body>
</html>
