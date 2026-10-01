{{--
    Shared field renderer used by:
      - crud/form.blade.php (the full-page create/edit fallback — still
        reachable by direct URL even though the index page now defaults
        to a modal)
      - crud/index.blade.php's create/edit <dialog> (the default UX)

    Expects: $fields (field config array) and $item (an Eloquent model
    instance, or a fresh `new $model` for "create"). Uses old()-then-item
    fallback so validation-error redisplay works in both contexts. A field
    can set 'default' (e.g. true for a checkbox) used only when there's no
    old() input AND no $item value — i.e. a brand new "create" form.
--}}
@php
    /*
     * Long forms are split into tabs: a field's optional 'tab' key names
     * its tab (fields without one join the first tab). With a single
     * group the form renders exactly as before, with no tab bar.
     */
    $fieldGroups = [];
    foreach ($fields as $groupField) {
        $fieldGroups[$groupField['tab'] ?? ($fields[0]['tab'] ?? 'Details')][] = $groupField;
    }
    $useFieldTabs = count($fieldGroups) > 1;
    $fieldTabsId = 'pm-form-tabs-' . \Illuminate\Support\Str::random(6);
    $activeFieldGroup = array_key_first($fieldGroups);
    foreach ($fieldGroups as $groupName => $groupFields) {
        if (collect($groupFields)->contains(fn ($f) => $errors->has($f['name']))) {
            $activeFieldGroup = $groupName;
            break;
        }
    }
@endphp

@if ($useFieldTabs)
    <div class="pm-form-tabs" role="tablist" aria-label="Form sections" data-pm-form-tabs>
        @foreach (array_keys($fieldGroups) as $index => $groupName)
            <button type="button" role="tab"
                    id="{{ $fieldTabsId }}-tab-{{ $index }}"
                    aria-controls="{{ $fieldTabsId }}-panel-{{ $index }}"
                    aria-selected="{{ $groupName === $activeFieldGroup ? 'true' : 'false' }}"
                    tabindex="{{ $groupName === $activeFieldGroup ? '0' : '-1' }}"
                    data-pm-form-tab="{{ $index }}"
                    class="pm-form-tab {{ $groupName === $activeFieldGroup ? 'is-active' : '' }}">
                {{ $groupName }}
            </button>
        @endforeach
    </div>

    @foreach (array_values($fieldGroups) as $index => $groupFields)
        <div class="pm-form-panel" role="tabpanel"
             id="{{ $fieldTabsId }}-panel-{{ $index }}"
             aria-labelledby="{{ $fieldTabsId }}-tab-{{ $index }}"
             data-pm-form-panel="{{ $index }}"
             @if (array_keys($fieldGroups)[$index] !== $activeFieldGroup) hidden @endif>
            @foreach ($groupFields as $field)
                @include('crud._field', ['field' => $field])
            @endforeach
        </div>
    @endforeach

    @include('partials.form-tabs')
@else
    @foreach ($fields as $field)
        @include('crud._field', ['field' => $field])
    @endforeach
@endif

{{-- ================================================================
     AI GENERATE — INSIDE NEW / EDIT CRUD MODAL
     Enabled only for Spiritual Practices, Relationships and Networks.
     Because this partial is rendered inside the CRUD form, the control
     is available in BOTH New and Edit modes.
================================================================ --}}
@if (in_array($routeName ?? '', ['spiritual-practices', 'relationships', 'network-contacts'], true))
    @php
        $pmAiConfig = match ($routeName) {
            'spiritual-practices' => [
                'module' => 'spiritual-practices',
                'trigger_fields' => ['practice_title', 'practice_type'],
                'context_fields' => ['faith_path', 'practice_title', 'practice_type'],
                'help' => 'Add a practice title/topic or choose a practice type, then use AI Generate to prepare an editable draft.',
            ],
            'relationships' => [
                'module' => 'relationships',
                'trigger_fields' => ['name', 'category', 'relation_label'],
                'context_fields' => ['name', 'category', 'relation_label', 'priority'],
                'help' => 'Add the person, relationship type or connection topic, then use AI Generate to prepare follow-up ideas and an editable relationship draft.',
            ],
            'network-contacts' => [
                'module' => 'network-contacts',
                'trigger_fields' => ['name', 'relationship_type', 'network_groups'],
                'context_fields' => ['name', 'relationship_type', 'network_groups', 'company', 'met_through'],
                'help' => 'Add the person, connection type or network/topic, then use AI Generate to prepare an editable networking draft.',
            ],
            default => null,
        };
    @endphp

    @if ($pmAiConfig)
        <div class="rounded-xl border border-violet-100 bg-violet-50/70 p-4"
             data-pm-ai-modal-assist
             data-module="{{ $pmAiConfig['module'] }}"
             data-trigger-fields='@json($pmAiConfig['trigger_fields'])'
             data-context-fields='@json($pmAiConfig['context_fields'])'>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1.5 text-violet-600"></i>
                        AI Generate
                    </p>
                    <p class="mt-1 text-xs leading-5 text-slate-600">
                        {{ $pmAiConfig['help'] }}
                    </p>
                </div>

                <button type="button"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                        data-pm-ai-modal-generate
                        disabled>
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>AI Generate</span>
                </button>
            </div>

            <p class="mt-2 hidden text-xs font-medium"
               data-pm-ai-modal-status
               aria-live="polite"></p>
        </div>

        <script>
            (function () {
                'use strict';

                const script = document.currentScript;
                const root = script?.previousElementSibling;

                if (!root || !root.matches('[data-pm-ai-modal-assist]')) {
                    return;
                }

                const form = root.closest('form');
                const button = root.querySelector('[data-pm-ai-modal-generate]');
                const status = root.querySelector('[data-pm-ai-modal-status]');

                if (!form || !button) {
                    return;
                }

                const moduleName = root.dataset.module || '';
                const triggerFields = JSON.parse(root.dataset.triggerFields || '[]');
                const contextFields = JSON.parse(root.dataset.contextFields || '[]');

                function findField(name) {
                    return form.querySelector('[name="' + CSS.escape(name) + '"]')
                        || form.querySelector('#field-' + CSS.escape(name));
                }

                function readField(name) {
                    const el = findField(name);

                    if (!el) {
                        return '';
                    }

                    if (el.type === 'checkbox') {
                        return el.checked ? (el.value || '1') : '';
                    }

                    return String(el.value || '').trim();
                }

                function topic() {
                    for (const name of triggerFields) {
                        const value = readField(name);

                        if (value !== '') {
                            return value;
                        }
                    }

                    return '';
                }

                function refresh() {
                    button.disabled = topic() === '';
                }

                function message(text, error) {
                    if (!status) {
                        return;
                    }

                    status.textContent = text || '';
                    status.classList.toggle('hidden', !text);
                    status.classList.toggle('text-rose-600', !!error);
                    status.classList.toggle('text-emerald-700', !error && !!text);
                }

                function applyValue(name, value) {
                    const el = findField(name);

                    if (!el || value === null || typeof value === 'undefined') {
                        return;
                    }

                    const newValue = String(value).trim();

                    if (newValue === '') {
                        return;
                    }

                    /*
                     * Preserve everything already typed in New or Edit.
                     * AI only fills blank fields.
                     */
                    if (String(el.value || '').trim() !== '') {
                        return;
                    }

                    if (el.tagName === 'SELECT') {
                        const exists = Array.from(el.options || [])
                            .some(option => option.value === newValue);

                        if (!exists) {
                            return;
                        }
                    }

                    el.value = newValue;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }

                async function generate() {
                    const currentTopic = topic();

                    if (!currentTopic) {
                        message('Add a title, topic or connection type first.', true);
                        refresh();
                        return;
                    }

                    const context = {};

                    contextFields.forEach(function (name) {
                        const value = readField(name);

                        if (value !== '') {
                            context[name] = value;
                        }
                    });

                    const originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i><span>Generating...</span>';
                    message('Preparing an editable draft...', false);

                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                        const response = await fetch('{{ route('ai.form-assist') }}', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            },
                            body: JSON.stringify({
                                module: moduleName,
                                topic: currentTopic,
                                context: context
                            })
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || payload.ok === false) {
                            throw new Error(
                                payload.message || 'AI could not prepare a draft right now.'
                            );
                        }

                        Object.entries(payload.data || {}).forEach(function ([name, value]) {
                            applyValue(name, value);
                        });

                        message(
                            payload.message
                                || 'AI draft generated. Review and edit it before saving.',
                            false
                        );
                    } catch (error) {
                        message(
                            error?.message
                                || 'AI could not prepare a draft right now. Your form was not changed.',
                            true
                        );
                    } finally {
                        button.innerHTML = originalHtml;
                        refresh();
                    }
                }

                triggerFields.forEach(function (name) {
                    const el = findField(name);

                    if (!el) {
                        return;
                    }

                    el.addEventListener('input', refresh);
                    el.addEventListener('change', refresh);
                });

                button.addEventListener('click', generate);

                /*
                 * New and Edit use the same modal/form. Edit values are injected
                 * after the dialog opens, so refresh again whenever the dialog
                 * state changes.
                 */
                const dialog = form.closest('dialog');

                if (dialog) {
                    new MutationObserver(function () {
                        window.setTimeout(refresh, 0);
                    }).observe(dialog, {
                        attributes: true,
                        attributeFilter: ['open']
                    });
                }

                document.addEventListener('DOMContentLoaded', refresh);
                window.setTimeout(refresh, 0);
            })();
        </script>
    @endif
@endif

