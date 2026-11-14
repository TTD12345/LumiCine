(() => {
    'use strict';

    const dataElement = document.getElementById('attendance-data');
    const config = window.hrmAttendanceConfig;
    const baseEmployees = JSON.parse(dataElement.textContent);
    const monthSelect = document.getElementById('month-select');
    const yearSelect = document.getElementById('year-select');
    const searchInput = document.getElementById('employee-search');
    const departmentFilter = document.getElementById('department-filter');
    const positionFilter = document.getElementById('position-filter');
    const statusFilter = document.getElementById('status-filter');
    const rowsElement = document.getElementById('employee-rows');
    const totalsElement = document.getElementById('employee-totals');
    const emptyState = document.getElementById('empty-state');
    const pagination = document.getElementById('pagination');
    const pageSizeSelect = document.getElementById('page-size');
    const toastContainer = document.getElementById('toast-container');
    const monthNames = [
        'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6',
        'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12',
    ];
    const statusLabels = {
        complete: 'Đủ công',
        incomplete: 'Thiếu công',
        leave: 'Nghỉ phép',
        absent: 'Vắng mặt',
    };

    let selectedMonth = Number(config.month);
    let selectedYear = Number(config.year);
    let currentPage = 1;
    let employees = [];
    let filteredEmployees = [];
    let modalInstance = null;

    const formatNumber = (value) => new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 1,
    }).format(value);

    const normalizeText = (value) => String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLocaleLowerCase('vi');

    const getWorkingDays = (year, month) => {
        let count = 0;
        const date = new Date(year, month - 1, 1);
        while (date.getMonth() === month - 1) {
            const day = date.getDay();
            if (day !== 0 && day !== 6) count += 1;
            date.setDate(date.getDate() + 1);
        }
        return count;
    };

    const getStatus = (employee, workingDays) => {
        if (employee.absent >= 2) return 'absent';
        if (employee.leave >= 2 && employee.worked < workingDays - 1) return 'leave';
        if (employee.absent === 0 && employee.leave === 0 && employee.worked >= workingDays - 1) {
            return 'complete';
        }
        return 'incomplete';
    };

    // Điều chỉnh dữ liệu mẫu theo số ngày làm việc của tháng đang chọn.
    const buildMonthlyEmployees = () => {
        const workingDays = getWorkingDays(selectedYear, selectedMonth);
        const monthOffset = selectedMonth - 1;

        return baseEmployees.map((employee, index) => {
            const variation = ((monthOffset + index) % 3) - 1;
            const leave = employee.leave;
            const absent = Math.max(0, employee.absent + ((monthOffset + index) % 7 === 0 ? 1 : 0));
            const availableDays = Math.max(0, workingDays - leave - absent);
            const worked = Math.min(availableDays, Math.max(0, employee.worked + variation));

            const monthlyEmployee = {
                ...employee,
                workingDays,
                worked,
                leave,
                absent,
                late: Math.max(0, employee.late + ((monthOffset + index) % 4 === 0 ? 1 : 0)),
                overtime: Math.max(0, employee.overtime + ((monthOffset + index) % 2 === 0 ? 0.5 : 0)),
            };
            monthlyEmployee.status = getStatus(monthlyEmployee, workingDays);
            return monthlyEmployee;
        });
    };

    const addCell = (row, value, className = '') => {
        const cell = document.createElement('td');
        if (className) cell.className = className;
        cell.textContent = value;
        row.append(cell);
        return cell;
    };

    const getInitial = (name) => Array.from(name.trim())[0] || '?';

    const createEmployeeRow = (employee, index) => {
        const row = document.createElement('tr');
        row.dataset.employeeId = employee.id;
        addCell(row, String(index + 1), 'row-index');
        const codeCell = row.insertCell();
        const code = document.createElement('span');
        code.className = 'employee-code';
        code.textContent = employee.id;
        codeCell.append(code);

        const nameCell = row.insertCell();
        const identity = document.createElement('div');
        identity.className = 'employee-cell';
        const avatar = document.createElement('span');
        avatar.className = 'avatar employee-avatar';
        avatar.textContent = getInitial(employee.name);
        const meta = document.createElement('span');
        meta.className = 'employee-meta';
        const name = document.createElement('strong');
        name.textContent = employee.name;
        const id = document.createElement('small');
        id.textContent = employee.id;
        meta.append(name, id);
        identity.append(avatar, meta);
        nameCell.append(identity);

        addCell(row, employee.department, 'department-cell');
        addCell(row, employee.position, 'position-cell');
        addCell(row, String(employee.workingDays), 'text-center');
        addCell(row, String(employee.worked), 'text-center actual-days');
        addCell(row, String(employee.leave), 'text-center');
        addCell(row, String(employee.absent), 'text-center');
        addCell(row, String(employee.late), 'text-center');
        addCell(row, formatNumber(employee.overtime), 'text-center');

        const statusCell = row.insertCell();
        const badge = document.createElement('span');
        badge.className = `status-badge status-${employee.status}`;
        badge.textContent = statusLabels[employee.status];
        statusCell.append(badge);

        const actionCell = row.insertCell();
        actionCell.className = 'text-center';
        const viewButton = document.createElement('button');
        viewButton.className = 'view-button';
        viewButton.type = 'button';
        viewButton.dataset.viewEmployee = employee.id;
        viewButton.setAttribute('aria-label', `Xem chi tiết ${employee.name}`);
        const viewIcon = document.createElement('i');
        viewIcon.className = 'bi bi-eye';
        viewIcon.setAttribute('aria-hidden', 'true');
        const viewLabel = document.createElement('span');
        viewLabel.textContent = 'Xem';
        viewButton.append(viewIcon, viewLabel);
        actionCell.append(viewButton);
        return row;
    };

    const renderTotals = (records) => {
        const total = records.reduce((result, employee) => ({
            expected: result.expected + employee.workingDays,
            worked: result.worked + employee.worked,
            leave: result.leave + employee.leave,
            absent: result.absent + employee.absent,
            late: result.late + employee.late,
            overtime: result.overtime + employee.overtime,
        }), { expected: 0, worked: 0, leave: 0, absent: 0, late: 0, overtime: 0 });

        totalsElement.replaceChildren();
        const row = document.createElement('tr');
        const label = document.createElement('td');
        label.colSpan = 5;
        label.className = 'total-label';
        label.textContent = 'Tổng cộng (' + records.length + ' nhân viên)';
        row.append(label);
        [
            total.expected,
            total.worked,
            total.leave,
            total.absent,
            total.late,
            formatNumber(total.overtime),
        ].forEach((value) => {
            const cell = document.createElement('td');
            cell.className = 'text-center';
            cell.textContent = String(value);
            row.append(cell);
        });
        const trailingCells = document.createElement('td');
        trailingCells.colSpan = 2;
        row.append(trailingCells);
        totalsElement.append(row);
    };

    const renderPagination = (pageCount) => {
        pagination.replaceChildren();
        const addButton = (label, page, options = {}) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `page-button${options.current ? ' current' : ''}`;
            button.textContent = label;
            button.disabled = Boolean(options.disabled);
            if (options.current) button.setAttribute('aria-current', 'page');
            if (options.label) button.setAttribute('aria-label', options.label);
            button.addEventListener('click', () => {
                currentPage = page;
                renderTable();
            });
            pagination.append(button);
        };

        addButton('‹', Math.max(1, currentPage - 1), {
            disabled: currentPage === 1,
            label: 'Trang trước',
        });

        const startPage = Math.max(1, Math.min(currentPage - 2, pageCount - 4));
        const endPage = Math.min(pageCount, startPage + 4);
        for (let page = startPage; page <= endPage; page += 1) {
            addButton(String(page), page, { current: page === currentPage, label: `Trang ${page}` });
        }

        addButton('›', Math.min(pageCount, currentPage + 1), {
            disabled: currentPage === pageCount,
            label: 'Trang tiếp theo',
        });
    };

    const applyFilters = () => {
        const query = normalizeText(searchInput.value.trim());
        const department = departmentFilter.value;
        const position = positionFilter.value;
        const status = statusFilter.value;

        filteredEmployees = employees.filter((employee) => {
            const matchesSearch = !query ||
                normalizeText(`${employee.id} ${employee.name}`).includes(query);
            return matchesSearch &&
                (!department || employee.department === department) &&
                (!position || employee.position === position) &&
                (!status || employee.status === status);
        });
        currentPage = 1;
        renderTable();
    };

    const renderTable = () => {
        const pageSize = Number(pageSizeSelect.value);
        const pageCount = Math.max(1, Math.ceil(filteredEmployees.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const start = (currentPage - 1) * pageSize;
        const visibleRecords = filteredEmployees.slice(start, start + pageSize);

        rowsElement.replaceChildren(...visibleRecords.map((employee, index) =>
            createEmployeeRow(employee, start + index)));
        emptyState.hidden = filteredEmployees.length > 0;
        rowsElement.closest('table').hidden = filteredEmployees.length === 0;

        const rangeStart = filteredEmployees.length ? start + 1 : 0;
        const rangeEnd = Math.min(start + pageSize, filteredEmployees.length);
        document.getElementById('record-range').textContent =
            `Hiển thị ${rangeStart}–${rangeEnd} trong ${filteredEmployees.length} nhân viên`;
        document.getElementById('toolbar-count').textContent = String(filteredEmployees.length);
        renderTotals(filteredEmployees);
        renderPagination(pageCount);
    };

    const renderStatistics = () => {
        const totals = employees.reduce((result, employee) => ({
            worked: result.worked + employee.worked,
            late: result.late + employee.late,
            leave: result.leave + employee.leave,
        }), { worked: 0, late: 0, leave: 0 });

        const setStat = (id, value, unit) => {
            const element = document.getElementById(id);
            const amount = document.createTextNode(`${formatNumber(value)} `);
            const label = document.createElement('span');
            label.textContent = unit;
            element.replaceChildren(amount, label);
        };
        setStat('stat-employees', employees.length, 'người');
        setStat('stat-workdays', totals.worked, 'ngày');
        setStat('stat-late', totals.late, 'lượt');
        setStat('stat-leave', totals.leave, 'ngày');
        document.getElementById('standard-workdays').textContent =
            `${employees[0]?.workingDays || 0} ngày`;
        document.getElementById('period-caption').textContent =
            `Tổng hợp công nhân viên · ${monthNames[selectedMonth - 1]} năm ${selectedYear}`;
    };

    const updatePeriod = ({ announce = false } = {}) => {
        selectedMonth = Number(monthSelect.value);
        selectedYear = Number(yearSelect.value);
        employees = buildMonthlyEmployees();
        renderStatistics();
        applyFilters();

        const url = new URL(window.location.href);
        url.searchParams.set('month', String(selectedMonth));
        url.searchParams.set('year', String(selectedYear));
        window.history.replaceState({}, '', url);

        if (announce) showToast(`Đang xem dữ liệu mẫu ${monthNames[selectedMonth - 1]} năm ${selectedYear}.`);
    };

    const fillFilterOptions = (select, values, placeholder) => {
        const options = [new Option(placeholder, '')];
        [...new Set(values)].sort((a, b) => a.localeCompare(b, 'vi')).forEach((value) => {
            options.push(new Option(value, value));
        });
        select.replaceChildren(...options);
    };

    const showToast = (message) => {
        const toast = document.createElement('div');
        toast.className = 'hrm-toast';
        toast.setAttribute('role', 'status');
        const icon = document.createElement('i');
        icon.className = 'bi bi-check-circle-fill';
        icon.setAttribute('aria-hidden', 'true');
        toast.append(icon, document.createTextNode(message));
        toastContainer.append(toast);
        window.setTimeout(() => toast.remove(), 3500);
    };

    // Giữ CSV UTF-8 tương thích Excel và tránh ô dữ liệu bị hiểu là công thức.
    const csvSafe = (value) => {
        const text = String(value);
        const safeText = /^[\s]*[=+\-@]/.test(text) ? `'${text}` : text;
        return `"${safeText.replace(/"/g, '""')}"`;
    };

    const exportReport = () => {
        const headers = [
            'Mã nhân viên', 'Họ và tên', 'Phòng ban', 'Chức vụ',
            'Ngày công quy định', 'Ngày công thực tế', 'Nghỉ phép',
            'Nghỉ không phép', 'Số lần đi muộn', 'Tổng giờ tăng ca', 'Trạng thái',
        ];
        const lines = [headers, ...filteredEmployees.map((employee) => [
            employee.id,
            employee.name,
            employee.department,
            employee.position,
            employee.workingDays,
            employee.worked,
            employee.leave,
            employee.absent,
            employee.late,
            employee.overtime,
            statusLabels[employee.status],
        ])].map((line) => line.map(csvSafe).join(','));

        const csv = `\uFEFF${lines.join('\r\n')}`;
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        const objectUrl = URL.createObjectURL(blob);
        link.href = objectUrl;
        link.download = `bang-tong-hop-cong-${selectedYear}-${String(selectedMonth).padStart(2, '0')}.csv`;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        showToast(`Đã xuất ${filteredEmployees.length} nhân viên ra tệp Excel tương thích CSV.`);
    };

    const getDailyLog = (employee) => {
        const days = [];
        const date = new Date(selectedYear, selectedMonth - 1, 1);
        let businessDay = 0;
        while (date.getMonth() === selectedMonth - 1 && days.length < 7) {
            const weekDay = date.getDay();
            if (weekDay !== 0 && weekDay !== 6) {
                let state = 'Đúng giờ';
                let checkIn = '08:00';
                let checkOut = '17:00';
                if (employee.absent > 0 && businessDay === 4) {
                    state = 'Vắng mặt';
                    checkIn = '—';
                    checkOut = '—';
                } else if (employee.leave > 0 && businessDay === 2) {
                    state = 'Nghỉ phép';
                    checkIn = '—';
                    checkOut = '—';
                } else if (employee.late > 0 && businessDay < employee.late) {
                    state = 'Đi muộn';
                    checkIn = `08:${String(8 + (businessDay * 7) % 45).padStart(2, '0')}`;
                }

                days.push({
                    date: new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit' }).format(date),
                    weekday: new Intl.DateTimeFormat('vi-VN', { weekday: 'short' }).format(date),
                    checkIn,
                    checkOut,
                    state,
                });
                businessDay += 1;
            }
            date.setDate(date.getDate() + 1);
        }
        return days;
    };

    const getEmployeeInitials = (name) => {
        const parts = name.trim().split(/\s+/);
        return parts.slice(-2).map((part) => Array.from(part)[0]).join('');
    };

    const appendDetailMetric = (container, label, value) => {
        const metric = document.createElement('div');
        metric.className = 'detail-metric';
        const title = document.createElement('span');
        title.textContent = label;
        const amount = document.createElement('strong');
        amount.textContent = String(value);
        metric.append(title, amount);
        container.append(metric);
    };

    const openEmployeeDetails = (employee) => {
        document.getElementById('employee-modal-title').textContent = `Chi tiết ${employee.name}`;
        document.getElementById('modal-period').textContent =
            `${monthNames[selectedMonth - 1]} ${selectedYear}`;
        const body = document.getElementById('employee-modal-body');
        body.replaceChildren();

        const profile = document.createElement('div');
        profile.className = 'detail-profile';
        const avatar = document.createElement('span');
        avatar.className = 'avatar detail-avatar';
        avatar.textContent = getEmployeeInitials(employee.name);
        const identity = document.createElement('div');
        const name = document.createElement('h3');
        name.textContent = employee.name;
        const details = document.createElement('p');
        details.textContent = `${employee.id} · ${employee.position} · ${employee.department}`;
        identity.append(name, details);
        const status = document.createElement('span');
        status.className = `status-badge status-${employee.status}`;
        status.textContent = statusLabels[employee.status];
        profile.append(avatar, identity, status);
        body.append(profile);

        const metrics = document.createElement('div');
        metrics.className = 'detail-metrics';
        appendDetailMetric(metrics, 'Ngày công thực tế', `${employee.worked}/${employee.workingDays}`);
        appendDetailMetric(metrics, 'Nghỉ phép', `${employee.leave} ngày`);
        appendDetailMetric(metrics, 'Nghỉ không phép', `${employee.absent} ngày`);
        appendDetailMetric(metrics, 'Đi muộn · Tăng ca', `${employee.late} lượt · ${formatNumber(employee.overtime)} giờ`);
        body.append(metrics);

        const sectionHeading = document.createElement('h3');
        sectionHeading.className = 'detail-section-title';
        sectionHeading.textContent = 'Nhật ký chấm công';
        const period = document.createElement('span');
        period.textContent = 'Một số ngày trong kỳ';
        sectionHeading.append(period);
        body.append(sectionHeading);

        const table = document.createElement('table');
        table.className = 'detail-log-table';
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        ['Ngày', 'Thứ', 'Giờ vào', 'Giờ ra', 'Ghi nhận'].forEach((label) => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = label;
            headerRow.append(th);
        });
        thead.append(headerRow);
        const tbody = document.createElement('tbody');
        getDailyLog(employee).forEach((log) => {
            const row = document.createElement('tr');
            [log.date, log.weekday, log.checkIn, log.checkOut].forEach((value) => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.append(cell);
            });
            const stateCell = document.createElement('td');
            const state = document.createElement('span');
            state.className = `log-state${log.state === 'Đi muộn' ? ' late' : log.state === 'Nghỉ phép' ? ' leave' : log.state === 'Vắng mặt' ? ' absent' : ''}`;
            state.textContent = log.state;
            stateCell.append(state);
            row.append(stateCell);
            tbody.append(row);
        });
        table.append(thead, tbody);
        body.append(table);

        if (window.bootstrap?.Modal) {
            modalInstance = modalInstance || new window.bootstrap.Modal(document.getElementById('employee-modal'));
            modalInstance.show();
        } else {
            const modal = document.getElementById('employee-modal');
            modal.style.display = 'block';
            modal.classList.add('show');
            modal.setAttribute('aria-modal', 'true');
            modal.removeAttribute('aria-hidden');
        }
    };

    const closeFallbackModal = () => {
        const modal = document.getElementById('employee-modal');
        if (modal.classList.contains('show') && !window.bootstrap?.Modal) {
            modal.classList.remove('show');
            modal.style.display = '';
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
        }
    };

    document.getElementById('apply-filters').addEventListener('click', applyFilters);
    document.getElementById('reset-filters').addEventListener('click', () => {
        searchInput.value = '';
        departmentFilter.value = '';
        positionFilter.value = '';
        statusFilter.value = '';
        applyFilters();
        showToast('Đã làm mới các điều kiện lọc.');
    });
    searchInput.addEventListener('input', applyFilters);
    [departmentFilter, positionFilter, statusFilter].forEach((select) => {
        select.addEventListener('change', applyFilters);
    });
    pageSizeSelect.addEventListener('change', () => {
        currentPage = 1;
        renderTable();
    });

    monthSelect.addEventListener('change', () => updatePeriod({ announce: true }));
    yearSelect.addEventListener('change', () => updatePeriod({ announce: true }));
    document.getElementById('previous-month').addEventListener('click', () => {
        let month = Number(monthSelect.value) - 1;
        let year = Number(yearSelect.value);
        if (month < 1) {
            month = 12;
            year -= 1;
        }
        if (![...yearSelect.options].some((option) => Number(option.value) === year)) {
            yearSelect.add(new Option(String(year), String(year)));
        }
        monthSelect.value = String(month);
        yearSelect.value = String(year);
        updatePeriod({ announce: true });
    });
    document.getElementById('next-month').addEventListener('click', () => {
        let month = Number(monthSelect.value) + 1;
        let year = Number(yearSelect.value);
        if (month > 12) {
            month = 1;
            year += 1;
        }
        if (![...yearSelect.options].some((option) => Number(option.value) === year)) {
            yearSelect.add(new Option(String(year), String(year)));
        }
        monthSelect.value = String(month);
        yearSelect.value = String(year);
        updatePeriod({ announce: true });
    });

    document.getElementById('export-report').addEventListener('click', exportReport);
    document.getElementById('print-report').addEventListener('click', () => window.print());
    document.getElementById('notification-button').addEventListener('click', () => {
        showToast(`${employees.length} nhân viên đang có dữ liệu chấm công trong kỳ này.`);
    });
    rowsElement.addEventListener('click', (event) => {
        const button = event.target.closest('[data-view-employee]');
        if (!button) return;
        const employee = employees.find((item) => item.id === button.dataset.viewEmployee);
        if (employee) openEmployeeDetails(employee);
    });

    document.getElementById('employee-modal').addEventListener('click', (event) => {
        if (event.target === event.currentTarget || event.target.closest('[data-bs-dismiss="modal"]')) {
            closeFallbackModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeFallbackModal();
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            searchInput.focus();
        }
    });

    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggleSidebar = () => {
        const isOpen = sidebar.classList.toggle('is-open');
        backdrop.classList.toggle('is-visible', isOpen);
        document.getElementById('sidebar-toggle').setAttribute('aria-expanded', String(isOpen));
    };
    document.getElementById('sidebar-toggle').addEventListener('click', toggleSidebar);
    backdrop.addEventListener('click', toggleSidebar);
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 760 && sidebar.classList.contains('is-open')) toggleSidebar();
        });
    });

    fillFilterOptions(departmentFilter, baseEmployees.map((employee) => employee.department), 'Tất cả phòng ban');
    fillFilterOptions(positionFilter, baseEmployees.map((employee) => employee.position), 'Tất cả chức vụ');
    updatePeriod();
})();
