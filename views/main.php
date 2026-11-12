<?php
$title = 'Lịch ca làm việc | LumiCine';
$shifts = [
    [
        'name' => 'Nguyễn Minh Anh',
        'initials' => 'MA',
        'role' => 'Quản lý ca',
        'department' => 'Vận hành',
        'time' => '08:00 - 16:00',
        'hours' => '8 giờ',
        'location' => 'Sảnh chính',
        'status' => 'Đang diễn ra',
        'tone' => 'violet',
    ],
    [
        'name' => 'Trần Quốc Bảo',
        'initials' => 'QB',
        'role' => 'Nhân viên bán vé',
        'department' => 'Quầy vé',
        'time' => '09:00 - 17:00',
        'hours' => '8 giờ',
        'location' => 'Quầy vé 01',
        'status' => 'Đang diễn ra',
        'tone' => 'blue',
    ],
    [
        'name' => 'Lê Hoàng Yến',
        'initials' => 'HY',
        'role' => 'Nhân viên soát vé',
        'department' => 'Soát vé',
        'time' => '12:00 - 20:00',
        'hours' => '8 giờ',
        'location' => 'Cụm phòng 1–4',
        'status' => 'Sắp bắt đầu',
        'tone' => 'pink',
    ],
    [
        'name' => 'Phạm Đức Long',
        'initials' => 'ĐL',
        'role' => 'Nhân viên quầy bắp nước',
        'department' => 'F&B',
        'time' => '13:00 - 21:00',
        'hours' => '8 giờ',
        'location' => 'Quầy bắp nước',
        'status' => 'Sắp bắt đầu',
        'tone' => 'orange',
    ],
    [
        'name' => 'Võ Ngọc Mai',
        'initials' => 'NM',
        'role' => 'Nhân viên vệ sinh',
        'department' => 'Vệ sinh',
        'time' => '07:00 - 15:00',
        'hours' => '8 giờ',
        'location' => 'Cụm phòng 5–8',
        'status' => 'Hoàn thành',
        'tone' => 'green',
    ],
    [
        'name' => 'Đặng Tuấn Kiệt',
        'initials' => 'TK',
        'role' => 'Kỹ thuật viên',
        'department' => 'Kỹ thuật',
        'time' => '15:00 - 23:00',
        'hours' => '8 giờ',
        'location' => 'Phòng chiếu',
        'status' => 'Sắp bắt đầu',
        'tone' => 'teal',
    ],
];

$initialDate = date('Y-m-d');
foreach ($shifts as $index => &$shift) {
    $shift['id'] = 'sample-' . ($index + 1);
}
unset($shift);

$activeCount = count(array_filter($shifts, fn ($shift) => $shift['status'] === 'Đang diễn ra'));
$upcomingCount = count(array_filter($shifts, fn ($shift) => $shift['status'] === 'Sắp bắt đầu'));
$completedCount = count(array_filter($shifts, fn ($shift) => $shift['status'] === 'Hoàn thành'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f7f7fb">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --ink: #202033;
            --muted: #89899b;
            --line: #eeedf3;
            --purple: #6d5ce8;
            --purple-dark: #5846d3;
            --canvas: #f7f7fb;
            --sidebar-width: 248px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--canvas);
            color: var(--ink);
            font-family: "DM Sans", sans-serif;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        button, input, select { font: inherit; }
        button { cursor: pointer; }
        .app { min-height: 100vh; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 3;
            display: flex;
            width: var(--sidebar-width);
            flex-direction: column;
            border-right: 1px solid #eeedf3;
            background: #fff;
            padding: 27px 17px 18px;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            margin: 0 8px 36px;
            color: var(--ink);
            font-family: Manrope, sans-serif;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.8px;
            text-decoration: none;
        }

        .brand-mark {
            display: grid;
            width: 37px;
            height: 37px;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(145deg, #8173f2, #5c4ad5);
            box-shadow: 0 6px 14px #6958df3b;
            color: #fff;
        }

        .brand-mark svg { width: 19px; height: 19px; }
        .nav-label { margin: 0 11px 11px; color: #aaa9b7; font-size: 10px; font-weight: 700; letter-spacing: 1.15px; }
        .nav-list { display: grid; gap: 5px; }
        .nav-link {
            display: flex;
            min-height: 43px;
            align-items: center;
            gap: 12px;
            border-radius: 9px;
            padding: 0 12px;
            color: #77778b;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background .18s ease, color .18s ease;
        }

        .nav-link:hover { background: #f7f6fc; color: var(--ink); }
        .nav-link.active { background: #f0eeff; color: var(--purple); }
        .nav-link svg { width: 18px; height: 18px; flex: 0 0 18px; stroke: currentColor; }
        .nav-spacer { height: 27px; }

        .sidebar-bottom { flex-shrink: 0; margin-top: auto; }
        .help-card {
            margin: 0 2px 19px;
            border: 1px solid #efedf7;
            border-radius: 12px;
            background: linear-gradient(150deg, #faf9ff, #f5f3ff);
            padding: 14px;
        }

        .help-icon { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 9px; background: #e9e5ff; color: var(--purple); }
        .help-icon svg { width: 16px; height: 16px; stroke: currentColor; }
        .help-card strong { display: block; margin-top: 11px; font-size: 12px; }
        .help-card p { margin: 5px 0 12px; color: var(--muted); font-size: 11px; line-height: 1.5; }
        .help-card a { color: var(--purple); font-size: 11px; font-weight: 700; text-decoration: none; }
        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
            border-top: 1px solid var(--line);
            padding: 16px 3px 0;
        }

        .profile-avatar { display: grid; width: 37px; height: 37px; place-items: center; border-radius: 50%; background: #ffeadb; color: #bd6a34; font-size: 12px; font-weight: 700; }
        .profile-info { min-width: 0; flex: 1; }
        .profile-info strong, .profile-info span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .profile-info strong { font-size: 12px; }
        .profile-info span { margin-top: 3px; color: var(--muted); font-size: 10px; }
        .profile-menu { border: 0; background: transparent; color: #9998a8; font-size: 19px; }

        .main { min-height: 100vh; margin-left: var(--sidebar-width); }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            height: 70px;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #eeedf3;
            background: #ffffffeb;
            padding: 0 42px;
            backdrop-filter: blur(12px);
        }

        .breadcrumbs { display: flex; align-items: center; gap: 9px; color: #aaa9b7; font-size: 12px; }
        .breadcrumbs strong { color: #55556b; font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .top-icon {
            position: relative;
            display: grid;
            width: 35px;
            height: 35px;
            place-items: center;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: #77778b;
        }

        .top-icon svg { width: 17px; height: 17px; stroke: currentColor; }
        .notification-dot { position: absolute; top: 7px; right: 7px; width: 6px; height: 6px; border: 1px solid #fff; border-radius: 50%; background: #f07e79; }
        .top-user { display: flex; align-items: center; gap: 9px; color: #55556b; font-size: 12px; font-weight: 600; }
        .top-user .profile-avatar { width: 31px; height: 31px; font-size: 10px; }

        .content { width: min(1240px, 100%); margin: 0 auto; padding: 37px 42px 54px; }
        .page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; }
        .eyebrow { margin-bottom: 9px; color: var(--purple); font-size: 10px; font-weight: 700; letter-spacing: 1.35px; text-transform: uppercase; }
        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 7px; font-family: Manrope, sans-serif; font-size: clamp(24px, 3vw, 30px); font-weight: 800; letter-spacing: -1.15px; }
        .subtitle { margin-bottom: 0; color: var(--muted); font-size: 13px; }
        .primary-button {
            display: inline-flex;
            min-height: 41px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 9px;
            background: var(--purple);
            padding: 0 15px;
            box-shadow: 0 5px 12px #6958df30;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: background .18s ease, transform .18s ease;
        }

        .primary-button:hover { transform: translateY(-1px); background: var(--purple-dark); }
        .primary-button svg { width: 16px; height: 16px; stroke: currentColor; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-top: 27px; }
        .summary-card {
            position: relative;
            min-height: 113px;
            overflow: hidden;
            border: 1px solid #eeedf3;
            border-radius: 12px;
            background: #fff;
            padding: 16px 17px;
        }

        .summary-card::after { position: absolute; top: -25px; right: -17px; width: 80px; height: 80px; border-radius: 50%; background: var(--soft); content: ""; opacity: .8; }
        .summary-top { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; }
        .summary-label { color: #77778b; font-size: 11px; font-weight: 600; }
        .summary-icon { display: grid; width: 29px; height: 29px; place-items: center; border-radius: 8px; background: var(--soft); color: var(--accent); }
        .summary-icon svg { width: 15px; height: 15px; stroke: currentColor; }
        .summary-value { position: relative; z-index: 1; display: flex; align-items: baseline; gap: 8px; margin-top: 11px; font-family: Manrope, sans-serif; font-size: 25px; font-weight: 800; letter-spacing: -.8px; }
        .summary-note { color: #a1a0af; font-family: "DM Sans", sans-serif; font-size: 10px; font-weight: 500; letter-spacing: 0; }
        .summary-note.positive { color: #38a886; }

        .schedule-card { margin-top: 21px; overflow: hidden; border: 1px solid #eeedf3; border-radius: 13px; background: #fff; }
        .schedule-heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 20px 21px 17px; }
        .schedule-heading h2 { margin: 0 0 5px; font-family: Manrope, sans-serif; font-size: 15px; font-weight: 800; letter-spacing: -.3px; }
        .schedule-heading p { margin: 0; color: var(--muted); font-size: 11px; }
        .date-control { display: flex; align-items: center; gap: 7px; }
        .date-control button, .date-picker {
            display: flex;
            height: 35px;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: #626276;
        }

        .date-control button { width: 34px; font-size: 17px; }
        .date-control button:hover { border-color: #d7d2fa; color: var(--purple); }
        .date-picker { gap: 7px; padding: 0 10px; font-size: 11px; font-weight: 600; }
        .date-picker svg { width: 15px; height: 15px; stroke: #89899b; }
        .date-picker input { width: 102px; border: 0; outline: 0; background: transparent; color: inherit; font-size: 11px; font-weight: 600; }

        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid #f3f2f6; padding: 13px 20px; }
        .filters { display: flex; align-items: center; gap: 7px; }
        .filter-button {
            min-height: 31px;
            border: 1px solid transparent;
            border-radius: 7px;
            background: transparent;
            padding: 0 10px;
            color: #858497;
            font-size: 10px;
            font-weight: 600;
        }

        .filter-button:hover { background: #f8f7fc; }
        .filter-button.active { border-color: #e5e1ff; background: #f3f1ff; color: var(--purple); }
        .filter-count { margin-left: 4px; color: #a1a0af; font-size: 9px; }
        .search-box { display: flex; width: min(240px, 35%); height: 34px; align-items: center; gap: 8px; border: 1px solid var(--line); border-radius: 8px; padding: 0 10px; color: #a1a0af; }
        .search-box svg { width: 15px; height: 15px; flex: 0 0 15px; stroke: currentColor; }
        .search-box input { width: 100%; min-width: 0; border: 0; outline: 0; color: var(--ink); font-size: 10px; }
        .search-box input::placeholder { color: #aaa9b7; }

        .table-wrap { overflow-x: auto; }
        .shift-table { width: 100%; border-collapse: collapse; text-align: left; }
        .shift-table th { height: 37px; background: #fafafd; padding: 0 15px; color: #9a99a8; font-size: 9px; font-weight: 700; letter-spacing: .45px; text-transform: uppercase; white-space: nowrap; }
        .shift-table th:first-child, .shift-table td:first-child { padding-left: 21px; }
        .shift-table th:last-child, .shift-table td:last-child { padding-right: 21px; }
        .shift-table td { height: 67px; border-top: 1px solid #f2f1f5; padding: 8px 15px; color: #5e5e73; font-size: 11px; white-space: nowrap; }
        .shift-table tbody tr { transition: background .15s ease; }
        .shift-table tbody tr:hover { background: #fcfbff; }
        .employee { display: flex; min-width: 180px; align-items: center; gap: 10px; }
        .employee-avatar { display: grid; width: 34px; height: 34px; flex: 0 0 34px; place-items: center; border-radius: 10px; font-size: 10px; font-weight: 700; }
        .employee-avatar.violet { background: #eeebff; color: #6a58d9; }
        .employee-avatar.blue { background: #e8f1ff; color: #4a80ce; }
        .employee-avatar.pink { background: #ffedf3; color: #cf6487; }
        .employee-avatar.orange { background: #fff0e5; color: #c97b43; }
        .employee-avatar.green { background: #e8f6ef; color: #42916b; }
        .employee-avatar.teal { background: #e5f6f5; color: #368b86; }
        .employee-name, .employee-role { display: block; }
        .employee-name { color: #303044; font-size: 11px; font-weight: 700; }
        .employee-role { margin-top: 3px; color: #a09faf; font-size: 9px; }
        .department { display: inline-flex; align-items: center; gap: 6px; color: #67677a; }
        .department-dot { width: 6px; height: 6px; border-radius: 50%; background: #aa9bf1; }
        .time-main { color: #45455b; font-weight: 700; }
        .time-hours { margin-top: 4px; color: #a09faf; font-size: 9px; }
        .location { color: #77778b; }
        .status { display: inline-flex; align-items: center; gap: 6px; border-radius: 20px; padding: 5px 8px; font-size: 9px; font-weight: 600; }
        .status::before { width: 5px; height: 5px; border-radius: 50%; background: currentColor; content: ""; }
        .status.active { background: #eaf8f1; color: #30946c; }
        .status.upcoming { background: #fff4e5; color: #bb7b26; }
        .status.completed { background: #f1f0f5; color: #89889a; }
        .row-menu { width: 27px; height: 27px; border: 0; border-radius: 7px; background: transparent; color: #aaa9b7; font-size: 18px; line-height: 1; }
        .row-menu:hover { background: #f3f1ff; color: var(--purple); }
        .shift-actions { position: relative; }
        .action-menu, .account-menu { position: absolute; z-index: 5; min-width: 154px; border: 1px solid var(--line); border-radius: 9px; background: #fff; padding: 5px; box-shadow: 0 12px 30px #29234420; }
        .action-menu { top: 31px; right: 0; }
        .action-menu button, .account-menu button { display: block; width: 100%; border: 0; border-radius: 6px; background: transparent; padding: 8px 9px; color: #5e5e73; text-align: left; font-size: 10px; }
        .action-menu button:hover, .account-menu button:hover { background: #f5f3ff; color: var(--purple); }
        .action-menu button.danger { color: #cb5555; }
        .action-menu button.danger:hover { background: #fff0ef; }
        .account-wrap { position: relative; }
        .account-menu { top: 34px; right: 0; }
        .empty-state { display: none; padding: 38px 16px; color: #8e8d9e; text-align: center; font-size: 12px; }
        .table-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid #f2f1f5; padding: 13px 21px; color: #9998a8; font-size: 10px; }
        .table-footer strong { color: #5f5e73; font-weight: 700; }
        .pagination { display: flex; align-items: center; gap: 5px; }
        .page-button { display: grid; width: 27px; height: 27px; place-items: center; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #8f8e9f; font-size: 10px; }
        .page-button.current { border-color: #e6e2ff; background: #f2f0ff; color: var(--purple); font-weight: 700; }
        .page-button:disabled { cursor: default; opacity: .45; }
        .shift-dialog { width: min(440px, calc(100% - 28px)); max-width: 440px; border: 1px solid var(--line); border-radius: 15px; padding: 0; box-shadow: 0 24px 80px #29234430; color: var(--ink); }
        .shift-dialog::backdrop { background: #25223875; backdrop-filter: blur(2px); }
        .dialog-content { padding: 23px; }
        .dialog-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
        .dialog-heading h2 { margin: 0 0 5px; font-family: Manrope, sans-serif; font-size: 18px; font-weight: 800; }
        .dialog-heading p { margin: 0; color: var(--muted); font-size: 11px; }
        .dialog-close { width: 29px; height: 29px; border: 0; border-radius: 8px; background: #f5f4f8; color: #77778b; font-size: 18px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 13px 11px; }
        .form-field { display: grid; gap: 6px; color: #626276; font-size: 10px; font-weight: 700; }
        .form-field.full { grid-column: 1 / -1; }
        .form-field input, .form-field select { width: 100%; height: 37px; border: 1px solid var(--line); border-radius: 8px; outline: 0; background: #fff; padding: 0 10px; color: var(--ink); font-size: 11px; }
        .form-field input:focus, .form-field select:focus { border-color: #a59bf2; box-shadow: 0 0 0 3px #6d5ce81a; }
        .dialog-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 21px; }
        .secondary-button { min-height: 37px; border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 0 12px; color: #77778b; font-size: 11px; font-weight: 600; }
        .toast-region { position: fixed; right: 22px; bottom: 20px; z-index: 10; display: grid; gap: 8px; width: min(360px, calc(100vw - 32px)); }
        .toast { border: 1px solid #e8e4fb; border-radius: 10px; background: #fff; padding: 12px 14px; box-shadow: 0 12px 34px #29234420; color: #55556b; font-size: 12px; line-height: 1.5; animation: toast-in .18s ease-out; }
        .toast.error { border-color: #f2d2cf; color: #a53d39; }
        @keyframes toast-in { from { transform: translateY(7px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 1100px) {
            :root { --sidebar-width: 218px; }
            .topbar { padding-right: 27px; padding-left: 27px; }
            .content { padding-right: 27px; padding-left: 27px; }
            .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .shift-table th, .shift-table td { padding-right: 11px; padding-left: 11px; }
            .shift-table th:first-child, .shift-table td:first-child { padding-left: 17px; }
        }

        @media (max-width: 760px) {
            :root { --sidebar-width: 67px; }
            .sidebar { align-items: center; padding: 20px 9px 14px; }
            .brand { margin: 0 0 32px; }
            .brand-name, .nav-label, .nav-link span, .help-card, .profile-info, .profile-menu { display: none; }
            .nav-list { width: 100%; }
            .nav-link { justify-content: center; padding: 0; }
            .nav-link svg { width: 19px; height: 19px; }
            .profile { justify-content: center; width: 100%; padding: 14px 0 0; }
            .topbar { height: 59px; padding: 0 17px; }
            .topbar-right { gap: 9px; }
            .top-user > span { display: none; }
            .content { padding: 27px 17px 38px; }
            .page-heading { align-items: flex-start; }
            .page-heading .primary-button { min-height: 37px; padding: 0 10px; font-size: 10px; }
            .summary-grid { gap: 9px; margin-top: 20px; }
            .summary-card { min-height: 102px; padding: 13px; }
            .summary-value { font-size: 22px; }
            .schedule-heading { align-items: flex-start; padding: 17px 15px 14px; }
            .date-control { gap: 4px; }
            .date-control button { width: 28px; height: 32px; }
            .date-picker { height: 32px; padding: 0 6px; }
            .date-picker input { width: 89px; font-size: 10px; }
            .toolbar { align-items: flex-start; flex-direction: column; padding: 11px 14px; }
            .filters { max-width: 100%; overflow-x: auto; }
            .filter-button { flex: 0 0 auto; padding: 0 8px; }
            .search-box { width: 100%; }
            .shift-table { min-width: 750px; }
            .table-footer { padding-right: 14px; padding-left: 14px; }
        }

        @media (max-width: 420px) {
            .breadcrumbs { gap: 5px; font-size: 10px; }
            .topbar { padding: 0 11px; }
            .topbar-right { gap: 7px; }
            .top-icon { width: 31px; height: 31px; }
            .content { padding-right: 12px; padding-left: 12px; }
            .page-heading { gap: 8px; }
            .eyebrow { font-size: 9px; }
            h1 { font-size: 22px; }
            .subtitle { max-width: 210px; font-size: 11px; line-height: 1.5; }
            .page-heading .primary-button { gap: 4px; padding: 0 8px; }
            .summary-card { padding: 11px; }
            .summary-label { font-size: 10px; }
            .summary-note { font-size: 9px; }
            .schedule-heading { flex-direction: column; }
            .date-control { align-self: flex-end; }
        }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="Điều hướng chính">
        <a class="brand" href="#overview" aria-label="LumiCine - trang chủ">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7.5h16v12H4z"/><path d="m8 7.5 2-4m4 4 2-4M7 12l4 2.5L7 17z"/></svg>
            </span>
            <span class="brand-name">LumiCine</span>
        </a>

        <p class="nav-label">TỔNG QUAN</p>
        <nav class="nav-list">
            <a class="nav-link" href="#overview" data-nav="overview"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="8" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="15" width="7" height="6" rx="1.5"/></svg><span>Bảng điều khiển</span></a>
            <a class="nav-link" href="#schedule-title" data-nav="calendar"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg><span>Lịch làm việc</span></a>
            <a class="nav-link active" href="#shift-list" data-nav="shifts" aria-current="page"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Ca làm việc</span></a>
            <a class="nav-link" href="#shift-list" data-nav="attendance"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h2m4 0h2m-8 4h2"/></svg><span>Chấm công</span></a>
        </nav>

        <div class="nav-spacer"></div>
        <p class="nav-label">QUẢN LÝ</p>
        <nav class="nav-list">
            <a class="nav-link" href="#shift-list" data-nav="employees"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M3 20v-1a6 6 0 0 1 12 0v1zm13-9a3 3 0 1 0-1-5.8M18 14a5 5 0 0 1 3 4.6V20h-3"/></svg><span>Nhân viên</span></a>
            <a class="nav-link" href="#shift-list" data-nav="reports"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5m0 14h17"/><path d="m7 15 4-4 3 2 6-7"/></svg><span>Báo cáo</span></a>
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card">
                <span class="help-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.3 1.7c-1.1 1-1.9 1.4-1.9 3M12 17.5v.1"/></svg></span>
                <strong>Cần hỗ trợ?</strong>
                <p>Đội ngũ LumiCine luôn sẵn sàng giúp bạn.</p>
                <a href="mailto:support@lumicine.vn">Liên hệ hỗ trợ →</a>
            </div>
            <div class="profile">
                <div class="profile-avatar" aria-hidden="true">AT</div>
                <div class="profile-info"><strong>Anh Tuấn</strong><span>Quản trị viên</span></div>
                <div class="account-wrap">
                    <button class="profile-menu" type="button" id="account-menu-button" aria-label="Mở tùy chọn tài khoản" aria-expanded="false">···</button>
                    <div class="account-menu" id="account-menu" hidden>
                        <button type="button" id="edit-account">Đổi tên hiển thị</button>
                        <button type="button" id="reset-sample-data">Khôi phục dữ liệu mẫu</button>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="breadcrumbs"><span>Quản lý nhân sự</span><span aria-hidden="true">/</span><strong>Ca làm việc</strong></div>
            <div class="topbar-right">
                <button class="top-icon" type="button" id="notifications-button" aria-label="Thông báo"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 13h4"/></svg><span class="notification-dot"></span></button>
                <div class="top-user"><div class="profile-avatar" aria-hidden="true">AT</div><span>Anh Tuấn</span></div>
            </div>
        </header>

        <section class="content">
            <div class="page-heading">
                <div>
                    <div class="eyebrow">Quản lý nhân sự</div>
                    <h1>Ca làm việc</h1>
                    <p class="subtitle">Theo dõi và sắp xếp lịch làm việc của đội ngũ hôm nay.</p>
                </div>
                <button class="primary-button" type="button" id="add-shift">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Tạo ca mới
                </button>
            </div>

            <div class="summary-grid" id="overview" aria-label="Tổng quan ca làm việc">
                <article class="summary-card" style="--soft:#efedff;--accent:#6d5ce8">
                    <div class="summary-top"><span class="summary-label">Tổng số ca hôm nay</span><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span></div>
                    <div class="summary-value"><span id="summary-total"><?= count($shifts) ?></span><span class="summary-note positive">ca làm việc</span></div>
                </article>
                <article class="summary-card" style="--soft:#eaf8f1;--accent:#30946c">
                    <div class="summary-top"><span class="summary-label">Đang diễn ra</span><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span></div>
                    <div class="summary-value"><span id="summary-active"><?= $activeCount ?></span><span class="summary-note">ca đang hoạt động</span></div>
                </article>
                <article class="summary-card" style="--soft:#fff4e5;--accent:#bb7b26">
                    <div class="summary-top"><span class="summary-label">Sắp bắt đầu</span><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span></div>
                    <div class="summary-value"><span id="summary-upcoming"><?= $upcomingCount ?></span><span class="summary-note">ca sắp tới</span></div>
                </article>
                <article class="summary-card" style="--soft:#f1f0f5;--accent:#89889a">
                    <div class="summary-top"><span class="summary-label">Đã hoàn thành</span><span class="summary-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span></div>
                    <div class="summary-value"><span id="summary-completed"><?= $completedCount ?></span><span class="summary-note">ca kết thúc</span></div>
                </article>
            </div>

            <section class="schedule-card" aria-labelledby="schedule-title">
                <div class="schedule-heading">
                    <div><h2 id="schedule-title">Danh sách ca làm</h2><p>Chi tiết lịch phân công nhân viên theo từng ca.</p></div>
                    <div class="date-control" aria-label="Chọn ngày">
                        <button type="button" id="previous-day" aria-label="Ngày trước">‹</button>
                        <label class="date-picker">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                            <input id="shift-date" type="date" value="<?= htmlspecialchars($initialDate, ENT_QUOTES, 'UTF-8') ?>" aria-label="Ngày làm việc">
                        </label>
                        <button type="button" id="next-day" aria-label="Ngày tiếp theo">›</button>
                    </div>
                </div>

                <div class="toolbar">
                    <div class="filters" role="group" aria-label="Lọc trạng thái ca">
                        <button class="filter-button active" type="button" data-filter="all">Tất cả <span class="filter-count" data-count="all"><?= count($shifts) ?></span></button>
                        <button class="filter-button" type="button" data-filter="active">Đang diễn ra <span class="filter-count" data-count="active"><?= $activeCount ?></span></button>
                        <button class="filter-button" type="button" data-filter="upcoming">Sắp bắt đầu <span class="filter-count" data-count="upcoming"><?= $upcomingCount ?></span></button>
                        <button class="filter-button" type="button" data-filter="completed">Hoàn thành <span class="filter-count" data-count="completed"><?= $completedCount ?></span></button>
                    </div>
                    <label class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="shift-search" type="search" placeholder="Tìm nhân viên, vị trí..." aria-label="Tìm theo tên nhân viên hoặc vị trí">
                    </label>
                </div>

                <div class="table-wrap">
                    <table class="shift-table">
                        <thead>
                            <tr><th>Nhân viên</th><th>Bộ phận</th><th>Thời gian</th><th>Khu vực</th><th>Trạng thái</th><th aria-label="Thao tác"></th></tr>
                        </thead>
                        <tbody id="shift-list">
                            <?php foreach ($shifts as $shift): ?>
                                <?php
                                $statusClass = match ($shift['status']) {
                                    'Đang diễn ra' => 'active',
                                    'Sắp bắt đầu' => 'upcoming',
                                    default => 'completed',
                                };
                                ?>
                                <tr data-status="<?= $statusClass ?>" data-shift-id="<?= htmlspecialchars($shift['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <td>
                                        <div class="employee">
                                            <span class="employee-avatar <?= htmlspecialchars($shift['tone'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($shift['initials'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span><span class="employee-name"><?= htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8') ?></span><span class="employee-role"><?= htmlspecialchars($shift['role'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                        </div>
                                    </td>
                                    <td><span class="department"><span class="department-dot"></span><?= htmlspecialchars($shift['department'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><div class="time-main"><?= htmlspecialchars($shift['time'], ENT_QUOTES, 'UTF-8') ?></div><div class="time-hours"><?= htmlspecialchars($shift['hours'], ENT_QUOTES, 'UTF-8') ?></div></td>
                                    <td class="location"><?= htmlspecialchars($shift['location'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="status <?= $statusClass ?>"><?= htmlspecialchars($shift['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><div class="shift-actions"><button class="row-menu" type="button" aria-label="Thao tác với ca của <?= htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">···</button></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="empty-state" id="empty-state">Không tìm thấy ca làm việc phù hợp.</div>
                </div>
                <footer class="table-footer">
                    <span>Hiển thị <strong id="range-start"><?= count($shifts) ? 1 : 0 ?></strong>–<strong id="range-end"><?= count($shifts) ?></strong> trong số <strong id="match-count"><?= count($shifts) ?></strong> ca (<strong id="all-count"><?= count($shifts) ?></strong> tổng)</span>
                    <div class="pagination" id="pagination" aria-label="Phân trang"></div>
                </footer>
            </section>
        </section>
    </main>
</div>
<dialog class="shift-dialog" id="shift-dialog" aria-labelledby="dialog-title">
    <form class="dialog-content" id="shift-form">
        <div class="dialog-heading">
            <div><h2 id="dialog-title">Tạo ca làm việc</h2><p>Thêm ca mới vào danh sách phân công.</p></div>
            <button class="dialog-close" type="button" id="close-dialog" aria-label="Đóng">×</button>
        </div>
        <div class="form-grid">
            <label class="form-field full">Tên nhân viên<input name="name" type="text" required maxlength="80" placeholder="Ví dụ: Nguyễn Minh Anh"></label>
            <label class="form-field">Bộ phận<input name="department" type="text" required maxlength="50" placeholder="Ví dụ: Quầy vé"></label>
            <label class="form-field">Vị trí<input name="role" type="text" required maxlength="60" placeholder="Ví dụ: Nhân viên bán vé"></label>
            <label class="form-field">Bắt đầu<input name="start" type="time" value="08:00" required></label>
            <label class="form-field">Kết thúc<input name="end" type="time" value="16:00" required></label>
            <label class="form-field full">Khu vực<input name="location" type="text" required maxlength="60" placeholder="Ví dụ: Quầy vé 01"></label>
            <label class="form-field full">Trạng thái<select name="status"><option value="upcoming">Sắp bắt đầu</option><option value="active">Đang diễn ra</option><option value="completed">Hoàn thành</option></select></label>
        </div>
        <div class="dialog-actions">
            <button class="secondary-button" type="button" id="cancel-dialog">Hủy</button>
            <button class="primary-button" type="submit">Thêm ca</button>
        </div>
    </form>
</dialog>
<dialog class="shift-dialog" id="account-dialog" aria-labelledby="account-dialog-title">
    <form class="dialog-content" id="account-form">
        <div class="dialog-heading">
            <div><h2 id="account-dialog-title">Thông tin tài khoản</h2><p>Tùy chỉnh tên hiển thị trên trang quản trị.</p></div>
            <button class="dialog-close" type="button" id="close-account-dialog" aria-label="Đóng">×</button>
        </div>
        <label class="form-field">Tên hiển thị<input id="account-name" name="name" type="text" required maxlength="60"></label>
        <div class="dialog-actions">
            <button class="secondary-button" type="button" id="cancel-account-dialog">Hủy</button>
            <button class="primary-button" type="submit">Lưu thay đổi</button>
        </div>
    </form>
</dialog>
<div class="toast-region" id="toast-region" role="status" aria-live="polite"></div>
<script>
    (() => {
        const initialDate = <?= json_encode($initialDate) ?>;
        const initialShifts = <?= json_encode($shifts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const storagePrefix = 'lumicine-shifts:';
        const accountStorageKey = 'lumicine-admin-name';
        const statusLabels = {
            active: 'Đang diễn ra',
            upcoming: 'Sắp bắt đầu',
            completed: 'Hoàn thành',
        };
        const legacyStatusMap = {
            'Đang diễn ra': 'active',
            'Sắp bắt đầu': 'upcoming',
            'Hoàn thành': 'completed',
        };
        const tones = ['violet', 'blue', 'pink', 'orange', 'green', 'teal'];
        const search = document.getElementById('shift-search');
        const shiftList = document.getElementById('shift-list');
        const filters = [...document.querySelectorAll('.filter-button')];
        const emptyState = document.getElementById('empty-state');
        const dateInput = document.getElementById('shift-date');
        const dialog = document.getElementById('shift-dialog');
        const form = document.getElementById('shift-form');
        const toastRegion = document.getElementById('toast-region');
        const pageSize = 6;
        let currentFilter = 'all';
        let currentPage = 1;
        let shifts = [];
        let editingId = null;

        const notify = (message, isError = false) => {
            const toast = document.createElement('div');
            toast.className = `toast${isError ? ' error' : ''}`;
            toast.textContent = message;
            toastRegion.append(toast);
            window.setTimeout(() => toast.remove(), 4200);
        };

        const readShifts = (date) => {
            const saved = localStorage.getItem(`${storagePrefix}${date}`);
            if (saved === null && date !== initialDate) return [];
            const parsed = saved === null ? structuredClone(initialShifts) : JSON.parse(saved);
            if (!Array.isArray(parsed)) {
                throw new Error('Dữ liệu ca làm việc đã lưu không đúng định dạng.');
            }
            return parsed.map(normalizeShift);
        };

        const persistShifts = (nextShifts) => {
            try {
                localStorage.setItem(`${storagePrefix}${dateInput.value}`, JSON.stringify(nextShifts));
                return true;
            } catch (error) {
                notify('Không thể lưu dữ liệu trên trình duyệt. Hãy kiểm tra dung lượng lưu trữ và thử lại.', true);
                return false;
            }
        };

        const getInitials = (name) => name.trim().split(/\s+/).slice(-2)
            .map((part) => part[0]).join('').toLocaleUpperCase('vi');

        const getDuration = (start, end) => {
            const [startHour, startMinute] = start.split(':').map(Number);
            const [endHour, endMinute] = end.split(':').map(Number);
            let minutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute);
            if (minutes <= 0) minutes += 24 * 60;
            return `${Math.floor(minutes / 60)} giờ${minutes % 60 ? ` ${minutes % 60} phút` : ''}`;
        };

        const normalizeShift = (shift) => {
            if (!shift || typeof shift.id !== 'string' || typeof shift.name !== 'string' ||
                typeof shift.department !== 'string' || typeof shift.role !== 'string' ||
                typeof shift.location !== 'string') {
                throw new Error('Dữ liệu ca làm việc đã lưu không đúng định dạng.');
            }
            const [legacyStart, legacyEnd] = (shift.time || '').split(' - ');
            const normalized = {
                ...shift,
                start: shift.start || legacyStart,
                end: shift.end || legacyEnd,
                status: legacyStatusMap[shift.status] || shift.status,
            };
            if (typeof normalized.start !== 'string' || typeof normalized.end !== 'string' || !statusLabels[normalized.status]) {
                throw new Error('Dữ liệu ca làm việc đã lưu không đúng định dạng.');
            }
            return normalized;
        };

        const makeElement = (tag, className, text) => {
            const element = document.createElement(tag);
            if (className) element.className = className;
            if (text !== undefined) element.textContent = text;
            return element;
        };

        const createRow = (shift, index) => {
            const row = document.createElement('tr');
            row.dataset.shiftId = shift.id;
            row.dataset.status = shift.status;

            const employeeCell = row.insertCell();
            const employee = makeElement('div', 'employee');
            employee.append(makeElement('span', `employee-avatar ${shift.tone || tones[index % tones.length]}`, shift.initials || getInitials(shift.name)));
            const identity = makeElement('span');
            identity.append(
                makeElement('span', 'employee-name', shift.name),
                makeElement('span', 'employee-role', shift.role)
            );
            employee.append(identity);
            employeeCell.append(employee);

            const departmentCell = row.insertCell();
            const department = makeElement('span', 'department');
            department.append(makeElement('span', 'department-dot'), document.createTextNode(shift.department));
            departmentCell.append(department);

            const timeCell = row.insertCell();
            timeCell.append(
                makeElement('div', 'time-main', `${shift.start} - ${shift.end}`),
                makeElement('div', 'time-hours', getDuration(shift.start, shift.end))
            );
            const locationCell = row.insertCell();
            locationCell.className = 'location';
            locationCell.textContent = shift.location;

            const statusCell = row.insertCell();
            statusCell.append(makeElement('span', `status ${shift.status}`, statusLabels[shift.status]));

            const actionCell = row.insertCell();
            const actionWrap = makeElement('div', 'shift-actions');
            const menuButton = makeElement('button', 'row-menu', '···');
            menuButton.type = 'button';
            menuButton.setAttribute('aria-label', `Thao tác với ca của ${shift.name}`);
            menuButton.setAttribute('aria-expanded', 'false');
            actionWrap.append(menuButton);
            actionCell.append(actionWrap);
            return row;
        };

        const renderPagination = (pageCount) => {
            const pagination = document.getElementById('pagination');
            pagination.replaceChildren();
            const addPageButton = (label, page, options = {}) => {
                const button = makeElement('button', `page-button${options.current ? ' current' : ''}`, label);
                button.type = 'button';
                button.disabled = Boolean(options.disabled);
                if (options.current) button.setAttribute('aria-current', 'page');
                if (options.label) button.setAttribute('aria-label', options.label);
                button.addEventListener('click', () => {
                    currentPage = page;
                    updateView();
                });
                pagination.append(button);
            };
            addPageButton('‹', Math.max(1, currentPage - 1), { disabled: currentPage === 1, label: 'Trang trước' });
            for (let page = 1; page <= pageCount; page += 1) {
                addPageButton(String(page), page, { current: page === currentPage, label: `Trang ${page}` });
            }
            addPageButton('›', Math.min(pageCount, currentPage + 1), { disabled: currentPage === pageCount, label: 'Trang tiếp theo' });
        };

        const updateView = () => {
            const query = search.value.trim().toLocaleLowerCase('vi');
            const matching = shifts.filter((shift) => {
                const matchesStatus = currentFilter === 'all' || shift.status === currentFilter;
                const searchText = `${shift.name} ${shift.role} ${shift.department} ${shift.location}`.toLocaleLowerCase('vi');
                return matchesStatus && searchText.includes(query);
            });
            const pageCount = Math.max(1, Math.ceil(matching.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const startIndex = (currentPage - 1) * pageSize;
            const visible = matching.slice(startIndex, startIndex + pageSize);
            shiftList.replaceChildren(...visible.map((shift) => createRow(shift, shifts.indexOf(shift))));
            const counts = { all: shifts.length, active: 0, upcoming: 0, completed: 0 };
            shifts.forEach((shift) => { counts[shift.status] += 1; });
            document.getElementById('summary-total').textContent = counts.all;
            document.getElementById('summary-active').textContent = counts.active;
            document.getElementById('summary-upcoming').textContent = counts.upcoming;
            document.getElementById('summary-completed').textContent = counts.completed;
            document.getElementById('all-count').textContent = counts.all;
            document.getElementById('match-count').textContent = matching.length;
            document.getElementById('range-start').textContent = matching.length ? startIndex + 1 : 0;
            document.getElementById('range-end').textContent = Math.min(startIndex + pageSize, matching.length);
            filters.forEach((button) => {
                button.querySelector('.filter-count').textContent = counts[button.dataset.filter];
                button.setAttribute('aria-pressed', String(button.dataset.filter === currentFilter));
            });
            emptyState.style.display = matching.length ? 'none' : 'block';
            renderPagination(pageCount);
        };

        const setCurrentFilter = (filter) => {
            currentFilter = filter;
            currentPage = 1;
            filters.forEach((button) => button.classList.toggle('active', button.dataset.filter === filter));
            updateView();
        };

        const closeRowMenus = () => {
            document.querySelectorAll('.action-menu').forEach((menu) => menu.remove());
            document.querySelectorAll('.row-menu[aria-expanded="true"]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };

        const openShiftDialog = (shift = null) => {
            editingId = shift ? shift.id : null;
            form.reset();
            document.getElementById('dialog-title').textContent = shift ? 'Chỉnh sửa ca làm việc' : 'Tạo ca làm việc';
            form.querySelector('[type="submit"]').textContent = shift ? 'Lưu thay đổi' : 'Thêm ca';
            if (shift) {
                Object.entries({
                    name: shift.name,
                    department: shift.department,
                    role: shift.role,
                    start: shift.start,
                    end: shift.end,
                    location: shift.location,
                    status: shift.status,
                }).forEach(([name, value]) => { form.elements[name].value = value; });
            }
            dialog.showModal();
            form.elements.name.focus();
        };

        const closeShiftDialog = () => {
            editingId = null;
            form.reset();
            dialog.close();
        };

        const loadSelectedDate = () => {
            try {
                shifts = readShifts(dateInput.value);
            } catch (error) {
                shifts = [];
                notify(error.message || 'Không thể đọc dữ liệu ca làm việc đã lưu.', true);
            }
            currentPage = 1;
            updateView();
            const selected = new Date(`${dateInput.value}T12:00:00`);
            if (!Number.isNaN(selected.getTime())) {
                const weekday = new Intl.DateTimeFormat('vi-VN', { weekday: 'long' }).format(selected);
                document.querySelector('.subtitle').textContent =
                    `Theo dõi và sắp xếp lịch làm việc của đội ngũ ${weekday}, ${selected.toLocaleDateString('vi-VN')}.`;
            }
        };

        const downloadReport = () => {
            const rows = [
                ['Nhân viên', 'Bộ phận', 'Vị trí', 'Bắt đầu', 'Kết thúc', 'Khu vực', 'Trạng thái'],
                ...shifts.map((shift) => [
                    shift.name, shift.department, shift.role, shift.start, shift.end, shift.location, statusLabels[shift.status],
                ]),
            ];
            const csv = '\uFEFF' + rows.map((row) =>
                row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(',')
            ).join('\r\n');
            const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = `lumicine-ca-lam-${dateInput.value}.csv`;
            document.body.append(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
            notify('Đã tải báo cáo ca làm việc dạng CSV.');
        };

        filters.forEach((button) => {
            button.addEventListener('click', () => setCurrentFilter(button.dataset.filter));
        });

        search.addEventListener('input', () => {
            currentPage = 1;
            updateView();
        });
        dateInput.addEventListener('change', loadSelectedDate);

        ['previous-day', 'next-day'].forEach((id) => {
            document.getElementById(id).addEventListener('click', () => {
                const selected = new Date(`${dateInput.value}T12:00:00`);
                selected.setDate(selected.getDate() + (id === 'next-day' ? 1 : -1));
                dateInput.value = [
                    selected.getFullYear(),
                    String(selected.getMonth() + 1).padStart(2, '0'),
                    String(selected.getDate()).padStart(2, '0'),
                ].join('-');
                loadSelectedDate();
            });
        });

        document.getElementById('add-shift').addEventListener('click', () => openShiftDialog());
        document.getElementById('close-dialog').addEventListener('click', closeShiftDialog);
        document.getElementById('cancel-dialog').addEventListener('click', closeShiftDialog);
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) closeShiftDialog();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const fields = new FormData(form);
            const status = fields.get('status');
            const name = fields.get('name').trim();
            const wasEditing = Boolean(editingId);
            const shift = {
                id: editingId || `shift-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                name,
                initials: getInitials(name),
                role: fields.get('role').trim(),
                department: fields.get('department').trim(),
                start: fields.get('start'),
                end: fields.get('end'),
                location: fields.get('location').trim(),
                status,
                tone: editingId ? shifts.find((item) => item.id === editingId)?.tone : tones[shifts.length % tones.length],
            };
            const nextShifts = editingId
                ? shifts.map((item) => item.id === editingId ? shift : item)
                : [...shifts, shift];
            if (!persistShifts(nextShifts)) return;
            shifts = nextShifts;
            closeShiftDialog();
            currentPage = 1;
            updateView();
            notify(wasEditing ? 'Đã cập nhật ca làm việc.' : 'Đã thêm ca làm việc.');
        });

        shiftList.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const row = button.closest('tr');
            const shift = shifts.find((item) => item.id === row?.dataset.shiftId);
            if (!shift) return;
            if (button.classList.contains('row-menu')) {
                const wasOpen = button.getAttribute('aria-expanded') === 'true';
                closeRowMenus();
                if (wasOpen) return;
                button.setAttribute('aria-expanded', 'true');
                const menu = makeElement('div', 'action-menu');
                menu.setAttribute('role', 'group');
                const addAction = (label, action, danger = false) => {
                    const actionButton = makeElement('button', danger ? 'danger' : '', label);
                    actionButton.type = 'button';
                    actionButton.dataset.action = action;
                    menu.append(actionButton);
                };
                addAction('Chỉnh sửa ca', 'edit');
                addAction('Đánh dấu đang diễn ra', 'active');
                addAction('Đánh dấu sắp bắt đầu', 'upcoming');
                addAction('Đánh dấu hoàn thành', 'completed');
                addAction('Xóa ca làm việc', 'delete', true);
                button.after(menu);
                return;
            }
            const action = button.dataset.action;
            if (action === 'edit') {
                closeRowMenus();
                openShiftDialog(shift);
            } else if (action === 'delete') {
                closeRowMenus();
                if (!window.confirm(`Bạn có chắc muốn xóa ca của ${shift.name}?`)) return;
                const nextShifts = shifts.filter((item) => item.id !== shift.id);
                if (!persistShifts(nextShifts)) return;
                shifts = nextShifts;
                updateView();
                notify('Đã xóa ca làm việc.');
            } else if (statusLabels[action]) {
                closeRowMenus();
                const nextShifts = shifts.map((item) => item.id === shift.id ? { ...item, status: action } : item);
                if (!persistShifts(nextShifts)) return;
                shifts = nextShifts;
                updateView();
                notify(`Đã chuyển ca sang trạng thái "${statusLabels[action]}".`);
            }
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.shift-actions')) closeRowMenus();
            if (!event.target.closest('.account-wrap')) {
                document.getElementById('account-menu').hidden = true;
                document.getElementById('account-menu-button').setAttribute('aria-expanded', 'false');
            }
        });

        document.getElementById('notifications-button').addEventListener('click', () => {
            const upcoming = shifts.filter((shift) => shift.status === 'upcoming').length;
            notify(upcoming ? `Hôm nay còn ${upcoming} ca sắp bắt đầu.` : 'Không có ca nào sắp bắt đầu.');
        });

        document.getElementById('account-menu-button').addEventListener('click', () => {
            const menu = document.getElementById('account-menu');
            menu.hidden = !menu.hidden;
            document.getElementById('account-menu-button').setAttribute('aria-expanded', String(!menu.hidden));
        });

        const accountDialog = document.getElementById('account-dialog');
        const accountForm = document.getElementById('account-form');
        const accountName = document.getElementById('account-name');
        const accountMenu = document.getElementById('account-menu');
        const profileNames = [...document.querySelectorAll('.profile-info strong, .top-user > span')];
        try {
            const savedAccountName = localStorage.getItem(accountStorageKey);
            if (savedAccountName) profileNames.forEach((element) => { element.textContent = savedAccountName; });
        } catch (error) {
            notify('Không thể đọc cài đặt tài khoản đã lưu trong trình duyệt.', true);
        }

        document.getElementById('edit-account').addEventListener('click', () => {
            accountMenu.hidden = true;
            accountName.value = profileNames[0].textContent;
            accountDialog.showModal();
        });
        document.getElementById('close-account-dialog').addEventListener('click', () => accountDialog.close());
        document.getElementById('cancel-account-dialog').addEventListener('click', () => accountDialog.close());
        accountForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const name = accountName.value.trim();
            if (!name) return;
            try {
                localStorage.setItem(accountStorageKey, name);
            } catch (error) {
                notify('Không thể lưu tên tài khoản trong trình duyệt.', true);
                return;
            }
            profileNames.forEach((element) => { element.textContent = name; });
            accountDialog.close();
            notify('Đã cập nhật tên hiển thị.');
        });

        document.getElementById('reset-sample-data').addEventListener('click', () => {
            accountMenu.hidden = true;
            if (!window.confirm('Khôi phục danh sách ca mẫu cho ngày hiện tại? Dữ liệu ca đã lưu của ngày này sẽ bị thay thế.')) return;
            const defaults = dateInput.value === initialDate ? initialShifts.map(normalizeShift) : [];
            if (!persistShifts(defaults)) return;
            shifts = defaults;
            search.value = '';
            setCurrentFilter('all');
            notify('Đã khôi phục danh sách ca mẫu.');
        });

        document.querySelectorAll('[data-nav]').forEach((link) => {
            link.addEventListener('click', (event) => {
                const section = link.dataset.nav;
                document.querySelectorAll('[data-nav]').forEach((item) => {
                    item.classList.toggle('active', item === link);
                    if (item === link) item.setAttribute('aria-current', 'page');
                    else item.removeAttribute('aria-current');
                });
                if (section === 'reports') {
                    event.preventDefault();
                    downloadReport();
                    return;
                }
                if (section === 'overview') {
                    setCurrentFilter('all');
                    document.getElementById('overview').scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else if (section === 'calendar') {
                    document.getElementById('schedule-title').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    dateInput.focus();
                    notify('Chọn ngày để xem hoặc lập lịch ca.');
                } else if (section === 'attendance') {
                    setCurrentFilter('active');
                    document.getElementById('schedule-title').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    notify('Đã lọc các ca đang diễn ra để đối chiếu chấm công.');
                } else if (section === 'employees') {
                    search.value = '';
                    setCurrentFilter('all');
                    document.getElementById('shift-list').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    search.focus({ preventScroll: true });
                    notify('Tìm nhân viên trong danh sách ca bằng ô tìm kiếm.');
                } else {
                    setCurrentFilter('all');
                    document.getElementById('shift-list').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });

        loadSelectedDate();
    })();
</script>
</body>
</html>
