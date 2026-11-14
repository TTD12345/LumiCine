(() => {
    'use strict';

    const config = window.employeeScheduleConfig;
    const initialDate = config.initialDate;
    const initialShifts = JSON.parse(document.getElementById('shift-data').textContent);
    const storagePrefix = 'lumicine-shifts:';
    const accountStorageKey = 'lumicine-admin-name';
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

    const normalizeShift = (shift) => {
        if (!shift || typeof shift.id !== 'string' || typeof shift.name !== 'string' ||
            typeof shift.department !== 'string' || typeof shift.role !== 'string' ||
            typeof shift.location !== 'string' || typeof shift.start !== 'string' ||
            typeof shift.end !== 'string' || !statusLabels[shift.status]) {
            throw new Error('Dữ liệu ca làm việc đã lưu không đúng định dạng.');
        }
        return shift;
    };

    const readShifts = (date) => {
        const saved = localStorage.getItem(`${storagePrefix}${date}`);
        if (saved === null && date !== initialDate) return [];
        const source = saved === null ? JSON.stringify(initialShifts) : saved;
        const parsed = JSON.parse(source);
        if (!Array.isArray(parsed)) {
            throw new Error('Danh sách ca làm việc không đúng định dạng.');
        }
        return parsed.map(normalizeShift);
    };

    const persistShifts = (nextShifts) => {
        try {
            localStorage.setItem(`${storagePrefix}${dateInput.value}`, JSON.stringify(nextShifts));
            return true;
        } catch (error) {
            notify('Không thể lưu dữ liệu trên trình duyệt. Hãy kiểm tra dung lượng lưu trữ và thử lại.', true);
            return false;
        }
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
        employee.append(makeElement('span', `employee-avatar ${shift.tone || tones[index % tones.length]}`, getInitials(shift.name)));
        const identity = makeElement('span', 'employee-identity');
        identity.append(
            makeElement('strong', '', shift.name),
            makeElement('small', '', shift.role)
        );
        employee.append(identity);
        employeeCell.append(employee);

        row.insertCell().textContent = shift.department;
        const timeCell = row.insertCell();
        timeCell.append(
            makeElement('strong', 'shift-time', `${shift.start} – ${shift.end}`),
            makeElement('small', 'shift-duration', getDuration(shift.start, shift.end))
        );
        row.insertCell().textContent = shift.location;
        const statusCell = row.insertCell();
        statusCell.append(makeElement('span', `shift-status ${shift.status}`, statusLabels[shift.status]));

        const actionCell = row.insertCell();
        const actionWrap = makeElement('div', 'shift-actions');
        const menuButton = makeElement('button', 'row-menu');
        menuButton.type = 'button';
        menuButton.setAttribute('aria-label', `Tùy chọn ca của ${shift.name}`);
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.append(makeElement('i', 'bi bi-three-dots'));
        actionWrap.append(menuButton);
        actionCell.append(actionWrap);
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

    const loadSelectedDate = () => {
        try {
            shifts = readShifts(dateInput.value);
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Không thể đọc dữ liệu lịch làm việc.', true);
            shifts = [];
        }
        currentPage = 1;
        updateView();
    };

    const openShiftDialog = (shift = null) => {
        editingId = shift ? shift.id : null;
        form.reset();
        document.getElementById('dialog-title').textContent = shift ? 'Chỉnh sửa ca làm việc' : 'Tạo ca làm việc';
        document.getElementById('save-shift').textContent = shift ? 'Lưu thay đổi' : 'Tạo ca';
        if (shift) {
            Object.entries({
                name: shift.name,
                department: shift.department,
                role: shift.role,
                start: shift.start,
                end: shift.end,
                location: shift.location,
                status: shift.status,
            }).forEach(([name, value]) => { form.elements[name].value = value; });
        }
        dialog.showModal();
        form.elements.name.focus();
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

    filters.forEach((button) => {
        button.addEventListener('click', () => setCurrentFilter(button.dataset.filter));
    });
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

    document.getElementById('add-shift').addEventListener('click', () => openShiftDialog());
    document.getElementById('close-dialog').addEventListener('click', () => dialog.close());
    document.getElementById('cancel-dialog').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const fields = new FormData(form);
        const name = String(fields.get('name') || '').trim();
        if (!name) return;
        const wasEditing = Boolean(editingId);
        const existingShift = shifts.find((shift) => shift.id === editingId);
        const shift = {
            id: editingId || `shift-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            name,
            department: String(fields.get('department')).trim(),
            role: String(fields.get('role')).trim(),
            start: String(fields.get('start')),
            end: String(fields.get('end')),
            location: String(fields.get('location')).trim(),
            status: String(fields.get('status')),
            tone: existingShift?.tone || tones[shifts.length % tones.length],
        };
        const nextShifts = wasEditing
            ? shifts.map((item) => item.id === editingId ? shift : item)
            : [...shifts, shift];
        if (!persistShifts(nextShifts)) return;
        shifts = nextShifts;
        dialog.close();
        currentPage = 1;
        updateView();
        notify(wasEditing ? 'Đã cập nhật ca làm việc.' : 'Đã thêm ca làm việc.');
    });

    shiftList.addEventListener('click', (event) => {
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
            const addAction = (label, action, danger = false) => {
                const actionButton = makeElement('button', danger ? 'danger' : '', label);
                actionButton.type = 'button';
                actionButton.dataset.action = action;
                menu.append(actionButton);
            };
            addAction('Chỉnh sửa ca', 'edit');
            addAction('Đánh dấu đang diễn ra', 'active');
            addAction('Đánh dấu sắp bắt đầu', 'upcoming');
            addAction('Đánh dấu hoàn thành', 'completed');
            addAction('Xóa ca làm việc', 'delete', true);
            button.after(menu);
            return;
        }

        const action = button.dataset.action;
        if (action === 'edit') {
            closeRowMenus();
            openShiftDialog(shift);
        } else if (action === 'delete') {
            closeRowMenus();
            if (!window.confirm(`Bạn có chắc muốn xóa ca của ${shift.name}?`)) return;
            const nextShifts = shifts.filter((item) => item.id !== shift.id);
            if (!persistShifts(nextShifts)) return;
            shifts = nextShifts;
            updateView();
            notify('Đã xóa ca làm việc.');
        } else if (statusLabels[action]) {
            closeRowMenus();
            const nextShifts = shifts.map((item) => item.id === shift.id ? { ...item, status: action } : item);
            if (!persistShifts(nextShifts)) return;
            shifts = nextShifts;
            updateView();
            notify(`Đã chuyển ca sang trạng thái "${statusLabels[action]}".`);
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.shift-actions')) closeRowMenus();
        if (!event.target.closest('.employee-profile')) {
            document.getElementById('account-menu').hidden = true;
        }
    });
    document.getElementById('notifications-button').addEventListener('click', () => {
        const upcoming = shifts.filter((shift) => shift.status === 'upcoming').length;
        notify(upcoming ? `Hôm nay còn ${upcoming} ca sắp bắt đầu.` : 'Không có ca nào sắp bắt đầu.');
    });
    document.getElementById('account-menu-button').addEventListener('click', () => {
        const menu = document.getElementById('account-menu');
        menu.hidden = !menu.hidden;
    });

    const accountDialog = document.getElementById('account-dialog');
    const accountForm = document.getElementById('account-form');
    const accountName = document.getElementById('account-name');
    const accountMenu = document.getElementById('account-menu');
    const profileNames = [
        document.getElementById('sidebar-account-name'),
        document.getElementById('top-account-name'),
    ];
    try {
        const savedAccountName = localStorage.getItem(accountStorageKey);
        if (savedAccountName) profileNames.forEach((element) => { element.textContent = savedAccountName; });
    } catch (error) {
        notify('Không thể đọc tên tài khoản đã lưu.', true);
    }

    document.getElementById('edit-account').addEventListener('click', () => {
        accountMenu.hidden = true;
        accountName.value = profileNames[0].textContent;
        accountDialog.showModal();
    });
    document.getElementById('close-account-dialog').addEventListener('click', () => accountDialog.close());
    document.getElementById('cancel-account-dialog').addEventListener('click', () => accountDialog.close());
    accountForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const name = accountName.value.trim();
        if (!name) return;
        try {
            localStorage.setItem(accountStorageKey, name);
        } catch (error) {
            notify('Không thể lưu tên tài khoản trong trình duyệt.', true);
            return;
        }
        profileNames.forEach((element) => { element.textContent = name; });
        accountDialog.close();
        notify('Đã cập nhật tên hiển thị.');
    });

    document.getElementById('reset-sample-data').addEventListener('click', () => {
        accountMenu.hidden = true;
        if (!window.confirm('Khôi phục danh sách ca mẫu cho ngày hiện tại? Dữ liệu đã lưu của ngày này sẽ bị thay thế.')) return;
        const defaults = dateInput.value === initialDate
            ? JSON.parse(JSON.stringify(initialShifts)).map(normalizeShift)
            : [];
        if (!persistShifts(defaults)) return;
        shifts = defaults;
        search.value = '';
        setCurrentFilter('all');
        notify('Đã khôi phục danh sách ca mẫu.');
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
            if (window.innerWidth <= 740 && sidebar.classList.contains('is-open')) toggleSidebar();
        });
    });

    loadSelectedDate();
})();
