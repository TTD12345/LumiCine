(() => {
    'use strict';

    const dateInput = document.getElementById('attendance-date');
    const rowsElement = document.getElementById('attendance-history');
    const emptyState = document.getElementById('attendance-history-empty');
    const toastRegion = document.getElementById('toast-region');
    let attendanceRecord = {
        check_in: '',
        check_out: '',
        late_minutes: 0,
        early_minutes: 0,
        has_assignment: false,
    };
    let historyRecords = [];
    let isLoading = false;
    let attendanceLoadError = false;

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

    const getCurrentTime = () => {
        const now = new Date();
        return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    };

    const formatMinutes = (minutes) => {
        if (minutes <= 0) return '0';
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return remainingMinutes ? `${hours} giờ ${remainingMinutes} phút` : `${hours}`;
    };

    const getWorkedMinutes = (record) => {
        if (Number.isFinite(Number(record.worked_minutes))) {
            return Number(record.worked_minutes);
        }
        if (!record.check_in || !record.check_out) return 0;
        const [inHour, inMinute] = record.check_in.split(':').map(Number);
        const [outHour, outMinute] = record.check_out.split(':').map(Number);
        return Math.max(0, outHour * 60 + outMinute - (inHour * 60 + inMinute));
    };

    const renderAttendance = () => {
        const { check_in: checkIn, check_out: checkOut } = attendanceRecord;
        const shiftName = document.getElementById('attendance-shift-name');
        const shiftTime = document.getElementById('attendance-shift-time');
        const shiftLocation = document.getElementById('attendance-shift-location');
        if (shiftName) shiftName.textContent = attendanceRecord.shift_name || 'Ca làm việc';
        if (shiftTime) {
            shiftTime.textContent = attendanceRecord.shift_start && attendanceRecord.shift_end
                ? `${attendanceRecord.shift_start} — ${attendanceRecord.shift_end}`
                : 'Chưa phân ca';
        }
        if (shiftLocation) {
            shiftLocation.textContent = attendanceRecord.location || 'Chưa có địa điểm';
        }
        const status = document.getElementById('attendance-status');
        const punchButton = document.getElementById('attendance-punch');
        const checkInState = document.getElementById('attendance-check-in-state');
        const checkOutState = document.getElementById('attendance-check-out-state');
        const isWorking = Boolean(checkIn) && !checkOut;
        const isComplete = Boolean(checkOut);
        const now = new Date();
        const currentDate = [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0'),
        ].join('-');
        const isCurrentDate = dateInput.value === currentDate;
        const nextType = isWorking ? 'check_out' : 'check_in';
        const isAbsent = attendanceRecord.attendance_status === 'VANG_MAT';
        const shiftEnd = attendanceRecord.shift_end
            ? new Date(`${dateInput.value}T${attendanceRecord.shift_end}:00`)
            : null;
        if (shiftEnd && attendanceRecord.shift_start && attendanceRecord.shift_end <= attendanceRecord.shift_start) {
            shiftEnd.setDate(shiftEnd.getDate() + 1);
        }
        const shiftEndPassed = Boolean(shiftEnd) && now >= shiftEnd;
        const missingPunch = attendanceRecord.has_assignment
            && !checkIn
            && (dateInput.value < currentDate || shiftEndPassed);

        document.getElementById('attendance-check-in').textContent = checkIn || 'Chưa ghi nhận';
        document.getElementById('attendance-check-out').textContent = checkOut || 'Chưa ghi nhận';
        const lateMinutes = Number(attendanceRecord.late_minutes) || 0;
        const earlyMinutes = Number(attendanceRecord.early_minutes) || 0;
        checkInState.textContent = checkIn
            ? `Đã ghi nhận${lateMinutes > 0 ? ` · Muộn ${lateMinutes} phút` : ''}`
            : 'Chờ chấm công';
        checkOutState.textContent = checkOut
            ? `Đã ghi nhận${earlyMinutes > 0 ? ` · Về sớm ${earlyMinutes} phút` : ''}`
            : 'Chờ chấm công';
        checkInState.classList.toggle('is-recorded', Boolean(checkIn));
        checkOutState.classList.toggle('is-recorded', Boolean(checkOut));
        status.textContent = attendanceLoadError
            ? 'Lỗi tải dữ liệu'
            : !attendanceRecord.has_assignment ? 'Chưa phân ca'
            : isAbsent ? 'Nghỉ không phép'
                : missingPunch ? 'Thiếu chấm công'
                : lateMinutes > 0 ? 'Đi muộn'
                    : earlyMinutes > 0 ? 'Về sớm'
                        : isComplete ? 'Đã hoàn thành' : isWorking ? 'Đang làm việc' : 'Chưa bắt đầu';
        status.classList.toggle('is-working', isWorking);
        status.classList.toggle('is-complete', isComplete);
        punchButton.disabled = isLoading || !attendanceRecord.has_assignment || !isCurrentDate || isComplete || isAbsent;
        punchButton.querySelector('span').textContent = !attendanceRecord.has_assignment
            ? 'Chưa được phân ca'
            : nextType === 'check_out'
                ? 'Chấm công ra'
                : 'Chấm công vào';
        punchButton.querySelector('i').className =
            `bi ${nextType === 'check_out' ? 'bi-box-arrow-right' : 'bi-box-arrow-in-right'}`;
        document.getElementById('attendance-status-summary').textContent = attendanceLoadError
            ? 'Không tải được'
            : !attendanceRecord.has_assignment ? 'Chưa được phân ca' : status.textContent;
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
            addCell(row, record.check_in || '—', 'attendance-history-time');
            addCell(row, record.check_out || '—', 'attendance-history-time');
            addCell(row, record.check_out ? formatMinutes(getWorkedMinutes(record)) : record.check_in ? 'Đang làm việc' : '—');
            addCell(row, Number(record.late_minutes) > 0 ? `${Number(record.late_minutes)} phút` : '—');
            addCell(row, Number(record.early_minutes) > 0 ? `${Number(record.early_minutes)} phút` : '—');
            const statusCell = row.insertCell();
            const status = document.createElement('span');
            status.className = `attendance-history-status${record.check_out ? ' complete' : record.check_in ? ' working' : ''}`;
            status.textContent = record.attendance_status === 'VANG_MAT'
                ? 'Nghỉ không phép'
                : Number(record.late_minutes) > 0 ? 'Đi muộn'
                    : Number(record.early_minutes) > 0 ? 'Về sớm'
                        : record.check_out ? 'Hoàn thành' : record.check_in ? 'Đang làm' : 'Chưa chấm công';
            statusCell.append(status);
            rowsElement.append(row);
        });

        const hasRecords = recentRecords.length > 0;
        emptyState.hidden = hasRecords;
        rowsElement.closest('table').hidden = !hasRecords;
        document.getElementById('attendance-history-count').textContent = hasRecords
            ? `Hiển thị ${recentRecords.length} trong ${historyRecords.length} ngày đã ghi nhận`
            : 'Chưa có dữ liệu chấm công';

        const daysWithCheckIn = historyRecords.filter((record) => record.check_in).length;
        const totalMinutes = historyRecords.reduce((sum, record) => sum + getWorkedMinutes(record), 0);
        document.getElementById('attendance-days-count').textContent = String(daysWithCheckIn);
        document.getElementById('attendance-hours-total').textContent = formatMinutes(totalMinutes);
    };

    const loadAttendance = async () => {
        isLoading = true;
        renderAttendance();
        try {
            const result = await window.lumiCineApi('attendance', {
                query: { date: dateInput.value },
            });
            attendanceLoadError = false;
            attendanceRecord = result.record;
            historyRecords = result.history;
            renderAttendance();
            renderHistory();
        } catch (error) {
            attendanceLoadError = true;
            notify(error instanceof Error ? error.message : 'Không thể tải dữ liệu chấm công.', true);
            attendanceRecord = {
                check_in: '',
                check_out: '',
                late_minutes: 0,
                early_minutes: 0,
                has_assignment: false,
            };
            historyRecords = [];
            renderAttendance();
            renderHistory();
        } finally {
            isLoading = false;
            renderAttendance();
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

    const recordTime = async () => {
        if (isLoading) return;
        isLoading = true;
        renderAttendance();
        try {
            const result = await window.lumiCineApi('attendance', {
                method: 'POST',
                body: { date: dateInput.value },
            });
            attendanceRecord = result.record;
            renderAttendance();
            notify(result.type === 'check_out'
                ? `Đã ghi nhận chấm công ra lúc ${attendanceRecord.check_out}.`
                : `Đã ghi nhận chấm công vào lúc ${attendanceRecord.check_in}.`);
            try {
                historyRecords = (await window.lumiCineApi('attendance', {
                    query: { date: dateInput.value },
                })).history;
                renderHistory();
            } catch (error) {
                notify('Chấm công đã được ghi nhận, nhưng không thể tải lại lịch sử.', true);
            }
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể ghi nhận chấm công.', true);
        } finally {
            isLoading = false;
            renderAttendance();
        }
    };

    dateInput.addEventListener('change', loadAttendance);
    document.getElementById('attendance-punch').addEventListener('click', recordTime);
    document.getElementById('notifications-button').addEventListener('click', () => {
        window.location.href = '?action=notifications';
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
