@extends('layouts.app')

@section('title', 'AI Planner')

@section('content')
<div id="ai-plans-page" class="pm-ai-plans-page min-w-0 max-w-full">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-robot text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">AI Planner</h1>
        </div>
        <button type="button" onclick="document.getElementById('ai-generate-modal').showModal()" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>Generate New Plan</span>
        </button>
    </div>

    @if ($plans->isEmpty() && !$search)
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl">
            <x-empty-state icon="fa-solid fa-robot" title="No plans generated yet" message="Add an API key first, then click Generate New Plan for a short, prioritized action plan.">
                <x-slot name="action">
                    <a href="{{ route('api-credentials.index') }}" class="text-sm font-semibold text-[var(--brand-1)] hover:underline">Add API key</a>
                </x-slot>
            </x-empty-state>
        </div>
    @else
        {{-- Stats always visible, never tabbed same reasoning as
             everywhere else in the app: a quick-reference summary
             shouldn't be hidden behind a click. --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            @foreach ($stats as $stat)
                <div class="pm-card-bg rounded-xl shadow-sm border border-slate-200 border-l-4 border-l-{{ $stat['color'] }}-400 p-3 flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-{{ $stat['color'] }}-50 text-{{ $stat['color'] }}-600 flex items-center justify-center shrink-0">
                        <i class="{{ $stat['icon'] }} text-sm" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                        <p class="text-lg font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        @php $pmShowTabs = !empty($chart); @endphp

        @if ($pmShowTabs)
            <div role="tablist" aria-label="AI Planner sections" class="pm-ai-tabs border-b border-slate-200 mb-6">
                <button type="button" role="tab" id="pm-ai-tab-chart" aria-controls="pm-ai-panel-chart"
                        aria-selected="false" tabindex="-1" data-tab="chart"
                        onclick="pmSelectAiTab('chart')" onkeydown="pmAiTabKeydown(event, 'chart')"
                        class="pm-ai-tab inline-flex shrink-0 items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    <span>Chart</span>
                </button>
                <button type="button" role="tab" id="pm-ai-tab-table" aria-controls="pm-ai-panel-table"
                        aria-selected="true" tabindex="0" data-tab="table"
                        onclick="pmSelectAiTab('table')" onkeydown="pmAiTabKeydown(event, 'table')"
                        class="pm-ai-tab inline-flex shrink-0 items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors border-[var(--brand-1)] text-[var(--brand-1)]">
                    <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                    <span>Plans</span>
                </button>
            </div>
        @endif

        @if (!empty($chart))
            <div role="tabpanel" id="pm-ai-panel-chart" aria-labelledby="pm-ai-tab-chart" tabindex="0" class="pm-ai-panel" hidden>
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 mb-6">
                    <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-robot text-violet-500" aria-hidden="true"></i>
                        {{ $chart['title'] }}
                    </h2>
                    <div class="h-48">
                        <canvas id="ai-plans-chart" role="img" aria-label="{{ $chart['title'] }}"></canvas>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
            <script>
                var pmAiChartInstance = null;
                function pmInitAiChartIfNeeded() {
                    if (pmAiChartInstance) { return; }
                    pmAiChartInstance = new Chart(document.getElementById('ai-plans-chart'), {
                        type: 'bar',
                        data: {
                            labels: @json($chart['labels']),
                            datasets: [{
                                label: 'Plans',
                                data: @json($chart['datasets'][0]['data']),
                                backgroundColor: '#8b5cf6',
                                borderRadius: 4,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                            plugins: { legend: { display: false } },
                        },
                    });
                }
            </script>
        @endif

        <div role="tabpanel" id="pm-ai-panel-table" aria-labelledby="pm-ai-tab-table" tabindex="0" class="pm-ai-panel">
        <form method="GET" action="{{ route('ai-plans.index') }}" class="mb-4 w-full max-w-xl">
            <label for="q" class="sr-only">Search your plans</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="q" name="q" value="{{ $search }}" placeholder="Search plan content..."
                       class="pl-9 pm-input text-sm">
            </div>
        </form>

        @if ($plans->isEmpty())
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl">
                <x-empty-state icon="fa-solid fa-magnifying-glass" title="No plans match your search" message="No plans match your current search.">
                    <x-slot name="action">
                        <a href="{{ route('ai-plans.index') }}" class="text-sm font-semibold text-[var(--brand-1)] hover:underline">Clear search</a>
                    </x-slot>
                </x-empty-state>
            </div>
        @else
            <form id="ai-plan-bulk-form" method="POST" action="{{ route('ai-plans.bulk-destroy') }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
            <div id="ai-plan-bulk-bar" class="hidden mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-100 bg-rose-50 px-4 py-3">
                <p class="text-sm font-medium text-rose-700"><span id="ai-plan-selected-count">0</span> plan(s) selected</p>
                <button type="button" onclick="pmOpenAiPlanBulkDeleteModal()" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete Selected
                </button>
            </div>
            {{-- Mobile plan cards: prevents table columns from collapsing vertically. --}}
            <div class="pm-ai-mobile-cards space-y-3" aria-label="AI plans">
                @foreach ($plans as $plan)
                    <article class="pm-card-bg min-w-0 rounded-xl border border-slate-200 p-4 shadow-sm">
                        <div class="flex min-w-0 items-start gap-3">
                            <input
                                type="checkbox"
                                name="ids[]"
                                value="{{ $plan->id }}"
                                form="ai-plan-bulk-form"
                                onchange="pmUpdateAiPlanBulkBar(this)"
                                class="ai-plan-row-checkbox mt-1 shrink-0 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]"
                                aria-label="Select AI plan from {{ $plan->created_at->format('Y-m-d H:i') }}"
                            >

                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate font-bold text-slate-800">
                                            {{ $plan->created_at->format('d M Y, g:i A') }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500">
                                            {{ $plan->provider === 'anthropic' ? 'Claude' : ($plan->provider === 'openai' ? 'ChatGPT' : ucfirst((string) $plan->provider)) }}
                                        </p>
                                    </div>

                                    <span class="shrink-0 rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-bold text-violet-700">
                                        AI Plan
                                    </span>
                                </div>

                                <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">
                                    {{ \Illuminate\Support\Str::limit(trim($plan->cleanContent()), 220) }}
                                </p>

                                <div class="mt-4 grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onclick='pmOpenAiPlanViewModal(
                                            @json($plan->created_at->format("d M Y, g:i A")),
                                            @json($plan->cleanContent()),
                                            @json(route("ai-plans.pdf", $plan->id)),
                                            @json(route("ai-plans.pdf", $plan->id))
                                        )'
                                        class="inline-flex min-w-0 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                    >
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        <span>View</span>
                                    </button>

                                    <a
                                        href="{{ route('ai-plans.pdf', $plan->id) }}"
                                        class="inline-flex min-w-0 items-center justify-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700"
                                    >
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        <span>PDF</span>
                                    </a>

                                    <a
                                        href="{{ route('ai-plans.pdf', $plan->id) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex min-w-0 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                    >
                                        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                                        <span>Preview</span>
                                    </a>

                                    <button
                                        type="button"
                                        onclick='pmOpenAiPlanDeleteModal(
                                            @json(route("ai-plans.destroy", $plan->id)),
                                            @json($plan->created_at->format("d M Y, g:i A"))
                                        )'
                                        class="inline-flex min-w-0 items-center justify-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700"
                                    >
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pm-ai-desktop-table pm-dt-wrap" role="region" aria-label="AI plans table">
                <table class="pm-dt">
                    <caption class="sr-only">Your generated AI plans, with view, download, and delete actions for each.</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="pm-dt-check">
                                <input type="checkbox" id="ai-plan-select-all" onchange="pmToggleAllAiPlans(this)" class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" aria-label="Select all AI plans">
                            </th>
                            <th scope="col">Plan</th>
                            <th scope="col">Created</th>
                            <th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            @php
                                $aiPlanProviderName = \App\Models\AiProvider::where('key', $plan->provider)->value('name') ?? ($plan->provider ?? '—');
                            @endphp
                            <tr class="has-check">
                                <td class="pm-dt-check">
                                    <input type="checkbox" name="ids[]" value="{{ $plan->id }}" form="ai-plan-bulk-form" onchange="pmUpdateAiPlanBulkBar(this)" class="ai-plan-row-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" aria-label="Select AI plan from {{ $plan->created_at->format('Y-m-d H:i') }}">
                                </td>
                                <td class="pm-dt-main">
                                    <button type="button"
                                            class="pm-dt-title"
                                            onclick="pmOpenAiPlanViewModal({{ json_encode($plan->created_at->format('Y-m-d H:i')) }}, {{ json_encode($plan->cleanContent()) }}, {{ json_encode(route('ai-plans.pdf', $plan->id)) }}, {{ json_encode(route('ai-plans.pdf', $plan->id)) }})"
                                            title="View plan">
                                        {{ \Illuminate\Support\Str::limit(str_replace(["\n", "\r"], ' ', $plan->cleanContent()), 90) }}
                                    </button>
                                    <span class="pm-dt-sub"><span>{{ $aiPlanProviderName }}</span></span>
                                </td>
                                <td class="pm-dt-aux">{{ $plan->created_at->format('d M Y, g:i A') }}</td>
                                <td class="pm-dt-actions">
                                    <button type="button"
                                            onclick="pmOpenAiPlanViewModal({{ json_encode($plan->created_at->format('Y-m-d H:i')) }}, {{ json_encode($plan->cleanContent()) }}, {{ json_encode(route('ai-plans.pdf', $plan->id)) }}, {{ json_encode(route('ai-plans.pdf', $plan->id)) }})"
                                            class="pm-dt-icon-btn" title="View plan" aria-label="View plan">
                                        <i class="fa-solid fa-eye text-xs" aria-hidden="true"></i>
                                    </button>
                                    <details class="pm-dt-menu">
                                        <summary class="pm-dt-icon-btn" aria-label="More actions" title="More actions">
                                            <i class="fa-solid fa-ellipsis-vertical text-xs" aria-hidden="true"></i>
                                        </summary>
                                        <div class="pm-dt-menu-list">
                                            <a href="{{ route('ai-plans.pdf', $plan->id) }}" target="_blank" rel="noopener" class="pm-dt-menu-item">
                                                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> View PDF
                                            </a>
                                            <a href="{{ route('ai-plans.pdf', $plan->id) }}" class="pm-dt-menu-item">
                                                <i class="fa-solid fa-download" aria-hidden="true"></i> Download
                                            </a>
                                            <button type="button"
                                                    onclick="pmOpenAiPlanDeleteModal({{ json_encode(route('ai-plans.destroy', $plan->id)) }}, {{ json_encode($plan->created_at->format('Y-m-d H:i')) }})"
                                                    class="pm-dt-menu-item is-danger">
                                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete
                                            </button>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <nav aria-label="Pagination" class="mt-4">
                {{ $plans->links() }}
            </nav>
        @endif
        </div>
    @endif



    <style>
        /* ================================================================
           MY DIGITAL DIARY — AI PLANNER RESPONSIVE LAYOUT
        ================================================================= */

        #ai-plans-page,
        #ai-plans-page * {
            box-sizing: border-box;
        }

        #ai-plans-page {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            overflow-x: clip;
        }

        #ai-plans-page .pm-ai-tabs {
            display: flex !important;
            flex-wrap: nowrap !important;
            gap: .25rem !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            white-space: nowrap !important;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        #ai-plans-page .pm-ai-tab {
            flex: 0 0 auto !important;
            width: auto !important;
            min-width: max-content !important;
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
        }

        #ai-plans-page table th,
        #ai-plans-page table td {
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
        }

        #ai-plans-page .pm-ai-mobile-cards {
            display: none;
        }

        #ai-plans-page .pm-ai-desktop-table {
            display: block;
            width: 100%;
            max-width: 100%;
        }

        .pm-ai-dialog {
            width: min(94vw, 760px);
            max-width: 760px;
            max-height: calc(100dvh - 24px);
            overflow: hidden;
        }

        #ai-generate-modal {
            width: min(92vw, 600px);
        }

        #ai-generate-modal textarea {
            display: block;
            width: 100%;
            min-height: 10rem;
            max-height: min(52dvh, 24rem);
            resize: vertical;
            line-height: 1.6;
        }

        #ai-generate-modal button:focus-visible {
            outline: 3px solid var(--brand-2);
            outline-offset: 3px;
        }

        .pm-ai-dialog > form,
        .pm-ai-dialog > div {
            max-height: calc(100dvh - 24px);
            overflow-y: auto;
            overflow-x: hidden;
        }

        @media (max-width: 767px) {
            #ai-plans-page .pm-ai-mobile-cards {
                display: block;
            }

            #ai-plans-page .pm-ai-desktop-table {
                display: none;
            }

            #ai-plans-page .grid.sm\:grid-cols-3 {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            #ai-plans-page form input,
            #ai-plans-page form select,
            #ai-plans-page form textarea {
                min-width: 0 !important;
                max-width: 100% !important;
            }

            #ai-plans-page #ai-plan-bulk-bar {
                align-items: stretch !important;
            }

            #ai-plans-page #ai-plan-bulk-bar button {
                width: 100%;
                justify-content: center;
            }

            .pm-ai-dialog {
                width: calc(100vw - 16px);
                max-width: calc(100vw - 16px);
                max-height: calc(100dvh - 16px);
                border-radius: 18px !important;
            }

            #ai-generate-modal {
                width: calc(100vw - 24px);
                max-width: calc(100vw - 24px);
            }

            #ai-generate-modal textarea {
                min-height: 8rem;
                max-height: 42dvh;
            }

            .pm-ai-dialog > form,
            .pm-ai-dialog > div {
                max-height: calc(100dvh - 16px);
                padding: 1rem !important;
            }

            .pm-ai-modal-actions {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) !important;
                width: 100%;
            }

            .pm-ai-modal-actions > * {
                width: 100% !important;
                min-width: 0 !important;
                justify-content: center !important;
                text-align: center !important;
            }

            #ai-plan-view-content {
                max-height: 48dvh !important;
            }
        }
    </style>

    <dialog id="ai-generate-modal" class="pm-ai-dialog rounded-2xl p-0 pm-dialog-xl shadow-2xl backdrop:bg-slate-900/50">
        <form method="POST" action="{{ route('ai-plans.store') }}" aria-labelledby="ai-generate-title" class="p-6 sm:p-7">
            @csrf
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4 mb-5">
                <h2 id="ai-generate-title" class="text-xl font-bold leading-tight text-slate-800">What should the AI Planner generate?</h2>
                <button type="button" onclick="this.closest('dialog').close()" aria-label="Close AI Planner request dialog" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <label for="custom_prompt" class="mb-2 block text-sm font-semibold text-slate-700">Your request</label>
            <textarea id="custom_prompt" name="custom_prompt" rows="5" maxlength="3000" class="pm-input" placeholder="Describe your goals, priorities, and timeframe…">{{ old('custom_prompt') }}</textarea>
            <div class="pm-ai-modal-actions mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-2)]">Cancel</button>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-2)]"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>Generate</span></button>
            </div>
        </form>
    </dialog>

    {{-- View full plan modal read-only. --}}
    <dialog id="ai-plan-view-modal" aria-labelledby="ai-plan-view-title" class="pm-ai-dialog rounded-2xl p-0 pm-dialog-xl shadow-2xl backdrop:bg-slate-900/50">
        <div class="p-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <h2 id="ai-plan-view-title" class="text-lg font-bold text-slate-800">Plan</h2>
                <button type="button" onclick="document.getElementById('ai-plan-view-modal').close()"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div id="ai-plan-view-content" class="text-sm whitespace-pre-line leading-relaxed max-h-[58vh] overflow-y-auto pr-1"></div>
            <div class="pm-ai-modal-actions mt-5 pt-4 border-t border-slate-100 flex flex-wrap justify-end gap-2">
                <button type="button" onclick="document.getElementById('ai-plan-view-modal').close()"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Close
                </button>
                <a id="ai-plan-view-pdf-link" href="#" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm font-semibold">
                    <i class="fa-solid fa-file-pdf"></i> Open PDF
                </a>
                <a id="ai-plan-download-pdf-link" href="#" class="hidden" aria-hidden="true" tabindex="-1"></a>
            </div>
        </div>
    </dialog>

    {{-- Delete confirmation modal same pattern as crud/index.blade.php. --}}
    <dialog id="ai-plan-delete-modal" aria-labelledby="ai-plan-delete-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <h2 id="ai-plan-delete-title" class="text-lg font-bold text-slate-800">Delete plan?</h2>
        </div>
        <p id="ai-plan-delete-desc" class="text-sm text-slate-600 mb-5">This action cannot be undone.</p>
        <form method="POST" id="ai-plan-delete-form">
            @csrf
            @method('DELETE')
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('ai-plan-delete-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    <span>Delete</span>
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="ai-plan-bulk-delete-modal" aria-labelledby="ai-plan-bulk-delete-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <h2 id="ai-plan-bulk-delete-title" class="text-lg font-bold text-slate-800">Delete selected plans?</h2>
        </div>
        <p id="ai-plan-bulk-delete-desc" class="text-sm text-slate-600 mb-5">This action cannot be undone.</p>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('ai-plan-bulk-delete-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">Cancel</button>
            <button type="button" onclick="document.getElementById('ai-plan-bulk-form').submit()" class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete Selected
            </button>
        </div>
    </dialog>

    <script>
        function pmOpenAiPlanViewModal(dateLabel, content, previewUrl, downloadUrl) {
            document.getElementById('ai-plan-view-title').textContent = 'Plan ' + dateLabel;
            document.getElementById('ai-plan-view-content').textContent = content;
            document.getElementById('ai-plan-view-pdf-link').href = previewUrl || '#';
            document.getElementById('ai-plan-download-pdf-link').href = downloadUrl || '#';
            document.getElementById('ai-plan-view-modal').showModal();
        }

        function pmOpenAiPlanDeleteModal(actionUrl, dateLabel) {
            document.getElementById('ai-plan-delete-form').action = actionUrl;
            document.getElementById('ai-plan-delete-desc').textContent =
                'Delete the plan from ' + dateLabel + '? This action cannot be undone.';
            document.getElementById('ai-plan-delete-modal').showModal();
        }

        function pmUpdateAiPlanBulkBar(changedBox) {
            var boxes = Array.prototype.slice.call(
                document.querySelectorAll('.ai-plan-row-checkbox')
            );

            if (changedBox && changedBox.value) {
                document
                    .querySelectorAll(
                        '.ai-plan-row-checkbox[value="' + changedBox.value + '"]'
                    )
                    .forEach(function (peer) {
                        peer.checked = changedBox.checked;
                    });
            }

            var selectedValues = {};
            boxes.forEach(function (box) {
                if (box.checked) {
                    selectedValues[String(box.value)] = true;
                }
            });

            var selected = Object.keys(selectedValues);
            var bar = document.getElementById('ai-plan-bulk-bar');
            var counter = document.getElementById('ai-plan-selected-count');
            var selectAll = document.getElementById('ai-plan-select-all');
            if (counter) { counter.textContent = selected.length; }
            if (bar) { bar.classList.toggle('hidden', selected.length === 0); }
            if (selectAll) {
                var uniqueIds = {};
                boxes.forEach(function (box) { uniqueIds[String(box.value)] = true; });
                var totalUnique = Object.keys(uniqueIds).length;
                selectAll.checked = totalUnique > 0 && selected.length === totalUnique;
                selectAll.indeterminate = selected.length > 0 && selected.length < totalUnique;
            }
        }

        function pmToggleAllAiPlans(source) {
            document.querySelectorAll('.ai-plan-row-checkbox').forEach(function (box) {
                box.checked = source.checked;
            });
            pmUpdateAiPlanBulkBar();
        }

        function pmOpenAiPlanBulkDeleteModal() {
            var selectedIds = {};
            document
                .querySelectorAll('.ai-plan-row-checkbox:checked')
                .forEach(function (box) {
                    selectedIds[String(box.value)] = true;
                });

            var selected = Object.keys(selectedIds).length;

            if (!selected) {
                return;
            }

            document.getElementById('ai-plan-bulk-delete-desc').textContent =
                'You are about to delete '
                + selected
                + ' selected AI plan'
                + (selected === 1 ? '' : 's')
                + '. This action cannot be undone.';

            document
                .getElementById('ai-plan-bulk-delete-modal')
                .showModal();
        }

        function pmSelectAiTab(key) {
            document.querySelectorAll('.pm-ai-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-ai-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-ai-panel-' + key;
            });
            if (key === 'chart' && typeof pmInitAiChartIfNeeded === 'function') {
                pmInitAiChartIfNeeded();
            }
        }

        function pmAiTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-ai-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectAiTab(tabs[nextIndex]);
        }
    </script>
</div>
@endsection
