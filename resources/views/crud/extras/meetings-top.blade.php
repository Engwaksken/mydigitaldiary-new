{{-- Meetings status filters + My Meetings Calendar modal + external calendar authorisation. --}}
@if (!empty($nearestMeeting))
    <section class="mb-4 rounded-2xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-4 shadow-sm" aria-label="Nearest meeting">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-white text-blue-600 flex items-center justify-center shrink-0 shadow-sm">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700">{{ $nearestMeeting->start_at->lessThanOrEqualTo(now()) ? 'Happening now' : 'Next meeting' }}</p>
                    <h2 class="text-lg font-bold text-slate-800 truncate">{{ $nearestMeeting->title }}</h2>
                    <p class="text-sm text-slate-600">{{ $nearestMeeting->start_at->format('D, M j · g:i A') }}@if($nearestMeeting->end_at) – {{ $nearestMeeting->end_at->format('g:i A') }}@endif</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                @if ($nearestMeeting->canBeJoinedBy(auth()->user()))
                    <a href="{{ $nearestMeeting->diary_join_url }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Join meeting
                    </a>
                @endif
                <button type="button" onclick="pmOpenMeetingsCalendar()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Open calendar
                </button>
            </div>
        </div>
    </section>
@endif
<div class="flex flex-wrap gap-2 mb-4">
    @foreach (['' => 'All', 'scheduled' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'missed' => 'Missed'] as $value => $label)
        <a href="{{ route('meetings.index', array_merge(request()->except('status_filter', 'page'), $value ? ['status_filter' => $value] : [])) }}"
           class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors {{ (string) ($statusFilter ?? '') === $value ? 'bg-[var(--brand-1)] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<section class="mb-4 rounded-xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3" aria-label="Create a diary meeting">
    <div class="flex items-start gap-3 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-white text-emerald-600 flex items-center justify-center shrink-0 shadow-sm">
            <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
        </div>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-800">Create a meeting in My Digital Diary</p>
            <p class="text-xs text-slate-600 mt-0.5">Schedule it here and invite attendees by email without connecting an external meeting platform.</p>
        </div>
    </div>
    <button type="button" onclick="openCrudCreateModal()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 shrink-0">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Create meeting
    </button>
</section>

@php
    /*
     * Do not depend on CrudController passing meeting-connection variables.
     * Meetings is a generic CRUD page and deployed controller versions differ,
     * so resolve the current user's calendar setup directly for this view.
     */
    $pmKnownCalendarPlatforms = collect([
        'google' => 'Google Calendar',
        'microsoft' => 'Outlook / Microsoft 365',
        'zoom' => 'Zoom',
        'webex' => 'Webex',
    ]);

    try {
        $pmPlatformConfigs = \App\Models\MeetingPlatformConfig::query()->get()->keyBy('platform');
    } catch (\Throwable $e) {
        $pmPlatformConfigs = collect();
    }

    try {
        $pmCalendarConnections = \App\Models\UserMeetingConnection::query()
            ->where('user_id', auth()->id())
            ->get()
            ->keyBy('platform');
    } catch (\Throwable $e) {
        $pmCalendarConnections = collect();
    }

    $calendarUser = auth()->user();
    $calendarMeetings = \App\Models\Meeting::visibleTo($calendarUser)
        ->filter(fn ($meeting) => $meeting->start_at?->between(now()->startOfMonth(), now()->addMonths(18)->endOfMonth()))
        ->sortBy('start_at')
        ->values();

    $calendarCopiedIds = \App\Models\Meeting::query()
        ->where('user_id', $calendarUser->id)
        ->whereNotNull('copied_from_meeting_id')
        ->pluck('copied_from_meeting_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    $calendarEvents = $calendarMeetings->map(function ($meeting) use ($calendarUser, $calendarCopiedIds) {
        $source = $meeting->external_platform ?: (($meeting->user_id === $calendarUser->id) ? 'diary' : 'shared');
        $location = (string) ($meeting->location ?? '');
        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'start' => optional($meeting->start_at)->toIso8601String(),
            'end' => optional($meeting->end_at)->toIso8601String(),
            'source' => $source,
            'status' => method_exists($meeting, 'displayStatus') ? $meeting->displayStatus() : ($meeting->meeting_status ?: $meeting->status),
            'location' => $location,
            'diary_join_url' => $meeting->canBeJoinedBy($calendarUser) ? $meeting->diary_join_url : null,
            'is_owner' => (int) $meeting->user_id === (int) $calendarUser->id,
            'already_added' => in_array((int) $meeting->id, $calendarCopiedIds, true),
            'add_url' => route('meetings.add-to-calendar', $meeting->id),
        ];
    })->values();
@endphp

{{--
    The actual tab button is inserted immediately before the generic "Meetings"
    tab by the script below.  The calendar itself lives in this dialog so the
    Meetings table remains the default working area and the calendar opens large.
--}}
<dialog id="pm-meetings-calendar-modal"
        class="w-[94vw] max-w-5xl max-h-[88vh] rounded-2xl p-0 backdrop:bg-slate-950/55 shadow-2xl overflow-hidden">
    <div class="pm-card-bg bg-white flex flex-col max-h-[88vh]">
        <div class="flex items-start justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-slate-200 bg-white sticky top-0 z-20">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-[var(--brand-1)]" aria-hidden="true"></i>
                    My Meetings Calendar
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">My Digital Diary meetings and authorised external calendar events in one place.</p>
            </div>
            <button type="button" onclick="pmCloseMeetingsCalendar()"
                    class="w-9 h-9 rounded-full border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center shrink-0"
                    aria-label="Close meetings calendar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="overflow-y-auto overscroll-contain p-3 sm:p-4">
            {{-- External calendar authorisation is deliberately always visible. --}}
            <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 mb-4" aria-labelledby="pm-external-calendar-heading">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-3">
                    <div>
                        <h3 id="pm-external-calendar-heading" class="font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-link text-[var(--brand-1)]"></i>
                            Connect external calendar
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">Authorise your own calendar account. My Digital Diary never asks for your external-calendar password.</p>
                    </div>
                    @if ($pmCalendarConnections->isNotEmpty() && \Illuminate\Support\Facades\Route::has('meetings.sync'))
                        <button type="button" onclick="document.getElementById('pm-calendar-sync-range').classList.toggle('hidden')"
                                class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold bg-[var(--brand-1)] text-white hover:opacity-90">
                            <i class="fa-solid fa-rotate"></i> Sync selected dates
                        </button>
                    @endif
                </div>

                @if ($pmCalendarConnections->isNotEmpty() && \Illuminate\Support\Facades\Route::has('meetings.sync'))
                    <form id="pm-calendar-sync-range" method="POST" action="{{ route('meetings.sync') }}" class="hidden mb-3 rounded-xl border border-slate-200 bg-white p-3">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                            <div><label class="text-[11px] font-semibold text-slate-600">Provider</label><select name="provider" class="w-full rounded-lg border-slate-300 text-sm"><option value="">All connected</option>@foreach($pmCalendarConnections as $c)<option value="{{ $c->platform }}">{{ ucfirst($c->platform) }}</option>@endforeach</select></div>
                            <div><label class="text-[11px] font-semibold text-slate-600">Sync from date</label><input required type="date" name="sync_from_date" value="{{ now()->toDateString() }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                            <div><label class="text-[11px] font-semibold text-slate-600">Sync to date</label><input type="date" name="sync_to_date" value="{{ now()->addMonth()->toDateString() }}" class="w-full rounded-lg border-slate-300 text-sm"></div>
                            <div class="flex items-end"><button class="w-full px-3 py-2 rounded-lg bg-[var(--brand-1)] text-white text-xs font-semibold"><i class="fa-solid fa-rotate mr-1"></i> Sync Calendar</button></div>
                        </div>
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="include_recurring" value="1" checked> Include recurring occurrences</label>
                    </form>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($pmKnownCalendarPlatforms as $pmPlatformKey => $pmPlatformLabel)
                        @php
                            $pmConfig = $pmPlatformConfigs->get($pmPlatformKey);
                            $pmConnection = $pmCalendarConnections->get($pmPlatformKey);
                            $pmEnabled = (bool) ($pmConfig?->is_enabled ?? false);
                            $pmConfigured = $pmConfig && method_exists($pmConfig, 'isConfigured')
                                ? $pmConfig->isConfigured()
                                : (bool) (($pmConfig?->client_id ?? null) && ($pmConfig?->client_secret ?? null));
                            $pmCanAuthorize = $pmEnabled && $pmConfigured && \Illuminate\Support\Facades\Route::has('meetings.connect');
                            $pmIcon = match ($pmPlatformKey) {
                                'google' => 'fa-brands fa-google',
                                'microsoft' => 'fa-brands fa-microsoft',
                                'zoom' => 'fa-solid fa-video',
                                'webex' => 'fa-solid fa-circle-nodes',
                                default => 'fa-solid fa-calendar',
                            };
                        @endphp

                        <div class="rounded-xl border {{ $pmConnection ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-white' }} p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                        <i class="{{ $pmIcon }}"></i>
                                    </span>
                                    <span class="text-sm font-semibold text-slate-700 leading-tight">{{ $pmConfig?->name ?: $pmPlatformLabel }}</span>
                                </div>
                                @if ($pmConnection)
                                    <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-100 px-2 py-1 rounded-full whitespace-nowrap">Connected</span>
                                @endif
                            </div>

                            @if ($pmConnection)
                                <p class="text-[11px] text-slate-500 mt-2">
                                    @if ($pmConnection->connected_email)
                                        {{ $pmConnection->connected_email }}<br>
                                    @endif
                                    @if ($pmConnection->last_synced_at)
                                        Last synced {{ $pmConnection->last_synced_at->diffForHumans() }}
                                    @else
                                        Waiting for first sync
                                    @endif
                                </p>
                                @if (\Illuminate\Support\Facades\Route::has('meetings.disconnect'))
                                    <form method="POST" action="{{ route('meetings.disconnect', $pmPlatformKey) }}" class="mt-3"
                                          data-confirm="Disconnect {{ $pmPlatformLabel }}? Previously synced meetings will remain visible." data-confirm-title="Disconnect calendar?" data-confirm-text="Disconnect">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-600 hover:text-rose-700">
                                            <i class="fa-solid fa-link-slash"></i> Disconnect
                                        </button>
                                    </form>
                                @elseif (!$pmEnabled)
                                    <div class="mt-3 text-[11px] text-slate-400">Provider disabled by administrator.</div>
                                @endif
                            @elseif ($pmCanAuthorize)
                                <a href="{{ route('meetings.connect', $pmPlatformKey) }}"
                                   class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-[var(--brand-1)] text-white px-3 py-2 rounded-lg text-xs font-semibold hover:opacity-90">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    Authorize
                                </a>
                            @elseif (!$pmEnabled)
                                <div class="mt-3 rounded-lg bg-slate-100 px-3 py-2 text-[11px] text-slate-500">
                                    Not enabled by administrator.
                                </div>
                            @else
                                <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] text-amber-700">
                                    Administrator setup is incomplete. Client ID/secret must be configured first.
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm" aria-label="Meetings calendar">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                    <div>
                        <div id="pm-calendar-month-title" class="text-lg font-semibold text-slate-700"></div>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500 mt-1">
                            <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1"></span>My Digital Diary</span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-blue-400 mr-1"></span>Google</span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1"></span>Outlook/Teams</span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-violet-400 mr-1"></span>Zoom/Webex</span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-1"></span>Shared</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="pmCalendarToday()" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">Today</button>
                        <button type="button" onclick="pmCalendarMove(-1)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
                        <button type="button" onclick="pmCalendarMove(1)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                </div>

                <div class="overflow-x-auto overscroll-x-contain pb-1">
                    <div class="min-w-[720px]">
                        <div class="grid grid-cols-7 border border-slate-200 rounded-t-xl overflow-hidden bg-slate-50">
                            @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dayName)
                                <div class="px-2 py-2 text-center text-xs font-semibold text-slate-500 border-r border-slate-200 last:border-r-0">{{ $dayName }}</div>
                            @endforeach
                        </div>
                        <div id="pm-calendar-grid" class="grid grid-cols-7 border-l border-slate-200"></div>
                    </div>
                </div>

                <div id="pm-calendar-detail" class="hidden mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4"></div>
            </section>
        </div>
    </div>
</dialog>

<script>
(function () {
    const events = @json($calendarEvents);
    let cursor = new Date();
    cursor.setDate(1);

    function sourceClass(source) {
        if (source === 'google') return 'bg-blue-100 text-blue-800 border-blue-200';
        if (source === 'microsoft') return 'bg-indigo-100 text-indigo-800 border-indigo-200';
        if (source === 'zoom' || source === 'webex') return 'bg-violet-100 text-violet-800 border-violet-200';
        if (source === 'shared') return 'bg-amber-100 text-amber-800 border-amber-200';
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    }

    function sourceLabel(source) {
        return ({google:'Google Calendar', microsoft:'Outlook / Teams', zoom:'Zoom', webex:'Webex', shared:'Shared with you', diary:'My Digital Diary'})[source] || 'External Calendar';
    }

    function sameDay(iso, year, month, day) {
        if (!iso) return false;
        const d = new Date(iso);
        return d.getFullYear() === year && d.getMonth() === month && d.getDate() === day;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
        });
    }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }

    function showDetail(event) {
        const detail = document.getElementById('pm-calendar-detail');
        if (!detail) return;
        const start = event.start ? new Date(event.start).toLocaleString() : '';
        const end = event.end ? new Date(event.end).toLocaleString() : '';
        const addAction = event.is_owner || event.already_added
            ? ''
            : '<form method="POST" action="' + escapeHtml(event.add_url) + '" class="mt-3">' +
                '<input type="hidden" name="_token" value="' + escapeHtml(@json(csrf_token())) + '">' +
                '<button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-amber-600"><i class="fa-solid fa-calendar-plus"></i> Add to my calendar</button>' +
              '</form>';
        detail.innerHTML =
            '<div class="flex items-start justify-between gap-3">' +
                '<div>' +
                    '<div class="text-xs font-semibold uppercase tracking-wide text-slate-400">' + escapeHtml(sourceLabel(event.source)) + '</div>' +
                    '<h3 class="text-base font-bold text-slate-800 mt-1">' + escapeHtml(event.title) + '</h3>' +
                    '<div class="text-sm text-slate-600 mt-2"><i class="fa-regular fa-clock mr-1"></i>' + escapeHtml(start) + (end ? ' – ' + escapeHtml(end) : '') + '</div>' +
                    (event.location ? '<div class="text-sm text-slate-600 mt-1"><i class="fa-solid fa-location-dot mr-1"></i>' + escapeHtml(event.location) + '</div>' : '') +
                    '<div class="text-xs text-slate-400 mt-2">Status: ' + escapeHtml(event.status || 'scheduled') + '</div>' +
                '</div>' +
                '<div class="flex flex-col items-end gap-2">' + (event.diary_join_url ? '<a href="' + escapeHtml(event.diary_join_url) + '" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-emerald-700"><i class="fa-solid fa-arrow-right-to-bracket"></i> Join meeting</a>' : '') + addAction + '</div>' +
            '</div>';
        detail.classList.remove('hidden');
    }

    window.pmCalendarRender = function () {
        const grid = document.getElementById('pm-calendar-grid');
        const title = document.getElementById('pm-calendar-month-title');
        if (!grid || !title) return;

        const year = cursor.getFullYear();
        const month = cursor.getMonth();
        title.textContent = cursor.toLocaleDateString([], {month:'long', year:'numeric'});
        grid.innerHTML = '';

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const previousMonthDays = new Date(year, month, 0).getDate();
        const today = new Date();

        for (let cell = 0; cell < 42; cell++) {
            let cellYear = year, cellMonth = month, day;
            let muted = false;
            if (cell < firstDay) {
                day = previousMonthDays - firstDay + cell + 1;
                cellMonth = month - 1;
                if (cellMonth < 0) { cellMonth = 11; cellYear--; }
                muted = true;
            } else if (cell >= firstDay + daysInMonth) {
                day = cell - firstDay - daysInMonth + 1;
                cellMonth = month + 1;
                if (cellMonth > 11) { cellMonth = 0; cellYear++; }
                muted = true;
            } else {
                day = cell - firstDay + 1;
            }

            const cellEvents = events.filter(e => sameDay(e.start, cellYear, cellMonth, day));
            const isToday = today.getFullYear() === cellYear && today.getMonth() === cellMonth && today.getDate() === day;
            const div = document.createElement('div');
            div.className = 'min-h-[112px] border-r border-b border-slate-200 p-1.5 ' + (muted ? 'bg-slate-50/60' : 'bg-white');
            div.innerHTML = '<div class="flex justify-end"><span class="text-xs ' + (isToday ? 'bg-[var(--brand-1)] text-white rounded-full w-6 h-6 inline-flex items-center justify-center font-bold' : (muted ? 'text-slate-300' : 'text-slate-500')) + '">' + day + '</span></div>';

            cellEvents.slice(0, 3).forEach(function (event) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'block w-full text-left mt-1 px-1.5 py-1 rounded border text-[10px] leading-tight truncate ' + sourceClass(event.source);
                btn.title = event.title;
                btn.innerHTML = '<span class="font-semibold">' + escapeHtml(formatTime(event.start)) + '</span> ' + escapeHtml(event.title);
                btn.addEventListener('click', function () { showDetail(event); });
                div.appendChild(btn);
            });

            if (cellEvents.length > 3) {
                const more = document.createElement('div');
                more.className = 'text-[10px] text-slate-400 mt-1 px-1';
                more.textContent = '+' + (cellEvents.length - 3) + ' more';
                div.appendChild(more);
            }
            grid.appendChild(div);
        }
    };

    window.pmCalendarMove = function (delta) {
        cursor.setMonth(cursor.getMonth() + delta);
        pmCalendarRender();
    };

    window.pmCalendarToday = function () {
        cursor = new Date();
        cursor.setDate(1);
        pmCalendarRender();
    };

    window.pmOpenMeetingsCalendar = function () {
        const dialog = document.getElementById('pm-meetings-calendar-modal');
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', 'open');
        pmCalendarRender();
    };

    window.pmCloseMeetingsCalendar = function () {
        const dialog = document.getElementById('pm-meetings-calendar-modal');
        if (!dialog) return;
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
    };

    function addMeetingsCalendarTab() {
        const meetingsTab = document.getElementById('pm-crud-tab-table');
        if (!meetingsTab || document.getElementById('pm-meetings-calendar-tab')) return;

        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'pm-meetings-calendar-tab';
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-selected', 'false');
        button.className = 'flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300';
        button.innerHTML = '<i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span>My Meetings Calendar</span>';
        button.addEventListener('click', pmOpenMeetingsCalendar);
        button.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                pmOpenMeetingsCalendar();
            }
        });
        meetingsTab.parentNode.insertBefore(button, meetingsTab);

        // Meetings now has the dedicated combined calendar modal, so avoid a
        // second generic Calendar tab that only knows the local CRUD date.
        const genericCalendarTab = document.getElementById('pm-crud-tab-calendar');
        const genericCalendarPanel = document.getElementById('pm-crud-panel-calendar');
        if (genericCalendarTab) genericCalendarTab.remove();
        if (genericCalendarPanel) genericCalendarPanel.remove();
    }

    document.addEventListener('DOMContentLoaded', function () {
        addMeetingsCalendarTab();
        pmCalendarRender();

        const dialog = document.getElementById('pm-meetings-calendar-modal');
        if (dialog) {
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) pmCloseMeetingsCalendar();
            });
        }
    });
})();
</script>
