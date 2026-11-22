(() => {
    'use strict';

    const employeeTable = document.getElementById('workforce-employee-table');
    const departmentTable = document.getElementById('workforce-department-table');
    const employeeDialog = document.getElementById('employee-dialog');
    const departmentDialog = document.getElementById('department-dialog');
    const employeeForm = document.getElementById('employee-form');
    const departmentForm = document.getElementById('department-form');
    const employeeFeedback = document.getElementById('employee-management-feedback');
    const departmentFeedback = document.getElementById('department-management-feedback');
    const employeeDialogFeedback = document.getElementById('employee-dialog-feedback');
    const departmentDialogFeedback = document.getElementById('department-dialog-feedback');
    const departmentSelect = document.getElementById('employee-department');

    const setFeedback = (element, message, state = '') => {
        element.textContent = message;
        element.dataset.state = state;
    };

    const appendCell = (row, value) => {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.append(cell);
        return cell;
    };

    const renderEmployees = (employees) => {
        const body = employeeTable.querySelector('tbody');
        body.replaceChildren();
        if (employees.length === 0) {
            const row = document.createElement('tr');
            appendCell(row, 'Chưa có hồ sơ nhân viên.').colSpan = 6;
            body.append(row);
            return;
        }

        employees.forEach((employee) => {
            const active = Number(employee.active) === 1;
            const row = document.createElement('tr');
            row.dataset.employeeRow = '';
            row.dataset.id = String(employee.id);
            row.dataset.code = employee.code;
            row.dataset.name = employee.name;
            row.dataset.position = employee.position;
            row.dataset.departmentId = employee.department_id === null ? '' : String(employee.department_id);
            row.dataset.active = active ? '1' : '0';
            appendCell(row, employee.code);
            appendCell(row, employee.name);
            appendCell(row, employee.department || 'Chưa phân công');
            appendCell(row, employee.position || '—');

            const statusCell = document.createElement('td');
            const status = document.createElement('span');
            status.className = `status-badge ${active ? 'status-complete' : 'status-incomplete'}`;
            status.textContent = active ? 'Đang làm việc' : 'Ngừng hoạt động';
            statusCell.append(status);
            row.append(statusCell);

            const actions = document.createElement('td');
            actions.className = 'workforce-row-actions';
            const editButton = document.createElement('button');
            editButton.className = 'button button-outline';
            editButton.type = 'button';
            editButton.dataset.editEmployee = '';
            editButton.textContent = 'Sửa';
            const statusButton = document.createElement('button');
            statusButton.className = 'button button-light';
            statusButton.type = 'button';
            statusButton.dataset.toggleEmployee = '';
            statusButton.textContent = active ? 'Ngừng hoạt động' : 'Kích hoạt lại';
            actions.append(editButton, statusButton);
            row.append(actions);
            body.append(row);
        });
    };

    const renderDepartments = (departments) => {
        const body = departmentTable.querySelector('tbody');
        body.replaceChildren();
        if (departments.length === 0) {
            const row = document.createElement('tr');
            appendCell(row, 'Chưa có phòng ban.').colSpan = 3;
            body.append(row);
            return;
        }

        departments.forEach((department) => {
            const row = document.createElement('tr');
            row.dataset.departmentRow = '';
            row.dataset.id = String(department.id);
            row.dataset.name = department.name;
            row.dataset.count = String(department.employee_count);
            appendCell(row, department.name);
            appendCell(row, String(department.employee_count));
            const actions = document.createElement('td');
            actions.className = 'workforce-row-actions';
            const editButton = document.createElement('button');
            editButton.className = 'button button-outline';
            editButton.type = 'button';
            editButton.dataset.editDepartment = '';
            editButton.textContent = 'Sửa';
            const deleteButton = document.createElement('button');
            deleteButton.className = 'button button-light';
            deleteButton.type = 'button';
            deleteButton.dataset.deleteDepartment = '';
            deleteButton.textContent = 'Xóa';
            actions.append(editButton, deleteButton);
            row.append(actions);
            body.append(row);
        });
    };

    const reloadManagementLists = async () => {
        const [employeeResult, departmentResult] = await Promise.all([
            window.lumiCineApi('employees', { query: { includeInactive: 1 } }),
            window.lumiCineApi('departments'),
        ]);
        renderEmployees(employeeResult.employees);
        renderDepartments(departmentResult.departments);

        const selectedDepartment = departmentSelect.value;
        departmentSelect.replaceChildren(new Option('Chưa phân công', ''));
        departmentResult.departments.forEach((department) => {
            departmentSelect.add(new Option(department.name, String(department.id)));
        });
        if ([...departmentSelect.options].some((option) => option.value === selectedDepartment)) {
            departmentSelect.value = selectedDepartment;
        }
    };

    const openEmployeeDialog = (row = null) => {
        employeeForm.reset();
        setFeedback(employeeDialogFeedback, '');
        document.getElementById('employee-record-id').value = row ? row.dataset.id : '';
        document.getElementById('employee-dialog-title').textContent = row ? 'Cập nhật nhân viên' : 'Thêm nhân viên';
        document.getElementById('employee-code').value = row ? row.dataset.code : '';
        document.getElementById('employee-name').value = row ? row.dataset.name : '';
        document.getElementById('employee-position').value = row ? row.dataset.position : '';
        departmentSelect.value = row ? row.dataset.departmentId : '';
        employeeDialog.showModal();
    };

    const openDepartmentDialog = (row = null) => {
        departmentForm.reset();
        setFeedback(departmentDialogFeedback, '');
        document.getElementById('department-record-id').value = row ? row.dataset.id : '';
        document.getElementById('department-dialog-title').textContent = row ? 'Cập nhật phòng ban' : 'Thêm phòng ban';
        document.getElementById('department-name').value = row ? row.dataset.name : '';
        departmentDialog.showModal();
    };

    document.getElementById('add-workforce-employee').addEventListener('click', () => openEmployeeDialog());
    document.getElementById('add-workforce-department').addEventListener('click', () => openDepartmentDialog());
    document.querySelectorAll('[data-close-employee-dialog]').forEach((button) => {
        button.addEventListener('click', () => employeeDialog.close());
    });
    document.querySelectorAll('[data-close-department-dialog]').forEach((button) => {
        button.addEventListener('click', () => departmentDialog.close());
    });

    employeeTable.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        const row = button && button.closest('[data-employee-row]');
        if (!row) return;
        if (button.hasAttribute('data-edit-employee')) {
            openEmployeeDialog(row);
            return;
        }
        if (!button.hasAttribute('data-toggle-employee')) return;

        const nextActive = row.dataset.active !== '1';
        const actionText = nextActive ? 'kích hoạt lại' : 'ngừng hoạt động';
        if (!window.confirm(`Bạn có chắc muốn ${actionText} nhân viên ${row.dataset.name}?`)) return;
        button.disabled = true;
        setFeedback(employeeFeedback, '');
        try {
            await window.lumiCineApi('employees', {
                method: 'POST',
                body: { operation: 'status', id: Number(row.dataset.id), active: nextActive },
            });
            await reloadManagementLists();
            setFeedback(employeeFeedback, nextActive ? 'Đã kích hoạt nhân viên.' : 'Đã ngừng hoạt động nhân viên.', 'success');
        } catch (error) {
            setFeedback(employeeFeedback, error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    departmentTable.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        const row = button && button.closest('[data-department-row]');
        if (!row) return;
        if (button.hasAttribute('data-edit-department')) {
            openDepartmentDialog(row);
            return;
        }
        if (!button.hasAttribute('data-delete-department')) return;
        if (!window.confirm(`Xóa phòng ban "${row.dataset.name}"? Phòng ban đang có nhân viên sẽ không thể xóa.`)) return;

        button.disabled = true;
        setFeedback(departmentFeedback, '');
        try {
            await window.lumiCineApi('departments', {
                method: 'POST',
                body: { operation: 'delete', id: Number(row.dataset.id) },
            });
            await reloadManagementLists();
            setFeedback(departmentFeedback, 'Đã xóa phòng ban.', 'success');
        } catch (error) {
            setFeedback(departmentFeedback, error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    employeeForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!employeeForm.reportValidity()) return;

        const saveButton = document.getElementById('save-workforce-employee');
        saveButton.disabled = true;
        setFeedback(employeeFeedback, '');
        setFeedback(employeeDialogFeedback, '');
        const id = document.getElementById('employee-record-id').value;
        try {
            await window.lumiCineApi('employees', {
                method: 'POST',
                body: {
                    operation: 'save',
                    id: id ? Number(id) : undefined,
                    code: document.getElementById('employee-code').value,
                    name: document.getElementById('employee-name').value,
                    position: document.getElementById('employee-position').value,
                    departmentId: departmentSelect.value || null,
                },
            });
            employeeDialog.close();
            await reloadManagementLists();
            setFeedback(employeeFeedback, id ? 'Đã cập nhật hồ sơ nhân viên.' : 'Đã thêm nhân viên.', 'success');
        } catch (error) {
            setFeedback(employeeDialogFeedback, error.message, 'error');
        } finally {
            saveButton.disabled = false;
        }
    });

    departmentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!departmentForm.reportValidity()) return;

        const saveButton = document.getElementById('save-workforce-department');
        saveButton.disabled = true;
        setFeedback(departmentFeedback, '');
        setFeedback(departmentDialogFeedback, '');
        const id = document.getElementById('department-record-id').value;
        try {
            await window.lumiCineApi('departments', {
                method: 'POST',
                body: {
                    operation: 'save',
                    id: id ? Number(id) : undefined,
                    name: document.getElementById('department-name').value,
                },
            });
            departmentDialog.close();
            await reloadManagementLists();
            setFeedback(departmentFeedback, id ? 'Đã cập nhật phòng ban.' : 'Đã thêm phòng ban.', 'success');
        } catch (error) {
            setFeedback(departmentDialogFeedback, error.message, 'error');
        } finally {
            saveButton.disabled = false;
        }
    });
})();
