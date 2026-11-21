(() => {
    'use strict';

    const dateInput = document.getElementById('schedule-date');
    const search = document.getElementById('schedule-search');
    const rows = document.getElementById('schedule-rows');
    const dialog = document.getElementById('schedule-dialog');
    const form = document.getElementById('schedule-form');
    let assignments = window.scheduleInitialData || [];
    let currentStatus = 'all';
    let editingId = null;

    const statusLabel = (status) => status === 'active' ? 'Đang diễn ra' : (status === 'upcoming' ? 'Sắp bắt đầu' : 'Hoàn thành');
    const initials = (name) => Array.from(String(name).trim())[0] || '?';

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
                <td>${escapeHtml(item.department)}</td><td><strong>${escapeHtml(item.shift_name || `Ca ${index + 1}`)}</strong></td>
                <td><strong>${escapeHtml(item.start.slice(0, 5))} - ${escapeHtml(item.end.slice(0, 5))}</strong></td>
                <td>${escapeHtml(item.location || '—')}</td>
                <td><span class="schedule-status ${escapeHtml(item.status)}">${statusLabel(item.status)}</span></td>
                <td><div class="schedule-row-actions"><button type="button" data-action="edit" title="Chỉnh sửa"><i class="bi bi-pencil"></i></button><button type="button" data-action="cancel" title="Hủy ca"><i class="bi bi-trash3"></i></button></div></td>
            </tr>`).join('') :
            '<tr class="schedule-empty-row"><td colspan="7">Không tìm thấy ca làm việc phù hợp.</td></tr>';
        document.getElementById('schedule-result-count').textContent = `Hiển thị ${visible.length} ca`;
        updateSummary();
    };

    const loadDate = async () => {
        try {
            const result = await window.lumiCineApi('schedule', { query: { date: dateInput.value } });
            assignments = result.assignments;
            document.getElementById('schedule-date-caption').textContent =
                `Ca làm việc ngày ${new Date(`${dateInput.value}T12:00:00`).toLocaleDateString('vi-VN')}`;
            render();
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể tải lịch làm việc.');
        }
    };

    const openDialog = (assignment = null) => {
        editingId = assignment ? assignment.id : null;
        form.reset();
        document.getElementById('schedule-dialog-title').textContent = assignment ? 'Chỉnh sửa ca làm việc' : 'Tạo ca làm việc';
        document.getElementById('schedule-save').textContent = assignment ? 'Lưu thay đổi' : 'Lưu ca làm việc';
        if (assignment) {
            form.elements.employeeId.value = String(assignment.employee_id);
            form.elements.shiftId.value = String(assignment.shift_id);
            form.elements.location.value = assignment.location;
        }
        dialog.showModal();
    };

    document.getElementById('schedule-create').addEventListener('click', () => openDialog());
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
    ['schedule-prev', 'schedule-next'].forEach((id) => document.getElementById(id).addEventListener('click', () => {
        const date = new Date(`${dateInput.value}T12:00:00`);
        date.setDate(date.getDate() + (id === 'schedule-next' ? 1 : -1));
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
                    date: dateInput.value,
                    shiftId: form.elements.shiftId.value,
                    location: form.elements.location.value.trim(),
                },
            });
            dialog.close();
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
        const header = ['Nhân viên', 'Bộ phận', 'Chức vụ', 'Thời gian', 'Địa điểm', 'Trạng thái'];
        const csvRows = assignments.map((item) => [item.name, item.department, item.role, `${item.start}-${item.end}`, item.location, statusLabel(item.status)]);
        const csv = '\uFEFF' + [header, ...csvRows].map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        link.download = `lumicine-phan-ca-${dateInput.value}.csv`;
        document.body.append(link);
        link.click();
        link.remove();
        notify('Đã xuất danh sách ca làm việc.');
    });

    document.getElementById('schedule-date-caption').textContent =
        `Ca làm việc ngày ${new Date(`${dateInput.value}T12:00:00`).toLocaleDateString('vi-VN')}`;
    render();
})();
