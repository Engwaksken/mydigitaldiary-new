<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementAudience;
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
        $announcements = Announcement::with('creator')->latest()->paginate(20);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'features' => Announcement::featureCatalogue(),
        ]);
    }

    public function create(Request $request, AnnouncementAudience $audience): View
    {
        // Set by the Users page "Send Announcement" bulk action (?recipients=1,2,3),
        // or carried back in old input after a validation error.
        $recipientIds = $this->recipientIdsFrom(
            $request->old('recipient_ids', $request->query('recipients'))
        );
        $recipients = $recipientIds === null ? null
            : User::query()->whereIn('id', $recipientIds)->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.announcements.create', [
            'types' => Announcement::TYPES,
            'features' => Announcement::featureCatalogue(),
            'recipientCounts' => $audience->counts($recipientIds),
            'recipients' => $recipients,
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
        ]);

        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
            'recipient_ids' => $this->recipientIdsFrom($request->input('recipient_ids')),
        ] + $this->validated($request));

        // Delivery (in-app, push, email) happens in a queued job, chunked,
        // so a large user base never holds up or times out this request.
        SendAnnouncementJob::dispatch($announcement);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement is being sent to '.mb_strtolower($announcement->audienceLabel()).' ('.implode(', ', $announcement->channels()).').');
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
