<?php
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = ($month !== false && $month >= 1 && $month <= 12) ? $month : (int) date('n');
$year = ($year !== false && $year >= 2020 && $year <= 2100) ? $year : (int) date('Y');

// Dữ liệu minh họa thay cho truy vấn MySQL trong giai đoạn dựng giao diện.
$employees = [
    ['id' => 'NV001', 'name' => 'Nguyễn Minh Anh', 'department' => 'Hành chính - Nhân sự', 'position' => 'Trưởng phòng nhân sự', 'worked' => 22, 'leave' => 0, 'absent' => 0, 'late' => 0, 'overtime' => 12.5],
    ['id' => 'NV002', 'name' => 'Trần Quốc Bảo', 'department' => 'Kinh doanh', 'position' => 'Chuyên viên kinh doanh', 'worked' => 20, 'leave' => 1, 'absent' => 0, 'late' => 3, 'overtime' => 8],
    ['id' => 'NV003', 'name' => 'Lê Hoàng Yến', 'department' => 'Marketing', 'position' => 'Chuyên viên marketing', 'worked' => 19, 'leave' => 2, 'absent' => 0, 'late' => 1, 'overtime' => 4.5],
    ['id' => 'NV004', 'name' => 'Phạm Đức Long', 'department' => 'Kế toán', 'position' => 'Kế toán viên', 'worked' => 18, 'leave' => 0, 'absent' => 3, 'late' => 2, 'overtime' => 6],
    ['id' => 'NV005', 'name' => 'Võ Ngọc Mai', 'department' => 'Hành chính - Nhân sự', 'position' => 'Nhân viên hành chính', 'worked' => 21, 'leave' => 1, 'absent' => 0, 'late' => 1, 'overtime' => 2],
    ['id' => 'NV006', 'name' => 'Đặng Tuấn Kiệt', 'department' => 'Công nghệ thông tin', 'position' => 'Chuyên viên IT', 'worked' => 22, 'leave' => 0, 'absent' => 0, 'late' => 0, 'overtime' => 15],
    ['id' => 'NV007', 'name' => 'Bùi Thùy Dương', 'department' => 'Kinh doanh', 'position' => 'Nhân viên kinh doanh', 'worked' => 19, 'leave' => 1, 'absent' => 1, 'late' => 4, 'overtime' => 3.5],
    ['id' => 'NV008', 'name' => 'Hoàng Gia Huy', 'department' => 'Công nghệ thông tin', 'position' => 'Lập trình viên', 'worked' => 19, 'leave' => 0, 'absent' => 2, 'late' => 2, 'overtime' => 10],
    ['id' => 'NV009', 'name' => 'Đỗ Khánh Linh', 'department' => 'Marketing', 'position' => 'Thiết kế đồ họa', 'worked' => 22, 'leave' => 0, 'absent' => 0, 'late' => 0, 'overtime' => 5],
    ['id' => 'NV010', 'name' => 'Ngô Thành Nam', 'department' => 'Kế toán', 'position' => 'Kế toán tổng hợp', 'worked' => 21, 'leave' => 0, 'absent' => 0, 'late' => 1, 'overtime' => 7.5],
];

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusFor = static function (array $employee): array {
    if ($employee['absent'] >= 2) {
        return ['Vắng mặt', 'absent'];
    }
    if ($employee['leave'] >= 2 && $employee['worked'] < 21) {
        return ['Nghỉ phép', 'leave'];
    }
    if ($employee['worked'] >= 21 && $employee['absent'] === 0) {
        return ['Đủ công', 'complete'];
    }
    return ['Thiếu công', 'incomplete'];
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f7fb">
    <title>Bảng tổng hợp công nhân viên | HRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/hrm.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <aside class="sidebar" id="sidebar" aria-label="Điều hướng chính">
        <a class="brand" href="#overview" aria-label="HRM - trang chủ">
            <span class="brand-mark"><i class="bi bi-buildings-fill" aria-hidden="true"></i></span>
            <span><strong>HRM</strong><small>PEOPLE MANAGEMENT</small></span>
        </a>

        <p class="nav-caption">KHÔNG GIAN LÀM VIỆC</p>
        <nav class="nav flex-column sidebar-nav">
            <a class="nav-link" href="#overview"><i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Tổng quan</span></a>
            <a class="nav-link" href="#employee-table"><i class="bi bi-people" aria-hidden="true"></i><span>Quản lý nhân viên</span></a>
            <a class="nav-link" href="#employee-table"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>Quản lý chấm công</span></a>
            <a class="nav-link active" href="#employee-table" aria-current="page"><i class="bi bi-clipboard-data" aria-hidden="true"></i><span>Tổng hợp công</span><span class="active-marker"></span></a>
            <a class="nav-link" href="#employee-table"><i class="bi bi-calendar2-minus" aria-hidden="true"></i><span>Quản lý nghỉ phép</span></a>
            <a class="nav-link" href="#summary-cards"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Báo cáo thống kê</span></a>
        </nav>

        <p class="nav-caption nav-caption-settings">HỆ THỐNG</p>
        <nav class="nav flex-column sidebar-nav">
            <a class="nav-link" href="#filters"><i class="bi bi-gear" aria-hidden="true"></i><span>Cài đặt</span></a>
            <a class="nav-link" href="?action=/"><i class="bi bi-person-workspace" aria-hidden="true"></i><span>Trang nhân viên</span></a>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-help">
                <span class="help-icon"><i class="bi bi-headset" aria-hidden="true"></i></span>
                <strong>Bạn cần hỗ trợ?</strong>
                <span>Liên hệ bộ phận quản trị hệ thống.</span>
                <a href="mailto:hr@example.com">Trung tâm trợ giúp <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="sidebar-profile">
                <span class="avatar avatar-admin">LA</span>
                <span class="profile-copy"><strong>Lan Anh</strong><small>Quản trị viên</small></span>
                <button class="profile-more" type="button" aria-label="Tùy chọn tài khoản"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu-button" type="button" id="sidebar-toggle" aria-label="Mở menu" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="topbar-title">
                    <strong>Bảng tổng hợp công</strong>
                    <div class="breadcrumbs" aria-label="Breadcrumb">
                        <a href="#overview">Trang chủ</a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <a href="#employee-table">Chấm công</a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <span>Tổng hợp công</span>
                    </div>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="icon-button notification-button" type="button" id="notification-button" aria-label="Thông báo">
                    <i class="bi bi-bell" aria-hidden="true"></i><span class="notification-indicator"></span>
                </button>
                <span class="topbar-divider"></span>
                <button class="user-menu" type="button" aria-label="Tài khoản Lan Anh">
                    <span class="avatar avatar-admin">LA</span>
                    <span class="user-copy"><strong>Lan Anh</strong><small>Quản trị viên</small></span>
                    <i class="bi bi-chevron-down user-chevron" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <div class="page-container">
            <section class="page-intro" id="overview">
                <div>
                    <div class="page-kicker"><span></span> NHÂN SỰ &amp; CHẤM CÔNG</div>
                    <h1>Bảng tổng hợp công nhân viên</h1>
                    <p>Theo dõi ngày công và tình hình làm việc của nhân viên theo tháng.</p>
                </div>
                <div class="page-actions">
                    <div class="period-control" aria-label="Chọn kỳ chấm công">
                        <button class="period-arrow" id="previous-month" type="button" aria-label="Tháng trước"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <label class="period-field">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <span class="visually-hidden">Tháng</span>
                            <select id="month-select" aria-label="Chọn tháng">
                                <?php for ($monthOption = 1; $monthOption <= 12; $monthOption++): ?>
                                    <option value="<?= $monthOption ?>" <?= $monthOption === $month ? 'selected' : '' ?>>Tháng <?= $monthOption ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="period-separator">/</span>
                            <span class="visually-hidden">Năm</span>
                            <select id="year-select" aria-label="Chọn năm">
                                <?php for ($yearOption = max(2024, $year - 2); $yearOption <= $year + 2; $yearOption++): ?>
                                    <option value="<?= $yearOption ?>" <?= $yearOption === $year ? 'selected' : '' ?>><?= $yearOption ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <button class="period-arrow" id="next-month" type="button" aria-label="Tháng tiếp theo"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                    <button class="button button-outline" id="print-report" type="button"><i class="bi bi-printer" aria-hidden="true"></i><span>In báo cáo</span></button>
                    <button class="button button-primary" id="export-report" type="button"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i><span>Xuất Excel</span></button>
                </div>
            </section>

            <section class="summary-grid" id="summary-cards" aria-label="Thống kê chấm công">
                <article class="summary-card">
                    <div class="summary-heading"><span class="summary-icon icon-blue"><i class="bi bi-people-fill" aria-hidden="true"></i></span><span class="summary-trend"><i class="bi bi-arrow-up-right" aria-hidden="true"></i> Nhân sự</span></div>
                    <p class="summary-label">Tổng số nhân viên</p>
                    <div class="summary-value" id="stat-employees">10 <span>người</span></div>
                    <div class="summary-foot">Đang được theo dõi trong kỳ</div>
                </article>
                <article class="summary-card">
                    <div class="summary-heading"><span class="summary-icon icon-green"><i class="bi bi-calendar2-check-fill" aria-hidden="true"></i></span><span class="summary-trend trend-green"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Trong kỳ</span></div>
                    <p class="summary-label">Tổng ngày công</p>
                    <div class="summary-value" id="stat-workdays">0 <span>ngày</span></div>
                    <div class="summary-foot">Ngày công thực tế toàn công ty</div>
                </article>
                <article class="summary-card">
                    <div class="summary-heading"><span class="summary-icon icon-orange"><i class="bi bi-clock-history" aria-hidden="true"></i></span><span class="summary-trend trend-orange"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Cần lưu ý</span></div>
                    <p class="summary-label">Số lượt đi muộn</p>
                    <div class="summary-value" id="stat-late">0 <span>lượt</span></div>
                    <div class="summary-foot">Tổng lượt đi muộn trong tháng</div>
                </article>
                <article class="summary-card">
                    <div class="summary-heading"><span class="summary-icon icon-purple"><i class="bi bi-calendar2-heart-fill" aria-hidden="true"></i></span><span class="summary-trend trend-purple"><i class="bi bi-info-circle" aria-hidden="true"></i> Đã ghi nhận</span></div>
                    <p class="summary-label">Số ngày nghỉ phép</p>
                    <div class="summary-value" id="stat-leave">0 <span>ngày</span></div>
                    <div class="summary-foot">Nghỉ phép có kế hoạch</div>
                </article>
            </section>

            <section class="data-card" id="employee-table" aria-labelledby="table-title">
                <div class="data-card-heading">
                    <div>
                        <div class="section-title-row"><h2 id="table-title">Chi tiết chấm công</h2><span class="live-pill"><span></span> Dữ liệu cập nhật</span></div>
                        <p id="period-caption">Tổng hợp công nhân viên trong kỳ đã chọn</p>
                    </div>
                    <div class="working-day-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Ngày công chuẩn: <strong id="standard-workdays">22 ngày</strong></div>
                </div>

                <div class="filter-panel" id="filters">
                    <label class="search-control">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="employee-search" type="search" placeholder="Tìm mã nhân viên hoặc họ tên..." autocomplete="off" aria-label="Tìm theo mã nhân viên hoặc họ tên">
                        <kbd>Ctrl K</kbd>
                    </label>
                    <label class="filter-select">
                        <span class="visually-hidden">Phòng ban</span>
                        <i class="bi bi-diagram-3" aria-hidden="true"></i>
                        <select id="department-filter" aria-label="Lọc theo phòng ban"><option value="">Tất cả phòng ban</option></select>
                    </label>
                    <label class="filter-select">
                        <span class="visually-hidden">Chức vụ</span>
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <select id="position-filter" aria-label="Lọc theo chức vụ"><option value="">Tất cả chức vụ</option></select>
                    </label>
                    <label class="filter-select status-select">
                        <span class="visually-hidden">Trạng thái công</span>
                        <i class="bi bi-funnel" aria-hidden="true"></i>
                        <select id="status-filter" aria-label="Lọc theo trạng thái công">
                            <option value="">Tất cả trạng thái</option>
                            <option value="complete">Đủ công</option>
                            <option value="incomplete">Thiếu công</option>
                            <option value="leave">Nghỉ phép</option>
                            <option value="absent">Vắng mặt</option>
                        </select>
                    </label>
                    <div class="filter-buttons">
                        <button class="button button-primary filter-submit" id="apply-filters" type="button"><i class="bi bi-funnel-fill" aria-hidden="true"></i> Lọc</button>
                        <button class="button button-light" id="reset-filters" type="button"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="reset-label">Làm mới</span></button>
                    </div>
                </div>

                <div class="table-toolbar">
                    <div><span class="table-toolbar-mark"><i class="bi bi-list-check" aria-hidden="true"></i></span><strong>Danh sách nhân viên</strong><span class="record-badge" id="toolbar-count">10</span></div>
                    <span class="table-toolbar-note"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Vuốt hoặc kéo ngang để xem thêm cột</span>
                </div>

                <div class="table-responsive attendance-table-wrap">
                    <table class="table attendance-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="col-index">STT</th>
                                <th scope="col">Mã nhân viên</th>
                                <th scope="col" class="col-name">Họ và tên</th>
                                <th scope="col">Phòng ban</th>
                                <th scope="col">Chức vụ</th>
                                <th scope="col" class="text-center">Ngày công<br>quy định</th>
                                <th scope="col" class="text-center">Ngày công<br>thực tế</th>
                                <th scope="col" class="text-center">Nghỉ phép</th>
                                <th scope="col" class="text-center">Nghỉ không<br>phép</th>
                                <th scope="col" class="text-center">Số lần<br>đi muộn</th>
                                <th scope="col" class="text-center">Tổng giờ<br>tăng ca</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col" class="text-center col-actions">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="employee-rows">
                            <?php foreach ($employees as $index => $employee): ?>
                                <?php [$statusLabel, $statusClass] = $statusFor($employee); ?>
                                <?php $initial = preg_match('/^./u', $employee['name'], $initialMatch) === 1 ? $initialMatch[0] : 'N'; ?>
                                <tr>
                                    <td class="row-index"><?= $index + 1 ?></td>
                                    <td><span class="employee-code"><?= $escape($employee['id']) ?></span></td>
                                    <td>
                                        <div class="employee-cell">
                                            <span class="avatar employee-avatar"><?= $escape($initial) ?></span>
                                            <span class="employee-meta"><strong><?= $escape($employee['name']) ?></strong><small><?= $escape($employee['id']) ?></small></span>
                                        </div>
                                    </td>
                                    <td class="department-cell"><?= $escape($employee['department']) ?></td>
                                    <td class="position-cell"><?= $escape($employee['position']) ?></td>
                                    <td class="text-center">22</td>
                                    <td class="text-center"><strong class="actual-days"><?= $employee['worked'] ?></strong></td>
                                    <td class="text-center"><?= $employee['leave'] ?></td>
                                    <td class="text-center"><?= $employee['absent'] ?></td>
                                    <td class="text-center"><?= $employee['late'] ?></td>
                                    <td class="text-center"><?= number_format($employee['overtime'], 1) ?></td>
                                    <td><span class="status-badge status-<?= $statusClass ?>"><?= $escape($statusLabel) ?></span></td>
                                    <td class="text-center"><button class="view-button" type="button" aria-label="Xem chi tiết <?= $escape($employee['name']) ?>"><i class="bi bi-eye" aria-hidden="true"></i><span>Xem</span></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot id="employee-totals"></tfoot>
                    </table>
                    <div class="empty-state" id="empty-state" hidden>
                        <span class="empty-icon"><i class="bi bi-person-exclamation" aria-hidden="true"></i></span>
                        <strong>Không tìm thấy nhân viên</strong>
                        <span>Thử thay đổi từ khóa hoặc điều kiện lọc.</span>
                    </div>
                </div>

                <footer class="table-footer">
                    <div class="table-footer-left">
                        <span id="record-range">Hiển thị 1–10 trong 10 nhân viên</span>
                        <label class="page-size-control">Hiển thị
                            <select id="page-size" aria-label="Số dòng mỗi trang">
                                <option value="8">8</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                            </select>
                            dòng
                        </label>
                    </div>
                    <nav class="pagination-nav" id="pagination" aria-label="Phân trang"></nav>
                </footer>
            </section>

            <footer class="page-footer">
                <span>© <?= date('Y') ?> <strong>HRM</strong> · Hệ thống quản lý nhân sự</span>
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Dữ liệu mẫu dùng để minh họa giao diện</span>
            </footer>
        </div>
    </main>
</div>

<div class="modal fade" id="employee-modal" tabindex="-1" aria-labelledby="employee-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content detail-modal">
            <div class="modal-header detail-modal-header">
                <div>
                    <span class="modal-kicker">HỒ SƠ CHẤM CÔNG</span>
                    <h2 class="modal-title" id="employee-modal-title">Chi tiết nhân viên</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body" id="employee-modal-body"></div>
            <div class="modal-footer detail-modal-footer">
                <span><i class="bi bi-info-circle" aria-hidden="true"></i> Dữ liệu minh họa · <span id="modal-period"></span></span>
                <button type="button" class="button button-primary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" aria-live="polite" aria-atomic="true"></div>

<script id="attendance-data" type="application/json"><?= json_encode($employees, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
    window.hrmAttendanceConfig = {
        month: <?= $month ?>,
        year: <?= $year ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/attendance.js" defer></script>
</body>
</html>
