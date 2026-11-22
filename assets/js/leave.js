(() => {
    'use strict';

    const feedback = document.getElementById('leave-feedback');
    const employeeRows = document.getElementById('employee-leave-rows');
    const adminRows = document.getElementById('admin-leave-rows');
    const statusLabels = {
        pending: 'Chờ duyệt',
        approved: 'Đã duyệt',
        rejected: 'Từ chối',
    };
    let requests = [];
    let isLoading = false;

    const showMessage = (message, isError = false) => {
        if (!feedback) return;
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
            makeCell(row, 'Bạn chưa có đơn nghỉ phép nào.');
            row.cells[0].colSpan = 4;
            return;
        }
        requests.forEach((request) => {
            const row = employeeRows.insertRow();
            makeCell(row, `${formatDateTime(request.start)} – ${formatDateTime(request.end)}`);
            makeCell(row, request.reason, 'overtime-reason');
            row.insertCell().append(createStatus(request.status));
            const actionCell = row.insertCell();
            if (request.status !== 'pending') {
                actionCell.textContent = '—';
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'overtime-button overtime-button-reject';
            button.dataset.cancelLeaveId = String(request.id);
            button.textContent = 'Hủy đơn';
            actionCell.append(button);
        });
    };

    const renderAdminRequests = () => {
        if (!adminRows) return;
        adminRows.replaceChildren();
        const pendingCount = requests.filter((request) => request.status === 'pending').length;
        document.getElementById('leave-pending-count').textContent = String(pendingCount);
        if (requests.length === 0) {
            const row = adminRows.insertRow();
            makeCell(row, 'Chưa có đơn đăng ký nghỉ phép.');
            row.cells[0].colSpan = 6;
            return;
        }
        requests.forEach((request) => {
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
            [['approved', 'Duyệt', 'overtime-button overtime-button-approve'],
             ['rejected', 'Từ chối', 'overtime-button overtime-button-reject']]
                .forEach(([status, label, className]) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = className;
                    button.dataset.leaveStatus = status;
                    button.dataset.leaveId = String(request.id);
                    button.textContent = label;
                    actions.append(button);
                });
            actionCell.append(actions);
        });
    };

    const loadRequests = async () => {
        try {
            const result = await window.lumiCineApi('leave');
            requests = result.requests;
            renderEmployeeRequests();
            renderAdminRequests();
            return true;
        } catch (error) {
            showMessage(error instanceof Error ? error.message : 'Không thể tải đơn nghỉ phép.', true);
            return false;
        }
    };

    if (employeeRows) {
        const dialog = document.getElementById('leave-dialog');
        const form = document.getElementById('leave-form');
        const openButton = document.getElementById('leave-open-form');
        if (openButton) {
            openButton.addEventListener('click', () => {
                const start = new Date();
                start.setHours(start.getHours() + 24, 0, 0, 0);
                const end = new Date(start.getTime());
                end.setHours(17, 0, 0, 0);
                const toLocalInput = (date) => [
                    date.getFullYear(),
                    String(date.getMonth() + 1).padStart(2, '0'),
                    String(date.getDate()).padStart(2, '0'),
                ].join('-') + `T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
                form.elements.start.value = toLocalInput(start);
                form.elements.end.value = toLocalInput(end);
                dialog.showModal();
            });
            ['leave-close-form', 'leave-cancel-form'].forEach((id) => {
                document.getElementById(id).addEventListener('click', () => dialog.close());
            });
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const start = form.elements.start.value;
                const end = form.elements.end.value;
                const reason = form.elements.reason.value.trim();
                if (!reason || reason.length < 5 || !start || !end ||
                    Date.parse(end) < Date.parse(start)) {
                    showMessage('Vui lòng nhập lý do (ít nhất 5 ký tự) và thời gian kết thúc không trước thời gian bắt đầu.', true);
                    return;
                }
                if (Date.parse(start) < Date.now()) {
                    showMessage('Không thể đăng ký nghỉ phép trong quá khứ.', true);
                    return;
                }
                try {
                    await window.lumiCineApi('leave', {
                        method: 'POST',
                        body: { start, end, reason },
                    });
                    form.reset();
                    dialog.close();
                    await loadRequests();
                    showMessage('Đã gửi đơn nghỉ phép để quản trị viên xem xét.');
                } catch (error) {
                    showMessage(error instanceof Error ? error.message : 'Không thể gửi đơn nghỉ phép.', true);
                }
            });
            employeeRows.addEventListener('click', async (event) => {
                const button = event.target.closest('button[data-cancel-leave-id]');
                if (!button || !window.confirm('Bạn có chắc muốn hủy đơn nghỉ phép này?')) return;
                try {
                    await window.lumiCineApi('leave', {
                        method: 'POST',
                        body: { operation: 'cancel', id: Number(button.dataset.cancelLeaveId) },
                    });
                    await loadRequests();
                    showMessage('Đã hủy đơn nghỉ phép.');
                } catch (error) {
                    showMessage(error instanceof Error ? error.message : 'Không thể hủy đơn nghỉ phép.', true);
                }
            });
        }
    }

    if (adminRows) {
        adminRows.addEventListener('click', async (event) => {
            const button = event.target.closest('button[data-leave-status][data-leave-id]');
            if (!button || isLoading) return;
            isLoading = true;
            try {
                await window.lumiCineApi('leave', {
                    method: 'POST',
                    body: {
                        operation: 'review',
                        id: Number(button.dataset.leaveId),
                        status: button.dataset.leaveStatus,
                    },
                });
                await loadRequests();
                showMessage(button.dataset.leaveStatus === 'approved'
                    ? 'Đã duyệt đơn nghỉ phép.'
                    : 'Đã từ chối đơn nghỉ phép.');
            } catch (error) {
                showMessage(error instanceof Error ? error.message : 'Không thể xử lý đơn nghỉ phép.', true);
            } finally {
                isLoading = false;
            }
        });
    }

    loadRequests();
})();
