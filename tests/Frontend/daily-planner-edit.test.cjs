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
