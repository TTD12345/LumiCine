(() => {
    'use strict';

    const storageKey = 'lumicine-overtime-requests';
    const officeStartHour = 8;
    const officeEndHour = 17;
    const statusLabels = {
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        rejected: 'Từ chối',
    };
    const feedback = document.getElementById('overtime-feedback');
    const employeeRows = document.getElementById('employee-overtime-rows');
    const adminRows = document.getElementById('admin-overtime-rows');
    let requests = [];

    const showMessage = (message, isError = false) => {
        feedback.textContent = message;
        feedback.classList.toggle('error', isError);
    };

    const getAccountName = () => {
        const accountName = document.getElementById('overtime-employee-name');
        return accountName ? accountName.textContent.trim() : null;
    };

    const normalizeRequest = (request) => {
        if (!request || typeof request.id !== 'string' || typeof request.employeeName !== 'string' ||
            typeof request.start !== 'string' || typeof request.end !== 'string' ||
            typeof request.reason !== 'string' || !statusLabels[request.status] ||
            Number.isNaN(Date.parse(request.start)) || Number.isNaN(Date.parse(request.end)) ||
            Date.parse(request.end) <= Date.parse(request.start)) {
            throw new Error('Dữ liệu đơn tăng ca đã lưu không đúng định dạng.');
        }
        return {
            ...request,
            department: typeof request.department === 'string' && request.department.trim() !== ''
                ? request.department
                : 'Chưa cập nhật',
        };
    };

    const readRequests = () => {
        const saved = window.localStorage.getItem(storageKey);
        if (saved === null) return [];
        const parsed = JSON.parse(saved);
        if (!Array.isArray(parsed)) throw new Error('Danh sách đơn tăng ca không đúng định dạng.');
        return parsed.map(normalizeRequest);
    };

    const persistRequests = (nextRequests) => {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(nextRequests));
        } catch (error) {
            showMessage('Không thể lưu đơn tăng ca trên trình duyệt. Hãy kiểm tra dung lượng lưu trữ và thử lại.', true);
            return false;
        }
        requests = nextRequests;
        render();
        return true;
    };

    const makeCell = (row, text, className = '') => {
        const cell = row.insertCell();
        if (className) cell.className = className;
        cell.textContent = text;
        return cell;
    };

    const formatDateTime = (value) => new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));

    const overlapsOfficeHours = (startValue, endValue) => {
        const start = Date.parse(startValue);
        const end = Date.parse(endValue);
        if (Number.isNaN(start) || Number.isNaN(end) || end <= start) return false;
        if (end - start >= 24 * 60 * 60 * 1000) return true;

        const day = new Date(start);
        day.setHours(0, 0, 0, 0);
        const lastDay = new Date(end);
        lastDay.setHours(0, 0, 0, 0);

        while (day <= lastDay) {
            const officeStart = new Date(day);
            officeStart.setHours(officeStartHour, 0, 0, 0);
            const officeEnd = new Date(day);
            officeEnd.setHours(officeEndHour, 0, 0, 0);
            if (start < officeEnd.getTime() && end > officeStart.getTime()) return true;
            day.setDate(day.getDate() + 1);
        }
        return false;
    };

    const createStatus = (status) => {
        const badge = document.createElement('span');
        badge.className = `overtime-status overtime-status-${status}`;
        badge.textContent = statusLabels[status];
        return badge;
    };

    const renderEmployeeRequests = () => {
        if (!employeeRows) return;
        const accountName = getAccountName();
        const ownRequests = accountName
            ? requests.filter((request) => request.employeeName === accountName)
            : [];
        employeeRows.replaceChildren();
        if (ownRequests.length === 0) {
            const row = employeeRows.insertRow();
            makeCell(row, 'Bạn chưa có đơn đăng ký tăng ca nào.');
            row.cells[0].colSpan = 4;
            return;
        }

        ownRequests.slice().reverse().forEach((request) => {
            const row = employeeRows.insertRow();
            makeCell(row, `${formatDateTime(request.start)} – ${formatDateTime(request.end)}`);
            makeCell(row, request.department);
            makeCell(row, request.reason, 'overtime-reason');
            row.insertCell().append(createStatus(request.status));
        });
    };

    const renderAdminRequests = () => {
        if (!adminRows) return;
        adminRows.replaceChildren();
        const pendingCount = requests.filter((request) => request.status === 'pending').length;
        document.getElementById('overtime-pending-count').textContent = String(pendingCount);
        const notificationIndicator = document.getElementById('notification-indicator');
        if (notificationIndicator) notificationIndicator.hidden = pendingCount === 0;
        if (requests.length === 0) {
            const row = adminRows.insertRow();
            makeCell(row, 'Chưa có đơn đăng ký tăng ca.');
            row.cells[0].colSpan = 7;
            return;
        }

        requests.slice().reverse().forEach((request) => {
            const row = adminRows.insertRow();
            makeCell(row, request.employeeName);
            makeCell(row, request.department);
            makeCell(row, formatDateTime(request.start));
            makeCell(row, formatDateTime(request.end));
            makeCell(row, request.reason, 'overtime-reason');
            row.insertCell().append(createStatus(request.status));
            const actionCell = row.insertCell();
            if (request.status !== 'pending') {
                actionCell.textContent = 'Đã xử lý';
                return;
            }
            const actions = document.createElement('div');
            actions.className = 'overtime-actions';
            [
                ['approve', 'Duyệt', 'overtime-button overtime-button-approve'],
                ['reject', 'Từ chối', 'overtime-button overtime-button-reject'],
            ].forEach(([action, label, className]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = className;
                button.dataset.action = action;
                button.dataset.requestId = request.id;
                button.textContent = label;
                actions.append(button);
            });
            actionCell.append(actions);
        });
    };

    const render = () => {
        renderEmployeeRequests();
        renderAdminRequests();
    };

    const loadRequests = () => {
        try {
            requests = readRequests();
            render();
        } catch (error) {
            showMessage(error instanceof Error ? error.message : 'Không thể đọc danh sách đơn tăng ca.', true);
        }
    };

    if (employeeRows) {
        const dialog = document.getElementById('overtime-dialog');
        const form = document.getElementById('overtime-form');
        const employeeName = document.getElementById('overtime-employee-name');
        employeeName.textContent = getAccountName() || 'Chưa xác định';
        const showFormMessage = (message, isError = false) => {
            const formFeedback = document.getElementById('overtime-form-feedback');
            formFeedback.textContent = message;
            formFeedback.classList.toggle('error', isError);
        };
        const validateTimeRange = () => {
            const start = form.elements.start.value;
            const end = form.elements.end.value;
            if (!start || !end) {
                showFormMessage('');
                return true;
            }
            if (Date.parse(end) <= Date.parse(start)) {
                showFormMessage('Thời gian kết thúc phải sau thời gian bắt đầu.', true);
                return false;
            }
            if (overlapsOfficeHours(start, end)) {
                showFormMessage('Thời gian tăng ca không được trùng giờ hành chính (08:00–17:00).', true);
                return false;
            }
            showFormMessage('');
            return true;
        };

        document.getElementById('overtime-open-form').addEventListener('click', () => {
            const start = new Date();
            if (start.getHours() < officeEndHour) {
                start.setHours(officeEndHour, 0, 0, 0);
            } else {
                start.setMinutes(0, 0, 0);
                start.setHours(start.getHours() + 1);
            }
            const end = new Date(start.getTime() + 60 * 60 * 1000);
            const toLocalInput = (date) => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-') + `T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
            form.elements.start.value = toLocalInput(start);
            form.elements.end.value = toLocalInput(end);
            showFormMessage('');
            dialog.showModal();
        });
        ['input', 'change'].forEach((eventName) => {
            form.elements.start.addEventListener(eventName, validateTimeRange);
            form.elements.end.addEventListener(eventName, validateTimeRange);
        });
        ['overtime-close-form', 'overtime-cancel-form'].forEach((id) => {
            document.getElementById(id).addEventListener('click', () => dialog.close());
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const name = getAccountName();
            const start = form.elements.start.value;
            const end = form.elements.end.value;
            const department = form.elements.department.value.trim();
            const reason = form.elements.reason.value.trim();
            if (!name || !department || !reason || reason.length < 5 || !start || !end ||
                Date.parse(end) <= Date.parse(start)) {
                showFormMessage('Vui lòng nhập bộ phận, lý do (ít nhất 5 ký tự) và thời gian kết thúc sau thời gian bắt đầu.', true);
                return;
            }
            if (!validateTimeRange()) return;
            const request = {
                id: `overtime-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                employeeName: name.trim(),
                department,
                start,
                end,
                reason,
                status: 'pending',
                submittedAt: new Date().toISOString(),
            };
            if (!persistRequests([...requests, request])) {
                showFormMessage('Không thể lưu đơn tăng ca trên trình duyệt. Hãy kiểm tra dung lượng lưu trữ và thử lại.', true);
                return;
            }
            form.reset();
            dialog.close();
            showMessage('Đã gửi đơn đăng ký tăng ca để quản trị viên xem xét.');
        });
    }

    if (adminRows) {
        adminRows.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-action][data-request-id]');
            if (!button) return;
            const request = requests.find((item) => item.id === button.dataset.requestId);
            if (!request || request.status !== 'pending') {
                showMessage('Đơn này đã được xử lý hoặc không còn tồn tại.', true);
                loadRequests();
                return;
            }
            if (!['approve', 'reject'].includes(button.dataset.action)) return;
            const status = button.dataset.action === 'approve' ? 'approved' : 'rejected';
            const nextRequests = requests.map((item) =>
                item.id === request.id ? { ...item, status, reviewedAt: new Date().toISOString() } : item
            );
            if (persistRequests(nextRequests)) {
                showMessage(status === 'approved' ? 'Đã duyệt đơn tăng ca.' : 'Đã từ chối đơn tăng ca.');
            }
        });
    }

    window.addEventListener('storage', (event) => {
        if (event.key === storageKey) loadRequests();
    });

    loadRequests();
})();
