// Read-only Blade contracts and real inline JS with DOM doubles, not browser E2E.
// Run alongside the existing frontend checks with: node --test
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const view = name => fs.readFileSync(path.join(__dirname, '../../resources/views', name + '.blade.php'), 'utf8');
const index = view('admin/users/index');
const show = view('admin/users/show');
const modal = view('admin/users/partials/manage-user-modal');
const planner = view('daily-planner/index');
const statuses = ['active', 'trial', 'inactive', 'expired', 'suspended', 'cancelled'];

for (const [name, source, user] of [['details', show, '$user'], ['dialog', modal, '$managedUser']]) {
    test(`admin ${name}: subscription submits PATCH to update action with identity and canonical statuses`, () => {
        const form = source.match(/<form\b[^>]*action="\{\{ route\('admin\.users\.subscription\.update'[\s\S]*?<\/form>/)[0];
        assert.match(form, /method="POST"/);
        assert.ok(form.includes(`route('admin.users.subscription.update', ${user})`));
        assert.match(form, /@csrf\s+@method\('PATCH'\)/);
        assert.ok(form.includes(`name="_managed_user_id" value="{{ ${user}->id }}"`));
        const options = form.match(/@foreach\s*\((\[[^\]]+\])/)[1];
        // The details form uses value => display-label pairs; the dialog uses
        // a value list. Compare submitted values, not the title-case labels.
        assert.deepEqual([...options.matchAll(/'([a-z_]+)'/g)].map(m => m[1]).sort(), [...statuses].sort());
    });
}

test('admin details plan selector offers supplied plans, preserves missing current assignment and respects old input', () => {
    const select = show.match(/<select\b[^>]*id="subscription_plan_id"[^>]*>[\s\S]*?<\/select>/)?.[0];
    assert.ok(select, 'Plan selector must be present');
    assert.match(select, /<option value="">No plan \/ unassigned<\/option>/);
    assert.match(select, /@foreach\s*\(\$plans as \$plan\)[\s\S]*?<option value="\{\{ \$plan->id \}\}" @selected\(\(string\) old\('subscription_plan_id', \$user->subscription_plan_id\) === \(string\) \$plan->id\)/);
    // Append only an assigned plan missing from supplied choices, preventing
    // duplicate choices while keeping disabled/otherwise omitted assignments.
    assert.match(select, /@if\s*\(\$user->subscriptionPlan && ! \$plans->contains\('id', \$user->subscription_plan_id\)\)/);
    assert.match(select, /<option value="\{\{ \$user->subscription_plan_id \}\}" @selected\(\(string\) old\('subscription_plan_id', \$user->subscription_plan_id\) === \(string\) \$user->subscription_plan_id\)/);
    assert.match(select, /\$user->subscriptionPlan->name\s*\?\?\s*\$user->subscriptionPlan->title[\s\S]*?\(current plan\)/);
    assert.match(show, /Choose an enabled plan, keep the current assigned plan, or select No plan to remove the assignment\./);
});

test('admin responsive lists share one per-user dialog outside the table and cards', () => {
    assert.equal([...index.matchAll(/@include\('admin\.users\.partials\.manage-user-modal'/g)].length, 1);
    const include = index.indexOf("@include('admin.users.partials.manage-user-modal'");
    assert.ok(include > index.lastIndexOf('</table>'));
    assert.ok(include > index.lastIndexOf('</article>'));
    assert.match(index.slice(index.lastIndexOf('</section>', include), include), /@foreach \(\$users as \$user\)/);
    assert.equal([...modal.matchAll(/<dialog\b/g)].length, 1);
    assert.match(modal, /id="manage-user-modal-\{\{ \$managedUser->id \}\}"/);
});

test('admin validation/success feedback and failed-value scoping remain in the markup', () => {
    assert.match(index, /@if\(session\('success'\)\)[\s\S]*?<x-alert type="success"/);
    assert.match(index, /@if\(\$errors->any\(\)\)[\s\S]*?The requested user update could not be saved/);
    assert.match(show, /\$subscriptionHasErrors = \$errors->hasAny/);
    assert.match(show, /id="pm-user-panel-subscription"[^\n]*@unless\(\$subscriptionHasErrors\) hidden/);
    for (const field of ['subscription_status', 'subscription_plan_id', 'subscription_started_at', 'subscription_expires_at', 'trial_ends_at']) assert.ok(modal.includes(`old('${field}',`));
    assert.match(modal, /old\('_managed_user_id'\) === \(string\) \$managedUser->id/);
});

function admin(failedUser = null) {
    const listeners = {};
    const node = extra => Object.assign({ classList: { toggle() {}, contains: name => name === 'admin-user-selector' }, checked: false, hidden: false }, extra);
    const desktop = ['7', '8'].map(value => node({ value }));
    const mobile = ['7', '8'].map(value => node({ value }));
    const panels = ['subscription', 'role', 'access', 'more'].map(value => node({ getAttribute: () => value }));
    const sections = ['role', 'subscription', 'suspend', 'reactivate', 'delete'].map(value => node({ id: 'admin-bulk-' + value + '-fields' }));
    const ids = Object.fromEntries(['admin-bulk-open', 'admin-bulk-count', 'admin-bulk-dialog-count', 'admin-users-select-all', 'admin-bulk-user-modal', 'manage-user-modal-7'].map(id => [id, node({ open: false, showModal() { this.open = true; } })]));
    const hidden = { inputs: [], set innerHTML(value) { this.inputs = []; }, appendChild(input) { this.inputs.push(input); } };
    ids['admin-bulk-hidden-ids'] = hidden;
    sections.forEach(section => { ids[section.id] = section; });
    const document = {
        getElementById: id => ids[id], createElement: () => ({}),
        addEventListener: (name, callback) => { listeners[name] = callback; },
        querySelectorAll(selector) {
            if (selector.startsWith('[data-admin-user-tab]')) return [];
            if (selector.startsWith('[data-admin-user-panel]')) return panels;
            if (selector === '.admin-bulk-action-fields') return sections;
            let boxes = selector.includes('.admin-user-desktop') ? desktop : selector.includes('.admin-user-mobile-list') ? mobile : [...desktop, ...mobile];
            const value = selector.match(/\[value="([^"]+)"\]/)?.[1];
            if (value) boxes = boxes.filter(box => box.value === value);
            return selector.includes(':checked') ? boxes.filter(box => box.checked) : boxes;
        },
    };
    let script = index.match(/<script>([\s\S]*?)<\/script>/)[1];
    script = script.replace(/@if \(\$errors->any\(\)\)([\s\S]*?)@endif/g, (_, body) => failedUser ? body.replace(/\{\{[^\n]+\}\}/, JSON.stringify(failedUser)) : '');
    const alerts = [];
    const context = { document, alert: message => alerts.push(message) };
    vm.runInNewContext(script, context);
    return { context, listeners, desktop, mobile, panels, sections, ids, hidden, alerts };
}

test('admin select-all counts unique users and sends each ID once; clear disables bulk manage', () => {
    const { context, ids, hidden } = admin();
    context.pmToggleAllUsers(true);
    assert.equal(ids['admin-bulk-count'].textContent, '2');
    assert.equal(ids['admin-bulk-open'].disabled, false);
    context.pmOpenBulkUserDialog();
    assert.deepEqual(hidden.inputs.map(input => [input.name, input.value]), [['ids[]', '7'], ['ids[]', '8']]);
    assert.equal(ids['admin-bulk-dialog-count'].textContent, '2');
    assert.equal(ids['admin-bulk-user-modal'].open, true);
    context.pmToggleAllUsers(false);
    assert.equal(ids['admin-bulk-count'].textContent, '0');
    assert.equal(ids['admin-bulk-open'].disabled, true);
});

for (const origin of ['desktop', 'mobile']) {
    test(`admin ${origin} deselection clears the responsive copy and updates select-all`, () => {
        const fixture = admin();
        fixture.context.pmToggleAllUsers(true);
        const target = fixture[origin][0];
        target.checked = false;
        fixture.listeners.change({ target });
        assert.equal(fixture.desktop[0].checked, false);
        assert.equal(fixture.mobile[0].checked, false);
        assert.equal(fixture.ids['admin-bulk-count'].textContent, '1');
        assert.equal(fixture.ids['admin-users-select-all'].checked, false);
        assert.equal(fixture.ids['admin-users-select-all'].indeterminate, true);
    });
}

test('admin bulk fields show only chosen action and invalid submission can recover', () => {
    const { context, sections, alerts } = admin();
    context.pmShowBulkActionFields('subscription');
    assert.deepEqual(sections.filter(section => !section.hidden).map(section => section.id), ['admin-bulk-subscription-fields']);
    let action = '';
    const form = { elements: { namedItem: () => ({ value: action }) }, confirmation: { value: '' } };
    assert.equal(context.pmValidateBulkUserForm(form), false);
    context.pmToggleAllUsers(true);
    assert.equal(context.pmValidateBulkUserForm(form), false);
    action = 'delete';
    assert.equal(context.pmValidateBulkUserForm(form), false);
    form.confirmation.value = 'DELETE';
    assert.equal(context.pmValidateBulkUserForm(form), true);
    assert.equal(alerts.length, 3);
});

test('admin failed subscription reopens matching dialog and subscription panel', () => {
    const { listeners, ids, panels } = admin('7');
    listeners.DOMContentLoaded();
    assert.equal(ids['manage-user-modal-7'].open, true);
    assert.deepEqual(panels.map(panel => panel.hidden), [false, true, true, true]);
});

const encodedButton = task => ({ dataset: { taskEncoded: Buffer.from(JSON.stringify(task), 'utf8').toString('base64') } });
function editor() {
    const fields = new Map([...planner.matchAll(/id="(edit[^"]+)"/g)].map(match => [match[1], { value: '', checked: false, classList: { toggle() {} } }]));
    const form = fields.get('editTaskForm');
    form.dataset = { errorFields: '[]', oldInput: '{}' };
    const days = ['mon', 'tue'].map(value => ({ value, checked: false }));
    const channels = ['in_app', 'push'].map(value => ({ value, checked: false }));
    const scopes = ['series', 'occurrence'].map(value => ({ value, checked: value === 'series' }));
    const errorControl = { name: 'repeat_interval', closest: () => ({ dataset: { formPanel: 'repeat' } }) };
    const query = selector => ({ '.edit-repeat-day': days, '.edit-reminder-channel': channels, '[name="edit_scope"]': scopes, '[name]': [errorControl] })[selector] || [];
    form.querySelectorAll = query;
    form.reset = () => { scopes.forEach(radio => { radio.checked = radio.value === 'series'; }); };
    const clocks = {};
    const opened = [];
    const errors = [];
    const tabs = [];
    const buttons = [];
    const context = {
        window: {}, atob, TextDecoder, Uint8Array,
        console: { error: (...args) => errors.push(args), warn() {} },
        document: { getElementById: id => fields.get(id), querySelectorAll: selector => selector === '[data-task-encoded]' ? buttons : query(selector) },
        toggleRepeatFields() {}, toggleTaskReminderFields() {}, toggleCustomTaskReminder() {},
        selectDpFormTab: (root, tab) => tabs.push(tab),
        openDpModal: id => opened.push(id),
    };
    context.window.pmSync12HourTimeControls = root => {
        assert.equal(root, form);
        for (const id of ['editTaskStart', 'editTaskEnd', 'editReminderCustom']) clocks[id] = fields.get(id).value;
    };
    const start = planner.indexOf('    function decodeDpTaskButton(');
    const end = planner.indexOf('    window.openMoveTaskModal =', start);
    assert.ok(start >= 0 && end > start, 'current payload helpers and edit function must be present');
    const script = planner.slice(start, end);
    assert.match(script, /window\.openEditTask = function\(task, oldInput = null\)/);
    vm.runInNewContext(script, context);
    assert.equal(typeof context.window.openEditTask, 'function');
    return { context, fields, form, days, channels, scopes, clocks, opened, errors, tabs, buttons, open: context.window.openEditTask };
}
const task = { id: 42, action: '/daily-planner/items/42', title: 'Saved task', plan_date: '2026-10-06' };

test('planner payload preserves Unicode, quotes and markup as plain field text', () => {
    const fixture = editor();
    const unicode = { ...task, title: '日本語 café 📝 "quotes"', description: '</script><img onerror="alert(1)"> & مرحبا' };
    fixture.context.window.openEditTaskFromButton(encodedButton(unicode));
    assert.equal(fixture.fields.get('editTaskName').value, unicode.title);
    assert.equal(fixture.fields.get('editTaskDescription').value, unicode.description);
    assert.deepEqual(fixture.opened, ['editTaskModal']);
    assert.match(planner, /data-task-encoded="\{\{ \$editTaskPayloadEncoded \}\}"/);
});

test('planner corrupt payload and missing identity/action do not open the dialog', () => {
    const fixture = editor();
    for (const button of [null, { dataset: {} }, { dataset: { taskEncoded: '!!!' } }, { dataset: { taskEncoded: btoa('not JSON') } }]) fixture.context.window.openEditTaskFromButton(button);
    fixture.open({ title: 'No identity' });
    fixture.open({ id: 42, action: '' });
    assert.equal(fixture.opened.length, 0);
    assert.equal(fixture.errors.length, 5);
});

test('planner checkbox helper preserves normalized true and false reminder values', () => {
    const fixture = editor();
    for (const [value, expected] of [[true, true], [1, true], ['1', true], ['true', true], [false, false], [0, false], ['0', false], ['false', false], [null, false]]) {
        fixture.open({ ...task, reminder_enabled: value });
        assert.equal(fixture.fields.get('editReminderEnabled').checked, expected, `reminder value ${JSON.stringify(value)}`);
    }
    assert.equal(fixture.opened.length, 9);
    assert.equal(fixture.errors.length, 0);
});

test('planner invalid identity/action leaves an already populated editor unchanged', () => {
    const fixture = editor();
    fixture.open(task);
    let resets = 0;
    fixture.form.reset = () => { resets++; };
    for (const invalid of [null, {}, { action: task.action }, { id: 42 }, { id: 42, action: 42 }, { id: 42, action: '   ' }]) fixture.open(invalid);
    assert.equal(resets, 0);
    assert.equal(fixture.opened.length, 1);
    assert.equal(fixture.form.action, task.action);
    assert.equal(fixture.fields.get('editTaskName').value, task.title);
    assert.equal(fixture.fields.get('editTaskId').value, task.id);
    assert.equal(fixture.errors.length, 6);
});

test('planner switching tasks synchronizes saved times then clears stale values', () => {
    const { open, fields, clocks, form, scopes } = editor();
    open({ ...task, start_time: '14:30', end_time: '15:00', reminder_custom_at: '2026-10-06T14:15' });
    assert.equal(form.action, task.action);
    assert.equal(fields.get('editTaskId').value, 42);
    assert.equal(clocks.editTaskStart, '14:30');
    assert.equal(clocks.editTaskEnd, '15:00');
    assert.equal(clocks.editReminderCustom, '2026-10-06T14:15');
    scopes[0].checked = false;
    scopes[1].checked = true;
    open({ ...task, id: 43, action: '/daily-planner/items/43', title: 'Next task', repeat_interval: 3 });
    assert.equal(fields.get('editTaskName').value, 'Next task');
    assert.equal(fields.get('editTaskId').value, 43);
    assert.equal(clocks.editTaskStart, '');
    assert.equal(clocks.editReminderCustom, '');
    assert.equal(fields.get('editRepeatInterval').value, 3);
    assert.deepEqual(scopes.map(radio => radio.checked), [true, false]);
});

test('planner failed edit preserves cleared values, false checks, identity and error tab', () => {
    const fixture = editor();
    fixture.form.dataset.errorFields = '["repeat_interval"]';
    fixture.open({ ...task, description: 'Saved', personal_goal_id: 8, start_time: '14:30', end_time: '15:00', reminder_enabled: true, repeat_days: ['mon'], reminder_channels: ['push'] }, {
        title: 'Rejected edit', description: null, personal_goal_id: '', start_time: null, end_time: '', reminder_enabled: '0', edit_scope: 'occurrence', action: '/untrusted', _editing_task_id: 99,
    });
    assert.equal(fixture.form.action, task.action);
    assert.equal(fixture.fields.get('editTaskId').value, 42);
    assert.equal(fixture.fields.get('editTaskName').value, 'Rejected edit');
    for (const id of ['editTaskDescription', 'editTaskGoal', 'editTaskStart', 'editTaskEnd']) assert.equal(fixture.fields.get(id).value, '');
    assert.equal(fixture.fields.get('editReminderEnabled').checked, false);
    assert.ok([...fixture.days, ...fixture.channels].every(box => !box.checked));
    assert.deepEqual(fixture.scopes.map(radio => radio.checked), [false, true]);
    assert.deepEqual(fixture.tabs, ['repeat']);
    assert.equal(fixture.clocks.editTaskStart, '');
});

test('planner restored custom reminder synchronizes times and absent checkbox remains off', () => {
    const { open, clocks, fields } = editor();
    open({ ...task, reminder_enabled: true }, { start_time: '09:45', reminder_custom_at: '2026-10-06T09:15', reminder_offset_minutes: null });
    assert.equal(clocks.editTaskStart, '09:45');
    assert.equal(clocks.editReminderCustom, '2026-10-06T09:15');
    assert.equal(fields.get('editReminderOffset').value, 'custom');
    assert.equal(fields.get('editReminderEnabled').checked, false);
});

test('planner validation recovery uses only matching rendered task; missing task invents no action', () => {
    const fixture = editor();
    fixture.fields.set('dpError', {});
    const oldInput = { _planner_form: 'edit-task', _editing_task_id: '42', title: 'Rejected title' };
    fixture.form.dataset.oldInput = JSON.stringify(oldInput);
    fixture.buttons.push(encodedButton({ ...task, id: 41 }), encodedButton(task));
    fixture.context.recoverDpTaskEdit();
    assert.equal(fixture.fields.get('editTaskName').value, 'Rejected title');
    assert.equal(fixture.form.action, task.action);
    assert.equal(fixture.opened.length, 1);
    fixture.form.dataset.oldInput = JSON.stringify({ ...oldInput, _editing_task_id: 99 });
    fixture.context.recoverDpTaskEdit();
    fixture.form.dataset.oldInput = JSON.stringify({ ...oldInput, _planner_form: 'add-task' });
    fixture.context.recoverDpTaskEdit();
    assert.equal(fixture.opened.length, 1);
});
