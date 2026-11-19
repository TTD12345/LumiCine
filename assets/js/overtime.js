(() => {
    'use strict';

    const storageKey = 'lumicine-overtime-requests';
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
        return request;
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
            row.cells[0].colSpan = 3;
            return;
        }

        ownRequests.slice().reverse().forEach((request) => {
            const row = employeeRows.insertRow();
            makeCell(row, `${formatDateTime(request.start)} – ${formatDateTime(request.end)}`);
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
            row.cells[0].colSpan = 6;
            return;
        }

        requests.slice().reverse().forEach((request) => {
            const row = adminRows.insertRow();
            makeCell(row, request.employeeName);
            makeCell(row, formatDateTime(request.start));
            makeCell(row, formatDateTime(request.end));
            makeCell(row, request.reason, 'overtime-reason');
            row.insertCell().append(createStatus(request.status));
            const actionCell = row.insertCell();
            if (request.status !== 'pending') {
                actionCell.textContent = '—';
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

        document.getElementById('overtime-open-form').addEventListener('click', () => {
            const start = new Date();
            start.setHours(start.getHours() + 1, 0, 0, 0);
            const end = new Date(start.getTime() + 60 * 60 * 1000);
            const toLocalInput = (date) => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-') + `T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
            form.elements.start.value = toLocalInput(start);
            form.elements.end.value = toLocalInput(end);
            dialog.showModal();
        });
        ['overtime-close-form', 'overtime-cancel-form'].forEach((id) => {
            document.getElementById(id).addEventListener('click', () => dialog.close());
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const name = getAccountName();
            const start = form.elements.start.value;
            const end = form.elements.end.value;
            const reason = form.elements.reason.value.trim();
            if (!name || !reason || reason.length < 5 || !start || !end ||
                Date.parse(end) <= Date.parse(start)) {
                showMessage('Vui lòng nhập lý do (ít nhất 5 ký tự) và thời gian kết thúc sau thời gian bắt đầu.', true);
                return;
            }
            const request = {
                id: `overtime-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                employeeName: name.trim(),
                start,
                end,
                reason,
                status: 'pending',
                submittedAt: new Date().toISOString(),
            };
            if (!persistRequests([...requests, request])) return;
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
