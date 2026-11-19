(() => {
    'use strict';

    const storagePrefix = 'lumicine-attendance:';
    const dateInput = document.getElementById('attendance-date');
    const rowsElement = document.getElementById('attendance-history');
    const emptyState = document.getElementById('attendance-history-empty');
    const toastRegion = document.getElementById('toast-region');
    let attendanceRecord = { checkIn: '', checkOut: '' };
    let historyRecords = [];

    const notify = (message, isError = false) => {
        const toast = document.createElement('div');
        toast.className = `employee-toast${isError ? ' error' : ''}`;
        toast.setAttribute('role', 'status');
        const icon = document.createElement('i');
        icon.className = `bi ${isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'}`;
        icon.setAttribute('aria-hidden', 'true');
        toast.append(icon, document.createTextNode(message));
        toastRegion.append(toast);
        window.setTimeout(() => toast.remove(), 4000);
    };

    const validateRecord = (record) => {
        if (!record || typeof record.checkIn !== 'string' || typeof record.checkOut !== 'string' ||
            (record.checkOut && !record.checkIn) ||
            (record.lastPunchType !== undefined &&
                record.lastPunchType !== 'checkIn' && record.lastPunchType !== 'checkOut')) {
            throw new Error('Dữ liệu chấm công đã lưu không đúng định dạng.');
        }
        return record;
    };

    const readAttendance = (date) => {
        const saved = localStorage.getItem(`${storagePrefix}${date}`);
        return saved === null ? { checkIn: '', checkOut: '' } : validateRecord(JSON.parse(saved));
    };

    const readHistory = () => {
        const records = [];
        for (let index = 0; index < localStorage.length; index += 1) {
            const key = localStorage.key(index);
            if (!key || !key.startsWith(storagePrefix)) continue;
            const date = key.slice(storagePrefix.length);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                throw new Error('Ngày trong lịch sử chấm công không hợp lệ.');
            }
            const record = validateRecord(JSON.parse(localStorage.getItem(key)));
            if (record.checkIn || record.checkOut) records.push({ date, ...record });
        }
        return records.sort((first, second) => second.date.localeCompare(first.date));
    };

    const getCurrentTime = () => {
        const now = new Date();
        return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    };

    const getNextPunchType = (record) => {
        if (record.lastPunchType) return record.lastPunchType === 'checkIn' ? 'checkOut' : 'checkIn';
        return record.checkIn && !record.checkOut ? 'checkOut' : 'checkIn';
    };

    const formatMinutes = (minutes) => {
        if (minutes <= 0) return '0';
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return remainingMinutes ? `${hours} giờ ${remainingMinutes} phút` : `${hours}`;
    };

    const getWorkedMinutes = (record) => {
        if (!record.checkIn || !record.checkOut) return 0;
        const [inHour, inMinute] = record.checkIn.split(':').map(Number);
        const [outHour, outMinute] = record.checkOut.split(':').map(Number);
        return Math.max(0, outHour * 60 + outMinute - (inHour * 60 + inMinute));
    };

    const renderAttendance = () => {
        const { checkIn, checkOut } = attendanceRecord;
        const nextPunchType = getNextPunchType(attendanceRecord);
        const isWorking = Boolean(checkIn) && nextPunchType === 'checkOut';
        const isComplete = Boolean(checkOut) && !isWorking;
        const status = document.getElementById('attendance-status');
        const punchButton = document.getElementById('attendance-punch');
        const checkInState = document.getElementById('attendance-check-in-state');
        const checkOutState = document.getElementById('attendance-check-out-state');

        document.getElementById('attendance-check-in').textContent = checkIn || 'Chưa ghi nhận';
        document.getElementById('attendance-check-out').textContent = checkOut || 'Chưa ghi nhận';
        checkInState.textContent = checkIn ? 'Đã ghi nhận' : 'Chờ chấm công';
        checkOutState.textContent = checkOut ? 'Đã ghi nhận' : 'Chờ chấm công';
        checkInState.classList.toggle('is-recorded', Boolean(checkIn));
        checkOutState.classList.toggle('is-recorded', Boolean(checkOut));
        status.textContent = isComplete ? 'Đã hoàn thành' : isWorking ? 'Đang làm việc' : 'Chưa bắt đầu';
        status.classList.toggle('is-working', isWorking);
        status.classList.toggle('is-complete', isComplete);
        punchButton.disabled = false;
        punchButton.querySelector('span').textContent = nextPunchType === 'checkOut'
            ? 'Chấm công ra'
            : 'Chấm công vào';
        punchButton.querySelector('i').className = `bi ${nextPunchType === 'checkOut' ? 'bi-box-arrow-right' : 'bi-box-arrow-in-right'}`;
        document.getElementById('attendance-status-summary').textContent = status.textContent;
    };

    const addCell = (row, value, className = '') => {
        const cell = document.createElement('td');
        if (className) cell.className = className;
        cell.textContent = value;
        row.append(cell);
    };

    const renderHistory = () => {
        rowsElement.replaceChildren();
        const recentRecords = historyRecords.slice(0, 7);
        recentRecords.forEach((record) => {
            const row = document.createElement('tr');
            const date = new Date(`${record.date}T00:00:00`);
            addCell(row, new Intl.DateTimeFormat('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(date), 'attendance-history-date');
            addCell(row, record.checkIn || '—', 'attendance-history-time');
            addCell(row, record.checkOut || '—', 'attendance-history-time');
            addCell(row, record.checkOut ? formatMinutes(getWorkedMinutes(record)) : record.checkIn ? 'Đang làm việc' : '—');
            const statusCell = row.insertCell();
            const status = document.createElement('span');
            status.className = `attendance-history-status${record.checkOut ? ' complete' : record.checkIn ? ' working' : ''}`;
            status.textContent = record.checkOut ? 'Hoàn thành' : record.checkIn ? 'Đang làm' : 'Chưa chấm công';
            statusCell.append(status);
            rowsElement.append(row);
        });

        const hasRecords = recentRecords.length > 0;
        emptyState.hidden = hasRecords;
        rowsElement.closest('table').hidden = !hasRecords;
        document.getElementById('attendance-history-count').textContent = hasRecords
            ? `Hiển thị ${recentRecords.length} trong ${historyRecords.length} ngày đã ghi nhận`
            : 'Chưa có dữ liệu chấm công';

        const daysWithCheckIn = historyRecords.filter((record) => record.checkIn).length;
        const totalMinutes = historyRecords.reduce((sum, record) => sum + getWorkedMinutes(record), 0);
        document.getElementById('attendance-days-count').textContent = String(daysWithCheckIn);
        document.getElementById('attendance-hours-total').textContent = formatMinutes(totalMinutes);
    };

    const loadAttendance = () => {
        try {
            attendanceRecord = readAttendance(dateInput.value);
            historyRecords = readHistory();
            renderAttendance();
            renderHistory();
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể đọc dữ liệu chấm công.', true);
            attendanceRecord = { checkIn: '', checkOut: '' };
            historyRecords = [];
            renderAttendance();
            renderHistory();
        }
    };

    const updateClock = () => {
        const now = new Date();
        document.getElementById('attendance-live-clock').textContent =
            `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
        document.getElementById('attendance-live-date').textContent =
            new Intl.DateTimeFormat('vi-VN', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }).format(now);
    };

    const recordTime = () => {
        const previousRecord = { ...attendanceRecord };
        const now = getCurrentTime();
        const punchType = getNextPunchType(attendanceRecord);
        if (punchType === 'checkIn') {
            if (!attendanceRecord.checkIn || now < attendanceRecord.checkIn) {
                attendanceRecord.checkIn = now;
            }
        } else {
            if (!attendanceRecord.checkOut || now > attendanceRecord.checkOut) {
                attendanceRecord.checkOut = now;
            }
        }
        attendanceRecord.lastPunchType = punchType;

        try {
            localStorage.setItem(`${storagePrefix}${dateInput.value}`, JSON.stringify(attendanceRecord));
        } catch (error) {
            attendanceRecord = previousRecord;
            renderAttendance();
            notify('Không thể lưu dữ liệu chấm công trên trình duyệt. Hãy kiểm tra dung lượng lưu trữ và thử lại.', true);
            return;
        }

        loadAttendance();
        notify(punchType === 'checkOut'
            ? `Đã ghi nhận chấm công ra. Giờ ra cuối cùng: ${attendanceRecord.checkOut}.`
            : `Đã ghi nhận chấm công vào. Giờ vào đầu tiên: ${attendanceRecord.checkIn}.`);
    };

    dateInput.addEventListener('change', loadAttendance);
    document.getElementById('attendance-punch').addEventListener('click', recordTime);
    document.getElementById('notifications-button').addEventListener('click', () => {
        notify('Dữ liệu chấm công của bạn được lưu trên trình duyệt này.');
    });
    document.addEventListener('click', (event) => {
        const menu = document.getElementById('account-menu');
        const button = document.getElementById('account-menu-button');
        if (!event.target.closest('.topbar-account')) {
            menu.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        }
    });
    document.getElementById('account-menu-button').addEventListener('click', () => {
        const menu = document.getElementById('account-menu');
        menu.hidden = !menu.hidden;
        document.getElementById('account-menu-button').setAttribute('aria-expanded', String(!menu.hidden));
    });

    const sidebar = document.getElementById('employee-sidebar');
    const scrim = document.getElementById('sidebar-scrim');
    const toggleSidebar = () => {
        sidebar.classList.toggle('is-open');
        scrim.classList.toggle('is-visible');
    };
    document.getElementById('mobile-menu-button').addEventListener('click', toggleSidebar);
    scrim.addEventListener('click', toggleSidebar);
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 740 && sidebar.classList.contains('is-open')) toggleSidebar();
        });
    });

    updateClock();
    window.setInterval(updateClock, 1000);
    loadAttendance();
})();
