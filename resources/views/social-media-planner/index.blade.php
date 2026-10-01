@extends('layouts.app')

@section('title', 'Social Media Planner')


@push('styles')
<style>
    /* =========================================================
       SOCIAL MEDIA PLANNER - RESPONSIVE TABLE
       Keep table columns horizontal on phones.
       The table scrolls left/right instead of squeezing words
       into vertical letters.
    ========================================================= */
    #social-media-planner {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    #social-media-planner .smp-table-card {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    #social-media-planner .smp-table-scroll {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: thin;
    }

    #social-media-planner .smp-table {
        width: 100% !important;
        min-width: 940px !important;
        border-collapse: collapse !important;
        border-spacing: 0 !important;
        table-layout: auto !important;
    }

    /*
     * Override any global mobile CSS that changes table elements
     * into block/grid/card layouts.
     */
    #social-media-planner .smp-table thead {
        display: table-header-group !important;
    }

    #social-media-planner .smp-table tbody {
        display: table-row-group !important;
    }

    #social-media-planner .smp-table tr {
        display: table-row !important;
        width: auto !important;
    }

    #social-media-planner .smp-table th,
    #social-media-planner .smp-table td {
        display: table-cell !important;
        float: none !important;
        width: auto !important;
        max-width: none !important;
        box-sizing: border-box;
        vertical-align: top;
        word-break: normal !important;
        overflow-wrap: normal !important;
        hyphens: none !important;
    }

    /* Checkbox */
    #social-media-planner .smp-table th:nth-child(1),
    #social-media-planner .smp-table td:nth-child(1) {
        width: 52px !important;
        min-width: 52px !important;
        text-align: center;
    }

    /* Post */
    #social-media-planner .smp-table th:nth-child(2),
    #social-media-planner .smp-table td:nth-child(2) {
        width: 300px !important;
        min-width: 300px !important;
    }

    /* Platforms */
    #social-media-planner .smp-table th:nth-child(3),
    #social-media-planner .smp-table td:nth-child(3) {
        width: 190px !important;
        min-width: 190px !important;
    }

    /* Schedule */
    #social-media-planner .smp-table th:nth-child(4),
    #social-media-planner .smp-table td:nth-child(4) {
        width: 180px !important;
        min-width: 180px !important;
        white-space: nowrap !important;
    }

    /* Status */
    #social-media-planner .smp-table th:nth-child(5),
    #social-media-planner .smp-table td:nth-child(5) {
        width: 145px !important;
        min-width: 145px !important;
        white-space: nowrap !important;
    }

    /* Actions */
    #social-media-planner .smp-table th:nth-child(6),
    #social-media-planner .smp-table td:nth-child(6) {
        width: 210px !important;
        min-width: 210px !important;
    }

    #social-media-planner .smp-table th {
        white-space: nowrap !important;
        line-height: 1.25;
    }

    #social-media-planner .smp-table td {
        line-height: 1.45;
    }

    #social-media-planner .smp-post-title,
    #social-media-planner .smp-post-caption {
        word-break: normal !important;
        overflow-wrap: anywhere !important;
        white-space: normal !important;
    }

    #social-media-planner .smp-platforms {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center;
        gap: .25rem;
        min-width: 0;
    }

    #social-media-planner .smp-platform-badge {
        display: inline-flex !important;
        align-items: center;
        width: auto !important;
        max-width: 100%;
        white-space: nowrap !important;
        word-break: keep-all !important;
    }

    #social-media-planner .smp-actions {
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: flex-end;
        align-items: center;
        gap: .5rem;
        white-space: nowrap;
    }

    #social-media-planner .smp-actions button,
    #social-media-planner .smp-actions form,
    #social-media-planner .smp-actions form button {
        width: auto !important;
        min-width: 0 !important;
        display: inline-flex !important;
        align-items: center;
        white-space: nowrap !important;
    }

    #social-media-planner .smp-scroll-hint {
        display: none;
    }


    /* Single schedule field - prevents global time enhancer duplication */
    #social-media-planner .smp-schedule-trigger { position: relative; }
    #social-media-planner .smp-schedule-trigger input {
        padding-right: 2.8rem;
        cursor: pointer;
        background: #fff;
    }
    #social-media-planner .smp-schedule-icon {
        position: absolute;
        right: .9rem;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: rgb(71 85 105);
    }
    #social-media-planner .smp-due-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        margin-bottom: .35rem;
        padding: .2rem .5rem;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }
    #social-media-planner .smp-due-today { background: rgb(236 253 245); color: rgb(4 120 87); }
    #social-media-planner .smp-due-tomorrow { background: rgb(239 246 255); color: rgb(29 78 216); }
    #social-media-planner .smp-due-upcoming { background: rgb(248 250 252); color: rgb(71 85 105); }
    #social-media-planner .smp-due-overdue { background: rgb(255 247 237); color: rgb(194 65 12); }
    #social-media-planner .smp-row-due-today { background: rgba(236, 253, 245, .42); }

    #social-media-planner .smp-picker-dialog {
        width: min(460px, calc(100vw - 24px));
        max-width: min(460px, calc(100vw - 24px));
        border: 0;
        padding: 0;
        border-radius: 1rem;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, .22);
    }
    #social-media-planner .smp-picker-dialog::backdrop {
        background: rgba(15, 23, 42, .48);
    }
    #social-media-planner .smp-picker-grid {
        display: grid;
        grid-template-columns: 1fr 88px 88px 88px;
        gap: .65rem;
        align-items: end;
    }
    @media (max-width: 640px) {
        #social-media-planner .smp-picker-grid { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 767.98px) {
        #social-media-planner {
            overflow-x: hidden;
        }

        #social-media-planner .apple-surface {
            max-width: 100%;
        }

        #social-media-planner .smp-table-card {
            border-radius: 1rem;
        }

        #social-media-planner .smp-table-toolbar {
            padding: .75rem;
            gap: .75rem;
        }

        #social-media-planner .smp-table-toolbar label,
        #social-media-planner .smp-table-toolbar button {
            white-space: nowrap;
        }

        #social-media-planner .smp-scroll-hint {
            display: flex;
            align-items: center;
            gap: .4rem;
            padding: .55rem .8rem;
            border-bottom: 1px solid rgb(226 232 240);
            background: rgb(248 250 252);
            color: rgb(100 116 139);
            font-size: 11px;
            font-weight: 700;
        }

        #social-media-planner .smp-table {
            /* Intentionally wider than the phone viewport. */
            width: 940px !important;
            min-width: 940px !important;
        }

        #social-media-planner .smp-table th,
        #social-media-planner .smp-table td {
            padding: .75rem !important;
        }

        #social-media-planner .smp-actions {
            justify-content: flex-start;
        }

        #social-media-planner .pm-dialog {
            width: calc(100vw - 20px) !important;
            max-width: calc(100vw - 20px) !important;
            max-height: calc(100dvh - 20px) !important;
            margin: auto !important;
        }

        #social-media-planner .pm-modal-content,
        #social-media-planner .pm-modal-body {
            min-width: 0 !important;
            max-width: 100% !important;
        }
    }
</style>
@endpush


@section('content')
@php
    $userTimezone = auth()->user()->timezone ?: 'Africa/Kampala';
    $todayLocal = now($userTimezone)->startOfDay();
    $tomorrowLocal = $todayLocal->copy()->addDay();
@endphp

<div class="space-y-4" id="social-media-planner">
    @include('social-media-planner.partials.navigation-tabs')
    <div class="apple-surface rounded-2xl p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                    Communication Planner
                </div>
                <h1 class="mt-1 text-xl font-black">Social Media Planner</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Schedule content, receive a reminder when it is due, then post it from the device you use for that account.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">

                <button type="button"
                        onclick="openPostModal()"
                        class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                    <i class="fa-solid fa-plus mr-1"></i> New Post
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Scheduled posts become <strong>Ready to Post</strong> at the selected time and trigger an in-app/push reminder.
            WhatsApp Status/Channels and any account without an authorised publishing API use <strong>Post Now</strong>.
        </div>
    </div>

    <form method="POST"
          action="{{ route('social-media-planner.bulk-destroy') }}"
          id="bulk-delete-form"
          class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <form method="GET"
          action="{{ route('social-media-planner.index') }}"
          class="mb-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-3 items-end">
            <div class="xl:col-span-2">
                <label for="smp-search" class="block text-xs font-semibold text-slate-500 mb-1">Search posts</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="search"
                           id="smp-search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search title, caption, hashtag or status..."
                           class="pm-input w-full pl-9">
                </div>
            </div>

            <div>
                <label for="smp-period" class="block text-xs font-semibold text-slate-500 mb-1">Period</label>
                <select id="smp-period"
                        name="period"
                        class="pm-input"
                        onchange="pmToggleSocialPeriodRange()">
                    <option value="all" @selected($period === 'all')>All time</option>
                    <option value="today" @selected($period === 'today')>Today</option>
                    <option value="week" @selected($period === 'week')>Last 7 days</option>
                    <option value="month" @selected($period === 'month')>This month</option>
                    <option value="three_months" @selected($period === 'three_months')>Last 3 months</option>
                    <option value="range" @selected($period === 'range')>Custom range</option>
                </select>
            </div>

            <div data-smp-period-range>
                <label for="smp-from" class="block text-xs font-semibold text-slate-500 mb-1">From</label>
                <input type="date" id="smp-from" name="from" value="{{ $from }}" class="pm-input">
            </div>

            <div data-smp-period-range>
                <label for="smp-to" class="block text-xs font-semibold text-slate-500 mb-1">To</label>
                <input type="date" id="smp-to" name="to" value="{{ $to }}" class="pm-input">
            </div>

            <div>
                <label for="smp-per-page" class="block text-xs font-semibold text-slate-500 mb-1">Per page</label>
                <select id="smp-per-page" name="per_page" class="pm-input">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}" @selected((int) $perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                    <i class="fa-solid fa-filter"></i>
                    Apply
                </button>

                <a href="{{ route('social-media-planner.index') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Clear
                </a>
            </div>
        </div>
    </form>

    <div class="apple-surface rounded-2xl overflow-hidden smp-table-card">
            <div class="smp-table-toolbar p-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-xs font-bold">
                    <input type="checkbox" id="select-all-posts">
                    Select all
                </label>

                <button type="submit"
                        form="bulk-delete-form"
                        id="bulk-delete-button"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-40"
                        disabled>
                    <i class="fa-solid fa-trash mr-1"></i>
                    Delete selected
                </button>
            </div>

            <div class="smp-scroll-hint">
                <i class="fa-solid fa-arrows-left-right"></i>
                Swipe left or right to view all table columns.
            </div>

            <div class="smp-table-scroll">
                <table class="smp-table text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3 text-left">Post</th>
                            <th class="px-4 py-3 text-left">Platforms</th>
                            <th class="px-4 py-3 text-left">Due / Schedule</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                    @forelse($posts as $post)
                        @php
                            $editPayload = [
                                'id' => (int) $post->id,
                                'title' => (string) ($post->title ?? ''),
                                'caption' => (string) ($post->caption ?? ''),
                                'hashtags' => (string) ($post->hashtags ?? ''),
                                'platforms' => array_values((array) ($post->platforms ?? [])),
                                'scheduled_at' => $post->scheduled_at
                                    ? $post->scheduled_at->timezone($userTimezone)->format('Y-m-d\TH:i')
                                    : '',
                                'posting_mode' => (string) ($post->posting_mode ?? 'manual'),
                                'media_type' => (string) ($post->media_type ?? 'text'),
                                'media_url' => $post->publicMediaUrl(),
                                'link_url' => $post->attachedLink(),
                            ];

                            $encodedEditPayload = base64_encode(
                                json_encode(
                                    $editPayload,
                                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                ) ?: '{}'
                            );

                            $sharePayload = base64_encode(
                                json_encode([
                                    'id' => (int) $post->id,
                                    'title' => (string) ($post->title ?? ''),
                                    'text' => $post->shareText(),
                                    'media_url' => $post->publicMediaUrl(),
                                    'media_type' => (string) ($post->media_type ?? 'text'),
                                    'link_url' => $post->attachedLink(),
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
                            );

                            $status = (string) ($post->status ?? 'draft');
                            $statusClass = match ($status) {
                                'published' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'ready_to_share' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'scheduled' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-slate-50 text-slate-600 border-slate-200',
                            };

                            $scheduledLocal = $post->scheduled_at
                                ? $post->scheduled_at->copy()->timezone($userTimezone)
                                : null;

                            $dueLabel = null;
                            $dueClass = 'smp-due-upcoming';
                            $isDueToday = false;

                            if ($scheduledLocal) {
                                $scheduledDay = $scheduledLocal->copy()->startOfDay();

                                if ($scheduledDay->equalTo($todayLocal)) {
                                    $dueLabel = 'Due today';
                                    $dueClass = 'smp-due-today';
                                    $isDueToday = true;
                                } elseif ($scheduledDay->equalTo($tomorrowLocal)) {
                                    $dueLabel = 'Tomorrow';
                                    $dueClass = 'smp-due-tomorrow';
                                } elseif ($scheduledDay->lessThan($todayLocal)) {
                                    $dueLabel = $status === 'published' ? 'Past' : 'Overdue';
                                    $dueClass = $status === 'published'
                                        ? 'smp-due-upcoming'
                                        : 'smp-due-overdue';
                                } else {
                                    $dueLabel = 'Upcoming';
                                    $dueClass = 'smp-due-upcoming';
                                }
                            }
                        @endphp

                        <tr class="{{ $isDueToday ? 'smp-row-due-today' : '' }}"
                            data-schedule="{{ $scheduledLocal ? $scheduledLocal->format('Y-m-d\TH:i') : '' }}"
                            data-due-today="{{ $isDueToday ? '1' : '0' }}">
                            <td class="px-4 py-3 align-top">
                                <input type="checkbox"
                                       class="post-checkbox"
                                       form="bulk-delete-form"
                                       name="ids[]"
                                       value="{{ $post->id }}">
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="smp-post-title font-bold text-slate-800">{{ $post->title }}</div>
                                <div class="smp-post-caption mt-1 max-w-xl text-xs text-slate-500 line-clamp-2">
                                    {{ $post->caption }}
                                </div>
                                @if($post->media_path || $post->attachedLink())
                                    <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                        @if($post->media_path)
                                            <a href="{{ $post->publicMediaUrl() }}" target="_blank" rel="noopener" class="font-bold text-sky-700">
                                                <i class="fa-solid {{ $post->media_type === 'video' ? 'fa-video' : 'fa-image' }} mr-1"></i>{{ ucfirst($post->media_type) }}
                                            </a>
                                        @endif
                                        @if($post->attachedLink())
                                            <a href="{{ $post->attachedLink() }}" target="_blank" rel="noopener" class="font-bold text-indigo-700">
                                                <i class="fa-solid fa-link mr-1"></i>Attached link
                                            </a>
                                        @endif
                                    </div>
                                @endif

                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="smp-platforms">
                                @foreach((array) $post->platforms as $platform)
                                    <span class="smp-platform-badge rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">
                                        @if($platform === 'x')
                                            <i class="fa-brands fa-x-twitter mr-1" aria-hidden="true"></i>X (Twitter)
                                        @else
                                            {{ ucwords(str_replace('_', ' ', $platform)) }}
                                        @endif
                                    </span>
                                @endforeach
                                </div>
                            </td>

                            <td class="px-4 py-3 align-top text-xs text-slate-600 whitespace-nowrap">
                                @if($scheduledLocal)
                                    <div>
                                        <span class="smp-due-badge {{ $dueClass }}">
                                            @if($isDueToday)
                                                <i class="fa-solid fa-circle-dot"></i>
                                            @elseif($dueLabel === 'Overdue')
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                            @else
                                                <i class="fa-regular fa-calendar"></i>
                                            @endif
                                            {{ $dueLabel }}
                                        </span>
                                    </div>
                                    <div class="font-bold text-slate-700">{{ $scheduledLocal->format('d M Y') }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-500">{{ $scheduledLocal->format('g:i A') }}</div>
                                @else
                                    <span class="smp-due-badge smp-due-upcoming">
                                        <i class="fa-regular fa-pen-to-square"></i> Draft
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex rounded-full border px-2 py-1 text-[10px] font-black uppercase {{ $statusClass }}">
                                    {{ $status === 'ready_to_share' ? 'Ready to Post' : str_replace('_', ' ', $status) }}
                                </span>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="smp-actions">
                                    <button type="button"
                                            class="js-edit-social-post text-xs font-bold text-sky-700"
                                            data-post="{{ $encodedEditPayload }}">
                                        <i class="fa-solid fa-pen-to-square mr-1"></i>Edit
                                    </button>

                                    @if($status !== 'published')
                                        <button type="button"
                                                class="js-post-now text-xs font-bold text-teal-700"
                                                data-post="{{ $sharePayload }}"
                                                data-url="{{ route('social-media-planner.post-now', $post) }}">
                                            <i class="fa-solid fa-paper-plane mr-1"></i>Post now
                                        </button>

                                        <form method="POST"
                                              action="{{ route('social-media-planner.mark-published', $post) }}"
                                              class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="text-xs font-bold text-emerald-700">
                                                <i class="fa-solid fa-circle-check mr-1"></i>Mark posted
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                                No social media posts yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        <div class="border-t border-slate-100 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Showing
                    <span class="font-semibold text-slate-700">{{ $posts->firstItem() ?? 0 }}</span>
                    to
                    <span class="font-semibold text-slate-700">{{ $posts->lastItem() ?? 0 }}</span>
                    of
                    <span class="font-semibold text-slate-700">{{ $posts->total() }}</span>
                    posts
                </p>

                @if($posts->hasPages())
                    <nav aria-label="Social media posts pagination">
                        {{ $posts->onEachSide(1)->links() }}
                    </nav>
                @endif
            </div>
        </div>
    </div>
</div>

<dialog id="social-post-modal" class="pm-dialog rounded-2xl">
    <form method="POST"
          action="{{ route('social-media-planner.store') }}"
          id="social-post-form"
          enctype="multipart/form-data"
          class="pm-modal-content">
        @csrf
        <input type="hidden" name="_method" id="post-method" value="POST">

        <div class="pm-modal-header">
            <div>
                <h2 class="pm-modal-title" id="post-modal-title">Schedule Social Media Post</h2>
                <p class="pm-modal-subtitle">Create once and choose where it should appear.</p>
            </div>
            <button type="button"
                    class="pm-modal-close"
                    onclick="document.getElementById('social-post-modal').close()">&times;</button>
        </div>

        <div class="pm-modal-body">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="pm-form-tabs md:col-span-2" role="tablist" aria-label="Post sections" data-pm-form-tabs>
                    <button type="button" role="tab" id="social-post-tab-0" aria-controls="social-post-panel-0" aria-selected="true" tabindex="0" data-pm-form-tab="0" class="pm-form-tab is-active">Content</button>
                    <button type="button" role="tab" id="social-post-tab-1" aria-controls="social-post-panel-1" aria-selected="false" tabindex="-1" data-pm-form-tab="1" class="pm-form-tab">Media &amp; Link</button>
                    <button type="button" role="tab" id="social-post-tab-2" aria-controls="social-post-panel-2" aria-selected="false" tabindex="-1" data-pm-form-tab="2" class="pm-form-tab">Schedule &amp; Publish</button>
                </div>

                <div class="pm-form-panel md:col-span-2 grid gap-3 md:grid-cols-2" role="tabpanel" id="social-post-panel-0" aria-labelledby="social-post-tab-0" data-pm-form-panel="0">
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-xs font-bold">Post title</label>
                            <button type="button"
                                    id="post-ai-generate"
                                    onclick="generateSocialPostAiDraft()"
                                    class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <span>AI Generate</span>
                            </button>
                        </div>
                        <input name="title"
                               id="post-title"
                               required
                               class="pm-input mt-1 w-full"
                               placeholder="Enter the post title or topic first">
                        <p id="post-ai-status" class="mt-1 text-[11px] text-slate-500" aria-live="polite"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Caption</label>
                        <textarea name="caption"
                                  id="post-caption"
                                  rows="10"
                                  class="pm-input mt-1 w-full"
                                  placeholder="Write the caption, or use AI Generate"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-bold">Hashtags</label>
                        <textarea name="hashtags" id="post-hashtags" rows="3" class="pm-input mt-1 w-full" placeholder="#brand #launch (optional)"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-bold">Content objective</label>
                        <input name="content_objective"
                               id="post-content-objective"
                               class="pm-input mt-1 w-full"
                               placeholder="e.g. awareness, engagement, promotion">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Media idea</label>
                        <textarea name="media_idea"
                                  id="post-media-idea"
                                  rows="2"
                                  class="pm-input mt-1 w-full"
                                  placeholder="Suggested image, video or carousel idea"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Call to action</label>
                        <input name="call_to_action"
                               id="post-call-to-action"
                               class="pm-input mt-1 w-full"
                               placeholder="What should the audience do next?">
                    </div>
                </div>

                <div class="pm-form-panel md:col-span-2 grid gap-3 md:grid-cols-2" role="tabpanel" id="social-post-panel-1" aria-labelledby="social-post-tab-1" data-pm-form-panel="1" hidden>
                    <div class="md:col-span-2 rounded-xl border border-slate-200 p-3">
                        <label class="text-xs font-bold">Attach image or video</label>
                        <input type="file"
                               name="attachment"
                               id="post-attachment"
                               accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm"
                               class="pm-input mt-1 w-full">
                        <div id="new-media-preview" class="mt-2 hidden rounded-xl border border-slate-200 bg-slate-50 p-2"></div>
                        <div id="current-media-row" class="mt-2 hidden items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                            <a id="current-media-link" href="#" target="_blank" rel="noopener" class="font-bold text-sky-700">View current media</a>
                            <label class="flex items-center gap-2 font-bold text-rose-600">
                                <input type="checkbox" name="remove_media" value="1" id="remove-media"> Remove
                            </label>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Attach link</label>
                        <input type="url" name="link_url" id="post-link-url" placeholder="https://example.com/… (optional)" class="pm-input mt-1 w-full">
                    </div>
                </div>

                <div class="pm-form-panel md:col-span-2 grid gap-3 md:grid-cols-2" role="tabpanel" id="social-post-panel-2" aria-labelledby="social-post-tab-2" data-pm-form-panel="2" hidden>
                    <div>
                        <label class="text-xs font-bold">Schedule</label>
                        <input type="hidden" name="scheduled_at" id="post-schedule">

                        <div class="smp-schedule-trigger mt-1">
                            <input type="text"
                                   id="post-schedule-display"
                                   class="pm-input w-full"
                                   placeholder="Choose date and time"
                                   autocomplete="off"
                                   readonly
                                   onclick="openSchedulePicker()">
                            <span class="smp-schedule-icon">
                                <i class="fa-regular fa-calendar"></i>
                            </span>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Posting method</label>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3">
                                <input type="radio"
                                       name="posting_mode"
                                       value="automatic"
                                       id="posting-mode-automatic">
                                <span>
                                    <strong class="block text-xs">Automatic posting</strong>
                                    <span class="block text-[11px] text-slate-500">
                                        Posts at the scheduled time from a connected account.
                                    </span>
                                </span>
                            </label>

                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3">
                                <input type="radio"
                                       name="posting_mode"
                                       value="manual"
                                       id="posting-mode-manual"
                                       checked>
                                <span>
                                    <strong class="block text-xs">Remind me to post</strong>
                                    <span class="block text-[11px] text-slate-500">
                                        You post it yourself after a reminder.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold">Platforms</label>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach([
                            'instagram' => 'Instagram',
                            'facebook' => 'Facebook',
                            'x' => 'X (Twitter)',
                            'tiktok' => 'TikTok',
                            'linkedin' => 'LinkedIn',
                            'whatsapp_status' => 'WhatsApp Status',
                            'whatsapp_channel' => 'WhatsApp Channel',
                        ] as $value => $label)
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-bold">
                                <input type="checkbox"
                                       name="platforms[]"
                                       value="{{ $value }}"
                                       class="platform-checkbox">
                                {{ $label }}
                            </label>
                        @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="pm-modal-footer">
            <button type="button"
                    class="apple-btn apple-btn-small"
                    onclick="document.getElementById('social-post-modal').close()">
                Cancel
            </button>
            <button class="btn-primary rounded-xl px-4 py-2 text-sm font-bold text-white">
                Save Post
            </button>
        </div>
    </form>
</dialog>

@include('partials.form-tabs')

<dialog id="social-schedule-picker" class="smp-picker-dialog">
    <div class="border-b border-slate-100 px-4 py-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-black text-slate-900">Choose Schedule</h3>
                <p class="mt-1 text-xs text-slate-500">Select one date and one time.</p>
            </div>
            <button type="button" class="rounded-lg px-2 py-1 text-xl text-slate-500"
                    onclick="closeSchedulePicker()">&times;</button>
        </div>
    </div>

    <div class="p-4">
        <div class="smp-picker-grid">
            <div>
                <label class="text-xs font-bold">Date</label>
                <input type="date" id="schedule-picker-date" class="pm-input mt-1 w-full">
            </div>

            <div>
                <label class="text-xs font-bold">Hour</label>
                <select id="schedule-picker-hour" class="pm-input mt-1 w-full">
                    @for($hour = 1; $hour <= 12; $hour++)
                        <option value="{{ $hour }}">{{ $hour }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="text-xs font-bold">Minute</label>
                <select id="schedule-picker-minute" class="pm-input mt-1 w-full">
                    @for($minute = 0; $minute < 60; $minute += 5)
                        <option value="{{ str_pad((string) $minute, 2, '0', STR_PAD_LEFT) }}">
                            {{ str_pad((string) $minute, 2, '0', STR_PAD_LEFT) }}
                        </option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="text-xs font-bold">AM / PM</label>
                <select id="schedule-picker-ampm" class="pm-input mt-1 w-full">
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <button type="button" class="apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                    onclick="clearSchedule()">Clear</button>

            <div class="flex gap-2">
                <button type="button" class="apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                        onclick="closeSchedulePicker()">Cancel</button>
                <button type="button" class="btn-primary rounded-xl px-4 py-2 text-xs font-bold text-white"
                        onclick="applySchedulePicker()">Apply Schedule</button>
            </div>
        </div>
    </div>
</dialog>

<script>
(function () {
    'use strict';

    const modal = document.getElementById('social-post-modal');
    const form = document.getElementById('social-post-form');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const schedulePicker = document.getElementById('social-schedule-picker');
    const scheduleHidden = document.getElementById('post-schedule');
    const scheduleDisplay = document.getElementById('post-schedule-display');
    const scheduleDate = document.getElementById('schedule-picker-date');
    const scheduleHour = document.getElementById('schedule-picker-hour');
    const scheduleMinute = document.getElementById('schedule-picker-minute');
    const scheduleAmPm = document.getElementById('schedule-picker-ampm');

    const pad2 = value => String(value).padStart(2, '0');

    function formatScheduleDisplay(value) {
        if (!value || !String(value).includes('T')) return '';

        const [datePart, timePart] = String(value).split('T');
        const [year, month, day] = datePart.split('-').map(Number);
        const [hour, minute] = timePart.split(':').map(Number);
        const date = new Date(year, month - 1, day, hour, minute, 0, 0);

        return new Intl.DateTimeFormat('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        }).format(date);
    }

    function setPickerFromValue(value) {
        let date = new Date();

        if (value && String(value).includes('T')) {
            const [datePart, timePart] = String(value).split('T');
            const [year, month, day] = datePart.split('-').map(Number);
            const [hour, minute] = timePart.split(':').map(Number);
            date = new Date(year, month - 1, day, hour, minute, 0, 0);
        } else {
            const next = Math.ceil(date.getMinutes() / 5) * 5;
            if (next >= 60) date.setHours(date.getHours() + 1, 0, 0, 0);
            else date.setMinutes(next, 0, 0);
        }

        scheduleDate.value =
            `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;

        scheduleHour.value = String(date.getHours() % 12 || 12);
        scheduleMinute.value = pad2(Math.floor(date.getMinutes() / 5) * 5);
        scheduleAmPm.value = date.getHours() >= 12 ? 'PM' : 'AM';
    }

    window.openSchedulePicker = function () {
        const now = new Date();
        scheduleDate.min =
            `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`;

        setPickerFromValue(scheduleHidden.value);
        schedulePicker?.showModal();
    };

    window.closeSchedulePicker = function () {
        schedulePicker?.close();
    };

    window.clearSchedule = function () {
        scheduleHidden.value = '';
        scheduleDisplay.value = '';
        schedulePicker?.close();
    };

    window.applySchedulePicker = function () {
        if (!scheduleDate.value) {
            scheduleDate.focus();
            return;
        }

        let hour24 = Number(scheduleHour.value) % 12;
        if (scheduleAmPm.value === 'PM') hour24 += 12;

        const value =
            `${scheduleDate.value}T${pad2(hour24)}:${pad2(scheduleMinute.value)}`;

        scheduleHidden.value = value;
        scheduleDisplay.value = formatScheduleDisplay(value);
        schedulePicker?.close();
    };

    function decodePayload(value) {
        try {
            const binary = atob(value || '');
            const bytes = Uint8Array.from(binary, c => c.charCodeAt(0));
            return JSON.parse(new TextDecoder().decode(bytes));
        } catch (_) {
            return {};
        }
    }

    window.openPostModal = function (post = null) {
        form.action = post
            ? `{{ url('/social-media-planner') }}/${post.id}`
            : `{{ route('social-media-planner.store') }}`;

        document.getElementById('post-method').value = post ? 'PUT' : 'POST';
        document.getElementById('post-modal-title').textContent =
            post ? 'Edit Social Media Post' : 'Schedule Social Media Post';

        document.getElementById('post-title').value = post?.title || '';
        document.getElementById('post-caption').value = post?.caption || '';
        document.getElementById('post-hashtags').value = post?.hashtags || '';
        const scheduledValue = post?.scheduled_at || '';
        scheduleHidden.value = scheduledValue;
        scheduleDisplay.value = formatScheduleDisplay(scheduledValue);
        document.getElementById('post-link-url').value = post?.link_url || '';
        document.getElementById('post-attachment').value = '';
        if (typeof clearNewMediaPreview === 'function') clearNewMediaPreview();
        document.getElementById('remove-media').checked = false;

        const mediaRow = document.getElementById('current-media-row');
        const mediaLink = document.getElementById('current-media-link');
        if (post?.media_url) {
            mediaRow.classList.remove('hidden');
            mediaRow.classList.add('flex');
            mediaLink.href = post.media_url;
            mediaLink.textContent = post.media_type === 'video' ? 'View current video' : 'View current image';
        } else {
            mediaRow.classList.add('hidden');
            mediaRow.classList.remove('flex');
            mediaLink.href = '#';
        }

        const mode = post?.posting_mode || 'manual';
        const automatic = document.getElementById('posting-mode-automatic');
        const manual = document.getElementById('posting-mode-manual');

        if (automatic) automatic.checked = mode === 'automatic';
        if (manual) manual.checked = mode !== 'automatic';

        const selected = Array.isArray(post?.platforms)
            ? post.platforms
            : [];

        document.querySelectorAll('.platform-checkbox').forEach(input => {
            input.checked = selected.includes(input.value);
        });

        modal?.showModal();
    };

    document.querySelectorAll('.js-edit-social-post').forEach(button => {
        button.addEventListener('click', () => {
            window.openPostModal(
                decodePayload(button.dataset.post)
            );
        });
    });

    const plannerBody = document.querySelector('.smp-table tbody');
    if (plannerBody) {
        const rows = [...plannerBody.querySelectorAll('tr[data-schedule]')];

        rows.sort((a, b) => {
            const aToday = a.dataset.dueToday === '1';
            const bToday = b.dataset.dueToday === '1';

            if (aToday !== bToday) return aToday ? -1 : 1;

            const aSchedule = a.dataset.schedule || '';
            const bSchedule = b.dataset.schedule || '';

            if (!aSchedule && !bSchedule) return 0;
            if (!aSchedule) return 1;
            if (!bSchedule) return -1;

            return aSchedule.localeCompare(bSchedule);
        });

        rows.forEach(row => plannerBody.appendChild(row));
    }

    async function mediaFileFromUrl(post) {
        if (!post?.media_url) return null;

        const response = await fetch(post.media_url, {
            credentials: 'same-origin',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error(`Could not load attached media (${response.status}).`);
        }

        const blob = await response.blob();
        const type = blob.type || (
            post.media_type === 'video' ? 'video/mp4' : 'image/jpeg'
        );

        const extByType = {
            'image/jpeg': '.jpg',
            'image/png': '.png',
            'image/webp': '.webp',
            'image/gif': '.gif',
            'video/mp4': '.mp4',
            'video/quicktime': '.mov',
            'video/webm': '.webm',
        };

        const extension = extByType[type] || '';
        const base = (post.title || 'social-post')
            .replace(/[^a-z0-9_-]+/gi, '-')
            .replace(/^-+|-+$/g, '')
            .toLowerCase() || 'social-post';

        return new File([blob], `${base}${extension}`, { type });
    }

    async function shareManualPost(post) {
        const text = post.text || post.title || '';
        const shareData = {
            title: post.title || 'Social media post',
            text
        };

        if (post.link_url) shareData.url = post.link_url;

        if (post.media_url && navigator.share) {
            try {
                const file = await mediaFileFromUrl(post);
                if (file) {
                    const dataWithFile = { ...shareData, files: [file] };

                    if (!navigator.canShare || navigator.canShare(dataWithFile)) {
                        await navigator.share(dataWithFile);
                        return;
                    }
                }
            } catch (error) {
                if (error?.name === 'AbortError') return;
                console.warn('File sharing is not available:', error);
            }
        }

        if (navigator.share) {
            try {
                await navigator.share(shareData);
                if (post.media_url) {
                    window.open(post.media_url, '_blank', 'noopener');
                }
                return;
            } catch (error) {
                if (error?.name === 'AbortError') return;
            }
        }

        try {
            await navigator.clipboard.writeText(text);
        } catch (_) {}

        if (post.media_url) {
            window.open(post.media_url, '_blank', 'noopener');
        }

        if (!post.media_url) {
            window.prompt('Copy this post:', text);
        }
    }

    document.querySelectorAll('.js-post-now').forEach(button => {
        button.addEventListener('click', async () => {
            const post = decodePayload(button.dataset.post);
            const original = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Preparing';

            try {
                await fetch(button.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    }
                });

                await shareManualPost(post);
            } catch (error) {
                console.error(error);

                try {
                    await navigator.clipboard.writeText(
                        post.text || post.title || ''
                    );
                } catch (_) {}

                if (post.media_url) {
                    window.open(post.media_url, '_blank', 'noopener');
                }
            } finally {
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    });

    const attachmentInput = document.getElementById('post-attachment');
    const previewBox = document.getElementById('new-media-preview');
    let previewObjectUrl = null;

    function clearNewMediaPreview() {
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }

        if (previewBox) {
            previewBox.innerHTML = '';
            previewBox.classList.add('hidden');
        }
    }

    attachmentInput?.addEventListener('change', () => {
        clearNewMediaPreview();

        const file = attachmentInput.files?.[0];
        if (!file || !previewBox) return;

        previewObjectUrl = URL.createObjectURL(file);
        previewBox.classList.remove('hidden');

        const info = document.createElement('div');
        info.className = 'mb-2 text-xs font-bold text-slate-700';
        info.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(1)} MB`;
        previewBox.appendChild(info);

        if (file.type.startsWith('video/')) {
            const video = document.createElement('video');
            video.src = previewObjectUrl;
            video.controls = true;
            video.preload = 'metadata';
            video.className = 'max-h-48 w-full rounded-lg bg-black';
            previewBox.appendChild(video);
        } else {
            const image = document.createElement('img');
            image.src = previewObjectUrl;
            image.alt = 'Selected attachment preview';
            image.className = 'max-h-48 w-full rounded-lg object-contain';
            previewBox.appendChild(image);
        }
    });


    const all = document.getElementById('select-all-posts');
    const boxes = [...document.querySelectorAll('.post-checkbox')];
    const bulkButton = document.getElementById('bulk-delete-button');

    function updateBulkState() {
        const selected = boxes.filter(box => box.checked).length;
        if (bulkButton) bulkButton.disabled = selected === 0;

        if (all) {
            all.checked = boxes.length > 0 && selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
        }
    }

    all?.addEventListener('change', () => {
        boxes.forEach(box => box.checked = all.checked);
        updateBulkState();
    });

    boxes.forEach(box => {
        box.addEventListener('change', updateBulkState);
    });

    updateBulkState();
})();
</script>

<script>
async function generateSocialPostAiDraft() {
    const form = document.getElementById('social-post-form');
    const title = document.getElementById('post-title');
    const button = document.getElementById('post-ai-generate');
    const status = document.getElementById('post-ai-status');

    if (!form || !title || !button || !status) return;

    const topic = title.value.trim();

    if (!topic) {
        status.textContent = 'Enter the post title or topic first.';
        title.focus();
        return;
    }

    const platforms = Array.from(
        form.querySelectorAll('input[name="platforms[]"]:checked')
    ).map(input => input.value);

    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Generating...</span>';
    status.textContent = 'Creating an editable post draft from your title...';

    try {
        const response = await fetch(@json(route('social-media-planner.store')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                _ai_generate: true,
                title: topic,
                platforms
            })
        });

        const json = await response.json();

        if (!response.ok || !json.ok) {
            throw new Error(json.message || 'Could not generate the post draft.');
        }

        const fields = {
            caption: 'post-caption',
            hashtags: 'post-hashtags',
            content_objective: 'post-content-objective',
            media_idea: 'post-media-idea',
            call_to_action: 'post-call-to-action'
        };

        let populated = 0;

        Object.entries(fields).forEach(([name, id]) => {
            const field = document.getElementById(id);
            const value = json.data?.[name];

            if (!field || value === undefined || value === null) return;

            field.value = String(value);
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            populated++;
        });

        status.textContent =
            `AI draft ready. ${populated} field${populated === 1 ? '' : 's'} populated. Review and edit before saving.`;

        button.innerHTML =
            '<i class="fa-solid fa-rotate"></i><span>Regenerate</span>';
    } catch (error) {
        status.textContent =
            error.message || 'AI could not generate the post. Your current fields were kept.';
        button.innerHTML = original;
    } finally {
        button.disabled = false;
    }
}
</script>

@endsection

<script>
function pmToggleSocialPeriodRange() {
    const select = document.getElementById('smp-period');
    const show = select && select.value === 'range';

    document.querySelectorAll('[data-smp-period-range]').forEach(function (element) {
        element.classList.toggle('hidden', !show);
    });
}

document.addEventListener('DOMContentLoaded', pmToggleSocialPeriodRange);
</script>
