(() => {
    'use strict';

    const dataElement = document.getElementById('attendance-data');
    const config = window.hrmAttendanceConfig;
    let baseEmployees = JSON.parse(dataElement.textContent);
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
    const leaveTypeLabels = {
        PHEP_NAM: 'Phép năm',
        NGHI_OM: 'Nghỉ ốm',
        KHONG_LUONG: 'Nghỉ không lương',
        VIEC_RIENG: 'Việc riêng',
        KHAC: 'Khác',
    };

    let selectedMonth = Number(config.month);
    let selectedYear = Number(config.year);
    let selectedPayrollStartDay = Number(config.payrollStartDay);
    let currentPage = 1;
    let employees = [];
    let filteredEmployees = [];
    let modalInstance = null;
    const holidayDataByYear = new Map();
    const holidayErrorsByYear = new Map();
    const manualHolidayErrorsByYear = new Map();
    let manualHolidays = [];
    let periodUpdateId = 0;

    const formatNumber = (value) => new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 1,
    }).format(value);
    const getPayrollPeriod = () => {
        const startMonth = selectedMonth - 1;
        const startDay = Math.min(
            selectedPayrollStartDay,
            new Date(selectedYear, startMonth + 1, 0).getDate(),
        );
        const start = new Date(selectedYear, startMonth, startDay);
        const nextPeriodMonth = new Date(selectedYear, selectedMonth, 1);
        const nextPeriodDay = Math.min(
            selectedPayrollStartDay,
            new Date(nextPeriodMonth.getFullYear(), nextPeriodMonth.getMonth() + 1, 0).getDate(),
        );
        const nextPeriodStart = new Date(
            nextPeriodMonth.getFullYear(),
            nextPeriodMonth.getMonth(),
            nextPeriodDay,
        );
        return {
            start,
            end: new Date(
                nextPeriodStart.getFullYear(),
                nextPeriodStart.getMonth(),
                nextPeriodStart.getDate() - 1,
            ),
        };
    };
    const formatDateKey = (date) => [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
    const formatDateLabel = (date) =>
        `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
    const getPayrollPeriodLabel = () => {
        const { start, end } = getPayrollPeriod();
        return `${formatDateLabel(start)} – ${formatDateLabel(end)}`;
    };
    const isValidHolidayRecord = (record) =>
        record &&
        typeof record.date === 'string' &&
        /^\d{4}-\d{2}-\d{2}$/.test(record.date) &&
        new Date(`${record.date}T00:00:00Z`).toISOString().slice(0, 10) === record.date &&
        typeof record.name === 'string' &&
        record.name.trim().length > 0;

    const loadManualHolidays = async (year) => {
        try {
            const result = await window.lumiCineApi('holidays', { query: { year } });
            manualHolidays = [
                ...manualHolidays.filter((holiday) => !holiday.date.startsWith(`${year}-`)),
                ...result.holidays.map((holiday) => ({
                    ...holiday,
                    source: 'manual',
                })),
            ];
            manualHolidayErrorsByYear.delete(year);
        } catch (error) {
            manualHolidayErrorsByYear.set(
                year,
                error instanceof Error ? error.message : 'Không đọc được lịch bổ sung.',
            );
            manualHolidays = manualHolidays.filter((holiday) => !holiday.date.startsWith(`${year}-`));
        }
    };

    const setHolidaySourceStatus = (message, state) => {
        if (!holidaySourceStatus) return;
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

    const getPayrollPeriodEntries = (type) => {
        const { start, end } = getPayrollPeriod();
        const startDate = formatDateKey(start);
        const endDate = formatDateKey(end);
        const entries = [];
        for (let year = start.getFullYear(); year <= end.getFullYear(); year++) {
            entries.push(...getYearEntries(year));
        }
        return entries.filter((entry) =>
            entry.type === type && entry.date >= startDate && entry.date <= endDate);
    };

    const loadHolidays = async (year) => {
        await loadManualHolidays(year);
        const manualHolidayError = manualHolidayErrorsByYear.get(year) || '';
        if (holidayDataByYear.has(year)) {
            const previousError = holidayErrorsByYear.get(year);
            if (year === selectedYear) {
                setHolidaySourceStatus(
                    manualHolidayError
                        ? `Không tải được ngày nghỉ từ database (${manualHolidayError}).`
                        : previousError
                            ? `Không tải được API ngày lễ (${previousError}); đang dùng ngày nghỉ trong database.`
                            : `Đã tải lịch ${year}; ngày nghỉ bổ sung được đồng bộ từ database.`,
                    manualHolidayError ? 'error' : previousError ? 'warning' : 'warning',
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
                setHolidaySourceStatus(
                    manualHolidayError || `Đã tải ${holidays.length} ngày từ Nager.Date ${year}; ngày bổ sung được đồng bộ từ database.`,
                    manualHolidayError ? 'error' : 'warning',
                );
            }
        } catch (error) {
            holidayDataByYear.set(year, []);
            const message = error instanceof Error ? error.message : 'Lỗi không xác định khi tải API.';
            holidayErrorsByYear.set(year, message);
            if (year === selectedYear) {
                setHolidaySourceStatus(`Không tải được API ngày lễ (${message}); đang dùng ngày bổ sung trong database.`, 'error');
            }
        }
        if (year === selectedYear) {
            renderHolidayList(year);
        }
    };

    const renderHolidayList = (year) => {
        const holidays = getYearEntries(year);
        const manualHolidayError = manualHolidayErrorsByYear.get(year) || '';
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

    const getStatus = (employee, workingDays) => {
        if (employee.absent > 0) return 'absent';
        if (workingDays > 0 && employee.leave === workingDays) return 'leave';
        if (
            workingDays > 0
            && employee.worked + employee.leave === workingDays
            && employee.incomplete === 0
        ) {
            return 'complete';
        }
        return 'incomplete';
    };

    const formatLeaveTypes = (leaveByType) => Object.entries(leaveByType || {})
        .filter(([, days]) => Number(days) > 0)
        .map(([type, days]) => `${leaveTypeLabels[type] || type}: ${days}`)
        .join(' · ');

    const buildMonthlyEmployees = () => {
        return baseEmployees.map((employee) => {
            const holidayDates = getPayrollPeriodEntries('holiday');
            const dailyRecords = employee.dailyRecords || [];
            const holidayWorkedDates = holidayDates
                .filter((holiday) => dailyRecords.some((record) =>
                    record.date === holiday.date && record.checkIn))
                .map((holiday) => holiday.date);
            const makeupWorkDates = getPayrollPeriodEntries('workday').map((workday) => workday.date);
            const monthlyEmployee = {
                ...employee,
                holidayWorkedDates,
                holidayWorkDays: holidayWorkedDates.length,
                holidayRestDays: holidayDates.length - holidayWorkedDates.length,
                makeupWorkDates,
            };
            monthlyEmployee.status = getStatus(monthlyEmployee, monthlyEmployee.workingDays);
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
        addCell(row, formatLeaveTypes(employee.leaveByType) || '—');
        addCell(row, String(employee.absent), 'text-center');
        addCell(row, String(employee.late), 'text-center');
        addCell(row, String(employee.lateMinutes), 'text-center');
        addCell(row, String(employee.earlyMinutes), 'text-center');
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
            leave: result.leave + employee.leave,
            absent: result.absent + employee.absent,
            late: result.late + employee.late,
            lateMinutes: result.lateMinutes + employee.lateMinutes,
            earlyMinutes: result.earlyMinutes + employee.earlyMinutes,
            holidayWorkDays: result.holidayWorkDays + employee.holidayWorkDays,
            overtime: result.overtime + employee.overtime,
        }), { expected: 0, worked: 0, leave: 0, absent: 0, late: 0, lateMinutes: 0, earlyMinutes: 0, holidayWorkDays: 0, overtime: 0 });

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
            '—',
            total.absent,
            total.late,
            total.lateMinutes,
            total.earlyMinutes,
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
            absent: result.absent + employee.absent,
            earlyMinutes: result.earlyMinutes + employee.earlyMinutes,
        }), { worked: 0, late: 0, leave: 0, absent: 0, earlyMinutes: 0 });

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
        document.getElementById('stat-absent').textContent = formatNumber(totals.absent);
        document.getElementById('stat-early-minutes').textContent = formatNumber(totals.earlyMinutes);
        document.getElementById('standard-workdays').textContent =
            `${employees[0]?.workingDays || 0} ngày`;
        document.getElementById('period-caption').textContent =
            `Kỳ chấm công: ${getPayrollPeriodLabel()}`;
    };

    const updatePeriod = async ({ announce = false } = {}) => {
        selectedMonth = Number(monthSelect.value);
        selectedYear = Number(yearSelect.value);
        const requestId = ++periodUpdateId;
        const url = new URL(window.location.href);
        url.searchParams.set('month', String(selectedMonth));
        url.searchParams.set('year', String(selectedYear));
        window.history.replaceState({}, '', url);

        try {
            const result = await window.lumiCineApi('report', {
                query: { month: selectedMonth, year: selectedYear },
            });
            if (requestId !== periodUpdateId) return false;
            if (!Number.isInteger(result.payrollStartDay) || result.payrollStartDay < 1 || result.payrollStartDay > 31) {
                throw new Error('Ngày bắt đầu kỳ lương từ máy chủ không hợp lệ.');
            }
            selectedPayrollStartDay = result.payrollStartDay;
            const { start, end } = getPayrollPeriod();
            const holidayYears = [...new Set([start.getFullYear(), end.getFullYear()])];
            await Promise.all(holidayYears.map(loadHolidays));
            if (requestId !== periodUpdateId) return false;
            baseEmployees = result.employees;
            employees = buildMonthlyEmployees();
            renderStatistics();
            applyFilters();

            if (announce) showToast(`Đang xem kỳ chấm công ${getPayrollPeriodLabel()}.`);
            return true;
        } catch (error) {
            if (requestId !== periodUpdateId) return false;
            baseEmployees = [];
            employees = [];
            renderStatistics();
            applyFilters();
            setHolidaySourceStatus(
                error instanceof Error ? error.message : 'Không tải được dữ liệu báo cáo từ database.',
                'error',
            );
            return false;
        }
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

    const exportReport = () => {
        const headers = [
            'Mã nhân viên', 'Họ và tên', 'Phòng ban', 'Chức vụ',
            'Ngày công quy định', 'Ngày công thực tế', 'Nghỉ phép (ngày)',
            'Loại nghỉ', 'Nghỉ không phép', 'Số lần đi muộn',
            'Tổng phút đi muộn', 'Tổng phút về sớm', 'Ngày lễ nghỉ theo lịch', 'Ngày lễ đi làm',
            'Tổng giờ tăng ca', 'Trạng thái',
        ];
        const rows = filteredEmployees.map((employee) => [
            employee.id,
            employee.name,
            employee.department,
            employee.position,
            employee.workingDays,
            employee.worked,
            employee.leave,
            formatLeaveTypes(employee.leaveByType),
            employee.absent,
            employee.late,
            employee.lateMinutes,
            employee.earlyMinutes,
            employee.holidayRestDays,
            employee.holidayWorkDays,
            employee.overtime,
            statusLabels[employee.status],
        ]);
        const columnColors = [
            ['#dbeafe', '#1e40af'],
            ['#dcfce7', '#166534'],
            ['#fef3c7', '#92400e'],
            ['#ffedd5', '#9a3412'],
            ['#ccfbf1', '#115e59'],
            ['#ede9fe', '#5b21b6'],
            ['#e0e7ff', '#3730a3'],
        ];
        const colorLegend = [
            ['Thông tin nhân sự', 4],
            ['Ngày công', 2],
            ['Nghỉ phép / vắng', 3],
            ['Đi muộn / về sớm', 3],
            ['Ngày lễ', 2],
            ['Tăng ca', 1],
            ['Trạng thái', 1],
        ];
        const columnGroups = [0, 0, 0, 0, 1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 5, 6];
        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        const cellHtml = (value, columnIndex, isHeader = false) => {
            const [fill, color] = columnColors[columnGroups[columnIndex]];
            const tag = isHeader ? 'th' : 'td';
            const style = [
                `background-color:${fill}`,
                `color:${color}`,
                'border:1px solid #cbd5e1',
                'padding:7px 9px',
                isHeader ? 'font-weight:bold;text-align:center' : 'vertical-align:middle',
                isHeader ? '' : typeof value === 'number' && Number.isFinite(value)
                    ? 'mso-number-format:0.##'
                    : 'mso-number-format:"\\@"',
                columnIndex === 4 || columnIndex === 6 || columnIndex === 9 || columnIndex === 12 || columnIndex === 14
                    ? 'border-left:2px solid #94a3b8'
                    : '',
            ].filter(Boolean).join(';');
            const text = typeof value === 'string' && /^[\s]*[=+\-@]/.test(value)
                ? `'${value}`
                : value;
            return `<${tag} style="${style}">${escapeHtml(text ?? '')}</${tag}>`;
        };
        const headerRow = `<tr>${headers.map((header, index) => cellHtml(header, index, true)).join('')}</tr>`;
        const bodyRows = rows
            .map((row) => `<tr>${row.map((value, index) => cellHtml(value, index)).join('')}</tr>`)
            .join('');
        const legendTitle = `<tr><th colspan="${headers.length}" style="background-color:#1e293b;color:#fff;font-weight:bold;text-align:left;padding:9px 12px">CHÚ THÍCH MÀU</th></tr>`;
        const legendRow = `<tr>${colorLegend.map(([label, span], index) => {
            const [fill, color] = columnColors[index];
            return `<td colspan="${span}" style="background-color:${fill};color:${color};border:1px solid #cbd5e1;padding:8px 9px;font-weight:bold;text-align:center">${escapeHtml(label)}</td>`;
        }).join('')}</tr>`;
        const periodRow = `<tr><th colspan="${headers.length}" style="background-color:#f1f5f9;color:#334155;text-align:left;padding:8px 12px">Kỳ chấm công: ${getPayrollPeriodLabel()}</th></tr>`;
        const workbook = `\uFEFF<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Báo cáo công</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>
<body><table><thead>${legendTitle}${legendRow}${periodRow}${headerRow}</thead><tbody>${bodyRows}</tbody></table></body></html>`;
        const blob = new Blob([workbook], { type: 'application/vnd.ms-excel;charset=utf-8' });
        const link = document.createElement('a');
        const objectUrl = URL.createObjectURL(blob);
        link.href = objectUrl;
        link.download = `bang-tong-hop-cong-${selectedYear}-${String(selectedMonth).padStart(2, '0')}.xls`;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        showToast(`Đã xuất báo cáo ${filteredEmployees.length} nhân viên với màu phân biệt từng nhóm dữ liệu.`);
    };

    const getDailyCalendar = (employee) => {
        const businessDays = [];
        const calendarDays = [];
        const { start, end } = getPayrollPeriod();
        const calendarEntries = new Map([
            ...getPayrollPeriodEntries('holiday'),
            ...getPayrollPeriodEntries('workday'),
        ].map((entry) => [entry.date, entry]));
        const attendanceByDate = new Map((employee.dailyRecords || []).map((record) => [record.date, record]));
        const date = new Date(start);

        while (date <= end) {
            const weekDay = date.getDay();
            const dateKey = formatDateKey(date);
            const attendance = attendanceByDate.get(dateKey);
            const day = {
                date: date.getDate(),
                dateLabel: `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`,
                fullDate: formatDateLabel(date),
                state: 'weekend',
                checkIn: '',
                checkOut: '',
            };
            const calendarEntry = calendarEntries.get(dateKey);
            const holiday = calendarEntry?.type === 'holiday' ? calendarEntry : null;
            const makeupWorkday = calendarEntry?.type === 'workday' ? calendarEntry : null;
            if (holiday) {
                day.state = attendance?.checkIn ? 'holiday-worked' : 'holiday-off';
                day.holidayName = holiday.name;
            } else if (makeupWorkday || (weekDay !== 0 && weekDay !== 6)) {
                day.state = 'unrecorded';
                day.makeupWorkday = Boolean(makeupWorkday);
                if (makeupWorkday) day.holidayName = makeupWorkday.name;
                businessDays.push(day);
            }
            if (attendance) {
                if (attendance.state === 'absent') {
                    day.state = 'absent';
                } else if (attendance.state === 'leave') {
                    day.state = 'leave';
                } else if (attendance.state === 'partial-leave') {
                    day.state = 'partial-leave';
                } else if (attendance.state === 'incomplete') {
                    day.state = 'incomplete';
                } else if (attendance.checkIn) {
                    day.state = attendance.state;
                    day.lateMinutes = attendance.lateMinutes;
                    day.earlyMinutes = attendance.earlyMinutes;
                }
                day.checkIn = attendance.checkIn;
                day.checkOut = attendance.checkOut;
                day.leaveTypes = attendance.leaveTypes || [];
            }
            calendarDays.push(day);
            date.setDate(date.getDate() + 1);
        }

        businessDays.forEach((day) => {
            if (day.makeupWorkday && day.state === 'unrecorded') day.state = 'makeup-workday';
        });

        const leadingEmptyDays = (start.getDay() + 6) % 7;
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
        document.getElementById('modal-period').textContent = getPayrollPeriodLabel();
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
        const recordsWithCheckIn = (employee.dailyRecords || [])
            .filter((record) => record.checkIn)
            .sort((first, second) => second.date.localeCompare(first.date));
        const latestCheckIn = recordsWithCheckIn[0]?.checkIn || 'Chưa có';
        const latestCheckOut = recordsWithCheckIn.find((record) => record.checkOut)?.checkOut || 'Chưa có';
        appendDetailMetric(metrics, 'Ngày công thực tế', `${employee.worked}/${employee.workingDays}`);
        appendDetailMetric(metrics, 'Nghỉ phép', `${employee.leave} ngày`, formatLeaveTypes(employee.leaveByType) || 'Chưa có đơn nghỉ trọn ca đã duyệt');
        appendDetailMetric(metrics, 'Vắng mặt', `${employee.absent} ngày`);
        appendDetailMetric(metrics, 'Đi muộn', `${employee.late} lượt · ${employee.lateMinutes} phút`);
        appendDetailMetric(metrics, 'Về sớm', `${employee.earlyMinutes} phút`);
        appendDetailMetric(metrics, 'Nghỉ việc', employee.terminationDate || 'Chưa ghi nhận');
        appendDetailMetric(metrics, 'Giờ check-in gần nhất', latestCheckIn);
        appendDetailMetric(metrics, 'Giờ check-out gần nhất', latestCheckOut);
        appendDetailMetric(metrics, 'Ngày lễ nghỉ theo lịch', `${employee.holidayRestDays} ngày`);
        appendDetailMetric(
        metrics,
        'Tăng ca',
        `${formatNumber(employee.overtime)} giờ`,
        `Lễ đi làm: ${employee.holidayWorkDays} ngày · Làm bù: ${employee.makeupWorkDates.length} ngày · Chưa cấu hình dữ liệu lương trong database.`,
        );
        body.append(metrics);

        const sectionHeading = document.createElement('h3');
        sectionHeading.className = 'detail-section-title';
        sectionHeading.textContent = 'Lịch chấm công';
        const period = document.createElement('span');
        period.textContent = getPayrollPeriodLabel();
        sectionHeading.append(period);
        body.append(sectionHeading);

        const legend = document.createElement('div');
        legend.className = 'calendar-legend';
        [
            ['on-time', 'Đúng giờ'],
            ['late', 'Đi muộn'],
            ['early', 'Về sớm'],
            ['leave', 'Nghỉ phép'],
            ['partial-leave', 'Nghỉ phép một phần'],
            ['absent', 'Nghỉ không phép'],
            ['incomplete', 'Chưa check-out'],
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
        calendar.setAttribute('aria-label', `Lịch chấm công ${getPayrollPeriodLabel()}`);

        ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'].forEach((label) => {
            const weekday = document.createElement('div');
            weekday.className = 'calendar-weekday';
            weekday.setAttribute('role', 'columnheader');
            weekday.textContent = label;
            calendar.append(weekday);
        });

        const stateLabels = {
            'on-time': 'Đúng giờ',
            late: 'Đi muộn',
            early: 'Về sớm',
            leave: 'Nghỉ phép',
            'partial-leave': 'Nghỉ phép một phần',
            absent: 'Nghỉ không phép',
            incomplete: 'Chưa check-out',
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
                dayNumber.textContent = day.dateLabel;
                if (day.checkIn || day.checkOut) {
                    const times = document.createElement('span');
                    times.className = 'calendar-day-times';
                    times.textContent = `Vào ${day.checkIn || '--:--'} · Ra ${day.checkOut || '--:--'}`;
                    cell.append(dayNumber, times);
                } else {
                    const times = document.createElement('span');
                    times.className = 'calendar-day-times calendar-day-times-empty';
                    times.textContent = 'Vào --:-- · Ra --:--';
                    cell.append(dayNumber, times);
                }
                const state = document.createElement('span');
                state.className = 'calendar-day-state';
                state.textContent = day.makeupWorkday
                    ? day.state === 'makeup-workday' || day.state === 'unrecorded'
                        ? 'Ngày làm bù'
                        : `Làm bù · ${stateLabels[day.state]}`
                    : day.holidayName
                        ? `${day.state === 'holiday-worked' ? 'Đi làm · ' : 'Nghỉ lễ · '}${day.holidayName}`
                        : stateLabels[day.state];
                const attendanceNotes = [];
                if (day.lateMinutes) attendanceNotes.push(`Muộn ${day.lateMinutes} phút`);
                if (day.earlyMinutes) attendanceNotes.push(`Về sớm ${day.earlyMinutes} phút`);
                if (attendanceNotes.length > 0) {
                    state.textContent = `${day.makeupWorkday ? 'Làm bù · ' : ''}${attendanceNotes.join(' · ')}`;
                }
                cell.append(state);
                cell.setAttribute('aria-label', `${day.fullDate}: ${day.makeupWorkday ? `Ngày làm bù, ${stateLabels[day.state]}` : day.holidayName || stateLabels[day.state]}`);
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

        const date = dateInput.value;
        const name = nameInput.value.trim();
        const dateYear = Number(date.slice(0, 4));
        const manualHolidayError = manualHolidayErrorsByYear.get(dateYear);
        if (manualHolidayError) {
            setHolidaySourceStatus(`${manualHolidayError} Không thể lưu ngày bổ sung.`, 'error');
            return;
        }
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

        try {
            await window.lumiCineApi('holidays', {
                method: 'POST',
                body: { date, name, type: typeInput.value },
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Không thể lưu ngày nghỉ.';
            setHolidaySourceStatus(message, 'error');
            return;
        }
        nameInput.value = '';
        if (await updatePeriod()) {
            showToast(`Đã thêm ${name} vào lịch database ${dateYear}.`);
        } else {
            setHolidaySourceStatus('Ngày nghỉ đã được lưu, nhưng không thể tải lại báo cáo từ database.', 'error');
        }
    });
    holidayList.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-holiday]');
        if (!button) return;
        const date = button.dataset.removeHoliday;
        try {
            await window.lumiCineApi('holidays', {
                method: 'POST',
                body: { operation: 'remove', date },
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Không thể xóa ngày nghỉ.';
            setHolidaySourceStatus(message, 'error');
            return;
        }
        if (await updatePeriod()) {
            showToast('Đã xóa ngày bổ sung khỏi database.');
        } else {
            setHolidaySourceStatus('Ngày nghỉ đã được xóa, nhưng không thể tải lại báo cáo từ database.', 'error');
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

    fillFilterOptions(departmentFilter, baseEmployees.map((employee) => employee.department), 'Tất cả phòng ban');
    fillFilterOptions(positionFilter, baseEmployees.map((employee) => employee.position), 'Tất cả chức vụ');
    updatePeriod();
})();
