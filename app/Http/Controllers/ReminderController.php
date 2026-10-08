<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Services\ReminderItemLookupService;
use App\Services\ReminderConsolidationService;
use Illuminate\Http\Request;

class ReminderController extends CrudController
{
    protected string $model = Reminder::class;
    protected string $routeName = 'reminders';
    protected string $title = 'Reminder';
    protected string $icon = 'fa-solid fa-bell';
    protected string $accent = 'yellow';

    protected array $fields = [
        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Call the dentist'],
        ['name' => 'module', 'label' => 'Related Module', 'type' => 'select', 'options' => [
            'daily_planner' => 'Daily Planner', 'income' => 'Income', 'budget' => 'Budget', 'expense' => 'Expense',
            'diet' => 'Diet', 'sleep' => 'Sleep', 'health' => 'Health Checkup', 'project' => 'Project',
            'meeting' => 'Meeting', 'custom' => 'Custom',
        ]],
        ['name' => 'frequency', 'label' => 'Repeats', 'type' => 'select', 'required' => true, 'options' => [
            'once' => 'Once',
            'every_n_minutes' => 'Every N Minutes',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'annually' => 'Annually',
        ]],
        ['name' => 'interval_minutes', 'table' => 'hidden', 'label' => 'N (only used if "Every N Minutes")', 'type' => 'number'],
        ['name' => 'next_run_at', 'label' => 'Reminder Date & Time', 'type' => 'datetime-local', 'required' => true],
        ['name' => 'channel', 'label' => 'Send Via', 'type' => 'select', 'required' => true, 'options' => [
            'database' => 'In-App Only', 'mail' => 'In-App + Email',
        ]],
        ['name' => 'alarm_enabled', 'table' => 'hidden', 'label' => 'Also pop up an in-app alarm (with sound) when due', 'type' => 'checkbox', 'default' => true],
        ['name' => 'message', 'label' => 'Message', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'title' => 'required|string|max:255',
        'module' => 'nullable|string|max:255',
        'frequency' => 'required|in:once,every_n_minutes,hourly,daily,weekly,monthly,annually',
        'interval_minutes' => 'nullable|integer|min:1|max:1440',
        'next_run_at' => 'required|date',
        'channel' => 'required|in:mail,database',
        'alarm_enabled' => 'nullable|boolean',
        'message' => 'nullable|string',
        'item_ids' => 'nullable|array',
        'item_ids.*' => 'integer',
    ];

    /**
     * Injected via the constructor, not as a store()/update() method
     * parameter — those two methods override CrudController's own
     * store()/update(), and PHP requires an overriding method's
     * signature to stay compatible with its parent's. Adding an extra
     * parameter there breaks that (a real fatal error, only caught once
     * the class actually loads — php -l can't detect this).
     */
    public function __construct(
        protected ReminderItemLookupService $lookup,
        protected ReminderConsolidationService $consolidation
    ) {
    }

    /**
     * AJAX endpoint — called by crud/extras/reminders-extra.blade.php
     * whenever "Related Module" changes, to populate the "which specific
     * item(s)" multi-select with that user's actual records from the
     * chosen module.
     */
    public function index(Request $request)
    {
        $this->consolidation->syncUser($request->user());

        return parent::index($request);
    }

    public function itemsForModule(Request $request)
    {
        $module = $request->query('module', '');

        return response()->json($this->lookup->optionsFor($module, $request->user()->id));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        $itemIds = $data['item_ids'] ?? [];
        unset($data['item_ids']);

        $reminder = Reminder::create($data);

        $overlapWarning = $this->syncItemsAndCheckOverlap($reminder, $itemIds, $this->lookup);

        return redirect()->route('reminders.index')
            ->with('success', 'Reminder created.')
            ->with($overlapWarning ? ['warning' => $overlapWarning] : []);
    }

    public function update(Request $request, int $id)
    {
        $reminder = Reminder::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate($this->rules);
        $itemIds = $data['item_ids'] ?? [];
        unset($data['item_ids']);

        $reminder->update($data);

        $overlapWarning = null;
        if ($request->has('item_ids')) {
            $overlapWarning = $this->syncItemsAndCheckOverlap($reminder, $itemIds, $this->lookup);
        }

        return redirect()->route('reminders.index')
            ->with('success', 'Reminder updated.')
            ->with($overlapWarning ? ['warning' => $overlapWarning] : []);
    }

    /**
     * Replaces a reminder's linked items wholesale (same "no diffing,
     * just clear and recreate" approach as expense line items — simple
     * and safe since nothing else references a reminder_items.id), then
     * checks whether any of those items' own datetimes — plus the
     * reminder's own next_run_at — land within 30 minutes of each other,
     * flashing a warning if so. This is advisory only: it never blocks
     * saving, it just tells the user their linked items appear to clash.
     */
    private function syncItemsAndCheckOverlap(Reminder $reminder, array $itemIds, ReminderItemLookupService $lookup): ?string
    {
        $reminder->items()->delete();

        if (empty($itemIds) || ! $reminder->module) {
            return null;
        }

        $options = collect($lookup->optionsFor($reminder->module, $reminder->user_id))
            ->keyBy('id');

        $datetimes = [$reminder->next_run_at->toIso8601String()];

        foreach ($itemIds as $itemId) {
            $option = $options->get($itemId);
            if (! $option) {
                continue;
            }

            $reminder->items()->create([
                'item_id' => $itemId,
                'item_label' => $option['label'],
                'item_datetime' => $option['datetime'],
            ]);

            if ($option['datetime']) {
                $datetimes[] = $option['datetime'];
            }
        }

        if ($lookup->hasOverlap($datetimes)) {
            return "Heads up — some of this reminder's linked items (and/or its own scheduled time) fall within 30 minutes of each other. Double-check they're not clashing.";
        }

        return null;
    }

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = Reminder::query()->where('user_id', $userId);

        if (\Illuminate\Support\Facades\Schema::hasColumn('reminders', 'is_archived')) {
            $base->where(function ($query): void {
                $query->whereNull('is_archived')->orWhere('is_archived', false);
            });
        }

        return [
            [
                'label' => 'Active',
                'value' => (string) (clone $base)->where('is_active', true)->count(),
                'icon' => 'fa-solid fa-bell',
                'color' => 'emerald',
            ],
            [
                'label' => 'Daily',
                'value' => (string) (clone $base)->where('frequency', 'daily')->count(),
                'icon' => 'fa-solid fa-calendar-day',
                'color' => 'sky',
            ],
            [
                'label' => 'Due today',
                'value' => (string) (clone $base)->where('is_active', true)
                    ->whereBetween('next_run_at', [now()->startOfDay(), now()->endOfDay()])
                    ->count(),
                'icon' => 'fa-solid fa-clock',
                'color' => 'amber',
            ],
            [
                'label' => 'Total',
                'value' => (string) (clone $base)->count(),
                'icon' => 'fa-solid fa-list-check',
                'color' => 'slate',
            ],
        ];
    }

    protected function nudge(Request $request): ?string
    {
        $next = Reminder::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->where('next_run_at', '>=', now())
            ->orderBy('next_run_at')
            ->first(['title', 'next_run_at']);

        if (! $next) {
            return 'No upcoming reminders — add one so nothing important slips by.';
        }

        return 'Next up: ' . \Illuminate\Support\Str::limit((string) $next->title, 40) . ' · ' . $next->next_run_at->diffForHumans();
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;

        $byFrequency = Reminder::where('user_id', $userId)
            ->selectRaw('frequency, COUNT(*) as total')
            ->groupBy('frequency')
            ->pluck('total', 'frequency');

        if ($byFrequency->isEmpty()) {
            return null;
        }

        $labels = ['once', 'every_n_minutes', 'hourly', 'daily', 'weekly', 'monthly', 'annually'];

        return [
            'type' => 'doughnut',
            'title' => 'Reminders by Repeat Frequency',
            'labels' => array_map(fn ($l) => ucwords(str_replace('_', ' ', $l)), $labels),
            'datasets' => [[
                'data' => array_map(fn ($l) => (int) ($byFrequency[$l] ?? 0), $labels),
            ]],
        ];
    }

    /**
     * Polled by a small script in layouts/app.blade.php every ~60s while a
     * user has the app open, to show an immediate in-app "alarm" modal
     * (with sound) for anything due right now — a real-time layer on top
     * of, not a replacement for, the email/database notification sent by
     * the `reminders:send` scheduled command. This endpoint only reads;
     * it does NOT reschedule recurring reminders or mark anything as
     * fired — that stays exclusively `reminders:send`'s job, so the two
     * never fight over whose turn it is to advance next_run_at.
     *
     * Only returns reminders with alarm_enabled=true — a user can keep a
     * reminder emailing without ever seeing the in-app popup, by
     * unchecking "Also pop up an in-app alarm" on that reminder. Returns
     * nothing at all, regardless of per-reminder settings, if the user has
     * globally muted alarms (the dashboard bell icon) — a fast "mute
     * everything right now" that doesn't require editing every reminder.
     */
    public function dueNow(Request $request)
    {
        if ($request->user()->alarms_muted) {
            return response()->json([]);
        }

        $reminders = Reminder::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->where('alarm_enabled', true)
            ->where('next_run_at', '>=', now())
            ->where('next_run_at', '<=', now()->addMinutes(30))
            ->orderBy('next_run_at')
            ->limit(5)
            ->get(['id', 'title', 'message']);

        return response()->json($reminders);
    }

    /**
     * The dashboard bell icon — flips users.alarms_muted. Deliberately a
     * single boolean rather than per-reminder, so it's a one-click "quiet
     * please" that doesn't touch any reminder's own alarm_enabled setting;
     * un-muting later restores exactly what was configured before.
     */
    public function toggleMute(Request $request)
    {
        $user = $request->user();
        $user->update(['alarms_muted' => ! $user->alarms_muted]);

        return back()->with('success', $user->alarms_muted
            ? 'Reminder alarms muted. Emails and in-app notifications still work as normal.'
            : 'Reminder alarms unmuted.');
    }
}
