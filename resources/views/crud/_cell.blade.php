{{--
    Renders one field's value for the shared list table (crud/index).
    Expects: $field, $item, $rowValues, $routeName, $countdownField.
    Output is inline so it can sit in a column or in the muted line under
    a row's title.
--}}
@php
    $value = $item->{$field['name']};
    $meetingFieldName = strtolower((string) ($field['name'] ?? ''));
    $isMeetingLinkField = $routeName === 'meetings' && in_array($meetingFieldName, ['location', 'meeting_link', 'video_link', 'join_url', 'url'], true);
    $isMeetingAttendeesField = $routeName === 'meetings' && in_array($meetingFieldName, ['attendees', 'attendee', 'participants'], true);
@endphp
@if ($isMeetingLinkField)
    @php
        $meetingLinkValue = trim((string) ($value ?? ''));
        $meetingSafeExternalUrl = $item->safe_external_url;
    @endphp
    @if ($meetingLinkValue === '')
    @elseif ($meetingSafeExternalUrl && $item->canBeJoinedBy(auth()->user()))
        <a href="{{ $item->diary_join_url }}"
           class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:underline font-semibold"
           title="Join meeting">
            <i class="fa-solid fa-arrow-right-to-bracket text-[10px]" aria-hidden="true"></i>
            Join meeting
        </a>
    @elseif ($meetingSafeExternalUrl)
        {{-- A link the diary can't join through (e.g. a calendar-synced
             Zoom/Meet URL) shows as "View", opening the meeting details;
             the UI never links straight to an external meeting URL. --}}
        <button type="button"
                onclick='openCrudViewModal({{ json_encode($rowValues) }}, {{ $item->id }}, {{ (($item->user_id ?? null) == auth()->id()) ? "true" : "false" }})'
                class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:underline font-semibold"
                title="View meeting link">
            <i class="fa-solid fa-eye text-[10px]" aria-hidden="true"></i>
            View link
        </button>
    @else
        <span title="{{ $meetingLinkValue }}">{{ \Illuminate\Support\Str::limit($meetingLinkValue, 34) }}</span>
    @endif
@elseif ($isMeetingAttendeesField)
    @php
        $meetingAttendeeParts = collect();
        if (is_array($value) || $value instanceof \Illuminate\Support\Collection) {
            $meetingAttendeeParts = collect($value)->map(function ($entry) {
                if (is_scalar($entry)) return trim((string) $entry);
                if (is_array($entry)) return trim((string) ($entry['email'] ?? $entry['name'] ?? $entry['value'] ?? ''));
                if (is_object($entry)) return trim((string) ($entry->email ?? $entry->name ?? $entry->value ?? ''));
                return '';
            });
        } else {
            $rawAttendees = trim((string) ($value ?? ''));
            if ($rawAttendees !== '') {
                $meetingAttendeeParts = collect(preg_split('/[,;\n]+/', $rawAttendees));
            }
        }
        $meetingAttendeeParts = $meetingAttendeeParts->map(fn ($part) => trim((string) $part))->filter()->unique()->values();
        $meetingAttendeeCount = $meetingAttendeeParts->count();
    @endphp
    @if ($meetingAttendeeCount > 0)
        <span class="inline-flex items-center gap-1" title="{{ $meetingAttendeeParts->implode(', ') }}">
            <i class="fa-solid fa-users text-[10px]" aria-hidden="true"></i>
            {{ $meetingAttendeeCount }} {{ $meetingAttendeeCount === 1 ? 'attendee' : 'attendees' }}
        </span>
    @endif
@elseif ($field['type'] === 'textarea' || $field['name'] === 'notes')
    @php $plainText = trim(strip_tags((string) ($value ?? ''))); @endphp
    @if ($plainText !== '')
        <span title="{{ \Illuminate\Support\Str::limit($plainText, 400) }}">{{ \Illuminate\Support\Str::limit($plainText, 90) }}</span>
    @endif
@elseif ($field['name'] === 'project_id' && isset($item->project))
    {{ $item->project->name }}
@elseif ($field['name'] === 'savings_goal_id' && isset($item->goal))
    {{ $item->goal->name }}
@elseif ($field['money'] ?? false)
    {{ $value !== null ? format_money($value) : '' }}
@elseif ($field['type'] === 'checkbox')
    @if ($value)
        <i class="fa-solid fa-circle-check text-emerald-500" aria-hidden="true"></i>
        <span class="sr-only">Yes</span>
    @else
        <i class="fa-solid fa-circle-xmark text-slate-300" aria-hidden="true"></i>
        <span class="sr-only">No</span>
    @endif
@elseif ($field['type'] === 'time' && $value)
    @php
        try {
            $displayTime = \Illuminate\Support\Carbon::parse((string) $value)->format('g:i A');
        } catch (\Throwable $e) {
            $displayTime = (string) $value;
        }
    @endphp
    {{ $displayTime }}
@elseif (is_object($value) && method_exists($value, 'format'))
    <span>{{ in_array($field['type'], ['datetime-local', 'datetime-native'], true) ? $value->format('d M Y, g:i A') : $value->format('d M Y') }}</span>
    @if (($withCountdown ?? false) && $field['name'] === $countdownField && !($routeName === 'debts' && $item->status === 'paid'))
        <x-countdown :date="$value" :status="$item->status ?? null" />
    @endif
@elseif (is_array($value) || $value instanceof \Illuminate\Support\Collection)
    @php
        $arrayValue = $value instanceof \Illuminate\Support\Collection ? $value->all() : $value;
        $displayParts = collect($arrayValue)
            ->map(function ($entry) {
                if (is_null($entry)) {
                    return null;
                }
                if (is_scalar($entry)) {
                    return trim((string) $entry);
                }
                if (is_array($entry)) {
                    foreach (['name', 'title', 'email', 'label', 'value'] as $key) {
                        if (isset($entry[$key]) && is_scalar($entry[$key])) {
                            return trim((string) $entry[$key]);
                        }
                    }
                    return collect($entry)
                        ->filter(fn ($part) => is_scalar($part) && trim((string) $part) !== '')
                        ->map(fn ($part) => trim((string) $part))
                        ->implode(' - ');
                }
                if (is_object($entry)) {
                    foreach (['name', 'title', 'email', 'label', 'value'] as $key) {
                        if (isset($entry->{$key}) && is_scalar($entry->{$key})) {
                            return trim((string) $entry->{$key});
                        }
                    }
                    if (method_exists($entry, '__toString')) {
                        return trim((string) $entry);
                    }
                }
                return null;
            })
            ->filter(fn ($part) => filled($part))
            ->values()
            ->implode(', ');
    @endphp
    {{ $displayParts !== '' ? \Illuminate\Support\Str::limit($displayParts, 120) : '' }}
@elseif (isset($field['options']) && $value !== null && !is_array($value) && array_key_exists($value, $field['options']))
    {{ $field['options'][$value] }}
@else
    {{ $value !== null ? \Illuminate\Support\Str::limit((string) $value, 60) : '' }}
@endif
