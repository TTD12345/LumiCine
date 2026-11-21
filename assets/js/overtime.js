(() => {
    'use strict';

    const feedback = document.getElementById('overtime-feedback');
    const employeeRows = document.getElementById('employee-overtime-rows');
    const adminRows = document.getElementById('admin-overtime-rows');
    const statusLabels = {
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        rejected: 'Từ chối',
    };
    let requests = [];
    let isLoading = false;

    const showMessage = (message, isError = false) => {
        feedback.textContent = message;
        feedback.classList.toggle('error', isError);
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
        employeeRows.replaceChildren();
        if (requests.length === 0) {
            const row = employeeRows.insertRow();
            makeCell(row, 'Bạn chưa có đơn đăng ký tăng ca nào.');
            row.cells[0].colSpan = 3;
            return;
        }

        requests.slice().reverse().forEach((request) => {
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
            makeCell(row, request.employee_name);
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
                ['approved', 'Duyệt', 'overtime-button overtime-button-approve'],
                ['rejected', 'Từ chối', 'overtime-button overtime-button-reject'],
            ].forEach(([status, label, className]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = className;
                button.dataset.status = status;
                button.dataset.requestId = String(request.id);
                button.textContent = label;
                actionCell.append(button);
            });
            actions.append(...actionCell.querySelectorAll('button'));
            actionCell.append(actions);
        });
    };

    const render = () => {
        renderEmployeeRequests();
        renderAdminRequests();
    };

    const loadRequests = async () => {
        try {
            const result = await window.lumiCineApi('overtime');
            requests = result.requests;
            render();
            return true;
        } catch (error) {
            showMessage(error instanceof Error ? error.message : 'Không thể tải đơn tăng ca.', true);
            return false;
        }
    };

    if (employeeRows) {
        const dialog = document.getElementById('overtime-dialog');
        const form = document.getElementById('overtime-form');
        const employeeName = document.getElementById('overtime-employee-name');
        const submitButton = document.getElementById('overtime-open-form');
        if (submitButton) {
            submitButton.addEventListener('click', () => {
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
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const start = form.elements.start.value;
                const end = form.elements.end.value;
                const reason = form.elements.reason.value.trim();
                if (!reason || reason.length < 5 || !start || !end ||
                    Date.parse(end) <= Date.parse(start)) {
                    showMessage('Vui lòng nhập lý do (ít nhất 5 ký tự) và thời gian kết thúc sau thời gian bắt đầu.', true);
                    return;
                }

                isLoading = true;
                try {
                    await window.lumiCineApi('overtime', {
                        method: 'POST',
                        body: { start, end, reason },
                    });
                    form.reset();
                    dialog.close();
                    if (await loadRequests()) {
                        showMessage('Đã gửi đơn đăng ký tăng ca để quản trị viên xem xét.');
                    } else {
                        showMessage('Đơn đã được gửi, nhưng không thể tải lại danh sách từ database.', true);
                    }
                } catch (error) {
                    showMessage(error instanceof Error ? error.message : 'Không thể gửi đơn tăng ca.', true);
                } finally {
                    isLoading = false;
                }
            });
        } else if (employeeName) {
            employeeName.textContent = 'Chưa liên kết hồ sơ nhân viên';
        }
    }

    if (adminRows) {
        adminRows.addEventListener('click', async (event) => {
            const button = event.target.closest('button[data-status][data-request-id]');
            if (!button || isLoading) return;
            isLoading = true;
            try {
                await window.lumiCineApi('overtime', {
                    method: 'POST',
                    body: {
                        operation: 'review',
                        id: button.dataset.requestId,
                        status: button.dataset.status,
                    },
                });
                if (await loadRequests()) {
                    showMessage(button.dataset.status === 'approved'
                        ? 'Đã duyệt đơn tăng ca.'
                        : 'Đã từ chối đơn tăng ca.');
                } else {
                    showMessage('Đơn đã được xử lý, nhưng không thể tải lại danh sách từ database.', true);
                }
            } catch (error) {
                showMessage(error instanceof Error ? error.message : 'Không thể xử lý đơn tăng ca.', true);
            } finally {
                isLoading = false;
            }
        });
    }

    loadRequests();
})();
