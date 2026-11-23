(() => {
    'use strict';

    const table = document.querySelector('.account-management tbody');
    const dialog = document.getElementById('employee-account-dialog');
    const form = document.getElementById('employee-account-form');
    const idInput = document.getElementById('employee-account-id');
    const employeeField = document.getElementById('account-employee-field');
    const employeeInput = document.getElementById('account-employee');
    const usernameInput = document.getElementById('account-username');
    const passwordInput = document.getElementById('account-password');
    const title = document.getElementById('employee-account-dialog-title');
    const feedback = document.getElementById('employee-account-dialog-feedback');
    const pageFeedback = document.getElementById('employee-accounts-feedback');
    const saveButton = document.getElementById('save-employee-account');
    const addButton = document.getElementById('add-employee-account');

    const closeDialog = () => dialog.close();
    const setFeedback = (element, message, state = '') => {
        element.textContent = message;
        element.dataset.state = state;
    };

    const openForCreate = () => {
        form.reset();
        idInput.value = '';
        title.textContent = 'Thêm tài khoản';
        employeeField.hidden = false;
        employeeInput.required = true;
        passwordInput.required = true;
        passwordInput.setAttribute('minlength', '8');
        setFeedback(feedback, '');
        dialog.showModal();
    };

    addButton.addEventListener('click', openForCreate);
    document.querySelectorAll('[data-close-account-dialog]').forEach((button) => {
        button.addEventListener('click', closeDialog);
    });

    table.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit-account]');
        if (editButton) {
            const row = editButton.closest('[data-account-row]');
            form.reset();
            idInput.value = row.dataset.id;
            usernameInput.value = row.dataset.username;
            title.textContent = 'Chỉnh sửa tài khoản';
            employeeField.hidden = true;
            employeeInput.required = false;
            passwordInput.required = false;
            passwordInput.removeAttribute('minlength');
            setFeedback(feedback, '');
            dialog.showModal();
            return;
        }

        const deleteButton = event.target.closest('[data-delete-account]');
        if (!deleteButton) return;

        const row = deleteButton.closest('[data-account-row]');
        if (!window.confirm(`Xóa tài khoản "${row.dataset.username}"? Hồ sơ nhân viên sẽ được giữ nguyên.`)) {
            return;
        }

        deleteButton.disabled = true;
        setFeedback(pageFeedback, '');
        try {
            await window.lumiCineApi('employee-accounts', {
                method: 'POST',
                body: { operation: 'delete', id: Number(row.dataset.id) },
            });
            window.location.reload();
        } catch (error) {
            setFeedback(pageFeedback, error.message, 'error');
            deleteButton.disabled = false;
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        const body = {
            username: usernameInput.value,
            password: passwordInput.value,
        };
        if (idInput.value) {
            body.id = Number(idInput.value);
        } else {
            body.employeeId = Number(employeeInput.value);
        }

        saveButton.disabled = true;
        setFeedback(feedback, '');
        try {
            await window.lumiCineApi('employee-accounts', { method: 'POST', body });
            closeDialog();
            window.location.reload();
        } catch (error) {
            setFeedback(feedback, error.message, 'error');
        } finally {
            saveButton.disabled = false;
        }
    });
})();
