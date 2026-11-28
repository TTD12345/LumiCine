(() => {
    'use strict';

    const statusLabels = {
        complete: 'Đủ công',
        late: 'Đi muộn',
        early: 'Về sớm',
        incomplete: 'Thiếu chấm công',
        working: 'Đang làm việc',
        not_started: 'Chưa chấm công',
        leave: 'Nghỉ phép',
        absent: 'Nghỉ không phép',
        unscheduled: 'Không có lịch phân công',
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        rejected: 'Từ chối',
    };
    const toast = (message, isError = false) => {
        const region = document.getElementById('toast-region');
        if (!region) return;
        const element = document.createElement('div');
        element.className = `employee-toast${isError ? ' error' : ''}`;
        element.setAttribute('role', 'status');
        element.textContent = message;
        region.append(element);
        window.setTimeout(() => element.remove(), 4000);
    };
    const setFeedback = (element, message, isError = false) => {
        if (!element) return;
        element.textContent = message;
        element.classList.toggle('error', isError);
    };
    const formatDate = (value) => new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
    const formatDateTime = (value) => new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value.replace(' ', 'T')));
    const formatDuration = (minutes) => {
        const value = Number(minutes) || 0;
        if (value <= 0) return '—';
        const hours = Math.floor(value / 60);
        const remaining = value % 60;
        return remaining ? `${hours} giờ ${remaining} phút` : `${hours} giờ`;
    };
    const addCell = (row, value, className = '') => {
        const cell = row.insertCell();
        if (className) cell.className = className;
        cell.textContent = value;
        return cell;
    };
    const addStatusBadge = (row, status, prefix = 'personal-status') => {
        const cell = row.insertCell();
        const badge = document.createElement('span');
        badge.className = `${prefix} ${prefix}-${status}`;
        badge.textContent = statusLabels[status] || status;
        cell.append(badge);
    };

    const monthInput = document.getElementById('timesheet-month');
    const timesheetRows = document.getElementById('employee-timesheet-rows');
    if (monthInput && timesheetRows) {
        const calendarPanel = document.getElementById('employee-timesheet-calendar');
        const calendarGrid = document.getElementById('timesheet-calendar-grid');
        const calendarCaption = document.getElementById('timesheet-calendar-caption');
        const calendarTrigger = document.getElementById('timesheet-calendar-trigger');
        const calendarToggle = document.getElementById('timesheet-calendar-toggle');
        const periodLabel = document.getElementById('timesheet-period-label');
        let records = [];
        let period = null;
        let activeStatus = 'all';
        let calendarError = '';
        let requestId = 0;
        let calendarHideTimer = null;
        const makeElement = (tag, className = '', text = '') => {
            const element = document.createElement(tag);
            if (className) element.className = className;
            if (text) element.textContent = text;
            return element;
        };
        const openDayDetails = (date, record) => {
            let dialog = document.getElementById('timesheet-day-dialog');
            if (!dialog) {
                dialog = makeElement('dialog', 'timesheet-day-dialog');
                dialog.id = 'timesheet-day-dialog';
                document.body.append(dialog);
            }
            dialog.replaceChildren();
            const heading = makeElement('header', 'timesheet-day-dialog-heading');
            const title = makeElement('div');
            const dateTitle = new Intl.DateTimeFormat('vi-VN', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
            }).format(new Date(`${date}T00:00:00`));
            title.append(
                makeElement('span', 'page-kicker', 'CHI TIẾT BẢNG CÔNG'),
                makeElement('h2', '', dateTitle),
                makeElement('p', '', statusLabels[record?.status || 'unscheduled']),
            );
            const close = makeElement('button', 'timesheet-day-close', 'Đóng');
            close.type = 'button';
            close.addEventListener('click', () => dialog.close());
            heading.append(title, close);
            dialog.append(heading);

            const details = makeElement('dl', 'timesheet-day-details');
            const values = record
                ? [
                    ['Ca dự kiến', record.shift_start && record.shift_end
                        ? `${String(record.shift_start).slice(0, 5)} – ${String(record.shift_end).slice(0, 5)}`
                        : 'Đã phân ca'],
                    ['Giờ vào', record.check_in || '—'],
                    ['Giờ ra', record.check_out || '—'],
                    ['Thời gian làm', formatDuration(record.worked_minutes)],
                    ['Tăng ca đã duyệt', formatDuration(record.overtime_minutes)],
                    ['Đi muộn', Number(record.late_minutes) > 0 ? `${record.late_minutes} phút` : '—'],
                    ['Về sớm', Number(record.early_minutes) > 0 ? `${record.early_minutes} phút` : '—'],
                ]
                : [['Lịch làm việc', 'Không có ca phân công trong ngày này.']];
            values.forEach(([label, value]) => {
                details.append(makeElement('dt', '', label), makeElement('dd', '', value));
            });
            dialog.append(details);
            if (!dialog.open) dialog.showModal();
        };
        const renderCalendar = () => {
            if (!calendarPanel || !calendarGrid || !calendarCaption) return;
            calendarGrid.replaceChildren();
            if (calendarError) {
                calendarCaption.textContent = calendarError;
                return;
            }
            if (!period) {
                calendarCaption.textContent = 'Chưa có thông tin kỳ công.';
                return;
            }
            const periodStart = new Date(`${period.start}T00:00:00`);
            const periodEnd = new Date(`${period.end}T00:00:00`);
            calendarCaption.textContent = `Kỳ công ${formatDate(period.start)} – ${formatDate(period.end)}`;

            const recordByDate = new Map(records.map((record) => [record.date, record]));
            const firstWeekday = (periodStart.getDay() + 6) % 7;
            for (let index = 0; index < firstWeekday; index += 1) {
                const empty = makeElement('span', 'timesheet-calendar-empty');
                empty.setAttribute('role', 'presentation');
                calendarGrid.append(empty);
            }
            for (let dateCursor = new Date(periodStart); dateCursor <= periodEnd;
                dateCursor.setDate(dateCursor.getDate() + 1)) {
                const year = dateCursor.getFullYear();
                const month = String(dateCursor.getMonth() + 1).padStart(2, '0');
                const dayNumber = dateCursor.getDate();
                const date = `${year}-${month}-${String(dayNumber).padStart(2, '0')}`;
                const record = recordByDate.get(date);
                const status = record?.status || 'unscheduled';
                const cell = makeElement('button', `timesheet-calendar-day status-${status}`);
                cell.type = 'button';
                cell.setAttribute('role', 'gridcell');
                cell.setAttribute(
                    'aria-label',
                    `${formatDate(date)} · ${statusLabels[status] || status}`,
                );
                const dateLabel = dateCursor.getMonth() === periodStart.getMonth()
                    && dateCursor.getFullYear() === periodStart.getFullYear()
                    ? String(dayNumber)
                    : `${String(dayNumber).padStart(2, '0')}/${month}`;
                cell.append(makeElement('span', 'timesheet-calendar-date', dateLabel));
                if (record) {
                    if (record.check_in || record.check_out) {
                        cell.append(makeElement(
                            'span',
                            'timesheet-calendar-times',
                            `${record.check_in || '—'}–${record.check_out || '—'}`,
                        ));
                    }
                    if (Number(record.worked_minutes) > 0) {
                        cell.append(makeElement(
                            'span',
                            'timesheet-calendar-hours',
                            formatDuration(record.worked_minutes),
                        ));
                    }
                }
                cell.append(makeElement(
                    'span',
                    'timesheet-calendar-state',
                    status === 'unscheduled' ? 'Không có ca' : statusLabels[status] || status,
                ));
                cell.addEventListener('click', () => openDayDetails(date, record));
                calendarGrid.append(cell);
            }
            while (calendarGrid.children.length % 7 !== 0) {
                const empty = makeElement('span', 'timesheet-calendar-empty');
                empty.setAttribute('role', 'presentation');
                calendarGrid.append(empty);
            }
        };
        const showCalendar = () => {
            if (calendarHideTimer) window.clearTimeout(calendarHideTimer);
            if (calendarPanel) calendarPanel.hidden = false;
            calendarToggle?.setAttribute('aria-expanded', 'true');
            renderCalendar();
        };
        const isCalendarTargetActive = () =>
            calendarToggle?.matches(':hover')
            || calendarPanel?.matches(':hover')
            || calendarToggle === document.activeElement
            || Boolean(calendarPanel?.contains(document.activeElement));
        const hideCalendar = () => {
            if (!calendarPanel) return;
            if (calendarHideTimer) window.clearTimeout(calendarHideTimer);
            calendarHideTimer = window.setTimeout(() => {
                if (!isCalendarTargetActive()) {
                    calendarPanel.hidden = true;
                    calendarToggle?.setAttribute('aria-expanded', 'false');
                }
            }, 200);
        };
        const renderTimesheet = () => {
            const visible = records.filter((record) => activeStatus === 'all' || record.status === activeStatus);
            timesheetRows.replaceChildren();
            if (visible.length === 0) {
                const row = timesheetRows.insertRow();
                addCell(row, records.length ? 'Không có ngày công phù hợp với bộ lọc.' : 'Chưa có lịch làm việc trong tháng này.');
                row.cells[0].colSpan = 9;
            } else {
                visible.forEach((record) => {
                    const row = timesheetRows.insertRow();
                    const date = new Date(`${record.date}T00:00:00`);
                    addCell(row, formatDate(record.date));
                    addCell(row, new Intl.DateTimeFormat('vi-VN', { weekday: 'long' }).format(date));
                    addCell(row, record.check_in || '—');
                    addCell(row, record.check_out || '—');
                    addCell(row, formatDuration(record.worked_minutes));
                    addCell(row, formatDuration(record.overtime_minutes));
                    addCell(row, ['complete', 'late', 'early'].includes(record.status)
                        ? '1 công'
                        : ['leave', 'absent'].includes(record.status) ? '0 công' : '—');
                    addStatusBadge(row, record.status);
                    const notes = [];
                    if (Number(record.late_minutes) > 0) notes.push(`Muộn ${record.late_minutes} phút`);
                    if (Number(record.early_minutes) > 0) notes.push(`Về sớm ${record.early_minutes} phút`);
                    addCell(row, notes.join(' · ') || '—');
                });
            }
            document.getElementById('timesheet-result-count').textContent =
                `Hiển thị ${visible.length} / ${records.length} ngày có phân ca`;
            renderCalendar();
        };
        const loadTimesheet = async () => {
            const [yearText, monthText] = monthInput.value.split('-');
            if (!yearText || !monthText) return;
            const currentRequestId = ++requestId;
            calendarError = 'Đang tải dữ liệu bảng công...';
            period = null;
            if (periodLabel) periodLabel.textContent = calendarError;
            timesheetRows.innerHTML = '<tr><td colspan="9">Đang tải bảng công...</td></tr>';
            renderCalendar();
            try {
                const result = await window.lumiCineApi('employee-timesheet', {
                    query: { month: Number(monthText), year: Number(yearText) },
                });
                if (currentRequestId !== requestId) return;
                calendarError = '';
                records = result.records;
                period = result.period;
                if (periodLabel && period) {
                    periodLabel.textContent =
                        `Kỳ công ${formatDate(period.start)} – ${formatDate(period.end)}`;
                }
                renderTimesheet();
            } catch (error) {
                if (currentRequestId !== requestId) return;
                records = [];
                calendarError = error instanceof Error ? error.message : 'Không thể tải bảng công.';
                if (periodLabel) periodLabel.textContent = calendarError;
                renderTimesheet();
                toast(calendarError, true);
            }
        };
        monthInput.addEventListener('change', loadTimesheet);
        calendarToggle?.addEventListener('pointerenter', showCalendar);
        calendarToggle?.addEventListener('pointerleave', hideCalendar);
        calendarToggle?.addEventListener('focusin', showCalendar);
        calendarTrigger?.addEventListener('focusout', hideCalendar);
        calendarPanel?.addEventListener('pointerenter', showCalendar);
        calendarPanel?.addEventListener('pointerleave', hideCalendar);
        document.getElementById('timesheet-status-filters').addEventListener('click', (event) => {
            const button = event.target.closest('button[data-status]');
            if (!button) return;
            activeStatus = button.dataset.status;
            document.querySelectorAll('#timesheet-status-filters .filter-chip')
                .forEach((item) => item.classList.toggle('active', item === button));
            renderTimesheet();
        });
        loadTimesheet();
    }

    const requestRows = document.getElementById('employee-request-rows');
    if (requestRows) {
        let requests = [];
        let activeStatus = 'all';
        const renderRequests = () => {
            const visible = requests.filter((request) => activeStatus === 'all' || request.status === activeStatus);
            requestRows.replaceChildren();
            if (visible.length === 0) {
                const row = requestRows.insertRow();
                addCell(row, requests.length ? 'Không có đơn phù hợp với bộ lọc.' : 'Bạn chưa gửi đơn nào.');
                row.cells[0].colSpan = 7;
            } else {
                visible.forEach((request) => {
                    const row = requestRows.insertRow();
                    addCell(row, request.code);
                    addCell(row, request.type);
                    addCell(row, request.date);
                    addCell(row, request.content, 'request-content');
                    addCell(row, request.submittedAt ? formatDateTime(request.submittedAt) : '—');
                    addCell(row, request.reviewer || 'Chưa xử lý');
                    addStatusBadge(row, request.status);
                });
            }
        };
        const loadRequests = async () => {
            requestRows.innerHTML = '<tr><td colspan="7">Đang tải danh sách đơn...</td></tr>';
            try {
                const [leaveResult, overtimeResult] = await Promise.all([
                    window.lumiCineApi('leave', { query: { mine: 1 } }),
                    window.lumiCineApi('overtime', { query: { mine: 1 } }),
                ]);
                requests = [
                    ...leaveResult.requests.map((request) => ({
                        code: `NP-${request.id}`,
                        type: 'Nghỉ phép',
                        date: `${formatDateTime(request.start)} – ${formatDateTime(request.end)}`,
                        content: `${leaveResult.types[request.leave_type] || request.leave_type}: ${request.reason || ''}`,
                        submittedAt: request.submitted_at,
                        reviewer: request.reviewer_name,
                        status: request.status,
                    })),
                    ...overtimeResult.requests.map((request) => ({
                        code: `TC-${request.id}`,
                        type: 'Tăng ca',
                        date: `${formatDateTime(request.start)} – ${formatDateTime(request.end)}`,
                        content: request.reason || '',
                        submittedAt: request.submitted_at,
                        reviewer: request.reviewer_name,
                        status: request.status,
                    })),
                ].sort((a, b) => new Date(b.submittedAt || 0) - new Date(a.submittedAt || 0));
                renderRequests();
            } catch (error) {
                requestRows.innerHTML = '<tr><td colspan="7">Không thể tải danh sách đơn.</td></tr>';
                toast(error instanceof Error ? error.message : 'Không thể tải danh sách đơn.', true);
            }
        };
        document.getElementById('request-status-filters').addEventListener('click', (event) => {
            const button = event.target.closest('button[data-status]');
            if (!button) return;
            activeStatus = button.dataset.status;
            document.querySelectorAll('#request-status-filters .filter-chip')
                .forEach((item) => item.classList.toggle('active', item === button));
            renderRequests();
        });
        loadRequests();
    }

    const notifications = document.getElementById('employee-notification-list');
    if (notifications) {
        const loadNotifications = async () => {
            notifications.replaceChildren();
            try {
                const now = new Date();
                const today = [
                    now.getFullYear(),
                    String(now.getMonth() + 1).padStart(2, '0'),
                    String(now.getDate()).padStart(2, '0'),
                ].join('-');
                const [attendanceResult, notificationResult] = await Promise.all([
                    window.lumiCineApi('attendance', { query: { date: today } }),
                    window.lumiCineApi('notifications'),
                ]);
                const items = [];
                const notificationTones = {
                    schedule: 'info',
                    schedule_updated: 'info',
                    schedule_cancelled: 'danger',
                    leave_approved: 'success',
                    leave_rejected: 'danger',
                    overtime_approved: 'success',
                    overtime_rejected: 'danger',
                };
                notificationResult.notifications.forEach((notification) => {
                    items.push({
                        tone: notificationTones[notification.type] || 'info',
                        title: notification.title,
                        message: notification.message,
                        createdAt: notification.created_at,
                    });
                });
                const attendance = attendanceResult.record;
                if (Number(attendance.late_minutes) >= 15) {
                    items.push({
                        tone: 'warning',
                        title: 'Nhắc chấm công',
                        message: `Bạn đi làm muộn ${attendance.late_minutes} phút hôm nay.`,
                    });
                }
                if (attendance.has_assignment && !attendance.check_in && attendance.shift_end) {
                    const shiftEnd = new Date(`${today}T${attendance.shift_end}:00`);
                    if (attendance.shift_start && attendance.shift_end <= attendance.shift_start) {
                        shiftEnd.setDate(shiftEnd.getDate() + 1);
                    }
                    if (now >= shiftEnd) {
                        items.push({
                            tone: 'warning',
                            title: 'Nhắc chấm công',
                            message: 'Bạn chưa chấm công vào hôm nay.',
                        });
                    }
                }
                if (attendance.check_in && !attendance.check_out) {
                    items.push({
                        tone: 'warning',
                        title: 'Nhắc chấm công',
                        message: 'Bạn chưa chấm công ra.',
                    });
                }
                if (items.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'notification-empty';
                    empty.textContent = 'Bạn chưa có thông báo mới.';
                    notifications.append(empty);
                    return;
                }
                items.slice(0, 30).forEach(({ tone, title, message, createdAt }) => {
                    const item = document.createElement('article');
                    item.className = `personal-notification ${tone}`;
                    const icon = document.createElement('i');
                    icon.className = `bi ${
                        tone === 'success'
                            ? 'bi-check-circle'
                            : tone === 'warning'
                                ? 'bi-exclamation-circle'
                                : tone === 'danger'
                                    ? 'bi-x-circle'
                                    : 'bi-calendar-event'
                    }`;
                    icon.setAttribute('aria-hidden', 'true');
                    const copy = document.createElement('span');
                    copy.className = 'personal-notification-copy';
                    const heading = document.createElement('strong');
                    heading.textContent = title;
                    const text = document.createElement('span');
                    text.textContent = message;
                    copy.append(heading, text);
                    if (createdAt) {
                        const timestamp = document.createElement('small');
                        timestamp.textContent = formatDateTime(createdAt);
                        copy.append(timestamp);
                    }
                    item.append(icon, copy);
                    notifications.append(item);
                });
                try {
                    await window.lumiCineApi('notifications', {
                        method: 'POST',
                        body: { operation: 'mark-read' },
                    });
                    window.dispatchEvent(new Event('lumicine:notifications-read'));
                } catch (error) {
                    toast(error instanceof Error ? error.message : 'Không thể đánh dấu thông báo đã đọc.', true);
                }
            } catch (error) {
                const message = document.createElement('p');
                message.className = 'notification-empty error';
                message.textContent = error instanceof Error ? error.message : 'Không thể tải thông báo.';
                notifications.append(message);
            }
        };
        loadNotifications();
        window.setInterval(loadNotifications, 30000);
    }

    const profileForm = document.getElementById('employee-profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = profileForm.querySelector('button[type="submit"]');
            const feedback = document.getElementById('profile-feedback');
            button.disabled = true;
            setFeedback(feedback, '');
            try {
                await window.lumiCineApi('employee-profile', {
                    method: 'POST',
                    body: {
                        phone: profileForm.elements.phone.value,
                        email: profileForm.elements.email.value,
                    },
                });
                setFeedback(feedback, 'Đã cập nhật thông tin liên hệ.');
            } catch (error) {
                setFeedback(feedback, error instanceof Error ? error.message : 'Không thể cập nhật hồ sơ.', true);
            } finally {
                button.disabled = false;
            }
        });
    }

    const passwordForm = document.getElementById('employee-password-form');
    if (passwordForm) {
        const passwordCard = document.getElementById('employee-password-card');
        const passwordToggle = document.getElementById('toggle-password-form');
        if (passwordCard && passwordToggle) {
            const toggleLabel = passwordToggle.querySelector('[data-password-toggle-label]');
            passwordToggle.addEventListener('click', () => {
                passwordCard.hidden = !passwordCard.hidden;
                const isOpen = !passwordCard.hidden;
                passwordToggle.setAttribute('aria-expanded', String(isOpen));
                toggleLabel.textContent = isOpen ? 'Đóng đổi mật khẩu' : 'Đổi mật khẩu';
                if (isOpen) {
                    passwordForm.elements.newPassword.focus();
                } else {
                    passwordForm.reset();
                    setFeedback(document.getElementById('password-feedback'), '');
                }
            });
        }

        const keepPasswordButton = passwordForm.querySelector('[data-keep-password]');
        const savePasswordButton = passwordForm.querySelector('button[type="submit"]');
        const passwordFeedback = document.getElementById('password-feedback');
        const completeForcedPrompt = () => {
            if (passwordForm.dataset.required === '1') {
                window.location.replace('?action=dashboard');
            }
        };

        if (keepPasswordButton) {
            keepPasswordButton.addEventListener('click', async () => {
                keepPasswordButton.disabled = true;
                savePasswordButton.disabled = true;
                setFeedback(passwordFeedback, '');
                try {
                    await window.lumiCineApi('change-password', {
                        method: 'POST',
                        body: {
                            operation: 'keep',
                            dontShowAgain: document.getElementById('dont-show-password-prompt').checked,
                        },
                    });
                    setFeedback(passwordFeedback, 'Bạn đang sử dụng mật khẩu hiện tại.');
                    completeForcedPrompt();
                } catch (error) {
                    setFeedback(passwordFeedback, error instanceof Error ? error.message : 'Không thể lưu lựa chọn.', true);
                } finally {
                    keepPasswordButton.disabled = false;
                    savePasswordButton.disabled = false;
                }
            });
        }

        passwordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!passwordForm.reportValidity()) return;

            savePasswordButton.disabled = true;
            if (keepPasswordButton) keepPasswordButton.disabled = true;
            setFeedback(passwordFeedback, '');
            try {
                const body = {
                    newPassword: passwordForm.elements.newPassword.value,
                    confirmation: passwordForm.elements.confirmation.value,
                };
                const currentPasswordInput = passwordForm.elements.currentPassword;
                if (currentPasswordInput) {
                    body.currentPassword = currentPasswordInput.value;
                }
                await window.lumiCineApi('change-password', {
                    method: 'POST',
                    body,
                });
                passwordForm.reset();
                setFeedback(passwordFeedback, 'Đã đổi mật khẩu thành công.');
                completeForcedPrompt();
            } catch (error) {
                setFeedback(passwordFeedback, error instanceof Error ? error.message : 'Không thể đổi mật khẩu.', true);
            } finally {
                savePasswordButton.disabled = false;
                if (keepPasswordButton) keepPasswordButton.disabled = false;
            }
        });
    }
})();
