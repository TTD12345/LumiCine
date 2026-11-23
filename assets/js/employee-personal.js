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

    document.getElementById('notifications-button')?.addEventListener('click', () => {
        window.location.href = '?action=notifications';
    });

    const monthInput = document.getElementById('timesheet-month');
    const timesheetRows = document.getElementById('employee-timesheet-rows');
    if (monthInput && timesheetRows) {
        let records = [];
        let activeStatus = 'all';
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
        };
        const loadTimesheet = async () => {
            const [yearText, monthText] = monthInput.value.split('-');
            if (!yearText || !monthText) return;
            timesheetRows.innerHTML = '<tr><td colspan="9">Đang tải bảng công...</td></tr>';
            try {
                const result = await window.lumiCineApi('employee-timesheet', {
                    query: { month: Number(monthText), year: Number(yearText) },
                });
                records = result.records;
                renderTimesheet();
            } catch (error) {
                records = [];
                renderTimesheet();
                toast(error instanceof Error ? error.message : 'Không thể tải bảng công.', true);
            }
        };
        monthInput.addEventListener('change', loadTimesheet);
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
                const [attendanceResult, leaveResult, overtimeResult] = await Promise.all([
                    window.lumiCineApi('attendance', { query: { date: today } }),
                    window.lumiCineApi('leave', { query: { mine: 1 } }),
                    window.lumiCineApi('overtime', { query: { mine: 1 } }),
                ]);
                const items = [];
                const attendance = attendanceResult.record;
                if (Number(attendance.late_minutes) >= 15) {
                    items.push(['warning', `Bạn đi làm muộn ${attendance.late_minutes} phút hôm nay.`]);
                }
                if (attendance.has_assignment && !attendance.check_in && attendance.shift_end) {
                    const shiftEnd = new Date(`${today}T${attendance.shift_end}:00`);
                    if (attendance.shift_start && attendance.shift_end <= attendance.shift_start) {
                        shiftEnd.setDate(shiftEnd.getDate() + 1);
                    }
                    if (now >= shiftEnd) {
                        items.push(['warning', 'Bạn chưa chấm công vào hôm nay.']);
                    }
                }
                if (attendance.check_in && !attendance.check_out) {
                    items.push(['warning', 'Bạn chưa chấm công ra.']);
                }
                leaveResult.requests.forEach((request) => {
                    if (request.status !== 'pending') {
                        items.push([
                            request.status === 'approved' ? 'success' : 'danger',
                            `Đơn nghỉ phép ${request.status === 'approved' ? 'đã được duyệt' : 'bị từ chối'}: ${formatDateTime(request.start)}.`,
                        ]);
                    }
                });
                overtimeResult.requests.forEach((request) => {
                    if (request.status !== 'pending') {
                        items.push([
                            request.status === 'approved' ? 'success' : 'danger',
                            `Đơn tăng ca ${request.status === 'approved' ? 'đã được duyệt' : 'bị từ chối'}: ${formatDateTime(request.start)}.`,
                        ]);
                    }
                });
                if (items.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'notification-empty';
                    empty.textContent = 'Chưa có thông báo cá nhân mới.';
                    notifications.append(empty);
                    return;
                }
                items.slice(0, 30).forEach(([tone, message]) => {
                    const item = document.createElement('article');
                    item.className = `personal-notification ${tone}`;
                    const icon = document.createElement('i');
                    icon.className = `bi ${tone === 'success' ? 'bi-check-circle' : tone === 'warning' ? 'bi-exclamation-circle' : 'bi-x-circle'}`;
                    icon.setAttribute('aria-hidden', 'true');
                    const text = document.createElement('span');
                    text.textContent = message;
                    item.append(icon, text);
                    notifications.append(item);
                });
            } catch (error) {
                const message = document.createElement('p');
                message.className = 'notification-empty error';
                message.textContent = error instanceof Error ? error.message : 'Không thể tải thông báo.';
                notifications.append(message);
            }
        };
        loadNotifications();
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
