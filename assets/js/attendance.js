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
    const holidaySourceStatus = document.getElementById('holiday-source-status');
    const holidayApiStatus = document.getElementById('holiday-api-status');
    const holidayList = document.getElementById('holiday-list');
    const holidayDialog = document.getElementById('holiday-dialog');
    const holidayStorageKey = 'hrm-vietnam-holiday-overrides';
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
    const holidayDataByYear = new Map();
    const holidayErrorsByYear = new Map();
    let manualHolidays = [];
    let manualHolidayError = '';
    let periodUpdateId = 0;

    const formatNumber = (value) => new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 1,
    }).format(value);
    const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);

    const isValidHolidayRecord = (record) =>
        record &&
        typeof record.date === 'string' &&
        /^\d{4}-\d{2}-\d{2}$/.test(record.date) &&
        new Date(`${record.date}T00:00:00Z`).toISOString().slice(0, 10) === record.date &&
        typeof record.name === 'string' &&
        record.name.trim().length > 0;

    const readManualHolidays = () => {
        try {
            const stored = window.localStorage.getItem(holidayStorageKey);
            if (!stored) return [];
            const parsed = JSON.parse(stored);
            if (!Array.isArray(parsed) || !parsed.every((holiday) =>
                isValidHolidayRecord(holiday) &&
                (holiday.type === undefined || ['holiday', 'workday'].includes(holiday.type)))) {
                throw new Error('Dữ liệu ngày lễ bổ sung không hợp lệ.');
            }
            return parsed.map((holiday) => ({
                date: holiday.date,
                name: holiday.name.trim(),
                type: holiday.type || 'holiday',
                source: 'manual',
            }));
        } catch (error) {
            manualHolidayError = error instanceof Error ? error.message : 'Không đọc được lịch bổ sung.';
            return [];
        }
    };

    const setHolidaySourceStatus = (message, state) => {
        holidaySourceStatus.textContent = message;
        holidaySourceStatus.dataset.state = state;
        holidayApiStatus.textContent = message;
    };

    const getYearEntries = (year) => {
        const apiHolidays = holidayDataByYear.get(year) || [];
        const entries = new Map();
        [...apiHolidays, ...manualHolidays.filter((holiday) => holiday.date.startsWith(`${year}-`))]
            .forEach((holiday) => {
                if (!entries.has(holiday.date)) {
                    entries.set(holiday.date, {
                        ...holiday,
                        type: holiday.type || 'holiday',
                        day: Number(holiday.date.slice(8, 10)),
                    });
                }
            });
        return [...entries.values()].sort((first, second) => first.date.localeCompare(second.date));
    };

    const getYearHolidays = (year) => getYearEntries(year).filter((entry) => entry.type === 'holiday');
    const getYearWorkdays = (year) => getYearEntries(year).filter((entry) => entry.type === 'workday');

    const getPublicHolidays = (year, month) =>
        getYearHolidays(year).filter((holiday) => holiday.date.slice(5, 7) === String(month).padStart(2, '0'));

    const loadHolidays = async (year) => {
        if (holidayDataByYear.has(year)) {
            const previousError = holidayErrorsByYear.get(year);
            if (year === selectedYear) {
                setHolidaySourceStatus(
                    previousError
                        ? `Không tải được API ngày lễ (${previousError}); đang dùng ngày bổ sung trong trình duyệt.`
                        : `Đã tải lịch ${year} (${holidayDataByYear.get(year).length} ngày); bổ sung Tết/ngày nghỉ bù còn thiếu.`,
                    previousError ? 'error' : 'warning',
                );
                renderHolidayList(year);
            }
            return;
        }

        if (year === selectedYear) {
            setHolidaySourceStatus(`Đang tải ngày lễ Việt Nam ${year} từ Nager.Date...`, 'loading');
            renderHolidayList(year);
        }
        try {
            const abortController = new AbortController();
            const timeoutId = window.setTimeout(() => abortController.abort(), 10000);
            let response;
            try {
                response = await fetch(`https://date.nager.at/api/v3/PublicHolidays/${year}/VN`, {
                    headers: { Accept: 'application/json' },
                    signal: abortController.signal,
                });
            } finally {
                window.clearTimeout(timeoutId);
            }
            if (!response.ok) {
                throw new Error(`API trả về mã HTTP ${response.status}.`);
            }
            const payload = await response.json();
            if (!Array.isArray(payload) || !payload.every((holiday) =>
                holiday && typeof holiday.date === 'string' &&
                typeof (holiday.localName || holiday.name) === 'string')) {
                throw new Error('Dữ liệu API không đúng định dạng ngày lễ.');
            }
            const holidays = payload.map((holiday) => ({
                date: holiday.date,
                name: holiday.localName || holiday.name,
                source: 'api',
            }));
            if (!holidays.every((holiday) =>
                isValidHolidayRecord(holiday) && holiday.date.startsWith(`${year}-`))) {
                throw new Error('API trả về ngày lễ không hợp lệ.');
            }
            holidayDataByYear.set(year, holidays);
            holidayErrorsByYear.delete(year);
            if (year === selectedYear) {
                setHolidaySourceStatus(`Đã tải ${holidays.length} ngày từ Nager.Date ${year}; bổ sung Tết/ngày nghỉ bù còn thiếu.`, 'warning');
            }
        } catch (error) {
            holidayDataByYear.set(year, []);
            const message = error instanceof Error ? error.message : 'Lỗi không xác định khi tải API.';
            holidayErrorsByYear.set(year, message);
            if (year === selectedYear) {
                setHolidaySourceStatus(`Không tải được API ngày lễ (${message}); đang dùng ngày bổ sung trong trình duyệt.`, 'error');
            }
        }
        if (year === selectedYear) {
            renderHolidayList(year);
        }
    };

    const renderHolidayList = (year) => {
        const holidays = getYearEntries(year);
        holidayList.replaceChildren();
        if (manualHolidayError) {
            const warning = document.createElement('li');
            warning.className = 'holiday-list-empty';
            warning.textContent = `${manualHolidayError} Ngày bổ sung chưa được nạp.`;
            holidayList.append(warning);
        }
        if (holidays.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'holiday-list-empty';
            empty.textContent = `Chưa có ngày lễ cho năm ${year} theo dữ liệu đang tải.`;
            holidayList.append(empty);
        }

        holidays.forEach((holiday) => {
            const item = document.createElement('li');
            item.className = 'holiday-list-item';
            const date = document.createElement('span');
            date.textContent = new Date(`${holiday.date}T00:00:00`).toLocaleDateString('vi-VN');
            const name = document.createElement('span');
            name.className = 'holiday-list-name';
            name.textContent = holiday.name;
            const source = document.createElement('span');
            source.className = 'holiday-list-source';
            source.textContent = holiday.source === 'api'
                ? 'API'
                : holiday.type === 'workday' ? 'Làm bù' : 'Nghỉ hưởng lương';
            item.append(date, name, source);
            if (holiday.source === 'manual') {
                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.dataset.removeHoliday = holiday.date;
                removeButton.textContent = 'Xóa';
                removeButton.setAttribute('aria-label', `Xóa ngày lễ ${holiday.name}`);
                item.append(removeButton);
            }
            holidayList.append(item);
        });

        document.getElementById('holiday-dialog-year').textContent = String(year);
        document.getElementById('holiday-dialog-title').textContent = `Ngày lễ và ngày làm bù năm ${year}`;
    };

    const normalizeText = (value) => String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLocaleLowerCase('vi');

    const getWorkingDays = (year, month) => {
        let count = 0;
        const holidays = getPublicHolidays(year, month);
        const makeupWorkdays = getYearWorkdays(year).filter((workday) =>
            workday.date.slice(5, 7) === String(month).padStart(2, '0'));
        const date = new Date(year, month - 1, 1);
        while (date.getMonth() === month - 1) {
            const day = date.getDay();
            const isHoliday = holidays.some((holiday) => holiday.day === date.getDate());
            const isMakeupWorkday = makeupWorkdays.some((workday) => workday.day === date.getDate());
            if (!isHoliday && (isMakeupWorkday || (day !== 0 && day !== 6))) {
                count += 1;
            }
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
            const paidLeave = employee.paidLeave;
            const unpaidLeave = employee.unpaidLeave;
            const leave = paidLeave + unpaidLeave;
            const absent = Math.max(0, employee.absent + ((monthOffset + index) % 7 === 0 ? 1 : 0));
            const availableDays = Math.max(0, workingDays - leave - absent);
            const worked = Math.min(availableDays, Math.max(0, employee.worked + variation));
            const late = Math.max(0, employee.late + ((monthOffset + index) % 4 === 0 ? 1 : 0));
            const holidayDates = getPublicHolidays(selectedYear, selectedMonth);
            const holidayWorkedDates = holidayDates
                .filter((day, holidayIndex) => (index + holidayIndex) % 2 === 0)
                .map((holiday) => holiday.date);
            const makeupWorkDates = getYearWorkdays(selectedYear)
                .filter((workday) => workday.date.slice(5, 7) === String(selectedMonth).padStart(2, '0'))
                .map((workday) => workday.date);
            const workedDaysIncludingHolidays = worked + holidayWorkedDates.length;

            const monthlyEmployee = {
                ...employee,
                workingDays,
                worked: workedDaysIncludingHolidays,
                leave,
                paidLeave,
                unpaidLeave,
                absent,
                late,
                lateMinutes: late * employee.lateMinutesPerOccurrence,
                holidayWorkedDates,
                holidayWorkDays: holidayWorkedDates.length,
                holidayRestDays: holidayDates.length - holidayWorkedDates.length,
                makeupWorkDates,
                estimatedHolidayPay: holidayWorkedDates.length * employee.dailyWage * 3,
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
        addCell(row, String(employee.paidLeave), 'text-center');
        addCell(row, String(employee.unpaidLeave), 'text-center');
        addCell(row, String(employee.absent), 'text-center');
        addCell(row, String(employee.late), 'text-center');
        addCell(row, String(employee.lateMinutes), 'text-center');
        addCell(row, String(employee.holidayWorkDays), 'text-center');
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
            paidLeave: result.paidLeave + employee.paidLeave,
            unpaidLeave: result.unpaidLeave + employee.unpaidLeave,
            absent: result.absent + employee.absent,
            late: result.late + employee.late,
            lateMinutes: result.lateMinutes + employee.lateMinutes,
            holidayWorkDays: result.holidayWorkDays + employee.holidayWorkDays,
            overtime: result.overtime + employee.overtime,
        }), { expected: 0, worked: 0, paidLeave: 0, unpaidLeave: 0, absent: 0, late: 0, lateMinutes: 0, holidayWorkDays: 0, overtime: 0 });

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
            total.paidLeave,
            total.unpaidLeave,
            total.absent,
            total.late,
            total.lateMinutes,
            total.holidayWorkDays,
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
            paidLeave: result.paidLeave + employee.paidLeave,
            unpaidLeave: result.unpaidLeave + employee.unpaidLeave,
        }), { worked: 0, late: 0, leave: 0, paidLeave: 0, unpaidLeave: 0 });

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
        document.getElementById('stat-paid-leave').textContent = formatNumber(totals.paidLeave);
        document.getElementById('stat-unpaid-leave').textContent = formatNumber(totals.unpaidLeave);
        document.getElementById('standard-workdays').textContent =
            `${employees[0]?.workingDays || 0} ngày`;
        document.getElementById('period-caption').textContent =
            `Tổng hợp công nhân viên · ${monthNames[selectedMonth - 1]} năm ${selectedYear}`;
    };

    const updatePeriod = async ({ announce = false } = {}) => {
        selectedMonth = Number(monthSelect.value);
        selectedYear = Number(yearSelect.value);
        const requestId = ++periodUpdateId;
        const url = new URL(window.location.href);
        url.searchParams.set('month', String(selectedMonth));
        url.searchParams.set('year', String(selectedYear));
        window.history.replaceState({}, '', url);

        await loadHolidays(selectedYear);
        if (requestId !== periodUpdateId) return;

        employees = buildMonthlyEmployees();
        renderStatistics();
        applyFilters();

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
            'Ngày công quy định', 'Ngày công thực tế', 'Nghỉ có lương',
            'Nghỉ không lương', 'Nghỉ không phép', 'Số lần đi muộn',
            'Tổng phút đi muộn', 'Ngày lễ nghỉ theo lịch', 'Ngày lễ đi làm',
            'Tiền làm ngày lễ ước tính',
            'Tổng giờ tăng ca', 'Trạng thái',
        ];
        const lines = [headers, ...filteredEmployees.map((employee) => [
            employee.id,
            employee.name,
            employee.department,
            employee.position,
            employee.workingDays,
            employee.worked,
            employee.paidLeave,
            employee.unpaidLeave,
            employee.absent,
            employee.late,
            employee.lateMinutes,
            employee.holidayRestDays,
            employee.holidayWorkDays,
            employee.estimatedHolidayPay,
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

    const getDailyCalendar = (employee) => {
        const businessDays = [];
        const calendarDays = [];
        const holidayDates = getPublicHolidays(selectedYear, selectedMonth);
        const makeupWorkdays = getYearWorkdays(selectedYear)
            .filter((workday) => workday.date.slice(5, 7) === String(selectedMonth).padStart(2, '0'));
        const date = new Date(selectedYear, selectedMonth - 1, 1);

        while (date.getMonth() === selectedMonth - 1) {
            const weekDay = date.getDay();
            const day = {
                date: date.getDate(),
                state: 'weekend',
            };
            const holiday = holidayDates.find((item) => item.day === day.date);
            const makeupWorkday = makeupWorkdays.find((item) => item.day === day.date);
            if (holiday) {
                day.state = employee.holidayWorkedDates.includes(holiday.date) ? 'holiday-worked' : 'holiday-off';
                day.holidayName = holiday.name;
            } else if (makeupWorkday || (weekDay !== 0 && weekDay !== 6)) {
                day.state = 'unrecorded';
                day.makeupWorkday = Boolean(makeupWorkday);
                if (makeupWorkday) day.holidayName = makeupWorkday.name;
                businessDays.push(day);
            }
            calendarDays.push(day);
            date.setDate(date.getDate() + 1);
        }

        const assignState = (state, count) => {
            const availableDays = businessDays.filter((day) => day.state === 'unrecorded');
            const assignedCount = Math.min(count, availableDays.length);
            for (let index = 0; index < assignedCount; index += 1) {
                const position = Math.floor(((index + 0.5) * availableDays.length) / assignedCount);
                availableDays[position].state = state;
            }
        };

        assignState('absent', employee.absent);
        assignState('paid-leave', employee.paidLeave);
        assignState('absent', employee.unpaidLeave);
        const assignLate = (state, count) => {
            const availableDays = businessDays.filter((day) => day.state === 'unrecorded');
            const assignedCount = Math.min(count, availableDays.length);
            for (let index = 0; index < assignedCount; index += 1) {
                const position = Math.floor(((index + 0.5) * availableDays.length) / assignedCount);
                availableDays[position].state = state;
                availableDays[position].lateMinutes = employee.lateMinutesPerOccurrence;
            }
        };
        assignLate('late', Math.min(employee.late, employee.worked));
        assignState('on-time', Math.max(0, employee.worked - employee.holidayWorkDays - employee.late));
        businessDays.forEach((day) => {
            if (day.makeupWorkday && day.state === 'unrecorded') day.state = 'makeup-workday';
        });

        const leadingEmptyDays = (new Date(selectedYear, selectedMonth - 1, 1).getDay() + 6) % 7;
        const cells = [...Array(leadingEmptyDays).fill(null), ...calendarDays];
        while (cells.length % 7 !== 0) cells.push(null);
        return cells;
    };

    const getEmployeeInitials = (name) => {
        const parts = name.trim().split(/\s+/);
        return parts.slice(-2).map((part) => Array.from(part)[0]).join('');
    };

    const appendDetailMetric = (container, label, value, note = '') => {
        const metric = document.createElement('div');
        metric.className = 'detail-metric';
        const title = document.createElement('span');
        title.textContent = label;
        const amount = document.createElement('strong');
        amount.textContent = String(value);
        metric.append(title, amount);
        if (note) {
            const detail = document.createElement('small');
            detail.textContent = note;
            metric.append(detail);
        }
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
        appendDetailMetric(metrics, 'Nghỉ có lương', `${employee.paidLeave} ngày`);
        appendDetailMetric(metrics, 'Nghỉ không phép', `${employee.absent + employee.unpaidLeave} ngày`);
        appendDetailMetric(metrics, 'Đi muộn', `${employee.late} lượt · ${employee.lateMinutes} phút`);
        appendDetailMetric(metrics, 'Ngày lễ nghỉ theo lịch', `${employee.holidayRestDays} ngày`);
        appendDetailMetric(
            metrics,
            'Tăng ca',
            `${formatNumber(employee.overtime)} giờ`,
            `Lễ đi làm: ${employee.holidayWorkDays} ngày · Làm bù: ${employee.makeupWorkDates.length} ngày · Tiền lễ ước tính: ${formatCurrency(employee.estimatedHolidayPay)}`,
        );
        body.append(metrics);

        const sectionHeading = document.createElement('h3');
        sectionHeading.className = 'detail-section-title';
        sectionHeading.textContent = 'Lịch chấm công';
        const period = document.createElement('span');
        period.textContent = `${monthNames[selectedMonth - 1]} ${selectedYear}`;
        sectionHeading.append(period);
        body.append(sectionHeading);

        const legend = document.createElement('div');
        legend.className = 'calendar-legend';
        [
            ['on-time', 'Đúng giờ'],
            ['late', 'Đi muộn'],
            ['paid-leave', 'Nghỉ có lương'],
            ['absent', 'Nghỉ không phép'],
            ['holiday-off', 'Nghỉ lễ'],
            ['holiday-worked', 'Đi làm ngày lễ'],
            ['makeup-workday', 'Ngày làm bù'],
            ['unrecorded', 'Chưa ghi nhận'],
        ].forEach(([stateName, label]) => {
            const item = document.createElement('span');
            item.className = `calendar-legend-item calendar-legend-${stateName}`;
            const marker = document.createElement('i');
            marker.className = `calendar-legend-marker calendar-marker-${stateName}`;
            marker.setAttribute('aria-hidden', 'true');
            item.append(marker, document.createTextNode(label));
            legend.append(item);
        });
        body.append(legend);

        const calendar = document.createElement('div');
        calendar.className = 'attendance-calendar';
        calendar.setAttribute('role', 'grid');
        calendar.setAttribute('aria-label', `Lịch chấm công ${monthNames[selectedMonth - 1]} ${selectedYear}`);

        ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'].forEach((label) => {
            const weekday = document.createElement('div');
            weekday.className = 'calendar-weekday';
            weekday.setAttribute('role', 'columnheader');
            weekday.textContent = label;
            calendar.append(weekday);
        });

        const stateLabels = {
            'on-time': 'Đúng giờ',
            late: `Muộn ${employee.lateMinutesPerOccurrence} phút`,
            'paid-leave': 'Nghỉ có lương',
            absent: 'Nghỉ không phép',
            unrecorded: 'Chưa ghi nhận',
            weekend: 'Cuối tuần',
            'holiday-off': 'Nghỉ lễ',
            'holiday-worked': 'Đi làm ngày lễ',
            'makeup-workday': 'Ngày làm bù',
        };
        getDailyCalendar(employee).forEach((day) => {
            const cell = document.createElement('div');
            cell.className = `calendar-day${day ? ` calendar-day-${day.state}${day.makeupWorkday ? ' calendar-day-makeup-workday' : ''}` : ' calendar-day-empty'}`;
            cell.setAttribute('role', 'gridcell');
            if (day) {
                const dayNumber = document.createElement('span');
                dayNumber.className = 'calendar-day-number';
                dayNumber.textContent = String(day.date);
                const state = document.createElement('span');
                state.className = 'calendar-day-state';
                state.textContent = day.makeupWorkday
                    ? day.state === 'makeup-workday' || day.state === 'unrecorded'
                        ? 'Ngày làm bù'
                        : `Làm bù · ${stateLabels[day.state]}`
                    : day.holidayName
                        ? `${day.state === 'holiday-worked' ? 'Đi làm · ' : 'Nghỉ lễ · '}${day.holidayName}`
                        : stateLabels[day.state];
                if (day.state === 'late' && day.lateMinutes) {
                    state.textContent = `${day.makeupWorkday ? 'Làm bù · ' : ''}Muộn ${day.lateMinutes} phút`;
                }
                cell.append(dayNumber, state);
                cell.setAttribute('aria-label', `Ngày ${day.date}: ${day.makeupWorkday ? `Ngày làm bù, ${stateLabels[day.state]}` : day.holidayName || stateLabels[day.state]}`);
                if (day.holidayName) cell.title = day.holidayName;
            }
            calendar.append(cell);
        });
        body.append(calendar);

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
    document.getElementById('manage-holidays').addEventListener('click', () => {
        const year = Number(yearSelect.value);
        const dateInput = document.getElementById('holiday-date');
        dateInput.min = `${year}-01-01`;
        dateInput.max = `${year}-12-31`;
        dateInput.value = `${year}-${String(selectedMonth).padStart(2, '0')}-01`;
        renderHolidayList(year);
        holidayDialog.showModal();
    });
    document.getElementById('close-holiday-dialog').addEventListener('click', () => holidayDialog.close());
    document.getElementById('reload-holiday-api').addEventListener('click', async () => {
        const year = Number(yearSelect.value);
        holidayDataByYear.delete(year);
        holidayErrorsByYear.delete(year);
        await updatePeriod();
    });
    document.getElementById('add-holiday').addEventListener('click', async () => {
        const dateInput = document.getElementById('holiday-date');
        const nameInput = document.getElementById('holiday-name');
        const typeInput = document.getElementById('holiday-type');
        if (!dateInput.reportValidity() || !nameInput.reportValidity()) return;
        if (manualHolidayError) {
            setHolidaySourceStatus(`${manualHolidayError} Không thể lưu ngày bổ sung.`, 'error');
            return;
        }

        const date = dateInput.value;
        const name = nameInput.value.trim();
        const dateYear = Number(date.slice(0, 4));
        if (!name) {
            showToast('Vui lòng nhập tên ngày hoặc ghi chú.');
            nameInput.focus();
            return;
        }
        if (dateYear !== Number(yearSelect.value)) {
            showToast('Ngày bổ sung phải thuộc năm đang chọn.');
            return;
        }
        if (getYearEntries(dateYear).some((entry) => entry.date === date)) {
            showToast('Ngày này đã có trong lịch API hoặc danh sách bổ sung.');
            return;
        }

        const updatedHolidays = [...manualHolidays, { date, name, type: typeInput.value, source: 'manual' }];
        try {
            window.localStorage.setItem(holidayStorageKey, JSON.stringify(updatedHolidays));
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Lỗi lưu trữ không xác định.';
            setHolidaySourceStatus(`Không thể lưu ngày bổ sung vào trình duyệt (${message}).`, 'error');
            return;
        }
        manualHolidays = updatedHolidays;
        nameInput.value = '';
        renderHolidayList(dateYear);
        await updatePeriod();
        showToast(`Đã thêm ${name} vào lịch ${dateYear} trên trình duyệt này.`);
    });
    holidayList.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-holiday]');
        if (!button) return;
        const date = button.dataset.removeHoliday;
        const updatedHolidays = manualHolidays.filter((holiday) => holiday.date !== date);
        try {
            if (updatedHolidays.length === 0) {
                window.localStorage.removeItem(holidayStorageKey);
            } else {
                window.localStorage.setItem(holidayStorageKey, JSON.stringify(updatedHolidays));
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Lỗi lưu trữ không xác định.';
            setHolidaySourceStatus(`Không thể cập nhật lịch bổ sung (${message}).`, 'error');
            return;
        }
        manualHolidays = updatedHolidays;
        renderHolidayList(Number(yearSelect.value));
        await updatePeriod();
        showToast('Đã xóa ngày bổ sung khỏi lịch.');
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
    const accountMenuButton = document.getElementById('admin-account-menu-button');
    const accountMenu = document.getElementById('admin-account-menu');
    if (accountMenuButton && accountMenu) {
        accountMenuButton.addEventListener('click', () => {
            accountMenu.hidden = !accountMenu.hidden;
            accountMenuButton.setAttribute('aria-expanded', String(!accountMenu.hidden));
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.topbar-actions')) {
                accountMenu.hidden = true;
                accountMenuButton.setAttribute('aria-expanded', 'false');
            }
        });
    }

    manualHolidays = readManualHolidays();
    fillFilterOptions(departmentFilter, baseEmployees.map((employee) => employee.department), 'Tất cả phòng ban');
    fillFilterOptions(positionFilter, baseEmployees.map((employee) => employee.position), 'Tất cả chức vụ');
    updatePeriod();
})();
