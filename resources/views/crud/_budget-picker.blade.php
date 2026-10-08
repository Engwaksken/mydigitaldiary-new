{{--
    Expense form: pick a Budget item (pre-fills category + amount and links
    the expense so the budget's spent/remaining updates) or "+ Add new
    item" (free-text fields as usual, optionally also added to that
    month's budget). Rendered by crud/_field.blade.php for type
    'budget-picker'; options come from ExpenseController::withBudgetOptions().
    Expects $field, $fieldId, $name, $old, $inputClasses, $selectPlaceholder.
--}}
@php
    $pmBudgetGroups = $field['groups'] ?? [];
    $pmBudgetOptionCount = collect($pmBudgetGroups)->sum(fn ($entries) => count($entries));
    $pmBudgetChoice = (string) ($old ?? '');
@endphp
<div class="space-y-2" data-pm-budget-picker>
    @if ($pmBudgetOptionCount > 8)
        <input type="search" class="pm-input text-sm" placeholder="Search budget items" aria-label="Search budget items" autocomplete="off" data-pm-budget-search>
    @endif

    <select id="{{ $fieldId }}" name="{{ $name }}" class="{{ $inputClasses }}" data-pm-budget-select
            @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>
        <option value="">{{ $selectPlaceholder }}</option>
        @foreach ($pmBudgetGroups as $groupLabel => $entries)
            <optgroup label="{{ $groupLabel }}">
                @foreach ($entries as $entry)
                    <option value="{{ $entry['id'] }}"
                            data-category="{{ $entry['category'] }}"
                            data-amount="{{ $entry['remaining'] > 0 ? $entry['remaining'] : $entry['planned'] }}"
                            @selected($pmBudgetChoice === (string) $entry['id'])>{{ $entry['label'] }}</option>
                @endforeach
            </optgroup>
        @endforeach
        <option value="new" @selected($pmBudgetChoice === 'new')>+ Add new item</option>
    </select>

    <label class="items-center gap-2 text-sm text-slate-700 {{ $pmBudgetChoice === 'new' ? 'flex' : 'hidden' }}" data-pm-budget-add-wrap>
        <input type="checkbox" name="add_to_budget" value="1" @checked(old('add_to_budget'))
               class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
        <span>Also add to <span data-pm-budget-month>{{ $field['current_month_label'] ?? now()->format('F Y') }}</span> budget</span>
    </label>
</div>

@once
<script>
    (function () {
        'use strict';

        function pickerParts(root) {
            return {
                select: root.querySelector('[data-pm-budget-select]'),
                search: root.querySelector('[data-pm-budget-search]'),
                addWrap: root.querySelector('[data-pm-budget-add-wrap]'),
                form: root.closest('form')
            };
        }

        function syncAddToBudget(root) {
            var parts = pickerParts(root);
            if (!parts.select || !parts.addWrap) { return; }
            var isNew = parts.select.value === 'new';
            parts.addWrap.classList.toggle('hidden', !isNew);
            parts.addWrap.classList.toggle('flex', isNew);
            if (!isNew) {
                var box = parts.addWrap.querySelector('input[type="checkbox"]');
                if (box) { box.checked = false; }
            }
        }

        function syncMonthLabel(root) {
            var parts = pickerParts(root);
            var label = root.querySelector('[data-pm-budget-month]');
            var date = parts.form ? parts.form.querySelector('[name="spent_at"]') : null;
            if (!label || !date || !/^\d{4}-\d{2}-\d{2}$/.test(date.value)) { return; }
            label.textContent = new Date(date.value + 'T00:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        }

        function applyChoice(root) {
            var parts = pickerParts(root);
            var option = parts.select ? parts.select.options[parts.select.selectedIndex] : null;
            var form = parts.form;
            if (!option || !form) { return; }

            var category = form.querySelector('[name="category"]');
            var amount = form.querySelector('[name="amount"]');

            if (option.dataset.category !== undefined) {
                // A budget item: pre-fill (amount stays editable).
                if (category) { category.value = option.dataset.category; category.dataset.pmFromBudget = '1'; }
                var itemized = document.getElementById('pm-expense-itemize-toggle');
                if (amount && !(itemized && itemized.checked)) {
                    amount.value = option.dataset.amount || '';
                }
            } else if (parts.select.value === 'new' && category) {
                if (category.dataset.pmFromBudget === '1') { category.value = ''; }
                delete category.dataset.pmFromBudget;
                category.focus();
            }

            syncAddToBudget(root);
            syncMonthLabel(root);
        }

        function filterOptions(root) {
            var parts = pickerParts(root);
            if (!parts.search || !parts.select) { return; }
            var term = parts.search.value.trim().toLowerCase();
            parts.select.querySelectorAll('optgroup').forEach(function (group) {
                var visible = 0;
                group.querySelectorAll('option').forEach(function (option) {
                    var match = !term || option.textContent.toLowerCase().indexOf(term) !== -1;
                    option.hidden = !match;
                    option.disabled = !match;
                    if (match) { visible++; }
                });
                group.hidden = visible === 0;
            });
        }

        // Edit modal: keep a link to a budget item that is no longer listed.
        function ensureOption(select, value) {
            if (!value || select.value === String(value)) { return; }
            var keep = document.createElement('option');
            keep.value = String(value);
            keep.textContent = 'Current budget item';
            keep.dataset.pmTemporary = '1';
            select.insertBefore(keep, select.options[1] || null);
            select.value = String(value);
        }

        function init(root) {
            if (root.dataset.pmBudgetReady) { return; }
            root.dataset.pmBudgetReady = '1';
            var parts = pickerParts(root);
            if (!parts.select) { return; }

            parts.select.addEventListener('change', function () { applyChoice(root); });
            if (parts.search) {
                parts.search.addEventListener('input', function () { filterOptions(root); });
            }
            if (parts.form) {
                parts.form.addEventListener('reset', function () {
                    window.setTimeout(function () {
                        parts.select.querySelectorAll('[data-pm-temporary]').forEach(function (o) { o.remove(); });
                        if (parts.search) { parts.search.value = ''; filterOptions(root); }
                        syncAddToBudget(root);
                    }, 0);
                });
                var date = parts.form.querySelector('[name="spent_at"]');
                if (date) { date.addEventListener('change', function () { syncMonthLabel(root); }); }
            }
            syncAddToBudget(root);
            syncMonthLabel(root);
        }

        function initAll() {
            document.querySelectorAll('[data-pm-budget-picker]').forEach(init);

            if (typeof window.openCrudEditModal === 'function' && !window.openCrudEditModal.pmBudgetWrapped) {
                var original = window.openCrudEditModal;
                var wrapped = function (actionUrl, values) {
                    original(actionUrl, values);
                    document.querySelectorAll('[data-pm-budget-picker]').forEach(function (root) {
                        var select = root.querySelector('[data-pm-budget-select]');
                        if (select && values && values.budget_id) { ensureOption(select, values.budget_id); }
                        syncAddToBudget(root);
                        syncMonthLabel(root);
                    });
                };
                wrapped.pmBudgetWrapped = true;
                window.openCrudEditModal = wrapped;
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll);
        } else {
            initAll();
        }
    })();
</script>
@endonce
