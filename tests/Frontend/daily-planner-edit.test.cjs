const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const template = fs.readFileSync(path.join(__dirname, '../../resources/views/daily-planner/index.blade.php'), 'utf8');
const editScript = template.slice(
    template.indexOf('    window.openEditTask = function(task) {'),
    template.indexOf('    window.openMoveTaskModal = function('),
);

function editor() {
    const fields = new Map();
    for (const match of template.matchAll(/id="(edit[^"]+)"/g)) {
        fields.set(match[1], { value: '', checked: false, classList: { toggle() {} } });
    }
    const form = fields.get('editTaskForm');
    const scope = { value: 'series' };
    const clocks = {};
    form.reset = () => { scope.value = 'series'; };

    const context = {
        window: {},
        document: {
            getElementById: id => fields.get(id),
            querySelectorAll: () => [],
        },
        console,
        toggleRepeatFields() {},
        toggleTaskReminderFields() {},
        toggleCustomTaskReminder() {},
        openDpModal() {
            assert.equal(clocks.editTaskStart, fields.get('editTaskStart').value);
            assert.equal(clocks.editReminderCustom, fields.get('editReminderCustom').value);
        },
    };
    context.window.pmSync12HourTimeControls = root => {
        assert.equal(root, form);
        for (const id of ['editTaskStart', 'editTaskEnd', 'editReminderCustom']) {
            clocks[id] = fields.get(id).value;
        }
    };
    vm.runInNewContext(editScript, context);

    return { fields, form, scope, clocks, open: context.window.openEditTask };
}

test('editing synchronizes saved times and custom reminders before opening', () => {
    const { fields, form, clocks, open } = editor();
    open({
        action: '/daily-planner/items/42',
        title: 'Call the bank',
        plan_date: '2026-10-03',
        start_time: '14:30',
        end_time: '15:00',
        reminder_enabled: true,
        reminder_custom_at: '2026-10-03T14:15',
    });

    // The global time widget copies these visible values back on submit.
    for (const [id, value] of Object.entries(clocks)) fields.get(id).value = value;
    assert.equal(form.action, '/daily-planner/items/42');
    assert.equal(fields.get('editTaskStart').value, '14:30');
    assert.equal(fields.get('editTaskEnd').value, '15:00');
    assert.equal(fields.get('editReminderCustom').value, '2026-10-03T14:15');
    assert.equal(fields.get('editReminderOffset').value, 'custom');
});

test('switching tasks clears stale times and resets scope while preserving the repeat interval', () => {
    const { fields, scope, clocks, open } = editor();
    open({ title: 'First task', start_time: '14:30', reminder_custom_at: '2026-10-03T14:15' });
    scope.value = 'occurrence';
    open({ title: 'Second task', repeat_type: 'weekly', repeat_interval: 3 });

    assert.equal(fields.get('editTaskName').value, 'Second task');
    assert.equal(clocks.editTaskStart, '');
    assert.equal(clocks.editReminderCustom, '');
    assert.equal(scope.value, 'series');
    assert.equal(fields.get('editRepeatInterval').value, 3);
});

test('bulk completion submits selected pending IDs and replaces the previous selection', () => {
    const script = template.slice(
        template.indexOf('    window.completeSelectedTasks = function() {'),
        template.indexOf("    document.querySelectorAll('.daily-row').forEach(box => {"),
    );
    let selected = [];
    let submissions = 0;
    const holder = {
        inputs: [],
        replaceChildren() { this.inputs = []; },
        appendChild(input) { this.inputs.push(input); },
    };
    const form = { requestSubmit() { submissions++; } };
    const context = {
        window: {},
        document: {
            querySelectorAll(selector) {
                assert.equal(selector, '.daily-row[data-pending="1"]:checked');
                return selected;
            },
            getElementById: id => id === 'bulkCompleteTaskIds' ? holder : form,
            createElement: () => ({}),
        },
    };
    vm.runInNewContext(script, context);
    context.window.completeSelectedTasks();
    assert.equal(submissions, 0);

    selected = [{ value: '12' }, { value: '34' }];
    context.window.completeSelectedTasks();
    assert.deepEqual(holder.inputs.map(input => [input.name, input.value]), [['ids[]', '12'], ['ids[]', '34']]);
    assert.equal(submissions, 1);

    selected = [{ value: '56' }];
    context.window.completeSelectedTasks();
    assert.deepEqual(holder.inputs.map(input => input.value), ['56']);
    assert.equal(submissions, 2);
});
