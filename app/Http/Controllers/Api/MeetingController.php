<?php

namespace App\Http\Controllers\Api;

use App\Models\Meeting;
use App\Services\RecurringMeetingService;
use App\Services\ExternalCalendarSyncService;
use App\Services\DailyInsightService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Mobile equivalent of the web app's MeetingController overrides —
 * same recurrence support (RecurringMeetingService generates upcoming
 * instances immediately after a recurring meeting is created).
 * recurrence_days_of_week is a genuine JSON array here rather than
 * web's comma-separated text field, since mobile's UI can send one
 * natively without needing the same workaround the shared 20-module
 * form renderer required on web.
 */
class MeetingController extends ApiCrudController
{
    protected string $model = Meeting::class;

    protected array $rules = [
        'title' => 'required|string|max:255',
        'start_at' => 'required|date',
        'end_at' => 'nullable|date|after:start_at',
        'location' => 'nullable|string|max:255',
        'attendees' => 'nullable|string',
        'status' => 'required|in:scheduled,completed,cancelled',
        'notes' => 'nullable|string',
        'recurrence_frequency' => 'nullable|in:daily,weekly,monthly',
        'recurrence_days_of_week' => 'nullable|array',
        'recurrence_days_of_week.*' => 'integer|min:1|max:7',
        'recurrence_ends_at' => 'nullable|date|after:start_at',
    ];


    /**
     * Native mobile Meetings pagination. Keeping this override meeting-specific
     * lets the app request compact pages without changing pagination defaults
     * for every other mobile module.
     */
       public function index(Request $request): JsonResponse
    {
        $perPage = max(5, min(50, (int) $request->integer('per_page', 10)));

        $query = Meeting::where('user_id', $request->user()->id)
            ->where('is_archived', false);

        // Search across title, location, attendees, notes
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('attendees', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Status filter
        if (in_array($request->query('status'), ['scheduled', 'completed', 'cancelled'], true)) {
            $query->where('status', $request->query('status'));
        }

        $items = $query->orderBy('start_at')->paginate($perPage)->withQueryString();

        return response()->json($items);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $meeting = Meeting::where('user_id', $request->user()->id)->findOrFail($id);
        $next = $meeting->status === 'completed' ? 'scheduled' : 'completed';
        $meeting->update(['status' => $next, 'meeting_status' => $next]);

        return response()->json($meeting->fresh());
    }

    public function syncCalendar(Request $request, ExternalCalendarSyncService $service): JsonResponse
    {
        $data=$request->validate([
            'provider'=>['nullable','in:google,microsoft,zoom,webex'],
            'sync_from_date'=>['required','date'],'sync_to_date'=>['nullable','date','after_or_equal:sync_from_date'],
            'include_recurring'=>['nullable','boolean'],
        ]);
        $from=Carbon::parse($data['sync_from_date']); $to=!empty($data['sync_to_date'])?Carbon::parse($data['sync_to_date']):$from->copy()->addMonth();
        $result=$service->syncUser((int)$request->user()->id,$from,$to,$data['provider'] ?? null,(bool)($data['include_recurring'] ?? true));
        app(DailyInsightService::class)->invalidateFor($request->user());
        return response()->json(['success'=>true,'data'=>$result+['from'=>$from->toDateString(),'to'=>$to->toDateString(),'synced_at'=>now()->toIso8601String()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        if (empty($data['recurrence_frequency'])) {
            $data['recurrence_days_of_week'] = null;
            $data['recurrence_ends_at'] = null;
        }

        $meeting = Meeting::create($data);

        if ($meeting->isRecurring()) {
            app(RecurringMeetingService::class)->generateUpcoming($meeting);
        }

        return response()->json($meeting, 201);
    }

    /**
     * Same reasoning as web's update() override: deliberately doesn't
     * regenerate already-created future instances just because the
     * rule changed — tomorrow's scheduled top-up picks up the new
     * rule naturally for instances it creates from then on.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $meeting = Meeting::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate($this->rules);
        if (empty($data['recurrence_frequency'])) {
            $data['recurrence_days_of_week'] = null;
            $data['recurrence_ends_at'] = null;
        }

        $meeting->update($data);

        return response()->json($meeting);
    }

    /**
     * Do not use ApiCrudController's builder delete here. Meeting deletion
     * events preserve external markers on copies before the source FK clears.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $meetings = Meeting::where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get();

        $meetings->each->delete();

        return response()->json([
            'message' => "{$meetings->count()} item(s) deleted.",
            'deleted' => $meetings->count(),
        ]);
    }
}
