<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\SocialMediaPost;
use App\Services\Ai\FormAssistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class SocialMediaPlannerController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'period' => ['nullable', 'in:today,week,month,three_months,range,all'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $period = (string) ($validated['period'] ?? 'all');
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 10);

        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $today = Carbon::now($timezone)->startOfDay();

        $query = SocialMediaPost::query()
            ->where('user_id', $request->user()->id);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('caption', 'like', '%'.$search.'%')
                    ->orWhere('hashtags', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%');
            });
        }

        switch ($period) {
            case 'today':
                $query->whereBetween('scheduled_at', [
                    $today->copy()->startOfDay()->timezone('UTC'),
                    $today->copy()->endOfDay()->timezone('UTC'),
                ]);
                break;

            case 'week':
                $query->whereBetween('scheduled_at', [
                    $today->copy()->subDays(6)->startOfDay()->timezone('UTC'),
                    $today->copy()->endOfDay()->timezone('UTC'),
                ]);
                break;

            case 'month':
                $query->whereBetween('scheduled_at', [
                    $today->copy()->startOfMonth()->timezone('UTC'),
                    $today->copy()->endOfMonth()->endOfDay()->timezone('UTC'),
                ]);
                break;

            case 'three_months':
                $query->whereBetween('scheduled_at', [
                    $today->copy()->subMonths(3)->startOfDay()->timezone('UTC'),
                    $today->copy()->endOfDay()->timezone('UTC'),
                ]);
                break;

            case 'range':
                if ($from && $to) {
                    $query->whereBetween('scheduled_at', [
                        Carbon::parse($from, $timezone)->startOfDay()->timezone('UTC'),
                        Carbon::parse($to, $timezone)->endOfDay()->timezone('UTC'),
                    ]);
                }
                break;

            case 'all':
            default:
                break;
        }

        $posts = $query
            ->orderByRaw('scheduled_at IS NULL')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $posts->items(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
            ]);
        }

        return view('social-media-planner.index', compact(
            'posts',
            'search',
            'period',
            'from',
            'to',
            'perPage'
        ));
    }

    public function store(Request $request, FormAssistService $formAssist)
    {
        /*
         * AI Generate intentionally reuses this existing endpoint instead of
         * requiring a separate ai.form-assist route. That keeps the Social
         * Media Planner page safe even when older production route files have
         * not yet registered the global AI helper route.
         */
        if ($request->boolean('_ai_generate')) {
            if (\App\Services\Ai\AiRateLimit::exceeded('social-draft', $request->user()->id, 15)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Too many AI drafts in a short time. Please wait a minute and try again.',
                ], 429);
            }

            $validated = $request->validate([
                'title' => ['required', 'string', 'max:500'],
                'platforms' => ['nullable', 'array'],
                'platforms.*' => ['string', 'max:50'],
            ]);

            try {
                $fields = $formAssist->generate(
                    'social-media-planner',
                    trim((string) $validated['title']),
                    [
                        'platforms' => array_values($validated['platforms'] ?? []),
                    ]
                );

                return response()->json([
                    'ok' => true,
                    'data' => $fields,
                    'message' => 'AI draft generated. Review and edit it before saving.',
                ]);
            } catch (Throwable $e) {
                report($e);

                return response()->json([
                    'ok' => false,
                    'message' => 'AI could not prepare the post draft right now. Your current form values were kept.',
                ], 422);
            }
        }

        $data = $this->validated($request);
        $data = $this->normaliseSchedule($request, $data);
        $data = $this->prepareAttachments($request, $data);

        $post = SocialMediaPost::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => $request->filled('scheduled_at') ? 'scheduled' : 'draft',
            'approval_status' => 'approved',
            'posting_attempts' => 0,
            'next_posting_attempt_at' => null,
            'last_error' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Social media post saved.',
                'data' => $post->fresh(),
            ], 201);
        }

        return back()->with('success', 'Social media post saved.');
    }

    public function update(Request $request, SocialMediaPost $socialMediaPost)
    {
        abort_unless($socialMediaPost->user_id === $request->user()->id, 403);

        $data = $this->validated($request);
        $data = $this->normaliseSchedule($request, $data);
        $data = $this->prepareAttachments($request, $data, $socialMediaPost);
        $data['status'] = !empty($data['scheduled_at']) ? 'scheduled' : 'draft';
        $data['posting_attempts'] = 0;
        $data['next_posting_attempt_at'] = null;
        $data['last_error'] = null;
        $data['reminder_sent_at'] = null;
        $data['posting_started_at'] = null;
        $data['posting_notification_sent_at'] = null;

        $socialMediaPost->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Social media post updated.',
                'data' => $socialMediaPost->fresh(),
            ]);
        }

        return back()->with('success', 'Social media post updated.');
    }

    public function destroy(Request $request, SocialMediaPost $socialMediaPost)
    {
        abort_unless($socialMediaPost->user_id === $request->user()->id, 403);
        $this->deleteStoredMedia($socialMediaPost->media_path);
        $socialMediaPost->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Social media post deleted.',
            ]);
        }

        return back()->with('success', 'Social media post deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $posts = SocialMediaPost::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($posts as $post) {
            $this->deleteStoredMedia($post->media_path);
        }

        $deleted = SocialMediaPost::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->delete();

        return back()->with('success', "{$deleted} post(s) deleted.");
    }

    public function postNow(Request $request, SocialMediaPost $socialMediaPost)
    {
        abort_unless($socialMediaPost->user_id === $request->user()->id, 403);

        if ($socialMediaPost->status !== 'published') {
            $socialMediaPost->forceFill(['status' => 'ready_to_share'])->save();
        }

        return response()->json([
            'data' => [
                'id' => $socialMediaPost->id,
                'title' => $socialMediaPost->title,
                'text' => $socialMediaPost->shareText(),
                'media_url' => $socialMediaPost->publicMediaUrl(),
                'link_url' => $socialMediaPost->attachedLink(),
                'platforms' => $socialMediaPost->platforms ?? [],
                'status' => $socialMediaPost->status,
                'full_post' => $socialMediaPost->fullPostPayload(),
            ],
        ]);
    }

    public function markPublished(Request $request, SocialMediaPost $socialMediaPost)
    {
        abort_unless($socialMediaPost->user_id === $request->user()->id, 403);

        $socialMediaPost->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'last_error' => null,
        ])->save();

        return back()->with('success', 'Post marked as published.');
    }

    public function readyToShare(Request $request)
    {
        $posts = SocialMediaPost::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->where('status', 'ready_to_share')
                    ->orWhere(function ($due) {
                        $due->where('status', 'scheduled')
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', now());
                    });
            })
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $posts->map(function (SocialMediaPost $post): array {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'caption' => $post->caption,
                    'hashtags' => $post->hashtags,
                    'platforms' => $post->platforms ?? [],
                    'status' => $post->status,
                    'scheduled_at' => $post->scheduled_at,
                    'full_post' => $post->fullPostPayload(),
                ];
            })->values(),
        ]);
    }

    private function normaliseSchedule(Request $request, array $data): array
    {
        $raw = trim((string) ($data['scheduled_at'] ?? ''));

        if ($raw === '') {
            $data['scheduled_at'] = null;
            return $data;
        }

        $appTimezone = (string) config('app.timezone', 'UTC');
        $userTimezone = trim((string) ($request->user()->timezone ?? '')) ?: 'Africa/Kampala';

        try {
            // Mobile sends an ISO value with Z/offset. Respect that absolute
            // instant. Browser datetime-local has no offset, so interpret it
            // in the user's saved timezone before converting to app storage.
            if (preg_match('/(?:Z|[+\\-]\\d{2}:?\\d{2})$/i', $raw)) {
                $scheduled = Carbon::parse($raw);
            } else {
                $scheduled = Carbon::parse($raw, $userTimezone);
            }

            $data['scheduled_at'] = $scheduled->setTimezone($appTimezone);
        } catch (\Throwable) {
            // Validation has already verified that this is date-like. Leave
            // the original value for Laravel to handle if parsing is unusual.
        }

        return $data;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'caption' => ['nullable', 'string', 'max:10000'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
            'content_objective' => ['nullable', 'string', 'max:300'],
            'media_idea' => ['nullable', 'string', 'max:1500'],
            'call_to_action' => ['nullable', 'string', 'max:600'],
            'attachment' => [
                'nullable',
                'file',
                'max:51200',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm',
            ],
            'link_url' => ['nullable', 'url:http,https', 'max:2000'],
            'remove_media' => ['nullable', 'boolean'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => [Rule::in([
                'instagram', 'facebook', 'x', 'tiktok', 'linkedin',
                'whatsapp_status', 'whatsapp_channel',
            ])],
            'scheduled_at' => ['nullable', 'date'],
            'posting_mode' => ['nullable', Rule::in(['manual', 'automatic'])],
        ]);
    }

    private function prepareAttachments(
        Request $request,
        array $data,
        ?SocialMediaPost $existing = null
    ): array {
        unset(
            $data['attachment'],
            $data['remove_media'],
            $data['link_url'],
            $data['content_objective'],
            $data['media_idea'],
            $data['call_to_action']
        );

        $platformContent = (array) ($existing?->platform_content ?? []);
        $link = trim((string) $request->input('link_url', ''));

        foreach (['content_objective', 'media_idea', 'call_to_action'] as $field) {
            $value = trim((string) $request->input($field, ''));

            if ($value !== '') {
                $platformContent[$field] = $value;
            } else {
                unset($platformContent[$field]);
            }
        }

        if ($link !== '') {
            $platformContent['link_url'] = $link;
        } else {
            unset($platformContent['link_url']);
        }

        $data['platform_content'] = $platformContent ?: null;
        $data['posting_mode'] = $request->input('posting_mode', 'manual');

        $mediaPath = $existing?->media_path;
        $mediaType = $existing?->media_type ?: 'text';

        if ($request->boolean('remove_media')) {
            $this->deleteStoredMedia($mediaPath);
            $mediaPath = null;
            $mediaType = 'text';
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            if (! $file || ! $file->isValid()) {
                abort(422, 'The selected image/video could not be uploaded.');
            }

            $newPath = $file->store(
                'social-media-posts/' . $request->user()->id,
                'public'
            );

            if (! $newPath) {
                abort(500, 'The image/video could not be saved.');
            }

            $this->deleteStoredMedia($mediaPath);

            $mediaPath = $newPath;
            $mime = strtolower((string) $file->getMimeType());
            $mediaType = str_starts_with($mime, 'video/')
                ? 'video'
                : 'image';
        }

        $data['media_path'] = $mediaPath;
        $data['media_type'] = $mediaPath ? $mediaType : 'text';

        return $data;
    }

    private function deleteStoredMedia(?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
