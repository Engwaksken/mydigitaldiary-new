<?php

namespace App\Http\Controllers;

use App\Mail\MeetingInvitationMail;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Mail\MeetingNotesMail;

class MeetingController extends CrudController
{
    protected string $model = Meeting::class;
    protected string $routeName = 'meetings';
    protected string $title = 'Meeting';
    protected string $icon = 'fa-solid fa-calendar-days';
    protected string $accent = 'blue';
    protected string $dateField = 'start_at';

    protected array $fields = [
        ['tab' => 'Details', 'name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],

        ['tab' => 'Details', 'name' => 'start_at', 'label' => 'Start Date & Time', 'type' => 'datetime-native', 'required' => true],
        ['tab' => 'Details', 'name' => 'end_at', 'label' => 'End Date & Time', 'type' => 'datetime-native'],

        ['tab' => 'Details', 'name' => 'location', 'label' => 'Location / Video Link', 'type' => 'text', 'placeholder' => 'e.g. Conference Room B, or a Zoom/Meet link'],
        ['tab' => 'Details', 'name' => 'attendees', 'label' => 'Attendees', 'type' => 'text', 'placeholder' => 'name@example.com, colleague@example.com'],

        ['tab' => 'Details', 'name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ]],

        ['tab' => 'Agenda', 'name' => 'notes', 'label' => 'Notes / Agenda', 'type' => 'textarea'],

        /*
         * Rendered as a normal select so the generic CRUD form cannot shrink
         * or hide the control. meetings-extra.blade.php upgrades it visually
         * to a large reminder toggle.
         */
        ['tab' => 'Reminder & Repeat', 'name' => 'set_reminder', 'label' => 'Reminder', 'type' => 'select', 'options' => [
            '0' => 'No reminder',
            '1' => 'Set reminder',
        ]],

        ['tab' => 'Reminder & Repeat', 'name' => 'recurrence_frequency', 'label' => 'Repeat', 'type' => 'select', 'options' => [
            '' => 'Does not repeat',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ]],
        ['tab' => 'Reminder & Repeat', 'name' => 'recurrence_days_of_week', 'label' => 'Repeat on (weekly only)', 'type' => 'text', 'placeholder' => '1=Mon … 7=Sun, e.g. 1,3,5'],
        ['tab' => 'Reminder & Repeat', 'name' => 'recurrence_ends_at', 'label' => 'Repeat until (optional)', 'type' => 'date'],
    ];

    protected array $tableColumns = [
        ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['name' => 'start_at', 'label' => 'Start Date & Time', 'type' => 'datetime-native'],
        ['name' => 'end_at', 'label' => 'End Date & Time', 'type' => 'datetime-native'],
        ['name' => 'location', 'label' => 'Location / Video Link', 'type' => 'text'],
        ['name' => 'attendees', 'label' => 'Attendees', 'type' => 'text'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => [
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ]],
    ];

    protected array $rules = [
        'title' => 'required|string|max:255',
        'start_at' => 'required|date',
        'end_at' => 'nullable|date|after:start_at',
        'location' => 'nullable|string|max:255',
        'attendees' => 'nullable|string',
        'status' => 'required|in:scheduled,completed,cancelled',
        'notes' => 'nullable|string',
        'recurrence_frequency' => 'nullable|in:daily,weekly,monthly',
        'recurrence_days_of_week' => 'nullable|string|max:50',
        'recurrence_ends_at' => 'nullable|date|after:start_at',
    ];

    /**
     * Browser <select>/<input> controls submit clock values as strings.
     * Values such as "00" and "05" are valid minutes but Laravel's
     * `integer` rule can reject zero-padded strings. Convert numeric clock
     * fields to real integers before validation.
     */
    private function normalizeMeetingClockFields(Request $request): void
    {
        $clockFields = [
            'start_hour',
            'start_minute',
            'end_hour',
            'end_minute',
        ];

        $normalized = [];

        foreach ($clockFields as $field) {
            if (! $request->exists($field)) {
                continue;
            }

            $value = $request->input($field);

            if ($value === null || $value === '') {
                $normalized[$field] = $value;
                continue;
            }

            if (is_numeric($value)) {
                $normalized[$field] = (int) $value;
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }

        if (! $request->has('slots') || ! is_array($request->input('slots'))) {
            return;
        }

        $slots = $request->input('slots');

        foreach ($slots as $index => $slot) {
            if (! is_array($slot)) {
                continue;
            }

            foreach ($clockFields as $field) {
                if (! array_key_exists($field, $slot)) {
                    continue;
                }

                $value = $slot[$field];

                if ($value === null || $value === '') {
                    continue;
                }

                if (is_numeric($value)) {
                    $slots[$index][$field] = (int) $value;
                }
            }
        }

        $request->merge(['slots' => $slots]);
    }

    private function meetingFormRules(): array
    {
        return [
            'title' => ['required','string','max:255'],

            'start_at' => ['required','date'],
            'end_at' => ['nullable','date'],

            'location' => ['nullable','string','max:255'],
            'attendees' => ['nullable','string'],
            'status' => ['required','in:scheduled,completed,cancelled'],
            'notes' => ['nullable','string'],
            'set_reminder' => ['nullable','boolean'],
            'recurrence_frequency' => ['nullable','in:daily,weekly,monthly'],
            'recurrence_days_of_week' => ['nullable','string','max:50'],
            'recurrence_ends_at' => ['nullable','date'],
        ];
    }

    /**
     * <input type="datetime-local"> submits "Y-m-d\TH:i". Convert that
     * wall-clock string (in the user's timezone) to the same stored format
     * the rest of the module uses.
     */
    private function parseMeetingDateTime(Request $request, string $value, string $field): ?string
    {
        $timezone = $request->user()?->timezone ?: config('app.timezone', 'Africa/Kampala');

        try {
            return Carbon::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $value), $timezone)
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'The selected meeting date or time is invalid.']);
        }
    }

    private function normaliseMeetingForm(Request $request): array
    {
        $form = $request->validate($this->meetingFormRules());

        $startAt = $this->parseMeetingDateTime($request, $form['start_at'], 'start_at');

        $endAt = null;

        if (! empty($form['end_at'])) {
            $endAt = $this->parseMeetingDateTime($request, $form['end_at'], 'end_at');

            if (Carbon::parse($endAt)->lessThanOrEqualTo(Carbon::parse($startAt))) {
                throw ValidationException::withMessages(['end_at' => 'End must be after Start.']);
            }
        }

        return [
            'title' => $form['title'],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => $form['location'] ?? null,
            'attendees' => $form['attendees'] ?? null,
            'status' => $form['status'],
            'notes' => $form['notes'] ?? null,
            'recurrence_frequency' => $form['recurrence_frequency'] ?? null,
            'recurrence_days_of_week' => $form['recurrence_days_of_week'] ?? null,
            'recurrence_ends_at' => $form['recurrence_ends_at'] ?? null,
        ];
    }

    /**
     * Parses the comma-separated "1,3,5" text field into the actual
     * int array the model casts recurrence_days_of_week to and
     * RecurringMeetingService expects — invalid/out-of-range entries
     * are silently dropped rather than rejecting the whole save over
     * a typo in one number.
     */
    private function parseDaysOfWeek(?string $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $days = array_filter(array_map('intval', explode(',', $raw)), fn ($d) => $d >= 1 && $d <= 7);

        return empty($days) ? null : array_values(array_unique($days));
    }

    /**
     * Overrides the generic CrudController::store() — a recurring
     * meeting needs its upcoming instances generated immediately
     * after being saved (via RecurringMeetingService), rather than
     * waiting for tomorrow's scheduled top-up to run — otherwise the
     * person who just set up a recurring meeting would see an empty
     * list until the next day.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->normaliseMeetingForm($request);
        $data['user_id'] = $request->user()->id;
        if (empty($data['recurrence_frequency'])) {
            $data['recurrence_days_of_week'] = null;
            $data['recurrence_ends_at'] = null;
        } else {
            $data['recurrence_days_of_week'] = $this->parseDaysOfWeek($data['recurrence_days_of_week'] ?? null);
        }

        $meeting = Meeting::create($data);

        if ($meeting->isRecurring()) {
            app(\App\Services\RecurringMeetingService::class)->generateUpcoming($meeting);
        }

        if ($request->boolean('set_reminder')) {
            $this->createLinkedReminder($request, $meeting);
        }

        $this->afterSave($request, $meeting, wasCreated: true);

        return redirect()->route('meetings.index')->with('success', 'Meeting created.');
    }

    /**
     * Same day-of-week parsing as store() above. Deliberately does
     * NOT regenerate already-created future instances just because
     * the rule changed on an edit — that could destructively wipe
     * out instances a person already customized individually.
     * Tomorrow's scheduled top-up naturally uses whatever rule is
     * current when it runs, so a rule change take effect for future
     * instances without needing special handling here.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $meeting = Meeting::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $this->normaliseMeetingForm($request);
        if (empty($data['recurrence_frequency'])) {
            $data['recurrence_days_of_week'] = null;
            $data['recurrence_ends_at'] = null;
        } else {
            $data['recurrence_days_of_week'] = $this->parseDaysOfWeek($data['recurrence_days_of_week'] ?? null);
        }

        $meeting->update($data);

        if ($request->boolean('set_reminder')) {
            $this->createLinkedReminder($request, $meeting);
        }

        $this->afterSave($request, $meeting, wasCreated: false);

        return redirect()->route('meetings.index')->with('success', 'Meeting updated.');
    }

    /**
     * Every meeting the user created, PLUS every meeting anyone else
     * created where the user's own email shows up in that meeting's
     * free-text "attendees" field — so being invited to someone else's
     * meeting (by email) surfaces it in this user's own Meetings page too,
     * for one-place tracking instead of only ever seeing meetings you
     * personally scheduled. `attendees` is a plain text field (not a
     * structured list), so this is a case-insensitive LIKE match, not an
     * exact-email guarantee — a false positive is possible if someone's
     * email happens to be a substring of another attendee's text, but
     * this is a reasonable trade-off given the field's free-text design.
     *
     * Edit/delete stay owner-only regardless — CrudController's default
     * edit()/update()/destroy() still filter by user_id, so an invited
     * (non-owner) user can see but never modify someone else's meeting.
     */
    private function visibleMeetingsQuery(Request $request): Builder
    {
        $ids = Meeting::visibleTo($request->user())->pluck('id');

        // A copied external meeting normally has its source markers copied
        // onto itself. Load a bounded legacy ancestry chain too, so older
        // multi-level copies do not issue one source query per row while the
        // index and its stats determine their classification.
        return Meeting::query()->whereKey($ids)->with(
            'copiedFrom.copiedFrom.copiedFrom.copiedFrom.copiedFrom.copiedFrom'
        );
    }

    /**
     * Use model deletion events rather than a builder delete: a source
     * meeting must pass its external markers to existing copies before the
     * database nulls their copied_from_meeting_id foreign key.
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $meetings = Meeting::where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get();

        $meetings->each->delete();
        $deleted = $meetings->count();

        return back()->with('success', $deleted === 1
            ? '1 Meeting deleted.'
            : "{$deleted} Meetings deleted.");
    }

    public function index(Request $request)
    {
        $query = $this->visibleMeetingsQuery($request);

        // "Missed" isn't a stored value (see Meeting::displayStatus()),
        // so this filters in PHP after fetching rather than in SQL —
        // fine at this app's scale, and keeps the derivation in one
        // place instead of duplicating the "past + still scheduled"
        // logic as a raw SQL condition too.
        $statusFilter = $request->query('status_filter');
        if ($statusFilter && in_array($statusFilter, ['scheduled', 'completed', 'cancelled', 'missed'], true)) {
            $matchingIds = $this->visibleMeetingsQuery($request)->get()->filter(fn ($m) => $m->displayStatus() === $statusFilter)->pluck('id');
            $query = $this->visibleMeetingsQuery($request)->whereIn('id', $matchingIds);
        }

        $connections = \Illuminate\Support\Facades\Schema::hasTable('user_meeting_connections')
            ? \App\Models\UserMeetingConnection::where('user_id', $request->user()->id)->get()->keyBy('platform')
            : collect();
        $enabledPlatforms = \Illuminate\Support\Facades\Schema::hasTable('meeting_platform_configs')
            ? \App\Models\MeetingPlatformConfig::where('is_enabled', true)->get()
            : collect();

        $nearestMeeting = (clone $query)
            ->where('status', 'scheduled')
            ->get()
            ->filter(function (Meeting $meeting) {
                $now = now();
                return ($meeting->start_at && $meeting->start_at->greaterThanOrEqualTo($now))
                    || ($meeting->start_at && $meeting->end_at
                        && $meeting->start_at->lessThanOrEqualTo($now)
                        && $meeting->end_at->greaterThanOrEqualTo($now));
            })
            ->sortBy(function (Meeting $meeting) {
                $now = now();
                return $meeting->start_at->greaterThan($now)
                    ? $meeting->start_at->timestamp
                    : $now->timestamp - 1;
            })
            ->first();

        return $this->renderIndex($request, $query, [
            'connections' => $connections,
            'enabledPlatforms' => $enabledPlatforms,
            'statusFilter' => $statusFilter,
            'nearestMeeting' => $nearestMeeting,
        ], function ($q) {
            // Sort the already-filtered result set in PHP so the current
            // user's timezone and every supported database agree on what is
            // current, upcoming, and past; then preserve that order in SQL
            // for pagination.
            $now = now();
            $records = (clone $q)->get()->all();
            usort($records, function (Meeting $a, Meeting $b) use ($now) {
                $bucket = function (Meeting $meeting) use ($now): int {
                    $start = $meeting->start_at?->timestamp;
                    $end = $meeting->end_at?->timestamp;
                    $current = $now->timestamp;
                    if ($start !== null && $end !== null && $start <= $current && $end >= $current) return 0;
                    return $start !== null && $start >= $current ? 1 : 2;
                };
                $bucketCompare = $bucket($a) <=> $bucket($b);
                if ($bucketCompare !== 0) return $bucketCompare;
                return $bucket($a) === 2
                    ? $b->start_at->timestamp <=> $a->start_at->timestamp
                    : $a->start_at->timestamp <=> $b->start_at->timestamp;
            });
            $orderedIds = collect($records)->pluck('id')->values();

            if ($orderedIds->isNotEmpty()) {
                $q->reorder()->orderByRaw('CASE id '.collect($orderedIds)->map(fn ($id, $i) => "WHEN {$id} THEN {$i}")->implode(' ').' ELSE 999999 END');
            }
        }, 100);
    }

    public function addToCalendar(Request $request, int $meeting): RedirectResponse
    {
        $user = $request->user();
        $source = Meeting::findOrFail($meeting);

        if ((int) $source->user_id === (int) $user->id) {
            return back()->with('info', 'This meeting is already in your Meetings calendar.');
        }

        abort_unless(
            $user->email && $source->attendeeEmails()->contains(strtolower(trim($user->email))),
            403
        );

        $alreadyAdded = Meeting::where('user_id', $user->id)
            ->where('copied_from_meeting_id', $source->id)
            ->exists();

        if ($alreadyAdded) {
            return back()->with('info', 'This meeting is already in your Meetings calendar.');
        }

        Meeting::create([
            'user_id' => $user->id,
            'copied_from_meeting_id' => $source->id,
            'title' => $source->title,
            'start_at' => $source->start_at,
            'end_at' => $source->end_at,
            'location' => $source->location,
            'attendees' => $source->attendees,
            'status' => $source->status,
            'notes' => $source->notes,
            // Keep the source classification on the copy. The foreign key is
            // nullified when its source is deleted, so relying on the relation
            // alone would incorrectly turn a copied external event into a
            // diary-created meeting.
            'external_platform' => $source->external_platform,
            'external_id' => $source->external_id,
            'calendar_provider' => $source->calendar_provider,
            'external_calendar_id' => $source->external_calendar_id,
            'external_event_id' => $source->external_event_id,
            'external_series_id' => $source->external_series_id,
        ]);

        return back()->with('success', 'Meeting added to your Meetings calendar.');
    }

    /**
     * A diary join is intentionally only a protected landing route; it does
     * not provision or connect to a video-call service.
     */
    public function join(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->isInternallyCreated(), 404);
        abort_unless($meeting->canBeJoinedBy($request->user()), 403);

        // A diary-created meeting can still be configured with a host's
        // meeting URL. Present its validated destination only after the
        // protected join check, so this route cannot be used as an open
        // redirect or phishing link.
        if ($externalUrl = $meeting->safe_external_url) {
            return view('meetings.join', [
                'meeting' => $meeting,
                'externalUrl' => $externalUrl,
            ]);
        }

        return view('meetings.join', ['meeting' => $meeting]);
    }

    protected function stats(Request $request): array
    {
        $meetings = $this->visibleMeetingsQuery($request)
            ->get()
            ->filter(fn (Meeting $meeting) => $meeting->isInternallyCreated());

        return [
            ['label' => 'Total', 'value' => (string) $meetings->count(), 'icon' => 'fa-solid fa-calendar-days', 'color' => 'indigo'],
            ['label' => 'Upcoming', 'value' => (string) $meetings->filter(fn (Meeting $meeting) => $meeting->status === 'scheduled' && $meeting->start_at?->greaterThanOrEqualTo(now()))->count(), 'icon' => 'fa-solid fa-calendar-week', 'color' => 'sky'],
            ['label' => 'Completed', 'value' => (string) $meetings->where('status', 'completed')->count(), 'icon' => 'fa-solid fa-circle-check', 'color' => 'emerald'],
            ['label' => 'Invited attendees', 'value' => (string) $meetings->flatMap(fn (Meeting $meeting) => $meeting->attendeeEmails())->unique()->count(), 'icon' => 'fa-solid fa-users', 'color' => 'blue'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $days = collect(range(0, 6))->map(fn ($d) => now()->addDays($d)->startOfDay());
        $visibleIds = Meeting::visibleTo($request->user())->pluck('id');
        $countsByDate = Meeting::query()
            ->whereKey($visibleIds)
            ->where('status', 'scheduled')
            ->whereBetween('start_at', [$days->first(), $days->last()->copy()->endOfDay()])
            ->selectRaw('DATE(start_at) as meeting_date, COUNT(*) as aggregate')
            ->groupBy('meeting_date')
            ->pluck('aggregate', 'meeting_date');
        $counts = $days->map(fn ($day) => (int) ($countsByDate[$day->toDateString()] ?? 0));

        if ($counts->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'bar',
            'title' => 'Meetings (next 7 days)',
            'labels' => $days->map(fn ($d) => $d->format('D, M j'))->all(),
            'datasets' => [['label' => 'Meetings', 'data' => $counts->all()]],
        ];
    }

    /**
     * Schedules one meeting across MULTIPLE date/time slots in a single
     * submission (e.g. a recurring standing meeting, or several proposed
     * times) — shared details (title, location, attendees, notes, status)
     * plus N separate start/end datetime pairs, added dynamically in the
     * UI via crud/extras/meetings-extra.blade.php. Creates one Meeting row
     * per slot; each is independently editable/deletable afterward through
     * the normal single-meeting modal.
     */
    public function storeMultiple(Request $request): RedirectResponse
    {
        $this->normalizeMeetingClockFields($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'string'],
            'status' => ['required', 'in:scheduled,completed,cancelled'],
            'notes' => ['nullable', 'string'],
            'set_reminder' => ['nullable', 'boolean'],
            'slots' => ['required', 'array', 'min:1'],

            'slots.*.start_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'slots.*.start_hour' => [
                'required',
                'integer',
                'between:1,12',
            ],
            'slots.*.start_minute' => [
                'required',
                'integer',
                'between:0,59',
            ],
            'slots.*.start_meridiem' => [
                'required',
                'in:AM,PM',
            ],

            'slots.*.end_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'slots.*.end_hour' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
            'slots.*.end_minute' => [
                'nullable',
                'integer',
                'between:0,59',
            ],
            'slots.*.end_meridiem' => [
                'nullable',
                'in:AM,PM',
            ],
        ]);

        $timezone = $request->user()?->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $created = 0;

        foreach ($data['slots'] as $index => $slot) {
            $startHour = (int) $slot['start_hour'];

            if ($slot['start_meridiem'] === 'AM') {
                $startHour = $startHour === 12 ? 0 : $startHour;
            } else {
                $startHour = $startHour === 12
                    ? 12
                    : $startHour + 12;
            }

            $startAt = Carbon::create(
                (int) substr($slot['start_date'], 0, 4),
                (int) substr($slot['start_date'], 5, 2),
                (int) substr($slot['start_date'], 8, 2),
                $startHour,
                (int) $slot['start_minute'],
                0,
                $timezone
            );

            $endAt = null;

            $hasAnyEnd =
                ! empty($slot['end_date'])
                || ! empty($slot['end_hour'])
                || isset($slot['end_minute'])
                    && $slot['end_minute'] !== ''
                || ! empty($slot['end_meridiem']);

            if ($hasAnyEnd) {
                if (
                    empty($slot['end_date'])
                    || empty($slot['end_hour'])
                    || ! isset($slot['end_minute'])
                    || $slot['end_minute'] === ''
                    || empty($slot['end_meridiem'])
                ) {
                    throw ValidationException::withMessages([
                        "slots.{$index}.end_date" =>
                            'Complete all End date/time fields or leave End blank.',
                    ]);
                }

                $endHour = (int) $slot['end_hour'];

                if ($slot['end_meridiem'] === 'AM') {
                    $endHour = $endHour === 12 ? 0 : $endHour;
                } else {
                    $endHour = $endHour === 12
                        ? 12
                        : $endHour + 12;
                }

                $endAt = Carbon::create(
                    (int) substr($slot['end_date'], 0, 4),
                    (int) substr($slot['end_date'], 5, 2),
                    (int) substr($slot['end_date'], 8, 2),
                    $endHour,
                    (int) $slot['end_minute'],
                    0,
                    $timezone
                );

                if ($endAt->lessThanOrEqualTo($startAt)) {
                    throw ValidationException::withMessages([
                        "slots.{$index}.end_hour" =>
                            'End must be after Start.',
                    ]);
                }
            }

            $meeting = Meeting::create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'start_at' => $startAt->format('Y-m-d H:i:s'),
                'end_at' => $endAt?->format('Y-m-d H:i:s'),
                'location' => $data['location'] ?? null,
                'attendees' => $data['attendees'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            if ($request->boolean('set_reminder')) {
                $this->createLinkedReminder($request, $meeting);
            }

            $this->sendInvitations(
                $meeting,
                (string) $request->user()->name
            );

            $created++;
        }

        return redirect()
            ->route('meetings.index')
            ->with(
                'success',
                "{$created} meeting(s) scheduled."
            );
    }

    /**
     * Hook from CrudController::store()/update() — sends an invitation
     * email to every address in the attendees field whenever a meeting
     * is created OR its attendee list changes on edit. Doesn't fire for
     * every OTHER field edit (e.g. just fixing a typo in the notes)
     * being resent as a "you're invited" email would be noise/confusing
     * for anyone already invited.
     */
    protected function afterSave(Request $request, $item, bool $wasCreated): void
    {
        if ($wasCreated || $item->wasChanged('attendees')) {
            $this->sendInvitations($item, $request->user()->name);
        }
    }

    private function sendInvitations(Meeting $meeting, string $organizerName): void
    {
        $emails = $meeting->attendeeEmails();

        foreach ($emails as $email) {
            Mail::to($email)->send(new MeetingInvitationMail($meeting, $organizerName));
        }
    }

    /**
     * Dedicated notes page — the same 'notes' column already editable via
     * the standard modal, but in a full-page textarea (easier to write a
     * real set of meeting notes in than a small modal field) plus
     * download/share actions.
     */
    public function notes(Request $request, int $meeting)
    {
        $item = Meeting::where('user_id', $request->user()->id)->findOrFail($meeting);

        $recordings = $item->recordings()
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'recordings_page')
            ->withQueryString();

        return view('meetings.notes', [
            'meeting' => $item,
            'recordings' => $recordings,
        ]);
    }

    public function updateNotes(Request $request, int $meeting): RedirectResponse
    {
        $item = Meeting::where('user_id', $request->user()->id)->findOrFail($meeting);

        $data = $request->validate(['notes' => ['nullable', 'string']]);
        $item->update(['notes' => $data['notes']]);

        return back()->with('success', 'Notes saved.');
    }

    public function emailNotes(Request $request, int $meeting): RedirectResponse
    {
        $item = Meeting::where('user_id', $request->user()->id)->findOrFail($meeting);
        $data = $request->validate(['emails' => ['required','string']]);
        $addresses = collect(explode(',', $data['emails']))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
        if ($addresses->isEmpty()) {
            return back()->withErrors(['emails' => 'Enter at least one valid email address.']);
        }
        $shareText = trim((string) $item->notes);
        if ($shareText === '') $shareText = 'No meeting notes have been added yet.';
        foreach ($addresses as $address) {
            Mail::to($address)->send(new MeetingNotesMail($item, $shareText, (string) $request->user()->name));
        }
        return back()->with('success', 'Meeting notes emailed to '.$addresses->count().' recipient(s).');
    }

    public function downloadNotesPdf(Request $request, int $meeting)
    {
        $item = Meeting::where('user_id', $request->user()->id)->findOrFail($meeting);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('meetings.notes-pdf', ['meeting' => $item, 'user' => $request->user()])
            ->setPaper('a4');

        return $pdf->download('meeting-notes-' . $item->start_at->format('Y-m-d') . '.pdf');
    }
}
