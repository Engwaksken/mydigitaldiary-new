<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAnnouncementJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
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

    public function create(AnnouncementAudience $audience): View
    {
        return view('admin.announcements.create', [
            'types' => Announcement::TYPES,
            'features' => Announcement::featureCatalogue(),
            'recipientCounts' => $audience->counts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
        ] + $this->validated($request));

        // Delivery (in-app, push, email) happens in a queued job, chunked,
        // so a large user base never holds up or times out this request.
        SendAnnouncementJob::dispatch($announcement);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement is being sent ('.implode(', ', $announcement->channels()).').');
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
