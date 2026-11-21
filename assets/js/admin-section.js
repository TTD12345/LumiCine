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
})();
