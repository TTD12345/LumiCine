(() => {
    'use strict';

    const form = document.getElementById('report-filter-form');
    if (!form) return;

    const fromInput = document.getElementById('report-from');
    const toInput = document.getElementById('report-to');
    const departmentInput = document.getElementById('report-department');
    const resetButton = document.getElementById('report-reset');
    const exportButton = document.getElementById('report-export');
    const feedback = document.getElementById('report-feedback');
    const charts = {};

    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const toDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    fromInput.value = toDate(firstDay);
    toInput.value = toDate(today);

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#718397', boxWidth: 10, boxHeight: 10, font: { family: 'Be Vietnam Pro', size: 10 } } },
            tooltip: { backgroundColor: '#213b56', titleFont: { family: 'Be Vietnam Pro' }, bodyFont: { family: 'Be Vietnam Pro' } },
        },
    };

    function setEmpty(id, visible) {
        document.getElementById(id).hidden = !visible;
    }

    function destroyAndCreate(id, config) {
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), config);
    }

    function hasValues(values) {
        return values.some((value) => Number(value) !== 0);
    }

    function renderReport(result) {
        const summary = result.summary;
        document.getElementById('report-total-employees').textContent = summary.total_employees;
        document.getElementById('report-total-work').textContent = summary.total_work_days;
        document.getElementById('report-total-late').textContent = summary.total_late;
        document.getElementById('report-total-leave').textContent = summary.total_leave_days;

        const attendance = result.attendance_daily;
        const attendanceLabels = attendance.map((item) => item.date);
        const onTime = attendance.map((item) => item.on_time);
        const late = attendance.map((item) => item.late);
        const absent = attendance.map((item) => item.absent);
        setEmpty('attendance-empty', !hasValues([...onTime, ...late, ...absent]));
        destroyAndCreate('attendanceChart', {
            type: 'line',
            data: {
                labels: attendanceLabels,
                datasets: [
                    { label: 'Đúng giờ', data: onTime, borderColor: '#39936e', backgroundColor: '#39936e22', tension: 0.25, fill: true },
                    { label: 'Đi muộn', data: late, borderColor: '#c68236', backgroundColor: '#c6823622', tension: 0.25, fill: false },
                    { label: 'Vắng mặt', data: absent, borderColor: '#d05d5d', backgroundColor: '#d05d5d22', tension: 0.25, fill: false },
                ],
            },
            options: {
                ...chartOptions,
                scales: {
                    x: { ticks: { color: '#8b98a6', font: { size: 9 }, maxTicksLimit: 12 }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#8b98a6', font: { size: 9 } }, grid: { color: '#edf1f5' } },
                },
            },
        });

        const leave = result.leave_status;
        const leaveValues = [leave.approved, leave.pending, leave.rejected];
        setEmpty('leave-empty', !hasValues(leaveValues));
        destroyAndCreate('leaveChart', {
            type: 'doughnut',
            data: {
                labels: ['Đã duyệt', 'Đang chờ duyệt', 'Bị từ chối'],
                datasets: [{ data: leaveValues, backgroundColor: ['#39936e', '#c68236', '#d05d5d'], borderWidth: 3, borderColor: '#fff' }],
            },
            options: { ...chartOptions, cutout: '68%' },
        });

        const departments = result.department_work;
        setEmpty('department-empty', departments.length === 0);
        destroyAndCreate('departmentChart', {
            type: 'bar',
            data: {
                labels: departments.map((item) => item.department),
                datasets: [{ label: 'Ngày công', data: departments.map((item) => item.work_days), backgroundColor: '#347abd', borderRadius: 6, maxBarThickness: 42 }],
            },
            options: {
                ...chartOptions,
                scales: {
                    x: { ticks: { color: '#8b98a6', font: { size: 9 } }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#8b98a6', font: { size: 9 } }, grid: { color: '#edf1f5' } },
                },
            },
        });

        const overtime = result.overtime_daily;
        const overtimeLabels = overtime.map((item) => item.date);
        const overtimeApproved = overtime.map((item) => item.approved);
        const overtimePending = overtime.map((item) => item.pending);
        const overtimeRejected = overtime.map((item) => item.rejected);
        setEmpty('overtime-empty', !hasValues([...overtimeApproved, ...overtimePending, ...overtimeRejected]));
        destroyAndCreate('overtimeChart', {
            type: 'bar',
            data: {
                labels: overtimeLabels,
                datasets: [
                    { label: 'Đã duyệt', data: overtimeApproved, backgroundColor: '#39936e', borderRadius: 4 },
                    { label: 'Chờ duyệt', data: overtimePending, backgroundColor: '#c68236', borderRadius: 4 },
                    { label: 'Từ chối', data: overtimeRejected, backgroundColor: '#d05d5d', borderRadius: 4 },
                ],
            },
            options: {
                ...chartOptions,
                scales: {
                    x: { stacked: true, ticks: { color: '#8b98a6', font: { size: 8 }, maxTicksLimit: 12 }, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: '#8b98a6', font: { size: 9 } }, grid: { color: '#edf1f5' } },
                },
            },
        });

        const top = result.top_employees;
        setEmpty('top-empty', top.length === 0);
        destroyAndCreate('topChart', {
            type: 'bar',
            data: {
                labels: top.map((item) => item.name),
                datasets: [{ label: 'Ngày công', data: top.map((item) => item.work_days), backgroundColor: '#725ec3', borderRadius: 6, maxBarThickness: 34 }],
            },
            options: {
                ...chartOptions,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, color: '#8b98a6', font: { size: 9 } }, grid: { color: '#edf1f5' } },
                    y: { ticks: { color: '#40566d', font: { size: 9 } }, grid: { display: false } },
                },
            },
        });
    }

    async function loadReport() {
        if (!form.reportValidity()) return;
        feedback.textContent = 'Đang tải dữ liệu...';
        feedback.dataset.state = '';
        try {
            const params = {
                from: fromInput.value,
                to: toInput.value,
                department: departmentInput.value,
            };
            const result = await window.lumiCineApi('report-dashboard', { query: params });
            renderReport(result.report);
            feedback.textContent = `Đã cập nhật báo cáo từ ${params.from} đến ${params.to}.`;
        } catch (error) {
            feedback.textContent = error instanceof Error ? error.message : 'Không thể tải dữ liệu báo cáo.';
            feedback.dataset.state = 'error';
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        loadReport();
    });

    resetButton.addEventListener('click', () => {
        fromInput.value = toDate(firstDay);
        toInput.value = toDate(today);
        departmentInput.value = '';
        loadReport();
    });

    exportButton.addEventListener('click', () => {
        if (!form.reportValidity()) return;
        const url = new URL(window.lumiCineApiConfig.url, window.location.href);
        url.searchParams.set('resource', 'report-export');
        url.searchParams.set('from', fromInput.value);
        url.searchParams.set('to', toInput.value);
        if (departmentInput.value) url.searchParams.set('department', departmentInput.value);
        window.location.assign(url.toString());
    });

    window.addEventListener('load', loadReport);
}) ();
