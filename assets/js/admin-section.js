(() => {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const accountButton = document.getElementById('admin-account-menu-button');
    const accountMenu = document.getElementById('admin-account-menu');

    document.getElementById('sidebar-toggle').addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
        backdrop.classList.toggle('is-visible');
    });
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-visible');
    });
    accountButton.addEventListener('click', () => {
        accountMenu.hidden = !accountMenu.hidden;
        accountButton.setAttribute('aria-expanded', String(!accountMenu.hidden));
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.topbar-actions')) {
            accountMenu.hidden = true;
            accountButton.setAttribute('aria-expanded', 'false');
        }
    });
    document.getElementById('notification-button').addEventListener('click', () => {
        const toast = document.createElement('div');
        toast.className = 'employee-toast';
        toast.textContent = 'Không có thông báo mới.';
        document.getElementById('toast-container').append(toast);
        window.setTimeout(() => toast.remove(), 4000);
    });

    const mappingTable = document.getElementById('employee-account-mappings');
    const mappingFeedback = document.getElementById('mapping-feedback');
    if (mappingTable && mappingFeedback) {
        mappingTable.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-save-mapping]');
            if (!button) {
                return;
            }

            const row = button.closest('[data-mapping-row]');
            const employeeCode = row.querySelector('[data-employee-code]').value;
            button.disabled = true;
            mappingFeedback.textContent = '';

            try {
                await window.lumiCineApi('user-mappings', {
                    method: 'POST',
                    body: {
                        userId: Number(row.dataset.userId),
                        employeeCode: employeeCode || null,
                    },
                });
                mappingFeedback.textContent = 'Đã lưu liên kết. Nhân viên cần đăng xuất rồi đăng nhập lại để áp dụng.';
            } catch (error) {
                mappingFeedback.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    }

    const payrollSettingsForm = document.getElementById('payroll-settings-form');
    if (payrollSettingsForm) {
        const startDayInput = document.getElementById('payroll-start-day');
        const saveButton = document.getElementById('save-payroll-settings');
        const feedback = document.getElementById('payroll-settings-feedback');

        payrollSettingsForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!startDayInput.reportValidity()) return;

            saveButton.disabled = true;
            feedback.textContent = '';
            feedback.dataset.state = '';
            try {
                const result = await window.lumiCineApi('payroll-settings', {
                    method: 'POST',
                    body: { payrollStartDay: Number(startDayInput.value) },
                });
                startDayInput.value = String(result.payrollStartDay);
                feedback.dataset.state = 'success';
                feedback.textContent = `Đã lưu. Bảng tổng hợp công sẽ tính kỳ lương bắt đầu vào ngày ${result.payrollStartDay}.`;
            } catch (error) {
                feedback.dataset.state = 'error';
                feedback.textContent = error instanceof Error ? error.message : 'Không thể lưu ngày lương.';
            } finally {
                saveButton.disabled = false;
            }
        });
    }

    const attendanceTable = document.getElementById('admin-attendance-table');
    if (attendanceTable && attendanceTable.dataset.filtersInitialized !== 'true') {
        attendanceTable.dataset.filtersInitialized = 'true';
        const searchInput = document.getElementById('admin-attendance-search');
        const departmentFilter = document.getElementById('admin-attendance-department');
        const positionFilter = document.getElementById('admin-attendance-position');
        const statusFilter = document.getElementById('admin-attendance-status');
        const countLabel = document.getElementById('attendance-filter-count');
        const emptyState = document.getElementById('admin-attendance-empty');
        const exportButton = document.getElementById('export-attendance-report');
        const printButton = document.getElementById('print-attendance-report');
        const rows = [...attendanceTable.querySelectorAll('[data-attendance-row]')];

        const normalizeText = (value) => String(value)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLocaleLowerCase('vi');

        const applyAttendanceFilters = () => {
            const query = normalizeText(searchInput.value.trim());
            const department = departmentFilter.value;
            const position = positionFilter.value;
            const status = statusFilter.value;
            let visibleCount = 0;

            rows.forEach((row) => {
                const matches = (!query || normalizeText(row.dataset.employeeSearch).includes(query))
                    && (!department || row.dataset.department === department)
                    && (!position || row.dataset.position === position)
                    && (!status || row.dataset.status === status);
                row.hidden = !matches;
                if (matches) visibleCount += 1;
            });

            countLabel.textContent = `Hiển thị ${visibleCount} trong ${rows.length} nhân viên`;
            attendanceTable.hidden = visibleCount === 0;
            emptyState.hidden = visibleCount > 0;
            exportButton.disabled = visibleCount === 0;
            printButton.disabled = visibleCount === 0;
        };

        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        exportButton.addEventListener('click', () => {
            const visibleRows = rows.filter((row) => !row.hidden);
            if (visibleRows.length === 0) return;

            const headers = [...attendanceTable.tHead.rows[0].cells].map((cell) => cell.textContent.trim());
            const columnColors = [
                { header: '#fbdada', headerText: '#a12626', fill: '#fff0f0', text: '#a12626' },
                { header: '#eaf1f8', headerText: '#304a63', fill: '#ffffff', text: '#40566d' },
                { header: '#fbdada', headerText: '#a12626', fill: '#fff0f0', text: '#a12626' },
                { header: '#eaf1f8', headerText: '#304a63', fill: '#ffffff', text: '#40566d' },
                { header: '#dff3e7', headerText: '#26734b', fill: '#edf8f1', text: '#26734b' },
                { header: '#e3efff', headerText: '#326da8', fill: '#f0f6ff', text: '#326da8' },
                { header: '#fde7e5', headerText: '#b5433e', fill: '#fff3f2', text: '#b5433e' },
                { header: '#fff0d9', headerText: '#a96516', fill: '#fff8ec', text: '#a96516' },
                { header: '#eee8ff', headerText: '#6548a5', fill: '#f6f2ff', text: '#6548a5' },
                { header: '#e6edf5', headerText: '#304a63', fill: '#f7f9fc', text: '#40566d' },
            ];
            const statusColors = {
                complete: { fill: '#e6f5eb', text: '#26734b' },
                incomplete: { fill: '#fff2dc', text: '#a96516' },
                leave: { fill: '#e8f1ff', text: '#326da8' },
                absent: { fill: '#fde9e7', text: '#b5433e' },
            };
            const cellHtml = (value, columnIndex, isHeader = false, statusCode = '') => {
                const rawText = String(value);
                const text = /^[\s]*[=+\-@]/.test(rawText) ? `'${rawText}` : rawText;
                const tag = isHeader ? 'th' : 'td';
                const colors = columnColors[columnIndex] || columnColors[0];
                let fill = isHeader ? colors.header : '#ffffff';
                let color = isHeader ? colors.headerText : '#40566d';
                let weight = isHeader;
                if (!isHeader && (columnIndex === 0 || columnIndex === 2)) {
                    fill = colors.fill;
                    color = colors.text;
                    weight = true;
                } else if (!isHeader && columnIndex >= 4 && columnIndex <= 8 && Number.parseInt(rawText, 10) > 0) {
                    fill = colors.fill;
                    color = colors.text;
                    weight = true;
                }
                if (!isHeader && columnIndex === 9 && statusColors[statusCode]) {
                    fill = statusColors[statusCode].fill;
                    color = statusColors[statusCode].text;
                    weight = true;
                }
                const style = [
                    `background-color:${fill}`,
                    `color:${color}`,
                    'border:1px solid #cbd5e1',
                    'padding:7px',
                    weight ? 'font-weight:bold' : '',
                    isHeader ? 'text-align:center' : '',
                    isHeader ? '' : 'mso-number-format:"\\@"',
                ].filter(Boolean).join(';');
                return `<${tag} style="${style}">${escapeHtml(text)}</${tag}>`;
            };
            const headerRow = `<tr>${headers.map((header, index) => cellHtml(header, index, true)).join('')}</tr>`;
            const dataRows = visibleRows.map((row) =>
                `<tr>${[...row.cells].map((cell, index) =>
                    cellHtml(cell.textContent.trim(), index, false, row.dataset.status)
                ).join('')}</tr>`
            ).join('');
            const legendItems = [
                ['#e6f5eb', '#26734b', 'Ngày công'],
                ['#e8f1ff', '#326da8', 'Nghỉ phép'],
                ['#fde9e7', '#b5433e', 'Vắng mặt'],
                ['#fff2dc', '#a96516', 'Đi muộn'],
                ['#eee8ff', '#6548a5', 'Về sớm'],
            ];
            const legendRow = `<tr>${legendItems.map(([fill, color, label]) =>
                `<td colspan="2" style="background-color:${fill};color:${color};font-weight:bold;border:1px solid #cbd5e1;padding:7px;text-align:center">${label}</td>`
            ).join('')}</tr>`;
            const month = document.querySelector('.attendance-period-filter select[name="month"]').value;
            const year = document.querySelector('.attendance-period-filter select[name="year"]').value;
            const workbook = `\uFEFF<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Chấm công</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>
<body><table><tr><th colspan="${headers.length}" style="background-color:#1e293b;color:#fff;font-size:14pt;padding:10px">BÁO CÁO CHẤM CÔNG</th></tr><tr><td colspan="${headers.length}" style="background-color:#f1f5f9;color:#334155;padding:8px">Kỳ chấm công: tháng ${month}/${year} · ${visibleRows.length} nhân viên</td></tr><tr><td colspan="${headers.length}" style="background-color:#f8fafc;color:#64748b;padding:7px">Chú thích màu: xanh lá — ngày công · xanh dương — nghỉ phép · đỏ — vắng mặt · cam — đi muộn · tím — về sớm</td></tr>${headerRow}${legendRow}${dataRows}</table></body></html>`;
            const blob = new Blob([workbook], { type: 'application/vnd.ms-excel;charset=utf-8' });
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = `bao-cao-cham-cong-${year}-${String(month).padStart(2, '0')}.xls`;
            document.body.append(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        });
        printButton.addEventListener('click', () => window.print());
        document.getElementById('apply-attendance-filters').addEventListener('click', applyAttendanceFilters);
        document.getElementById('reset-attendance-filters').addEventListener('click', () => {
            searchInput.value = '';
            departmentFilter.value = '';
            positionFilter.value = '';
            statusFilter.value = '';
            applyAttendanceFilters();
        });
        searchInput.addEventListener('input', applyAttendanceFilters);
        [departmentFilter, positionFilter, statusFilter].forEach((filter) => {
            filter.addEventListener('change', applyAttendanceFilters);
        });
        applyAttendanceFilters();
    }
})();
