(() => {
    'use strict';

    const api = window.lumiCineApi;
    const feedback = document.getElementById('auto-schedule-feedback');
    const ruleForm = document.getElementById('auto-rule-form');
    const policyForm = document.getElementById('auto-policy-form');
    const generateForm = document.getElementById('auto-generate-form');
    const ruleList = document.getElementById('auto-rule-list');
    const positionsContainer = document.getElementById('auto-rule-positions');
    const previewSection = document.getElementById('auto-preview');
    const planRows = document.getElementById('auto-plan-rows');
    const catalogRows = document.getElementById('auto-rule-catalog-rows');
    const catalogDetails = document.getElementById('auto-rule-details');
    const catalogPageSize = 6;
    let configuration = null;
    let preview = null;
    let catalogPage = 1;

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[character]);

    const setFeedback = (message, isError = false) => {
        feedback.textContent = message;
        feedback.dataset.state = isError ? 'error' : 'success';
    };

    const request = async (operation, body) => api('schedule-planner', {
        method: 'POST',
        body: { operation, ...body },
    });

    const renderOptions = (select, items, label, value = '') => {
        select.replaceChildren(new Option(label, ''));
        items.forEach((item) => {
            select.add(new Option(item.name, String(item.id)));
        });
        if (value && Array.from(select.options).some((option) => option.value === String(value))) {
            select.value = String(value);
        }
    };

    const renderPositionChoices = () => {
        positionsContainer.innerHTML = configuration.positions.length
            ? configuration.positions.map((position, index) => `
                <label><input type="checkbox" value="${escapeHtml(position)}" id="auto-position-${index}">
                    <span>${escapeHtml(position)}</span></label>`).join('')
            : '<span class="auto-schedule-muted">Chưa có chức vụ trong hồ sơ nhân viên.</span>';
    };

    const renderRules = () => {
        ruleList.innerHTML = configuration.rules.length ? configuration.rules.map((rule) => `
            <article class="auto-rule-item ${rule.active ? '' : 'inactive'}" data-rule-id="${Number(rule.id)}">
                <div><strong>${escapeHtml(rule.shift_name)} · ${escapeHtml(rule.department)}</strong>
                    <span>${Number(rule.min_staff)}–${Number(rule.max_staff)} nhân viên</span>
                    <small>${rule.positions.length ? rule.positions.map(escapeHtml).join(', ') : 'Mọi chức vụ'} · ${rule.active ? 'Đang áp dụng' : 'Đã ngừng áp dụng'}</small></div>
                <button class="button button-outline" type="button" data-edit-rule="${Number(rule.id)}">Sửa</button>
                <button class="button button-light" type="button" data-toggle-rule="${Number(rule.id)}" data-active="${rule.active ? '1' : '0'}">${rule.active ? 'Ngừng áp dụng' : 'Kích hoạt lại'}</button>
            </article>`).join('') :
            '<p class="auto-schedule-muted">Chưa có quy tắc nhân sự. Tạo ít nhất một quy tắc trước khi chạy.</p>';
        updateShiftOptions();
    };

    const filteredCatalog = () => {
        const search = document.getElementById('auto-rule-search').value.trim().toLocaleLowerCase('vi');
        const type = document.getElementById('auto-rule-type-filter').value;
        const status = document.getElementById('auto-rule-status-filter').value;
        const scope = document.getElementById('auto-rule-scope-filter').value;
        return configuration.ruleCatalog.filter((rule) =>
            (!search || `${rule.code} ${rule.name}`.toLocaleLowerCase('vi').includes(search))
            && (!type || rule.type === type)
            && (!status || rule.status === status)
            && (!scope || rule.scope.includes(scope)));
    };

    const renderCatalog = () => {
        const catalog = configuration.ruleCatalog;
        document.getElementById('auto-rule-total').textContent = String(catalog.length);
        document.getElementById('auto-rule-active').textContent =
            String(catalog.filter((rule) => rule.status === 'active').length);
        document.getElementById('auto-rule-mandatory').textContent =
            String(catalog.filter((rule) => rule.type === 'mandatory').length);
        document.getElementById('auto-rule-priority').textContent =
            String(catalog.filter((rule) => rule.type === 'priority').length);

        const filtered = filteredCatalog();
        const pages = Math.max(1, Math.ceil(filtered.length / catalogPageSize));
        catalogPage = Math.min(catalogPage, pages);
        const start = (catalogPage - 1) * catalogPageSize;
        const rows = filtered.slice(start, start + catalogPageSize);
        catalogRows.innerHTML = rows.length ? rows.map((rule) => {
            const typeLabel = rule.type === 'mandatory' ? 'Bắt buộc' : 'Ưu tiên';
            const actions = rule.can_manage ? `
                <div class="auto-catalog-controls">
                    <select data-rule-enabled="${escapeHtml(rule.code)}" aria-label="Trạng thái ${escapeHtml(rule.name)}">
                        <option value="1" ${rule.enabled ? 'selected' : ''}>Bật</option>
                        <option value="0" ${rule.enabled ? '' : 'selected'}>Tắt</option>
                    </select>
                    <label>Thứ tự<input data-rule-priority="${escapeHtml(rule.code)}" type="number" min="1" max="99" value="${Number(rule.priority_order || 1)}" aria-label="Thứ tự ưu tiên ${escapeHtml(rule.name)}"></label>
                    <button class="button button-outline" type="button" data-save-rule-setting="${escapeHtml(rule.code)}">Lưu</button>
                </div>` : rule.target ? `
                    <div class="auto-catalog-actions"><button class="button button-outline" type="button" data-rule-target="${escapeHtml(rule.target)}">Cấu hình</button>
                    <button class="button button-light" type="button" data-rule-detail="${escapeHtml(rule.code)}">Chi tiết</button></div>` : `
                    <button class="button button-light" type="button" data-rule-detail="${escapeHtml(rule.code)}">Chi tiết</button>`;
            const detailAction = rule.can_manage
                ? `<button class="button button-light" type="button" data-rule-detail="${escapeHtml(rule.code)}">Chi tiết</button>`
                : '';
            return `<tr>
                <td><strong>${escapeHtml(rule.code)}</strong></td>
                <td><strong>${escapeHtml(rule.name)}</strong><small>${escapeHtml(rule.description)}</small></td>
                <td><span class="auto-rule-type ${escapeHtml(rule.type)}">${typeLabel}</span></td>
                <td>${escapeHtml(rule.scope)}</td>
                <td>${escapeHtml(rule.configuration)}</td>
                <td><span class="auto-rule-status ${escapeHtml(rule.status)}">${escapeHtml(rule.status_label)}</span></td>
                <td>${actions}${detailAction}</td>
            </tr>`;
        }).join('') : '<tr class="schedule-empty-row"><td colspan="7">Không tìm thấy quy tắc phù hợp.</td></tr>';
        document.getElementById('auto-rule-page-label').textContent =
            filtered.length ? `Hiển thị ${start + 1}–${Math.min(start + catalogPageSize, filtered.length)} / ${filtered.length} quy tắc` : 'Không có quy tắc';
        document.getElementById('auto-rule-prev').disabled = catalogPage <= 1;
        document.getElementById('auto-rule-next').disabled = catalogPage >= pages;
    };

    const syncRuleForm = () => {
        if (!configuration) return;
        const shiftId = document.getElementById('auto-rule-shift').value;
        const departmentId = document.getElementById('auto-rule-department').value;
        const rule = configuration.rules.find((item) => String(item.shift_id) === shiftId
            && String(item.department_id) === departmentId);
        document.getElementById('auto-rule-min').value = rule ? String(rule.min_staff) : '1';
        document.getElementById('auto-rule-max').value = rule ? String(rule.max_staff) : '5';
        positionsContainer.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.checked = Boolean(rule && rule.positions.includes(input.value));
        });
    };

    const updateShiftOptions = () => {
        const selectedDepartment = document.getElementById('auto-department').value;
        const rules = configuration.rules.filter((rule) => rule.active
            && (!selectedDepartment || String(rule.department_id) === selectedDepartment));
        const availableShiftIds = new Set(rules.map((rule) => String(rule.shift_id)));
        const selected = Array.from(document.getElementById('auto-shifts').selectedOptions)
            .map((option) => option.value)
            .filter((id) => availableShiftIds.has(id));
        const shifts = configuration.shiftTypes.filter((shift) => shift.active && availableShiftIds.has(String(shift.id)));
        const select = document.getElementById('auto-shifts');
        select.replaceChildren(...shifts.map((shift) => {
            const option = new Option(`${shift.name} (${shift.start.slice(0, 5)}–${shift.end.slice(0, 5)})`, String(shift.id));
            option.selected = selected.length ? selected.includes(option.value) : true;
            return option;
        }));
        if (shifts.length === 0) {
            select.add(new Option('Chưa có ca được cấu hình quy tắc', ''));
        }
    };

    const displayPolicy = () => {
        const {
            max_daily_minutes: daily,
            max_weekly_minutes: weekly,
            min_rest_minutes: rest,
            max_consecutive_nights: maxConsecutiveNights,
        } = configuration.policy;
        document.getElementById('auto-max-daily').value = daily === null ? '' : String(daily / 60);
        document.getElementById('auto-max-weekly').value = weekly === null ? '' : String(weekly / 60);
        document.getElementById('auto-min-rest').value = String(rest / 60);
        document.getElementById('auto-max-consecutive-nights').value =
            maxConsecutiveNights === null ? '' : String(maxConsecutiveNights);
        const nightRule = configuration.ruleCatalog.find((rule) => rule.code === 'QT012');
        const description = [
            daily === null ? 'không đặt giới hạn giờ/ngày' : `tối đa ${daily / 60} giờ/ngày`,
            weekly === null ? 'không đặt giới hạn giờ/tuần' : `tối đa ${weekly / 60} giờ/tuần`,
            rest === 0 ? 'không yêu cầu nghỉ tối thiểu giữa ca' : `nghỉ ít nhất ${rest / 60} giờ giữa ca`,
            maxConsecutiveNights === null
                ? 'không giới hạn ca đêm liên tiếp'
                : nightRule?.enabled
                    ? `tối đa ${maxConsecutiveNights} ca đêm liên tiếp`
                    : `đã lưu mức ${maxConsecutiveNights} ca đêm nhưng QT012 đang tắt`,
            'chỉ xét ngày nghỉ đã duyệt',
            'không có dữ liệu nguyện vọng ca',
        ];
        document.getElementById('auto-policy-note').textContent =
            `Đang áp dụng: ${description.join('; ')}. Thứ tự ưu tiên được cấu hình trong danh mục quy tắc.`;
    };

    const initialize = async () => {
        try {
            configuration = await api('schedule-planner');
            const activeShifts = configuration.shiftTypes.filter((shift) => Number(shift.active) === 1);
            renderOptions(document.getElementById('auto-rule-shift'), activeShifts, 'Chọn ca');
            renderOptions(document.getElementById('auto-rule-department'), configuration.departments, 'Chọn phòng ban');
            renderOptions(document.getElementById('auto-department'), configuration.departments, 'Tất cả phòng ban có quy tắc');
            renderPositionChoices();
            renderRules();
            renderCatalog();
            displayPolicy();
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Không tải được cấu hình tự động phân ca.', true);
        }
    };

    ruleForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!configuration) return;
        const button = ruleForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const positions = Array.from(positionsContainer.querySelectorAll('input:checked'))
                .map((input) => input.value);
            configuration = await request('save-rule', {
                shiftId: Number(document.getElementById('auto-rule-shift').value),
                departmentId: Number(document.getElementById('auto-rule-department').value),
                minStaff: Number(document.getElementById('auto-rule-min').value),
                maxStaff: Number(document.getElementById('auto-rule-max').value),
                positions,
            });
            renderRules();
            renderCatalog();
            syncRuleForm();
            setFeedback('Đã lưu quy tắc nhân sự theo ca.');
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Không thể lưu quy tắc.', true);
        } finally {
            button.disabled = false;
        }
    });

    ruleList.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit-rule]');
        if (editButton) {
            const rule = configuration.rules.find((item) => item.id === Number(editButton.dataset.editRule));
            if (rule) {
                document.getElementById('auto-rule-shift').value = String(rule.shift_id);
                document.getElementById('auto-rule-department').value = String(rule.department_id);
                syncRuleForm();
            }
            return;
        }
        const button = event.target.closest('[data-toggle-rule]');
        if (!button) return;
        const active = button.dataset.active === '1';
        if (active && !window.confirm('Ngừng áp dụng quy tắc này? Lịch đã xác nhận sẽ được giữ nguyên.')) return;
        button.disabled = true;
        try {
            configuration = await request('set-rule-status', {
                id: Number(button.dataset.toggleRule),
                active: !active,
            });
            renderRules();
            renderCatalog();
            setFeedback(active ? 'Đã ngừng áp dụng quy tắc.' : 'Đã kích hoạt lại quy tắc.');
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Không thể thay đổi trạng thái quy tắc.', true);
        } finally {
            button.disabled = false;
        }
    });

    catalogRows.addEventListener('click', async (event) => {
        const saveButton = event.target.closest('[data-save-rule-setting]');
        if (saveButton) {
            const code = saveButton.dataset.saveRuleSetting;
            const enabled = catalogRows.querySelector(`[data-rule-enabled="${code}"]`).value === '1';
            const priorityOrder = Number(catalogRows.querySelector(`[data-rule-priority="${code}"]`).value);
            saveButton.disabled = true;
            try {
                configuration = await request('save-rule-settings', { code, enabled, priorityOrder });
                renderCatalog();
                displayPolicy();
                setFeedback(`Đã cập nhật ${code}.`);
            } catch (error) {
                setFeedback(error instanceof Error ? error.message : 'Không thể cập nhật quy tắc.', true);
            } finally {
                saveButton.disabled = false;
            }
            return;
        }

        const targetButton = event.target.closest('[data-rule-target]');
        if (targetButton) {
            const target = document.getElementById(targetButton.dataset.ruleTarget);
            if (!target) return;
            const policyFields = Array.from(document.querySelectorAll('[data-policy-rule]'));
            if (policyFields.includes(target)) {
                policyFields.forEach((fieldset) => {
                    fieldset.hidden = fieldset !== target;
                });
                document.getElementById('auto-save-policy').disabled = false;
            }
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
            target.querySelector('input, select')?.focus();
            return;
        }

        const detailButton = event.target.closest('[data-rule-detail]');
        if (detailButton) {
            const rule = configuration.ruleCatalog.find((item) => item.code === detailButton.dataset.ruleDetail);
            if (!rule) return;
            catalogDetails.innerHTML = `<strong>${escapeHtml(rule.code)} · ${escapeHtml(rule.name)}</strong>
                <p>${escapeHtml(rule.description)}</p><small>Phạm vi: ${escapeHtml(rule.scope)} · ${escapeHtml(rule.configuration)}</small>`;
            catalogDetails.hidden = false;
        }
    });

    document.getElementById('auto-add-rule').addEventListener('click', () => {
        document.getElementById('auto-rule-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('auto-rule-shift').focus();
    });
    document.getElementById('auto-rule-search').addEventListener('input', () => {
        catalogPage = 1;
        renderCatalog();
    });
    ['auto-rule-type-filter', 'auto-rule-status-filter', 'auto-rule-scope-filter'].forEach((id) => {
        document.getElementById(id).addEventListener('change', () => {
            catalogPage = 1;
            renderCatalog();
        });
    });
    document.getElementById('auto-rule-prev').addEventListener('click', () => {
        catalogPage = Math.max(1, catalogPage - 1);
        renderCatalog();
    });
    document.getElementById('auto-rule-next').addEventListener('click', () => {
        catalogPage += 1;
        renderCatalog();
    });

    policyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!configuration) return;
        const button = policyForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            configuration = await request('save-policy', {
                maxDailyHours: document.getElementById('auto-max-daily').value || null,
                maxWeeklyHours: document.getElementById('auto-max-weekly').value || null,
                minRestHours: document.getElementById('auto-min-rest').value || '0',
                maxConsecutiveNights: document.getElementById('auto-max-consecutive-nights').value || null,
            });
            renderCatalog();
            displayPolicy();
            setFeedback('Đã lưu giới hạn giờ làm và nghỉ giữa ca.');
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Không thể lưu giới hạn.', true);
        } finally {
            button.disabled = false;
        }
    });

    const collectCriteria = () => ({
        fromDate: document.getElementById('auto-from-date').value,
        toDate: document.getElementById('auto-to-date').value,
        departmentId: document.getElementById('auto-department').value || null,
        shiftIds: Array.from(document.getElementById('auto-shifts').selectedOptions)
            .map((option) => Number(option.value))
            .filter((id) => id > 0),
        location: document.getElementById('auto-location').value.trim(),
    });

    const reasonLabel = (reason) => ({
        department: 'QT002 · không thuộc phòng ban áp dụng',
        inactive: 'QT007 · nhân viên đã ngừng hoạt động',
        terminated: 'QT007 · đã đến ngày nghỉ việc',
        position: 'QT001 · không đúng chức vụ',
        previously_cancelled: 'QT003 · ca này đã từng bị hủy',
        approved_leave: 'QT004 · nghỉ phép đã được duyệt',
        schedule_conflict: 'QT003 · trùng lịch',
        insufficient_rest: 'QT006 · không đủ thời gian nghỉ giữa ca',
        daily_limit: 'QT005 · vượt giới hạn giờ/ngày',
        weekly_limit: 'QT005 · vượt giới hạn giờ/tuần',
        night_streak_limit: 'QT012 · vượt số ca đêm liên tiếp tối đa',
    })[reason] || reason;

    const renderShortages = () => {
        const container = document.getElementById('auto-shortages');
        const shortages = preview.coverage.map((item) => {
            const planned = preview.assignments.filter((assignment) =>
                assignment.date === item.date
                && assignment.shift_id === item.shift_id
                && assignment.department_id === item.department_id).length;
            return {
                ...item,
                planned,
                missing: Math.max(0, item.minimum - item.existing - planned),
            };
        }).filter((item) => item.missing > 0);
        const originalShortages = new Map(preview.shortages.map((item) => [
            `${item.date}:${item.shift_id}:${item.department_id}`,
            item,
        ]));
        preview.summary.shortage_slots = shortages.reduce((sum, item) => sum + item.missing, 0);
        container.innerHTML = shortages.length ? shortages.map((item) => {
            const original = originalShortages.get(`${item.date}:${item.shift_id}:${item.department_id}`);
            const blockedEmployees = (original?.employees || []).map((employee) =>
                `${employee.name}${employee.position ? ` (${employee.position})` : ''}: ${employee.reasons.map(reasonLabel).join(', ')}`);
            const reasonSummary = Object.entries(original?.reasons || {}).map(([reason, count]) =>
                `${reasonLabel(reason)} (${Number(count)} nhân viên)`);
            const explanations = [
                blockedEmployees.length ? `Nhân viên không phù hợp: ${blockedEmployees.join('; ')}` : '',
                reasonSummary.length ? `Nguyên nhân loại: ${reasonSummary.join('; ')}` : '',
            ].filter(Boolean);
            return `<article class="auto-result-item warning">
                <strong>${escapeHtml(item.date)} · ${escapeHtml(item.shift_name)} · ${escapeHtml(item.department)}</strong>
                <span>Còn thiếu ${Number(item.missing)} người để đạt tối thiểu ${Number(item.minimum)}.</span>
                <small>${explanations.length ? escapeHtml(explanations.join(' · ')) : original ? 'Không tìm thấy nhân viên phù hợp.' : 'Đã loại ca khỏi phương án; cần quản trị viên xem xét.'}</small>
            </article>`;
        }).join('') : '<p class="auto-result-empty">Đã đáp ứng đủ số lượng tối thiểu của các quy tắc đã chọn.</p>';
    };

    const renderIssues = () => {
        const container = document.getElementById('auto-issues');
        const issueItems = preview.issues.map((item) =>
            `<article class="auto-result-item error"><strong>${escapeHtml(item.date)} · ${escapeHtml(item.message)}</strong></article>`);
        container.innerHTML = issueItems.length ? issueItems.join('') :
            '<p class="auto-result-empty">Không có cảnh báo cấu hình hiện hữu vượt mức tối đa.</p>';
    };

    const renderPreview = () => {
        const usedIds = (assignment) => new Set(preview.assignments
            .filter((item) => item !== assignment && item.date === assignment.date && item.shift_id === assignment.shift_id)
            .map((item) => Number(item.employee_id)));
        planRows.innerHTML = preview.assignments.length ? preview.assignments.map((assignment) => {
            const used = usedIds(assignment);
            const candidateOptions = assignment.candidates
                .filter((candidate) => !used.has(Number(candidate.id)) || Number(candidate.id) === Number(assignment.employee_id))
                .map((candidate) => `<option value="${Number(candidate.id)}" ${Number(candidate.id) === Number(assignment.employee_id) ? 'selected' : ''}>
                    ${escapeHtml(candidate.name)}${candidate.position ? ` · ${escapeHtml(candidate.position)}` : ''}
                </option>`).join('');
            return `<tr data-slot-id="${escapeHtml(assignment.slot_id)}">
                <td>${escapeHtml(assignment.date)}</td><td>${escapeHtml(assignment.department)}</td>
                <td><strong>${escapeHtml(assignment.shift_name)}</strong><small>${escapeHtml(assignment.start)}–${escapeHtml(assignment.end)}</small></td>
                <td><select data-employee-choice aria-label="Thay nhân viên cho ca ngày ${escapeHtml(assignment.date)}">${candidateOptions}</select></td>
                <td><span class="auto-plan-status">Đã phân ca</span></td>
                <td><button class="auto-remove-plan" type="button" data-remove-slot="${escapeHtml(assignment.slot_id)}" aria-label="Loại bỏ lịch dự kiến"><i class="bi bi-x-lg"></i></button></td>
            </tr>`;
        }).join('') : '<tr class="schedule-empty-row"><td colspan="6">Không tìm được ca phù hợp để phân.</td></tr>';

        renderShortages();
        document.getElementById('auto-preview-summary').textContent =
            `${preview.summary.planned} ca dự kiến · ${preview.summary.shortage_slots} vị trí còn thiếu · ${preview.summary.warnings} cảnh báo. Lịch chưa được lưu.`;
        document.getElementById('auto-confirm').disabled = preview.assignments.length === 0;
        renderIssues();
        previewSection.hidden = false;
    };

    const runPreview = async () => {
        const button = document.getElementById('auto-preview-button');
        button.disabled = true;
        setFeedback('');
        try {
            preview = await request('preview', collectCriteria());
            renderPreview();
            setFeedback('Đã tạo bản xem trước. Hãy kiểm tra và xác nhận để lưu lịch.');
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Không thể tạo lịch dự kiến.', true);
        } finally {
            button.disabled = false;
        }
    };

    generateForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        await runPreview();
    });
    document.getElementById('auto-rerun').addEventListener('click', runPreview);
    document.getElementById('auto-department').addEventListener('change', updateShiftOptions);
    document.getElementById('auto-rule-shift').addEventListener('change', syncRuleForm);
    document.getElementById('auto-rule-department').addEventListener('change', syncRuleForm);
    planRows.addEventListener('change', (event) => {
        const select = event.target.closest('[data-employee-choice]');
        if (!select) return;
        const row = select.closest('[data-slot-id]');
        const assignment = preview.assignments.find((item) => item.slot_id === row.dataset.slotId);
        const candidate = assignment.candidates.find((item) => Number(item.id) === Number(select.value));
        if (!candidate) return;
        assignment.employee_id = Number(candidate.id);
        assignment.employee_name = candidate.name;
        assignment.position = candidate.position;
        renderPreview();
    });
    planRows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-slot]');
        if (!button) return;
        preview.assignments = preview.assignments.filter((item) => item.slot_id !== button.dataset.removeSlot);
        preview.summary.planned = preview.assignments.length;
        renderPreview();
    });
    document.getElementById('auto-confirm').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        if (!preview || preview.assignments.length === 0) return;
        button.disabled = true;
        try {
            const result = await request('confirm', {
                fromDate: preview.criteria.from_date,
                toDate: preview.criteria.to_date,
                departmentId: preview.criteria.department_id,
                shiftIds: preview.criteria.shift_ids,
                location: preview.criteria.location,
                assignments: preview.assignments.map((assignment) => ({
                    date: assignment.date,
                    shiftId: assignment.shift_id,
                    departmentId: assignment.department_id,
                    employeeId: assignment.employee_id,
                    location: assignment.location,
                })),
            });
            previewSection.hidden = true;
            preview = null;
            setFeedback(`Đã xác nhận và lưu ${result.saved} ca. Lịch hiện có được giữ nguyên.`);
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Không thể xác nhận lịch.', true);
        } finally {
            button.disabled = false;
        }
    });

    initialize();
})();
