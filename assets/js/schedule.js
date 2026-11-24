(() => {
    'use strict';

    const dateInput = document.getElementById('schedule-date');
    const search = document.getElementById('schedule-search');
    const rows = document.getElementById('schedule-rows');
    const dialog = document.getElementById('schedule-dialog');
    const form = document.getElementById('schedule-form');
    const shiftTypesDialog = document.getElementById('shift-types-dialog');
    const shiftTypesList = document.getElementById('shift-types-list');
    const viewSelect = document.getElementById('schedule-view');
    const departmentFilter = document.getElementById('schedule-department-filter');
    const employeeFilter = document.getElementById('schedule-employee-filter');
    let assignments = window.scheduleInitialData || [];
    let currentStatus = 'all';
    let editingId = null;
    let editingDate = null;

    const statusLabel = (status) => status === 'active' ? 'Đang diễn ra' : (status === 'upcoming' ? 'Sắp bắt đầu' : 'Hoàn thành');
    const initials = (name) => Array.from(String(name).trim())[0] || '?';
    const formatDate = (date) => new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));

    const getDateRange = () => {
        const anchor = new Date(`${dateInput.value}T12:00:00`);
        let from = new Date(anchor);
        let to = new Date(anchor);
        if (viewSelect.value === 'week') {
            from.setDate(from.getDate() - ((from.getDay() + 6) % 7));
            to = new Date(from);
            to.setDate(to.getDate() + 6);
        } else if (viewSelect.value === 'month') {
            from = new Date(anchor.getFullYear(), anchor.getMonth(), 1, 12);
            to = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0, 12);
        }
        const toIso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        return { from: toIso(from), to: toIso(to) };
    };

    const dateCaption = () => {
        const range = getDateRange();
        return range.from === range.to
            ? `Ca làm việc ngày ${formatDate(range.from)}`
            : `Ca làm việc từ ${formatDate(range.from)} đến ${formatDate(range.to)}`;
    };

    const notify = (message) => {
        const toast = document.createElement('div');
        toast.className = 'hrm-toast';
        toast.textContent = message;
        document.getElementById('toast-container').append(toast);
        window.setTimeout(() => toast.remove(), 4000);
    };

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[character]);

    const updateSummary = () => {
        const counts = { active: 0, upcoming: 0, completed: 0 };
        assignments.forEach((item) => { counts[item.status] = (counts[item.status] || 0) + 1; });
        document.getElementById('schedule-total').textContent = assignments.length;
        document.getElementById('schedule-active').textContent = counts.active;
        document.getElementById('schedule-upcoming').textContent = counts.upcoming;
        document.getElementById('filter-all').textContent = assignments.length;
        document.getElementById('filter-active').textContent = counts.active;
        document.getElementById('filter-upcoming').textContent = counts.upcoming;
        document.getElementById('filter-completed').textContent = counts.completed;
    };

    const render = () => {
        const query = search.value.trim().toLocaleLowerCase('vi');
        const visible = assignments.filter((item) => {
            const matchesStatus = currentStatus === 'all' || item.status === currentStatus;
            const haystack = `${item.name} ${item.role} ${item.department} ${item.location}`.toLocaleLowerCase('vi');
            return matchesStatus && haystack.includes(query);
        });
        rows.innerHTML = visible.length ? visible.map((item, index) => `
            <tr data-assignment-id="${escapeHtml(item.id)}">
                <td><div class="schedule-employee"><span>${escapeHtml(initials(item.name))}</span><div><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.role)}</small></div></div></td>
                <td>${escapeHtml(formatDate(item.work_date))}</td><td>${escapeHtml(item.department)}</td><td><strong>${escapeHtml(item.shift_name || `Ca ${index + 1}`)}</strong></td>
                <td><strong>${escapeHtml(item.start.slice(0, 5))} - ${escapeHtml(item.end.slice(0, 5))}</strong></td>
                <td>${escapeHtml(item.location || '—')}</td>
                <td><span class="schedule-status ${escapeHtml(item.status)}">${statusLabel(item.status)}</span></td>
                <td><div class="schedule-row-actions"><button type="button" data-action="edit" title="Chỉnh sửa"><i class="bi bi-pencil"></i></button><button type="button" data-action="cancel" title="Hủy ca"><i class="bi bi-trash3"></i></button></div></td>
            </tr>`).join('') :
            '<tr class="schedule-empty-row"><td colspan="8">Không tìm thấy ca làm việc phù hợp.</td></tr>';
        document.getElementById('schedule-result-count').textContent = `Hiển thị ${visible.length} ca`;
        updateSummary();
    };

    const loadDate = async () => {
        try {
            const range = getDateRange();
            const result = await window.lumiCineApi('schedule', {
                query: {
                    from: range.from,
                    to: range.to,
                    departmentId: departmentFilter.value,
                    employeeId: employeeFilter.value,
                },
            });
            assignments = result.assignments;
            document.getElementById('schedule-date-caption').textContent = dateCaption();
            render();
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể tải lịch làm việc.');
        }
    };

    const openDialog = (assignment = null) => {
        editingId = assignment ? assignment.id : null;
        editingDate = assignment ? assignment.work_date : dateInput.value;
        form.reset();
        document.getElementById('schedule-dialog-title').textContent = assignment
            ? `Chỉnh sửa ca làm việc · ${formatDate(assignment.work_date)}`
            : `Tạo ca làm việc · ${formatDate(dateInput.value)}`;
        document.getElementById('schedule-save').textContent = assignment ? 'Lưu thay đổi' : 'Lưu ca làm việc';
        if (assignment) {
            form.elements.employeeId.value = String(assignment.employee_id);
            form.elements.shiftId.value = String(assignment.shift_id);
            form.elements.location.value = assignment.location;
        }
        dialog.showModal();
    };

    const renderShiftTypes = (shiftTypes) => {
        shiftTypesList.innerHTML = shiftTypes.length ? shiftTypes.map((shift) => `
            <div class="shift-type-row ${Number(shift.active) === 1 ? '' : 'inactive'}">
                <div>
                    <strong>${escapeHtml(shift.name)}</strong>
                    <small>${escapeHtml(shift.start.slice(0, 5))} - ${escapeHtml(shift.end.slice(0, 5))}</small>
                </div>
                <button type="button"
                    class="shift-type-toggle ${Number(shift.active) === 1 ? 'active' : ''}"
                    data-shift-id="${escapeHtml(shift.id)}"
                    data-active="${Number(shift.active) === 1 ? '1' : '0'}">
                    ${Number(shift.active) === 1 ? 'Ngừng hoạt động' : 'Kích hoạt'}
                </button>
            </div>`).join('') :
            '<div class="schedule-empty-row">Chưa có loại ca làm việc.</div>';
    };

    const openShiftTypesDialog = async () => {
        try {
            const result = await window.lumiCineApi('schedule', {
                query: { date: dateInput.value, includeInactive: '1' },
            });
            renderShiftTypes(result.shiftTypes);
            shiftTypesDialog.showModal();
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể tải danh sách loại ca.');
        }
    };

    document.getElementById('schedule-create').addEventListener('click', () => openDialog());
    document.getElementById('schedule-manage-shifts').addEventListener('click', openShiftTypesDialog);
    document.getElementById('shift-types-close').addEventListener('click', () => shiftTypesDialog.close());
    document.getElementById('shift-types-cancel').addEventListener('click', () => shiftTypesDialog.close());
    shiftTypesDialog.addEventListener('click', (event) => { if (event.target === shiftTypesDialog) shiftTypesDialog.close(); });
    shiftTypesList.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-shift-id]');
        if (!button) return;
        button.disabled = true;
        try {
            const result = await window.lumiCineApi('schedule', {
                method: 'POST',
                body: {
                    operation: 'shift-status',
                    id: Number(button.dataset.shiftId),
                    active: button.dataset.active !== '1',
                },
            });
            renderShiftTypes(result.shiftTypes);
            await loadDate();
            notify(button.dataset.active === '1' ? 'Đã ngừng hoạt động loại ca.' : 'Đã kích hoạt lại loại ca.');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể cập nhật trạng thái loại ca.');
        } finally {
            button.disabled = false;
        }
    });
    document.getElementById('schedule-dialog-close').addEventListener('click', () => dialog.close());
    document.getElementById('schedule-dialog-cancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    search.addEventListener('input', render);
    document.querySelectorAll('.schedule-filters button').forEach((button) => button.addEventListener('click', () => {
        currentStatus = button.dataset.status;
        document.querySelectorAll('.schedule-filters button').forEach((item) => item.classList.toggle('active', item === button));
        render();
    }));
    dateInput.addEventListener('change', loadDate);
    viewSelect.addEventListener('change', loadDate);
    departmentFilter.addEventListener('change', loadDate);
    employeeFilter.addEventListener('change', loadDate);
    ['schedule-prev', 'schedule-next'].forEach((id) => document.getElementById(id).addEventListener('click', () => {
        const date = new Date(`${dateInput.value}T12:00:00`);
        const direction = id === 'schedule-next' ? 1 : -1;
        if (viewSelect.value === 'month') {
            date.setDate(1);
            date.setMonth(date.getMonth() + direction);
        } else {
            date.setDate(date.getDate() + direction * (viewSelect.value === 'week' ? 7 : 1));
        }
        dateInput.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        loadDate();
    }));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.getElementById('schedule-save');
        button.disabled = true;
        try {
            await window.lumiCineApi('schedule', {
                method: 'POST',
                body: {
                    id: editingId,
                    employeeId: form.elements.employeeId.value,
                    date: editingDate || dateInput.value,
                    shiftId: form.elements.shiftId.value,
                    location: form.elements.location.value.trim(),
                },
            });
            dialog.close();
            editingDate = null;
            await loadDate();
            notify(editingId ? 'Đã cập nhật ca làm việc.' : 'Đã tạo ca làm việc.');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể lưu ca làm việc.');
        } finally {
            button.disabled = false;
        }
    });

    rows.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        const row = button.closest('[data-assignment-id]');
        const assignment = assignments.find((item) => String(item.id) === row.dataset.assignmentId);
        if (!assignment) return;
        if (button.dataset.action === 'edit') {
            openDialog(assignment);
        } else if (window.confirm(`Bạn có chắc muốn hủy ca của ${assignment.name}?`)) {
            try {
                await window.lumiCineApi('schedule', { method: 'POST', body: { operation: 'cancel', id: assignment.id } });
                await loadDate();
                notify('Đã hủy ca làm việc.');
            } catch (error) {
                notify(error instanceof Error ? error.message : 'Không thể hủy ca làm việc.');
            }
        }
    });

    document.getElementById('schedule-export').addEventListener('click', () => {
        const header = ['Nhân viên', 'Ngày làm', 'Bộ phận', 'Chức vụ', 'Ca làm', 'Thời gian', 'Địa điểm', 'Trạng thái'];
        const csvRows = assignments.map((item) => [item.name, item.work_date, item.department, item.role, item.shift_name, `${item.start}-${item.end}`, item.location, statusLabel(item.status)]);
        const csv = '\uFEFF' + [header, ...csvRows].map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        link.download = `lumicine-phan-ca-${dateInput.value}.csv`;
        document.body.append(link);
        link.click();
        link.remove();
        notify('Đã xuất danh sách ca làm việc.');
    });

    document.getElementById('schedule-date-caption').textContent = dateCaption();
    render();
})();
