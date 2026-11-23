(() => {
    'use strict';

    const config = window.employeeScheduleConfig;
    const initialDate = config.initialDate;
    const statusLabels = {
        active: 'Đang diễn ra',
        upcoming: 'Sắp bắt đầu',
        completed: 'Hoàn thành',
    };
    const tones = ['violet', 'blue', 'pink', 'orange', 'green', 'teal'];
    const search = document.getElementById('shift-search');
    const shiftList = document.getElementById('shift-list');
    const filters = [...document.querySelectorAll('.filter-chip')];
    const emptyState = document.getElementById('empty-state');
    const dateInput = document.getElementById('shift-date');
    const dialog = document.getElementById('shift-dialog');
    const form = document.getElementById('shift-form');
    const toastRegion = document.getElementById('toast-region');
    const pageSize = 6;
    let currentFilter = 'all';
    let currentPage = 1;
    let shifts = [];
    let editingId = null;

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

    const getInitials = (name) => name.trim().split(/\s+/).slice(-2)
        .map((part) => Array.from(part)[0]).join('').toLocaleUpperCase('vi');

    const getDuration = (start, end) => {
        const [startHour, startMinute] = start.split(':').map(Number);
        const [endHour, endMinute] = end.split(':').map(Number);
        let minutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute);
        if (minutes <= 0) minutes += 24 * 60;
        return `${Math.floor(minutes / 60)} giờ${minutes % 60 ? ` ${minutes % 60} phút` : ''}`;
    };

    const makeElement = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };

    const createRow = (shift, index) => {
        const row = document.createElement('tr');
        row.dataset.shiftId = shift.id;
        row.dataset.status = shift.status;

        const employeeCell = row.insertCell();
        const employee = makeElement('div', 'employee-cell');
        employee.append(makeElement('span', `employee-avatar ${tones[index % tones.length]}`, getInitials(shift.name)));
        const identity = makeElement('span', 'employee-identity');
        identity.append(makeElement('strong', '', shift.name), makeElement('small', '', shift.role));
        employee.append(identity);
        employeeCell.append(employee);

        row.insertCell().textContent = shift.department;
        const timeCell = row.insertCell();
        timeCell.append(
            makeElement('strong', 'shift-time', `${shift.start.slice(0, 5)} – ${shift.end.slice(0, 5)}`),
            makeElement('small', 'shift-duration', getDuration(shift.start, shift.end))
        );
        row.insertCell().textContent = shift.location || '—';
        const statusCell = row.insertCell();
        statusCell.append(makeElement('span', `shift-status ${shift.status}`, statusLabels[shift.status]));

        const actionCell = row.insertCell();
        if (config.canManage) {
            const menuButton = makeElement('button', 'row-menu');
            menuButton.type = 'button';
            menuButton.setAttribute('aria-label', `Tùy chọn ca của ${shift.name}`);
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.append(makeElement('i', 'bi bi-three-dots'));
            actionCell.append(menuButton);
        }
        return row;
    };

    const closeRowMenus = () => {
        document.querySelectorAll('.action-menu').forEach((menu) => menu.remove());
        document.querySelectorAll('.row-menu[aria-expanded="true"]').forEach((button) => {
            button.setAttribute('aria-expanded', 'false');
        });
    };

    const renderPagination = (pageCount) => {
        const pagination = document.getElementById('pagination');
        pagination.replaceChildren();
        const addButton = (label, page, options = {}) => {
            const button = makeElement('button', `page-button${options.current ? ' current' : ''}`, label);
            button.type = 'button';
            button.disabled = Boolean(options.disabled);
            if (options.current) button.setAttribute('aria-current', 'page');
            if (options.label) button.setAttribute('aria-label', options.label);
            button.addEventListener('click', () => {
                currentPage = page;
                updateView();
            });
            pagination.append(button);
        };
        addButton('‹', Math.max(1, currentPage - 1), { disabled: currentPage === 1, label: 'Trang trước' });
        for (let page = 1; page <= pageCount; page += 1) {
            addButton(String(page), page, { current: page === currentPage, label: `Trang ${page}` });
        }
        addButton('›', Math.min(pageCount, currentPage + 1), { disabled: currentPage === pageCount, label: 'Trang tiếp theo' });
    };

    const updateSummary = () => {
        const counts = { active: 0, upcoming: 0, completed: 0 };
        shifts.forEach((shift) => { counts[shift.status] += 1; });
        document.getElementById('summary-total').textContent = String(shifts.length);
        document.getElementById('summary-active').textContent = String(counts.active);
        document.getElementById('summary-upcoming').textContent = String(counts.upcoming);
        document.getElementById('summary-completed').textContent = String(counts.completed);
        const notificationIndicator = document.getElementById('notification-indicator');
        if (notificationIndicator) notificationIndicator.hidden = counts.upcoming === 0;
        document.querySelectorAll('[data-count]').forEach((element) => {
            const status = element.dataset.count;
            element.textContent = String(status === 'all' ? shifts.length : counts[status]);
        });
    };

    const updateView = () => {
        const query = search.value.trim().toLocaleLowerCase('vi');
        const matching = shifts.filter((shift) => {
            const matchesStatus = currentFilter === 'all' || shift.status === currentFilter;
            const searchText = `${shift.name} ${shift.role} ${shift.department} ${shift.location}`.toLocaleLowerCase('vi');
            return matchesStatus && searchText.includes(query);
        });
        const pageCount = Math.max(1, Math.ceil(matching.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const startIndex = (currentPage - 1) * pageSize;
        const visible = matching.slice(startIndex, startIndex + pageSize);
        shiftList.replaceChildren(...visible.map((shift, index) => createRow(shift, startIndex + index)));
        emptyState.hidden = matching.length > 0;
        shiftList.closest('table').hidden = matching.length === 0;
        document.getElementById('result-count').textContent =
            `Hiển thị ${matching.length ? startIndex + 1 : 0}–${Math.min(startIndex + pageSize, matching.length)} trong ${matching.length} ca`;
        updateSummary();
        renderPagination(pageCount);
    };

    const setCurrentFilter = (filter) => {
        currentFilter = filter;
        currentPage = 1;
        filters.forEach((button) => button.classList.toggle('active', button.dataset.filter === filter));
        updateView();
    };

    const loadSelectedDate = async () => {
        try {
            const result = await window.lumiCineApi('schedule', {
                query: { date: dateInput.value },
            });
            shifts = result.assignments;
            currentPage = 1;
            updateView();
            return true;
        } catch (error) {
            shifts = [];
            updateView();
            notify(error instanceof Error ? error.message : 'Không thể tải lịch làm việc.', true);
            return false;
        }
    };

    const openShiftDialog = (shift = null) => {
        editingId = shift ? shift.id : null;
        form.reset();
        document.getElementById('dialog-title').textContent = shift ? 'Chỉnh sửa ca làm việc' : 'Tạo ca làm việc';
        document.getElementById('save-shift').textContent = shift ? 'Lưu thay đổi' : 'Tạo ca';
        if (shift) {
            form.elements.employeeId.value = String(shift.employee_id);
            form.elements.start.value = shift.start.slice(0, 5);
            form.elements.end.value = shift.end.slice(0, 5);
            form.elements.location.value = shift.location;
        }
        dialog.showModal();
        form.elements.employeeId.focus();
    };

    const downloadReport = () => {
        const rows = [
            ['Nhân viên', 'Chức vụ', 'Bộ phận', 'Thời gian', 'Thời lượng', 'Địa điểm', 'Trạng thái'],
            ...shifts.map((shift) => [
                shift.name,
                shift.role,
                shift.department,
                `${shift.start} - ${shift.end}`,
                getDuration(shift.start, shift.end),
                shift.location,
                statusLabels[shift.status],
            ]),
        ];
        const csv = `\uFEFF${rows.map((row) =>
            row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(',')
        ).join('\r\n')}`;
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `lumicine-lich-lam-viec-${dateInput.value}.csv`;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        notify('Đã tải báo cáo ca làm việc dạng CSV.');
    };

    filters.forEach((button) => button.addEventListener('click', () => setCurrentFilter(button.dataset.filter)));
    search.addEventListener('input', () => {
        currentPage = 1;
        updateView();
    });
    dateInput.addEventListener('change', loadSelectedDate);
    ['previous-day', 'next-day'].forEach((id) => {
        document.getElementById(id).addEventListener('click', () => {
            const selected = new Date(`${dateInput.value}T12:00:00`);
            selected.setDate(selected.getDate() + (id === 'next-day' ? 1 : -1));
            dateInput.value = [
                selected.getFullYear(),
                String(selected.getMonth() + 1).padStart(2, '0'),
                String(selected.getDate()).padStart(2, '0'),
            ].join('-');
            loadSelectedDate();
        });
    });

    if (config.canManage) {
        document.getElementById('add-shift').addEventListener('click', () => openShiftDialog());
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const saveButton = document.getElementById('save-shift');
            saveButton.disabled = true;
            try {
                const wasEditing = Boolean(editingId);
                await window.lumiCineApi('schedule', {
                    method: 'POST',
                    body: {
                        id: editingId,
                        employeeId: form.elements.employeeId.value,
                        date: dateInput.value,
                        start: form.elements.start.value,
                        end: form.elements.end.value,
                        location: form.elements.location.value.trim(),
                    },
                });
                dialog.close();
                if (await loadSelectedDate()) {
                    notify(wasEditing ? 'Đã cập nhật ca làm việc.' : 'Đã thêm ca làm việc.');
                } else {
                    notify('Ca đã được lưu, nhưng không thể tải lại lịch từ database.', true);
                }
            } catch (error) {
                notify(error instanceof Error ? error.message : 'Không thể lưu ca làm việc.', true);
            } finally {
                saveButton.disabled = false;
            }
        });
        shiftList.addEventListener('click', async (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const row = button.closest('tr');
            const shift = shifts.find((item) => item.id === row?.dataset.shiftId);
            if (!shift) return;

            if (button.classList.contains('row-menu')) {
                const wasOpen = button.getAttribute('aria-expanded') === 'true';
                closeRowMenus();
                if (wasOpen) return;
                button.setAttribute('aria-expanded', 'true');
                const menu = makeElement('div', 'action-menu');
                const edit = makeElement('button', '', 'Chỉnh sửa ca');
                edit.type = 'button';
                edit.dataset.action = 'edit';
                const remove = makeElement('button', 'danger', 'Hủy ca làm việc');
                remove.type = 'button';
                remove.dataset.action = 'cancel';
                menu.append(edit, remove);
                button.after(menu);
                return;
            }

            closeRowMenus();
            if (button.dataset.action === 'edit') {
                openShiftDialog(shift);
            } else if (button.dataset.action === 'cancel' &&
                window.confirm(`Bạn có chắc muốn hủy ca của ${shift.name}?`)) {
                try {
                    await window.lumiCineApi('schedule', {
                        method: 'POST',
                        body: { operation: 'cancel', id: shift.id },
                    });
                    if (await loadSelectedDate()) {
                        notify('Đã hủy ca làm việc.');
                    } else {
                        notify('Ca đã được hủy, nhưng không thể tải lại lịch từ database.', true);
                    }
                } catch (error) {
                    notify(error instanceof Error ? error.message : 'Không thể hủy ca làm việc.', true);
                }
            }
        });
    }

    document.getElementById('close-dialog').addEventListener('click', () => dialog.close());
    document.getElementById('cancel-dialog').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.shift-actions')) closeRowMenus();
        if (!event.target.closest('.topbar-account')) {
            const menu = document.getElementById('account-menu');
            if (menu) menu.hidden = true;
            document.getElementById('account-menu-button').setAttribute('aria-expanded', 'false');
        }
    });
    document.getElementById('notifications-button').addEventListener('click', () => {
        window.location.href = '?action=notifications';
    });
    document.getElementById('account-menu-button').addEventListener('click', () => {
        const menu = document.getElementById('account-menu');
        menu.hidden = !menu.hidden;
        document.getElementById('account-menu-button').setAttribute('aria-expanded', String(!menu.hidden));
    });
    document.getElementById('export-report').addEventListener('click', downloadReport);

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
            if (link.classList.contains('employee-nav-link')) {
                document.querySelectorAll('.employee-nav-link').forEach((navLink) => {
                    navLink.classList.toggle('active', navLink === link);
                });
            }
            if (window.innerWidth <= 740 && sidebar.classList.contains('is-open')) toggleSidebar();
        });
    });

    loadSelectedDate();
})();
