<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\DispatchScheduledAnnouncements;
use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\User;
use App\Services\AdminUserSelection;
use App\Services\AnnouncementAudience;
use App\Services\SendTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::with('creator')
            ->withCount('deliveries')
            // Upcoming scheduled sends first, then everything else newest first.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Announcement::STATUS_SCHEDULED])
            ->orderBy('scheduled_for')
            ->latest('id')
            ->paginate(20);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'features' => Announcement::featureCatalogue(),
        ]);
    }

    public function create(Request $request, AnnouncementAudience $audience): View|RedirectResponse
    {
        // Set by the Users page "Send Announcement" bulk action: a few picked
        // users (?recipients=1,2,3) or everyone matching the filters
        // (?selection=<token>), or carried back in old input after an error.
        $selectionToken = (string) $request->old('selection', $request->query('selection', ''));
        $selection = $selectionToken !== '' ? AdminUserSelection::get($selectionToken) : null;

        if ($selectionToken !== '' && $selection === null) {
            return redirect()->route('admin.users.index')->withErrors([
                'bulk' => 'That selection has expired. Select the users again.',
            ]);
        }

        $recipientIds = $selection !== null
            ? $selection['ids']
            : $this->recipientIdsFrom($request->old('recipient_ids', $request->query('recipients')));
        $recipients = $selection === null && $recipientIds !== null
            ? User::query()->whereIn('id', $recipientIds)->orderBy('name')->get(['id', 'name', 'email'])
            : null;

        $type = (string) $request->old('type', '');
        $featureKey = (string) $request->old('feature_key', '');
        $skip = (bool) $request->old('skip_already_told', true);
        $skipFeature = $type === Announcement::TYPE_NEW_FEATURE && $skip && $featureKey !== '' ? $featureKey : null;

        return view('admin.announcements.create', [
            'types' => Announcement::TYPES,
            'features' => Announcement::featureCatalogue(),
            'recipientCounts' => $audience->counts($recipientIds, $skipFeature),
            'alreadyToldNotice' => $type === Announcement::TYPE_NEW_FEATURE
                ? $audience->alreadyToldNotice($audience->alreadyTold($recipientIds, $featureKey ?: null)) : null,
            'recipients' => $recipients,
            'selectionToken' => $selection !== null ? $selectionToken : null,
            'selectionCount' => $selection !== null ? count($selection['ids']) : null,
            'selectionDescription' => $selection['description'] ?? null,
            'timezone' => SendTime::timezone(),
            'minScheduledFor' => SendTime::minInput(),
        ]);
    }

    /**
     * Recipient counts for the Delivery tab as the type, feature or "skip
     * users who were already told" choice changes.
     */
    public function audience(Request $request, AnnouncementAudience $audience): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Announcement::TYPES))],
            'feature_key' => ['nullable', 'string', Rule::in(array_keys(Announcement::featureCatalogue()))],
            'skip_already_told' => ['nullable', 'boolean'],
            'selection' => ['nullable', 'string', 'max:64'],
            'recipients' => ['nullable', 'string'],
        ]);

        $recipientIds = filled($data['selection'] ?? null)
            ? (AdminUserSelection::get($data['selection'])['ids'] ?? [])
            : $this->recipientIdsFrom($data['recipients'] ?? null);

        $isFeature = ($data['type'] ?? null) === Announcement::TYPE_NEW_FEATURE && filled($data['feature_key'] ?? null);
        $skip = $isFeature && $request->boolean('skip_already_told', true);
        $told = $isFeature ? $audience->alreadyTold($recipientIds, $data['feature_key']) : ['count' => 0, 'last_sent_at' => null];

        return response()->json($audience->counts($recipientIds, $skip ? $data['feature_key'] : null) + [
            'already_told' => $told['count'],
            'notice' => $audience->alreadyToldNotice($told),
            'skipping' => $skip && $told['count'] > 0,
        ]);
    }

    /**
     * Existing user IDs from "1,2,3" or [1, 2, 3]; null when none were given,
     * meaning the announcement goes to everyone.
     *
     * @return list<int>|null
     */
    private function recipientIdsFrom(mixed $value): ?array
    {
        $ids = collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        return User::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all() ?: null;
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'selection' => ['nullable', 'string', 'max:64'],
        ] + SendTime::rules());

        if (filled($request->input('selection'))) {
            $selection = AdminUserSelection::get($request->input('selection'));
            if ($selection === null) {
                return back()->withInput()->withErrors([
                    'selection' => 'That selection of users has expired. Go back to Users and select them again.',
                ]);
            }
            $recipientIds = $this->recipientIdsFrom($selection['ids']) ?? [];
        } else {
            $recipientIds = $this->recipientIdsFrom($request->input('recipient_ids'));
        }

        $fields = $this->validated($request);
        $scheduledFor = SendTime::fromRequest($request);

        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
            'recipient_ids' => $recipientIds,
            'status' => $scheduledFor ? Announcement::STATUS_SCHEDULED : Announcement::STATUS_SENDING,
            'scheduled_for' => $scheduledFor,
            'skip_already_told' => $fields['type'] === Announcement::TYPE_NEW_FEATURE
                && filled($fields['feature_key'])
                && ($request->has('skip_already_told') ? $request->boolean('skip_already_told') : true),
        ] + $fields);

        if ($scheduledFor) {
            return redirect()->route('admin.announcements.index')
                ->with('success', 'Announcement scheduled for '.SendTime::format($scheduledFor).' ('.SendTime::timezone().') to '
                    .mb_strtolower($announcement->audienceLabel()).' ('.implode(', ', $announcement->channels()).').');
        }

        // Delivery (in-app, push, email) happens in a queued job, chunked,
        // so a large user base never holds up or times out this request.
        SendAnnouncementJob::dispatch($announcement);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement is being sent to '.mb_strtolower($announcement->audienceLabel()).' ('.implode(', ', $announcement->channels()).').');
    }

    /** Counts per channel, the audience and schedule, and who it reached. */
    public function show(Request $request, Announcement $announcement): View
    {
        $announcement->loadMissing('creator');
        $search = trim((string) $request->query('search', ''));

        $deliveries = AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->id);

        $stats = [
            'recipients' => (clone $deliveries)->count(),
            'in_app' => (clone $deliveries)->whereNotNull('in_app_at')->count(),
            'push' => (clone $deliveries)->whereNotNull('push_at')->count(),
            'email' => (clone $deliveries)->whereNotNull('email_at')->count(),
            'failed' => (clone $deliveries)->whereNotNull('failed_channels')->count(),
        ];

        $list = (clone $deliveries)
            ->with('user:id,name,email')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.announcements.show', [
            'announcement' => $announcement,
            'stats' => $stats,
            'deliveries' => $list,
            'search' => $search,
            'timezone' => SendTime::timezone(),
        ]);
    }

    /** Cancels a scheduled announcement before it sends. */
    public function cancel(Announcement $announcement): RedirectResponse
    {
        $cancelled = Announcement::query()->whereKey($announcement->id)
            ->where('status', Announcement::STATUS_SCHEDULED)
            ->update(['status' => Announcement::STATUS_CANCELLED, 'updated_at' => now()]) === 1;

        return back()->with(
            $cancelled ? 'success' : 'error',
            $cancelled ? 'Scheduled announcement cancelled. Nothing was sent.' : 'Only a scheduled announcement can be cancelled; this one has already started sending.'
        );
    }

    /** Sends a scheduled announcement straight away. */
    public function sendNow(Announcement $announcement): RedirectResponse
    {
        if (! DispatchScheduledAnnouncements::claim($announcement->id)) {
            return back()->with('error', 'Only a scheduled announcement can be sent now; this one has already started sending or was cancelled.');
        }

        SendAnnouncementJob::dispatch($announcement->fresh());

        return back()->with('success', 'Announcement is being sent now.');
    }

    /**
     * Renders the email exactly as it will be sent, addressed to the admin,
     * for the form's Preview tab. Nothing is stored or sent.
     */
    public function preview(Request $request): Response
    {
        $announcement = new Announcement($this->validated($request, forPreview: true));
        $announcement->id = 0;

        $html = (new AnnouncementMail($request->user(), $announcement))->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = Announcement::whereIn('id', $data['ids'])->delete();

        return back()->with('success', $deleted === 1 ? '1 announcement deleted.' : "{$deleted} announcements deleted.");
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    /** @return array{title:string,body:string,type:string,feature_key:?string,link_url:?string,cta_label:?string,send_push:bool,send_email:bool} */
    private function validated(Request $request, bool $forPreview = false): array
    {
        $required = $forPreview ? 'nullable' : 'required';

        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'body' => [$required, 'string', 'max:2000'],
            'type' => ['nullable', Rule::in(array_keys(Announcement::TYPES))],
            'feature_key' => ['nullable', 'string', Rule::in(array_keys(Announcement::featureCatalogue()))],
            'link_url' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! self::isAllowedLink((string) $value)) {
                    $fail('The link must be a path such as /meetings or a full https:// address.');
                }
            }],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'send_push' => ['nullable', 'boolean'],
            'send_email' => ['nullable', 'boolean'],
            'skip_already_told' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'type' => $data['type'] ?? Announcement::TYPE_GENERAL,
            'feature_key' => $data['feature_key'] ?? null,
            'link_url' => filled($data['link_url'] ?? null) ? trim($data['link_url']) : null,
            'cta_label' => filled($data['cta_label'] ?? null) ? trim($data['cta_label']) : null,
            // The form always posts send_push (hidden 0 + checkbox 1); when
            // absent it follows the column default of on.
            'send_push' => $request->has('send_push') ? $request->boolean('send_push') : true,
            'send_email' => $request->boolean('send_email'),
        ];
    }

    /** A site-relative path ("/meetings", not "//evil.test") or an https URL. */
    public static function isAllowedLink(string $value): bool
    {
        $value = trim($value);

        if (Str::startsWith($value, '/')) {
            return ! Str::startsWith($value, ['//', '/\\']) && ! preg_match('/\s/', $value);
        }

        return Str::startsWith(strtolower($value), 'https://')
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
