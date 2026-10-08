{{--
    One field of the shared CRUD form, rendered by crud/_fields.blade.php.
    Expects $field (one field config) plus the parent's $item and $errors.
--}}
@php
    $name = $field['name'];
    $fieldId = 'field-' . $name;
    $errorId = $fieldId . '-error';
    $rawDefault = data_get($item, $name, $field['default'] ?? '');
    $old = old($name, $rawDefault);

    // Keep HTML source values canonical even when the database stores
    // seconds (e.g. 21:00:00). The global 12-hour control then presents
    // 9:00 PM while Laravel still receives H:i safely.
    if (is_object($old) && method_exists($old, 'format')) {
        if (in_array($field['type'], ['datetime-local', 'datetime-native'], true)) {
            $old = $old->format('Y-m-d\TH:i');
        } elseif ($field['type'] === 'time') {
            $old = $old->format('H:i');
        } else {
            $old = $old->format('Y-m-d');
        }
    } elseif ($field['type'] === 'time' && is_string($old) && $old !== '') {
        $old = substr($old, 0, 5);
    } elseif (in_array($field['type'], ['datetime-local', 'datetime-native'], true) && is_string($old) && $old !== '') {
        try {
            $old = \Illuminate\Support\Carbon::parse($old)->format('Y-m-d\TH:i');
        } catch (\Throwable $e) {
            $old = str_replace(' ', 'T', substr($old, 0, 16));
        }
    }
    $hasError = $errors->has($name);
    $isRequired = !empty($field['required']);
    $inputClasses = 'pm-input' . ($hasError ? ' border-rose-400' : '');
    $describedBy = implode(' ', array_filter([
        $hasError ? $errorId : null,
    ]));

    // Hints are deliberately not used as placeholders. Keep the configured
    // placeholder text and retain the optional suffix for optional fields.
    $optionalPattern = '/\s*\(optional\)|,\s*optional(?=\))/i';
    $labelText = trim(preg_replace($optionalPattern, '', (string) $field['label']));
    $isOptional = ! $isRequired && ! in_array($field['type'], ['checkbox', 'readonly'], true);
    $placeholder = $field['placeholder'] ?? null;
    if ($placeholder && $isOptional && ! preg_match('/\(optional\)\s*$/i', $placeholder)) {
        $placeholder .= ' (optional)';
    }
    $selectPlaceholder = $field['placeholder']
        ?? ('Choose ' . \Illuminate\Support\Str::lower($labelText) . ($isRequired ? '' : ' (optional)'));
    if (($field['placeholder'] ?? null) && $isOptional && ! preg_match('/\(optional\)\s*$/i', $selectPlaceholder)) {
        $selectPlaceholder .= ' (optional)';
    }
@endphp
<div class="min-w-0 space-y-1">
    @if ($field['type'] !== 'checkbox')
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-slate-700 mb-1">
            {{ $labelText }}
            @if ($isOptional)
                <span class="text-slate-500 font-normal">(optional)</span>
            @endif
            @if ($isRequired)
                <span class="text-rose-500" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
            @if ($field['money'] ?? false)
                <span class="text-slate-400 font-normal">({{ $siteSettings->default_currency_code ?? 'UGX' }})</span>
            @endif
        </label>
    @endif

    @if ($field['type'] === 'checkbox')
        <div class="flex items-center gap-2">
            {{-- Hidden fallback ensures an unchecked box still submits
                 "0" rather than omitting the field entirely. --}}
            <input type="hidden" name="{{ $name }}" value="0">
            <input
                type="checkbox"
                id="{{ $fieldId }}"
                name="{{ $name }}"
                value="1"
                @checked((bool) $old)
                class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]"
            >
            <label for="{{ $fieldId }}" class="text-sm text-slate-700">{{ $labelText }}</label>
        </div>
    @elseif (in_array($field['type'], ['datetime-local', 'datetime-native'], true))
        @php
            $datePart = '';
            $hourPart = '';
            $minutePart = '';
            $periodPart = 'AM';
            $canonicalValue = '';

            if (filled($old)) {
                try {
                    $dt = \Illuminate\Support\Carbon::parse($old);
                    $datePart = $dt->format('Y-m-d');
                    $hourPart = $dt->format('g');
                    $minutePart = $dt->format('i');
                    $periodPart = $dt->format('A');
                    $canonicalValue = $dt->format('Y-m-d\TH:i');
                } catch (\Throwable $e) {
                    $canonicalValue = (string) $old;
                }
            }
        @endphp

        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3"
             data-pm-datetime12
             data-field-name="{{ $name }}">
            {{-- The canonical 24-hour value is hidden for Laravel only.
                 Users see ONLY the 12-hour Date + Hour + Minute + AM/PM controls below. --}}
            <input type="hidden"
                   id="{{ $fieldId }}"
                   name="{{ $name }}"
                   value="{{ $canonicalValue }}"
                   data-pm-datetime12-value>

            <div class="grid grid-cols-1 sm:grid-cols-[minmax(180px,1fr)_90px_100px_90px] gap-2 items-end">
                <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1" for="{{ $fieldId }}-date">Date</label>
                    <input type="date"
                           id="{{ $fieldId }}-date"
                           value="{{ $datePart }}"
                           class="pm-input"
                            data-pm-datetime12-date
                            @if ($isRequired) required aria-required="true" @endif
                            @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="{{ $fieldId }}-hour">Hour (1–12)</label>
                    <select id="{{ $fieldId }}-hour"
                            class="pm-input text-center"
                            data-pm-datetime12-hour
                            @if ($isRequired) required aria-required="true" @endif
                            @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                        <option value="">Hour</option>
                        @for ($hour = 1; $hour <= 12; $hour++)
                            <option value="{{ $hour }}" @selected((string) $hourPart === (string) $hour)>{{ $hour }}</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="{{ $fieldId }}-minute">Minute</label>
                    <select id="{{ $fieldId }}-minute"
                            class="pm-input text-center"
                            data-pm-datetime12-minute
                            @if ($isRequired) required aria-required="true" @endif
                            @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                        <option value="">Min</option>
                        @for ($minute = 0; $minute <= 59; $minute++)
                            @php $minuteValue = str_pad((string) $minute, 2, '0', STR_PAD_LEFT); @endphp
                            <option value="{{ $minuteValue }}" @selected((string) $minutePart === $minuteValue)>{{ $minuteValue }}</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="{{ $fieldId }}-period">AM / PM</label>
                    <select id="{{ $fieldId }}-period"
                            class="pm-input text-center"
                             data-pm-datetime12-period
                             @if ($isRequired) required aria-required="true" @endif
                             @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                        @if (! $isRequired)<option value="">AM / PM</option>@endif
                        <option value="AM" @selected($periodPart === 'AM' && (! $isRequired || filled($old)))>AM</option>
                        <option value="PM" @selected($periodPart === 'PM')>PM</option>
                    </select>
                </div>
            </div>

        </div>

        <script>
            (function () {
                const root = document.currentScript?.previousElementSibling;
                if (!root || !root.matches('[data-pm-datetime12]')) return;

                const hidden = root.querySelector('[data-pm-datetime12-value]');
                const date = root.querySelector('[data-pm-datetime12-date]');
                const hour = root.querySelector('[data-pm-datetime12-hour]');
                const minute = root.querySelector('[data-pm-datetime12-minute]');
                const period = root.querySelector('[data-pm-datetime12-period]');

                if (!hidden || !date || !hour || !minute || !period) return;

                function syncHidden() {
                    if (!date.value || !hour.value || minute.value === '') {
                        hidden.value = '';
                        return;
                    }

                    let h = Number(hour.value);
                    if (period.value === 'AM' && h === 12) h = 0;
                    if (period.value === 'PM' && h !== 12) h += 12;

                    hidden.value =
                        date.value + 'T' +
                        String(h).padStart(2, '0') + ':' +
                        minute.value;
                }

                function syncVisibleFromHidden() {
                    const value = String(hidden.value || '').trim();
                    const match = value.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{1,2}):(\d{2})/);
                    if (!match) return;

                    const hour24 = Number(match[2]);
                    date.value = match[1];
                    hour.value = String((hour24 % 12) || 12);
                    minute.value = match[3];
                    period.value = hour24 >= 12 ? 'PM' : 'AM';
                }

                [date, hour, minute, period].forEach(function (el) {
                    el.addEventListener('change', syncHidden);
                });

                root.pmSyncFromHidden = syncVisibleFromHidden;
                syncVisibleFromHidden();
                syncHidden();
            })();
        </script>

    @elseif ($field['type'] === 'sleep-range')
        @php
            $normaliseTime = function ($value) {
                $value = trim((string) $value);
                if ($value === '') {
                    return ['hour' => '', 'minute' => '', 'period' => 'AM', 'value' => ''];
                }

                try {
                    $time = \Carbon\Carbon::parse($value);

                    return [
                        'hour' => $time->format('g'),
                        'minute' => $time->format('i'),
                        'period' => $time->format('A'),
                        'value' => $time->format('H:i'),
                    ];
                } catch (\Throwable $e) {
                    return ['hour' => '', 'minute' => '', 'period' => 'AM', 'value' => ''];
                }
            };

            $bed = $normaliseTime(old('bed_time', data_get($item, 'bed_time', '')));
            $wake = $normaliseTime(old('wake_time', data_get($item, 'wake_time', '')));
            $bedError = $errors->has('bed_time');
            $wakeError = $errors->has('wake_time');
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3"
             data-sleep-range>
            @foreach ([
                ['prefix' => 'bed', 'label' => 'Bedtime', 'name' => 'bed_time', 'time' => $bed, 'error' => $bedError],
                ['prefix' => 'wake', 'label' => 'Wake time', 'name' => 'wake_time', 'time' => $wake, 'error' => $wakeError],
            ] as $clock)
                <div class="min-w-0">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        {{ $clock['label'] }}
                    </label>

                    <input
                        type="hidden"
                        name="{{ $clock['name'] }}"
                        id="{{ $clock['prefix'] }}_time_value"
                        value="{{ $clock['time']['value'] }}"
                        data-time-hidden
                    >

                    <div class="grid grid-cols-[1fr_auto_1fr_1fr] items-center gap-2">
                        <select
                            class="pm-input text-center"
                            data-time-hour
                            aria-label="{{ $clock['label'] }} hour"
                            required
                        >
                            <option value="">Hour</option>
                            @for ($hour = 1; $hour <= 12; $hour++)
                                <option value="{{ $hour }}" @selected((string) $clock['time']['hour'] === (string) $hour)>
                                    {{ $hour }}
                                </option>
                            @endfor
                        </select>

                        <span class="font-bold text-slate-400">:</span>

                        <select
                            class="pm-input text-center"
                            data-time-minute
                            aria-label="{{ $clock['label'] }} minute"
                            required
                        >
                            <option value="">Min</option>
                            @for ($minute = 0; $minute <= 59; $minute++)
                                @php $minuteValue = str_pad((string) $minute, 2, '0', STR_PAD_LEFT); @endphp
                                <option value="{{ $minuteValue }}" @selected((string) $clock['time']['minute'] === $minuteValue)>
                                    {{ $minuteValue }}
                                </option>
                            @endfor
                        </select>

                        <select
                            class="pm-input text-center"
                            data-time-period
                            aria-label="{{ $clock['label'] }} AM or PM"
                        >
                            <option value="AM" @selected($clock['time']['period'] === 'AM')>AM</option>
                            <option value="PM" @selected($clock['time']['period'] === 'PM')>PM</option>
                        </select>
                    </div>

                    @error($clock['name'])
                        <p role="alert" class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <script>
            (function () {
                const root = document.currentScript?.previousElementSibling;
                if (!root || !root.matches('[data-sleep-range]')) return;

                root.querySelectorAll(':scope > div').forEach(group => {
                    const hidden = group.querySelector('[data-time-hidden]');
                    const hour = group.querySelector('[data-time-hour]');
                    const minute = group.querySelector('[data-time-minute]');
                    const period = group.querySelector('[data-time-period]');

                    if (!hidden || !hour || !minute || !period) return;

                    const sync = () => {
                        if (!hour.value || minute.value === '') {
                            hidden.value = '';
                            return;
                        }

                        let h = Number(hour.value);
                        if (period.value === 'AM' && h === 12) h = 0;
                        if (period.value === 'PM' && h !== 12) h += 12;

                        hidden.value = String(h).padStart(2, '0') + ':' + minute.value;
                    };

                    hour.addEventListener('change', sync);
                    minute.addEventListener('change', sync);
                    period.addEventListener('change', sync);
                    sync();
                });
            })();
        </script>
    @elseif ($field['type'] === 'readonly')
        <input
            type="text"
            id="{{ $fieldId }}"
            value="{{ $old !== '' ? $old : 'Calculated after saving' }}"
            readonly
            aria-readonly="true"
            tabindex="-1"
            class="{{ $inputClasses }} bg-slate-50 text-slate-500 cursor-default"
        >
    @elseif ($field['type'] === 'textarea')
        <div class="flex items-start gap-2">
            <textarea
                id="{{ $fieldId }}"
                name="{{ $name }}"
                rows="3"
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($isRequired) required aria-required="true" @endif
                @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="flex-1 {{ $inputClasses }}"
            >{{ $old }}</textarea>
            <button type="button"
                    class="pm-voice-input-btn hidden mt-1 w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-[var(--brand-1)] hover:bg-[var(--brand-1-tint-10)] transition-colors"
                    onclick="pmStartDictation('{{ $fieldId }}', this)"
                    aria-label="Dictate {{ $field['label'] }} using voice"
                    aria-pressed="false"
                    title="Voice input">
                <i class="fa-solid fa-microphone" aria-hidden="true"></i>
            </button>
        </div>
    @elseif ($field['type'] === 'budget-picker')
        @include('crud._budget-picker')
    @elseif ($field['type'] === 'select')
        <select
            id="{{ $fieldId }}"
            name="{{ $name }}"
            @if ($isRequired) required aria-required="true" @endif
            @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            class="{{ $inputClasses }}"
        >
            @unless (array_key_exists('', $field['options'] ?? []))
                <option value="">{{ $selectPlaceholder }}</option>
            @endunless
            @foreach (($field['options'] ?? []) as $value => $label)
                <option value="{{ $value }}" @selected((string) $old === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    @elseif ($field['type'] === 'text')
        <div class="flex items-center gap-2">
            <input
                type="text"
                id="{{ $fieldId }}"
                name="{{ $name }}"
                value="{{ $old }}"
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($isRequired) required aria-required="true" @endif
                @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="flex-1 {{ $inputClasses }}"
            >
            <button type="button"
                    class="pm-voice-input-btn hidden w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-[var(--brand-1)] hover:bg-[var(--brand-1-tint-10)] transition-colors"
                    onclick="pmStartDictation('{{ $fieldId }}', this)"
                    aria-label="Dictate {{ $field['label'] }} using voice"
                    aria-pressed="false"
                    title="Voice input">
                <i class="fa-solid fa-microphone" aria-hidden="true"></i>
            </button>
        </div>
    @else
        <input
            type="{{ $field['type'] }}"
            id="{{ $fieldId }}"
            name="{{ $name }}"
            value="{{ $old }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($field['type'] === 'number') step="0.01" @endif
            @if ($isRequired) required aria-required="true" @endif
            @if ($hasError) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            class="{{ $inputClasses }}"
        >
    @endif

            @error($name)
        <p id="{{ $errorId }}" role="alert" class="text-sm text-rose-600 mt-1 flex items-center gap-1">
            <i class="fa-solid fa-circle-exclamation text-xs" aria-hidden="true"></i>
            {{ $message }}
        </p>
    @enderror
</div>
