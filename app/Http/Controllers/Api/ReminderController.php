<?php

namespace App\Http\Controllers\Api;

use App\Models\Reminder;
use App\Services\ReminderItemLookupService;
use App\Services\ReminderConsolidationService;
use App\Services\ReminderCopyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends ApiCrudController
{
    protected string $model = Reminder::class;

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

    // Injected via the constructor for the same PHP signature-
    // compatibility reason the web ReminderController documents on its
    // own constructor — store()/update() below override
    // ApiCrudController's versions, and PHP requires an overriding
    // method's signature to stay compatible with its parent's.
    public function __construct(
        protected ReminderItemLookupService $lookup,
        protected ReminderConsolidationService $consolidation
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->overview($request);
    }

    public function overview(Request $request): JsonResponse
    {
        try {
            $this->consolidation->syncUser($request->user());
        } catch (\Throwable $exception) {
            report($exception);
        }

        $query = Reminder::query()
            ->where('user_id', $request->user()->id);

        if (\Illuminate\Support\Facades\Schema::hasColumn('reminders', 'is_archived')) {
            $query->where(function ($builder): void {
                $builder->whereNull('is_archived')->orWhere('is_archived', false);
            });
        }

        $reminders = (clone $query)
            ->orderByDesc('is_active')
            ->orderBy('next_run_at')
            ->orderByDesc('id')
            ->get();

        $stats = [
            'active' => (clone $query)->where('is_active', true)->count(),
            'daily' => (clone $query)->where('frequency', 'daily')->count(),
            'weekly' => (clone $query)->where('frequency', 'weekly')->count(),
            'total' => (clone $query)->count(),
        ];

        return response()->json([
            'data' => $reminders,
            'stats' => $stats,
        ]);
    }

    /**
     * Mobile equivalent of the web app's AJAX itemsForModule() — the
     * "which specific item(s)" picker's data source, fetched fresh
     * whenever the Related Module selection changes.
     */
    public function itemsForModule(Request $request): JsonResponse
    {
        $module = $request->query('module', '');

        return response()->json($this->lookup->optionsFor($module, $request->user()->id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        $itemIds = $data['item_ids'] ?? [];
        unset($data['item_ids']);

        $reminder = Reminder::create($data);

        $overlapWarning = $this->syncItemsAndCheckOverlap($reminder, $itemIds);

        return response()->json($reminder, 201)->header('X-Overlap-Warning', $overlapWarning ?? '');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $reminder = Reminder::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate($this->rules);
        $itemIds = $data['item_ids'] ?? [];
        unset($data['item_ids']);

        $reminder->update($data);

        $overlapWarning = null;
        if ($request->has('item_ids')) {
            $overlapWarning = $this->syncItemsAndCheckOverlap($reminder, $itemIds);
        }

        return response()->json($reminder)->header('X-Overlap-Warning', $overlapWarning ?? '');
    }

    /**
     * Same logic as the web app's private method of the same name —
     * clears and recreates the reminder's linked items, then checks
     * for a 30-minute-window clash between them and the reminder's
     * own next_run_at. Advisory only, returned as a response header
     * here rather than a flashed session message, since mobile has no
     * session to flash into.
     */
    private function syncItemsAndCheckOverlap(Reminder $reminder, array $itemIds): ?string
    {
        $reminder->items()->delete();

        if (empty($itemIds) || ! $reminder->module) {
            return null;
        }

        $options = collect($this->lookup->optionsFor($reminder->module, $reminder->user_id))->keyBy('id');
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

        if ($this->lookup->hasOverlap($datetimes)) {
            return "Heads up — some of this reminder's linked items (and/or its own scheduled time) fall within 30 minutes of each other. Double-check they're not clashing.";
        }

        return null;
    }

    /**
     * Mobile equivalent of the web app's polling endpoint behind its
     * alarm popup — same query, same alarms_muted short-circuit. The
     * mobile app is expected to poll this periodically while active
     * and show its own popup/sound, mirroring the web behavior.
     */
    public function dueNow(Request $request, ReminderCopyService $copy): JsonResponse
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
            ->get(['id', 'title', 'message', 'next_run_at', 'source_type', 'source_id', 'source_signature', 'module']);

        return response()->json($reminders->map(function (Reminder $reminder) use ($copy, $request): array {
            $message = $copy->forDelivery($reminder, $request->user());

            return [
                'id' => $reminder->id,
                'title' => $message['title'],
                'message' => $message['message'],
                'next_run_at' => $reminder->next_run_at?->toIso8601String(),
                'source_type' => $reminder->source_type,
                'source_id' => $reminder->source_id,
                'reminder_stage' => $message['stage'],
                'link' => ReminderCopyService::linkFor($reminder),
            ];
        }));
    }

    /**
     * Same one-click global mute as the web dashboard's bell icon —
     * flips users.alarms_muted, doesn't touch any individual
     * reminder's own alarm_enabled setting.
     */
    public function toggleMute(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['alarms_muted' => ! $user->alarms_muted]);

        return response()->json(['alarms_muted' => $user->alarms_muted]);
    }
}
