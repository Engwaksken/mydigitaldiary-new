@extends('layouts.app')

@section('title', 'Signatures')

@section('content')
    {{--
        PDF.js  renders every page of an uploaded PDF client-side for the
        signature-placement editor below. Picked a widely-used, stable
        version; if this exact version ever 404s on cdnjs (library
        versions do get pruned occasionally), bump the version number in
        both lines below to whatever's current.
    --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>

    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-signature text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Signatures</h1>
    </div>

    <div role="tablist" aria-label="Signature sections" class="flex gap-1 border-b border-slate-200 mb-6 max-w-3xl overflow-x-auto">
        <button type="button" role="tab" id="pm-sig-tab-mine" aria-controls="pm-sig-panel-mine"
                aria-selected="true" tabindex="0" data-tab="mine"
                onclick="pmSelectSigTab('mine')" onkeydown="pmSigTabKeydown(event, 'mine')"
                class="pm-sig-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-[var(--brand-1)] text-[var(--brand-1)]">
            <i class="fa-solid fa-signature" aria-hidden="true"></i>
            <span>My Signatures</span>
        </button>
        <button type="button" role="tab" id="pm-sig-tab-sign" aria-controls="pm-sig-panel-sign"
                aria-selected="false" tabindex="-1" data-tab="sign"
                onclick="pmSelectSigTab('sign')" onkeydown="pmSigTabKeydown(event, 'sign')"
                class="pm-sig-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
            <span>Sign a Document</span>
        </button>
        <button type="button" role="tab" id="pm-sig-tab-documents" aria-controls="pm-sig-panel-documents"
                aria-selected="false" tabindex="-1" data-tab="documents"
                onclick="pmSelectSigTab('documents')" onkeydown="pmSigTabKeydown(event, 'documents')"
                class="pm-sig-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
            <span>Signed Documents ({{ $documents->count() }})</span>
        </button>
    </div>

    <div id="pm-signature-inline-alert" class="mb-4 max-w-3xl" hidden></div>

    {{-- ================= MY SIGNATURES (the library) ================= --}}
    <div role="tabpanel" id="pm-sig-panel-mine" aria-labelledby="pm-sig-tab-mine" tabindex="0" class="pm-sig-panel max-w-3xl space-y-6">
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
            <h2 class="font-semibold text-slate-800 mb-1">Your Signatures</h2>
            <p class="text-sm text-slate-500 mb-4">
                Keep as many as you need  a full signature, initials, whatever you use. You'll pick
                one when signing a document.
            </p>

            @if ($signatures->isEmpty())
                <x-empty-state icon="fa-solid fa-signature" title="No signatures saved yet" message="Add one below to start signing documents." />
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    @foreach ($signatures as $signature)
                        <div class="border border-slate-200 rounded-lg p-4 flex flex-col items-center gap-3">
                            <img src="{{ $signature->dataUri() ?: $signature->url() }}" alt="{{ $signature->displayLabel() }}"
                                 class="h-16 w-full object-contain bg-white">
                            <p class="text-sm font-medium text-slate-700 truncate w-full text-center">{{ $signature->displayLabel() }}</p>
                            <form method="POST" action="{{ route('signature.signatures.destroy', $signature->id) }}"
                                  data-confirm="Remove this signature? This action cannot be undone." data-confirm-title="Delete signature?" data-confirm-text="Delete">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-rose-500 hover:underline">
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('signature.signatures.store') }}" enctype="multipart/form-data" class="border-t border-slate-100 pt-5" onsubmit="return pmPrepareSignatureSubmit(this)">
                @csrf
                <h3 class="text-sm font-semibold text-slate-700 mb-1">Create or upload an e-signature</h3>
                <p class="text-xs text-slate-500 mb-4">Sign naturally with your finger, stylus or digital pen, or upload an existing signature image.</p>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-slate-200 p-3 bg-slate-50">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Finger / Pen</p>
                                <p class="text-[11px] text-slate-500">Use a finger on touch screens or a stylus/mouse.</p>
                            </div>
                            <button type="button" onclick="pmClearSignaturePad()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600">Clear</button>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-slate-300 bg-white touch-none">
                            <canvas id="pm-signature-pad" class="block w-full" style="height:220px; touch-action:none; cursor:crosshair;" aria-label="Draw your signature"></canvas>
                        </div>
                        <input type="hidden" name="drawn_signature" id="pm-drawn-signature">
                        <p id="pm-signature-pad-hint" class="mt-2 text-[11px] text-slate-400">Sign inside the box. Pressure/stylus input is supported by the browser where available.</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-3 bg-white">
                        <p class="text-sm font-semibold text-slate-700 mb-1">Upload e-signature</p>
                        <p class="text-[11px] text-slate-500 mb-3">PNG with transparent background works best. JPG/JPEG/WEBP are also accepted.</p>
                        <label for="signature-file" class="block rounded-xl border-2 border-dashed border-slate-200 p-5 text-center cursor-pointer hover:border-[var(--brand-1)] transition-colors">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl text-[var(--brand-1)]" aria-hidden="true"></i>
                            <span class="mt-2 block text-sm font-medium text-slate-700">Choose signature image</span>
                            <span id="pm-signature-file-name" class="mt-1 block text-xs text-slate-400">No file selected</span>
                        </label>
                        <input type="file" id="signature-file" name="signature" accept="image/png,image/jpeg,image/webp" class="sr-only" onchange="pmSignatureFileChanged(this)">
                    </div>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                    <div class="flex-1">
                        <label for="signature-label" class="block text-xs font-semibold text-slate-600 mb-1">Signature name (optional)</label>
                        <input type="text" id="signature-label" name="label" value="{{ old('label') }}" placeholder="e.g. Full signature, Initials" class="pm-input">
                    </div>
                    <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all whitespace-nowrap">
                        <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i> Save Signature
                    </button>
                </div>

                @error('signature')<p role="alert" class="text-sm text-rose-600 mt-2">{{ $message }}</p>@enderror
                @error('drawn_signature')<p role="alert" class="text-sm text-rose-600 mt-2">{{ $message }}</p>@enderror
            </form>
        </div>
    </div>

    {{-- ================= SIGN A DOCUMENT ================= --}}
    <div role="tabpanel" id="pm-sig-panel-sign" aria-labelledby="pm-sig-tab-sign" tabindex="0" class="pm-sig-panel space-y-6" hidden>
        @if ($pendingPreview)
            <div class="max-w-3xl pm-card-bg shadow-sm border-2 border-[var(--brand-2)] rounded-xl p-6">
                <h2 class="font-semibold text-slate-800 mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-eye text-[var(--brand-1)]" aria-hidden="true"></i>
                    Preview  not saved yet
                </h2>
                <p class="text-sm text-slate-500 mb-4">
                    "{{ $pendingPreview['original_filename'] }}"
                    @if ($pendingPreview['was_stamped'])
                        with {{ count($pendingPreview['placements']) }} signature{{ count($pendingPreview['placements']) === 1 ? '' : 's' }} placed.
                    @else
                         the signatures could not be inserted automatically; the file is shown as uploaded.
                    @endif
                </p>

                @if (! $pendingPreview['was_stamped'] && ! empty($pendingPreview['stamp_error']))
                    <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm mb-4">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ $pendingPreview['stamp_error'] }}
                    </div>
                @endif

                <div class="border border-slate-200 rounded-lg overflow-hidden mb-4 bg-slate-50" style="height: 460px;">
                    @php
                        $previewUrl = route('signature.documents.preview-file');
                    @endphp
                    @if ($pendingPreview['mime_type'] === 'application/pdf')
                        <iframe src="{{ $previewUrl }}" class="w-full h-full" title="Document preview"></iframe>
                    @elseif (str_starts_with($pendingPreview['mime_type'], 'image/'))
                        <img src="{{ $previewUrl }}" alt="Document preview" class="w-full h-full object-contain">
                    @else
                        <div class="flex items-center justify-center h-full text-slate-400 text-sm">
                            No inline preview available for this file type.
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('signature.documents.confirm') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            Confirm &amp; Save
                        </button>
                    </form>
                    <form method="POST" action="{{ route('signature.documents.cancel') }}">
                        @csrf
                        <button type="submit" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">Start over</button>
                    </form>
                </div>
            </div>
        @else
            @if ($signatures->isEmpty())
                <div class="max-w-3xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                    <p class="text-sm text-slate-500">
                        <i class="fa-solid fa-circle-info text-slate-400" aria-hidden="true"></i>
                        Add a signature under "My Signatures" first, then come back here.
                    </p>
                </div>
            @else
                @if (! $gdAvailable || ! $fpdiAvailable)
                    <div class="max-w-3xl rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
                        <p class="font-medium mb-1"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Limited on this server right now</p>
                        <ul class="list-disc list-inside">
                            @if (! $gdAvailable)
                                <li>Image stamping is unavailable  the GD PHP extension isn't enabled.</li>
                            @endif
                            @if (! $fpdiAvailable)
                                <li>PDF stamping is unavailable  run <code class="bg-amber-100 px-1 rounded">composer require setasign/fpdi setasign/fpdf</code> on the server.</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <form id="pm-sign-form" method="POST" action="{{ route('signature.documents.preview') }}" enctype="multipart/form-data" onsubmit="return pmPrepareAndSubmit();">
                    @csrf

                    <div id="pm-sign-step-upload" class="max-w-3xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Add the document</label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label for="document" class="flex min-h-[72px] cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 hover:border-[var(--brand-1)]">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                <span><strong class="block text-sm text-slate-800">Upload document</strong><span class="text-xs text-slate-500">PDF or image from this device</span></span>
                            </label>
                            <button type="button" onclick="pmOpenDocumentScanner()" class="flex min-h-[72px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left hover:border-[var(--brand-1)]">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i class="fa-solid fa-camera"></i></span>
                                <span><strong class="block text-sm text-slate-800">Scan document</strong><span class="text-xs text-slate-500">Use your phone/tablet camera</span></span>
                            </button>
                        </div>
                        <input type="file" id="document" name="document" accept="image/*,application/pdf" class="sr-only" onchange="pmLoadDocumentForEditing(this)">
                        <input type="file" id="pm-scan-document" accept="image/*" capture="environment" class="sr-only" onchange="pmUseScannedDocument(this)">
                        <p id="pm-document-name" class="mt-2 text-xs font-medium text-slate-600"></p>
                        <p class="text-xs text-slate-400">Max 10MB. After you choose a file, the editable page preview appears below.</p>
                        <div id="pm-sign-loading" class="hidden mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            <i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>
                            <span id="pm-sign-loading-text">Loading document preview...</span>
                        </div>
                        <div id="pm-sign-notice" class="hidden mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800"></div>

                        @error('document')
                            <p role="alert" class="text-sm text-rose-600 mt-3">{{ $message }}</p>
                        @enderror
                        @error('placements')
                            <p role="alert" class="text-sm text-rose-600 mt-3">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Everything below only appears once a document is loaded — see pmLoadDocumentForEditing(). --}}
                    <div id="pm-sign-editor" class="hidden mt-4">
                        <div class="lg:flex lg:gap-6 lg:items-start">
                            {{-- Signature palette — drag any of these onto a page. On touch
                                 devices (no real drag-and-drop), tapping one arms it, then
                                 tapping a page drops it there — see pmHandlePageTap(). --}}
                            <div class="lg:w-56 lg:shrink-0 mb-4 lg:mb-0 lg:sticky lg:top-4">
                                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-4">
                                    <h2 class="text-sm font-semibold text-slate-700 mb-3">Your Signatures</h2>
                                    <p class="text-xs text-slate-400 mb-3">Drag onto a page below. On a phone or tablet, tap one, then tap where it should go.</p>
                                    <div class="grid grid-cols-3 lg:grid-cols-2 gap-2">
                                        @foreach ($signatures as $signature)
                                            <img src="{{ $signature->dataUri() ?: $signature->url() }}" alt="{{ $signature->displayLabel() }}"
                                                 data-signature-id="{{ $signature->id }}"
                                                 draggable="true"
                                                 class="pm-sig-palette-item h-12 w-full object-contain border-2 border-slate-200 rounded-lg p-1 cursor-grab bg-white hover:border-[var(--brand-1)] transition-colors"
                                                 ondragstart="pmHandlePaletteDragStart(event)"
                                                 onclick="pmArmSignature({{ $signature->id }}, @js($signature->dataUri() ?: $signature->url()))">
                                        @endforeach
                                    </div>
                                    <p id="pm-armed-hint" class="hidden text-xs text-emerald-600 mt-3">
                                        <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> Tap a page to place it.
                                    </p>
                                </div>

                                <button type="submit" id="pm-apply-button"
                                        class="w-full mt-3 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                                    Apply Signatures &amp; Preview
                                </button>
                                <p id="pm-placement-count" class="text-xs text-slate-400 text-center mt-2">No signatures placed yet.</p>
                            </div>

                            {{-- Pages render here — one .pm-page-container per page, each
                                 holding a canvas/img and any placements dropped onto it. --}}
                            <div id="pm-pages-container" class="flex-1 space-y-4 max-w-full overflow-x-auto"></div>
                        </div>
                    </div>

                    <input type="hidden" name="placements" id="pm-placements-input">
                </form>
            @endif
        @endif
    </div>

    {{-- ================= SIGNED DOCUMENTS ================= --}}
    <div role="tabpanel" id="pm-sig-panel-documents" aria-labelledby="pm-sig-tab-documents" tabindex="0" class="pm-sig-panel max-w-3xl" hidden>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-blue-400 p-4">
                <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-2">
                    <i class="fa-solid fa-cloud-arrow-up text-sm" aria-hidden="true"></i>
                </div>
                <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Today's Uploads</p>
                <p class="text-xl font-bold text-slate-800 truncate">{{ $stats['today'] }}</p>
            </div>
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-emerald-400 p-4">
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                    <i class="fa-solid fa-file-circle-check text-sm" aria-hidden="true"></i>
                </div>
                <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Signed</p>
                <p class="text-xl font-bold text-slate-800 truncate">{{ $stats['signed'] }}</p>
            </div>
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-slate-400 p-4">
                <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center mb-2">
                    <i class="fa-solid fa-folder-open text-sm" aria-hidden="true"></i>
                </div>
                <p class="text-xs text-slate-500 uppercase tracking-wide truncate">Total Documents</p>
                <p class="text-xl font-bold text-slate-800 truncate">{{ $stats['total'] }}</p>
            </div>
        </div>

        <form method="GET" action="{{ route('signature.show') }}#documents" class="flex flex-wrap items-end gap-3 mb-4" onsubmit="return pmSubmitDocFilterForm(event);">
            <div class="flex-1 min-w-[180px] max-w-xs">
                <label for="doc-q" class="sr-only">Search documents</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                    <input type="search" id="doc-q" name="doc_q" value="{{ $docSearch }}" placeholder="Search by filename..." class="pm-input pl-9 text-sm">
                </div>
            </div>

            <div>
                <label for="doc-period" class="sr-only">Filter by date</label>
                <select id="doc-period" name="doc_period" onchange="pmToggleDocDateRange(this)" class="pm-input text-sm">
                    <option value="" @selected(!$docPeriod)>All time</option>
                    <option value="daily" @selected($docPeriod === 'daily')>Today</option>
                    <option value="weekly" @selected($docPeriod === 'weekly')>This week</option>
                    <option value="monthly" @selected($docPeriod === 'monthly')>This month</option>
                    <option value="annual" @selected($docPeriod === 'annual')>This year</option>
                    <option value="range" @selected($docPeriod === 'range')>Custom range...</option>
                </select>
            </div>

            <div id="doc-date-range" class="flex items-end gap-2" style="{{ $docPeriod === 'range' ? '' : 'display: none;' }}">
                <input type="date" name="doc_from" value="{{ $docFrom }}" class="pm-input text-sm">
                <span class="text-slate-400 text-sm pb-2">to</span>
                <input type="date" name="doc_to" value="{{ $docTo }}" class="pm-input text-sm">
            </div>

            <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
            @if ($docSearch || $docPeriod)
                <a href="{{ route('signature.show') }}#documents" onclick="sessionStorage.setItem('pmSigActiveTab', 'documents');" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">Clear</a>
            @endif
        </form>

        <script>
            function pmToggleDocDateRange(select) {
                var wrapper = document.getElementById('doc-date-range');
                if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
            }
            // Filtering re-submits the whole page (GET), which would
            // otherwise dump the user back on "My Signatures" — this
            // keeps them on the Documents tab across that reload.
            function pmSubmitDocFilterForm() {
                sessionStorage.setItem('pmSigActiveTab', 'documents');
                return true;
            }
        </script>

        <div id="pm-sig-bulk-bar" class="hidden items-center gap-3 mb-3 bg-slate-800 text-white rounded-lg px-4 py-2.5 text-sm">
            <span id="pm-sig-bulk-count">0 selected</span>
            <button type="button" onclick="pmShareSelectedDocuments('email')" class="ms-auto text-white/80 hover:text-white">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email
            </button>
            <button type="button" onclick="pmShareSelectedDocuments('whatsapp')" class="text-white/80 hover:text-white">
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp
            </button>
            <form method="POST" action="{{ route('signature.documents.bulk-destroy') }}" id="pm-sig-bulk-delete-form"
                  data-confirm="Remove all selected documents? This action cannot be undone." data-confirm-title="Delete selected documents?" data-confirm-text="Delete selected">
                @csrf
                <button type="submit" class="text-rose-300 hover:text-rose-100">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete Selected
                </button>
            </form>
        </div>

        <div class="pm-documents-scroll pm-card-bg shadow-sm border border-slate-100 rounded-xl" role="region" aria-label="Signed documents" tabindex="0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="min-width: 900px; table-layout: auto;">
                    <caption class="sr-only">Documents you've signed, with download, remove, and multi-select bulk actions.</caption>
                    <thead class="bg-slate-50 text-left border-b border-slate-100">
                        <tr>
                            <th scope="col" class="px-4 py-3 w-12 whitespace-nowrap">
                                <label class="sr-only" for="pm-sig-select-all">Select all documents</label>
                                <input type="checkbox" id="pm-sig-select-all" onchange="pmToggleAllDocuments(this)" class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]">
                            </th>
                            <th scope="col" class="px-4 py-3 min-w-[260px] font-semibold text-slate-500 text-xs uppercase tracking-wide whitespace-nowrap">Document</th>
                            <th scope="col" class="px-4 py-3 min-w-[150px] font-semibold text-slate-500 text-xs uppercase tracking-wide whitespace-nowrap">Signatures Placed</th>
                            <th scope="col" class="px-4 py-3 min-w-[90px] font-semibold text-slate-500 text-xs uppercase tracking-wide whitespace-nowrap">Pages</th>
                            <th scope="col" class="px-4 py-3 min-w-[150px] font-semibold text-slate-500 text-xs uppercase tracking-wide whitespace-nowrap">Date</th>
                            <th scope="col" class="px-4 py-3 min-w-[220px] whitespace-nowrap"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $doc)
                            <tr>
                                <td class="px-4 py-3 align-top whitespace-nowrap">
                                    <label class="sr-only" for="pm-sig-doc-{{ $doc->id }}">Select {{ $doc->original_filename }}</label>
                                    <input type="checkbox" id="pm-sig-doc-{{ $doc->id }}" name="document_ids[]" value="{{ $doc->id }}"
                                           form="pm-sig-bulk-delete-form"
                                           data-filename="{{ $doc->original_filename }}"
                                           data-url="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('signature.documents.shared', now()->addDays(30), ['signedDocument' => $doc->id]) }}"
                                           class="pm-sig-doc-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]"
                                           onchange="pmUpdateBulkBar()">
                                </td>
                                <td class="px-4 py-3 align-top min-w-[260px]">
                                    <div class="max-w-[320px] whitespace-normal break-words leading-5 text-slate-700">
                                        <i class="fa-solid {{ $doc->was_stamped ? 'fa-file-circle-check text-emerald-500' : 'fa-file text-slate-400' }} mr-1.5" aria-hidden="true"></i>
                                        {{ $doc->original_filename }}
                                        @if (! $doc->was_stamped && $doc->stamp_error)
                                            <span class="block text-xs text-amber-600 mt-0.5">
                                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $doc->stamp_error }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top whitespace-nowrap">{{ $doc->placements->count() }}</td>
                                <td class="px-4 py-3 align-top whitespace-nowrap">{{ $doc->placements->pluck('page_number')->unique()->sort()->implode(', ') ?: '—' }}</td>
                                <td class="px-4 py-3 align-top whitespace-nowrap">{{ $doc->signed_at?->format('Y-m-d H:i') ?? $doc->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                                    <a href="{{ route('signature.documents.view', $doc->id) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:underline mr-3">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i> View
                                    </a>
                                    <a href="{{ route('signature.documents.download', $doc->id) }}" class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:underline mr-3">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i> Download
                                    </a>
                                    <a href="mailto:?subject={{ urlencode('Signed document: ' . $doc->original_filename) }}&body={{ urlencode('Here is the document: ' . \Illuminate\Support\Facades\URL::temporarySignedRoute('signature.documents.shared', now()->addDays(30), ['signedDocument' => $doc->id])) }}" class="inline-flex items-center text-[var(--brand-1)] hover:underline mr-3" aria-label="Email document">
                                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                    </a>
                                    <a href="https://wa.me/?text={{ urlencode($doc->original_filename . ': ' . \Illuminate\Support\Facades\URL::temporarySignedRoute('signature.documents.shared', now()->addDays(30), ['signedDocument' => $doc->id])) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-[var(--brand-1)] hover:underline mr-3" aria-label="Share on WhatsApp">
                                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                                    </a>
                                    <form action="{{ route('signature.documents.destroy', $doc->id) }}" method="POST" class="inline" data-confirm="Remove this signed document? This action cannot be undone." data-confirm-title="Delete document?" data-confirm-text="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-slate-500">No signed documents yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-400 sm:hidden">
            <i class="fa-solid fa-arrows-left-right mr-1" aria-hidden="true"></i>
            Swipe sideways to view all document columns.
        </p>

        @if ($documents->hasPages())
            <div class="mt-4">{{ $documents->links() }}</div>
        @endif
    </div>

    <script>
        function pmSelectSigTab(key) {
            document.querySelectorAll('.pm-sig-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-sig-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-sig-panel-' + key;
            });
        }

        function pmSigTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-sig-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectSigTab(tabs[nextIndex]);
        }

        function pmToggleAllDocuments(selectAllCheckbox) {
            document.querySelectorAll('.pm-sig-doc-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            pmUpdateBulkBar();
        }

        function pmSelectedDocumentCheckboxes() {
            return Array.prototype.filter.call(document.querySelectorAll('.pm-sig-doc-checkbox'), function (c) { return c.checked; });
        }

        function pmUpdateBulkBar() {
            var selected = pmSelectedDocumentCheckboxes();
            var bar = document.getElementById('pm-sig-bulk-bar');
            var countLabel = document.getElementById('pm-sig-bulk-count');

            bar.classList.toggle('hidden', selected.length === 0);
            bar.classList.toggle('flex', selected.length > 0);
            countLabel.textContent = selected.length + ' selected';

            var selectAll = document.getElementById('pm-sig-select-all');
            var allCheckboxes = document.querySelectorAll('.pm-sig-doc-checkbox');
            selectAll.checked = allCheckboxes.length > 0 && selected.length === allCheckboxes.length;
        }

        // Bulk share can't actually attach files — mailto:/wa.me only
        // carry text — so this builds one message listing every selected
        // document's name and link, same idea as the single-document
        // share buttons just combined into one message.
        function pmShareSelectedDocuments(channel) {
            var selected = pmSelectedDocumentCheckboxes();
            if (selected.length === 0) { return; }

            var lines = selected.map(function (c) { return c.dataset.filename + ': ' + c.dataset.url; });
            var body = lines.join('\n');

            if (channel === 'email') {
                window.location.href = 'mailto:?subject=' + encodeURIComponent(selected.length + ' signed document(s)') + '&body=' + encodeURIComponent(body);
            } else {
                window.open('https://wa.me/?text=' + encodeURIComponent(body), '_blank', 'noopener,noreferrer');
            }
        }

    </script>

    <style>
        .pm-documents-scroll {
            overflow: hidden;
        }

        .pm-documents-scroll > .overflow-x-auto {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-inline: contain;
        }

        @media (max-width: 640px) {
            #pm-sig-panel-documents {
                max-width: 100%;
                min-width: 0;
            }

            #pm-pages-container {
                width: 100%;
                min-width: 0;
                overflow-x: visible;
            }

            .pm-page-container {
                width: 100% !important;
                max-width: 100% !important;
            }
        }

        /*
         * Keep the rendered document underneath signature placements.
         * The page bitmap fills the stable page box created in JavaScript.
         */
        .pm-page-container > .pm-rendered-page,
        .pm-page-container > canvas {
            position: absolute;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
            z-index: 0;
            background: #fff;
        }

        .pm-page-container > .pm-placement {
            z-index: 10;
        }
    </style>

    <script>
        // ===== Multi-page signature placement editor =====
        // Renders every page of the uploaded document (PDF via PDF.js,
        // reading the file straight from the browser's memory — no
        // upload round-trip needed just to preview it; a single image
        // is treated as one "page"). Signatures are dragged from the
        // palette (or tap-to-arm/tap-to-place on touch devices, since
        // real HTML5 drag-and-drop doesn't work well there) onto any
        // page, then can be moved, resized, or removed before
        // "Apply Signatures & Preview" sends the final list to the
        // server for actual stamping.
        var pmArmedSignature = null;
        var pmPlacementCounter = 0;
        var pmActiveInteraction = null; // { el, mode: 'move'|'resize', ... }

        var pmLocalDocumentObjectUrl = null;

        function pmSetSignLoading(show, message) {
            var loading = document.getElementById('pm-sign-loading');
            var text = document.getElementById('pm-sign-loading-text');
            if (!loading) { return; }
            if (text && message) { text.textContent = message; }
            loading.classList.toggle('hidden', !show);
        }

        function pmRevokeLocalDocumentUrl() {
            if (pmLocalDocumentObjectUrl) {
                try { URL.revokeObjectURL(pmLocalDocumentObjectUrl); } catch (e) {}
                pmLocalDocumentObjectUrl = null;
            }
        }

        function pmShowPreviewNotice(message) {
            var note = document.getElementById('pm-sign-notice');
            if (!note) { return; }
            note.textContent = message || '';
            note.classList.toggle('hidden', !message);
        }

        function pmClearDocumentSelection() {
            var documentInput = document.getElementById('document');
            var scannerInput = document.getElementById('pm-scan-document');
            var name = document.getElementById('pm-document-name');
            var editor = document.getElementById('pm-sign-editor');
            var pagesContainer = document.getElementById('pm-pages-container');

            if (documentInput) {
                documentInput.value = '';
                if (!documentInput.getAttribute('name')) { documentInput.setAttribute('name', 'document'); }
            }
            if (scannerInput) {
                scannerInput.value = '';
                scannerInput.removeAttribute('name');
            }
            if (name) { name.textContent = ''; }
            if (pagesContainer) { pagesContainer.innerHTML = ''; }
            if (editor) { editor.classList.add('hidden'); }
            pmSetSignLoading(false);
            pmShowPreviewNotice('');
            pmRevokeLocalDocumentUrl();
            pmArmedSignature = null;
            pmUpdatePlacementCount();
        }

        function pmRenderPdfFallbackMessage(error) {
            pmSetSignLoading(false);
            var message = 'The editable PDF preview could not be rendered.';
            if (error && error.message) { message += ' ' + error.message; }
            pmShowPreviewNotice(message + ' Check the connection, then reload or choose the file again.');
        }

        function pmLoadDocumentForEditing(input) {
            var file = input && input.files && input.files[0];
            var pagesContainer = document.getElementById('pm-pages-container');
            var editor = document.getElementById('pm-sign-editor');
            var name = document.getElementById('pm-document-name');
            if (!file || !pagesContainer || !editor) { return; }

            if (name) { name.textContent = 'Selected: ' + file.name; }
            pmShowPreviewNotice('');
            pagesContainer.innerHTML = '';
            editor.classList.add('hidden');
            pmSetSignLoading(true, 'Preparing editable document pages...');

            var isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');

            if (isPdf) {
                var reader = new FileReader();
                reader.onerror = function () {
                    pmRenderPdfFallbackMessage(new Error('The browser could not read this PDF.'));
                };
                reader.onload = function () {
                    if (typeof pdfjsLib === 'undefined') {
                        pmRenderPdfFallbackMessage(new Error('PDF.js did not load.'));
                        return;
                    }

                    var bytes = new Uint8Array(reader.result);

                    /*
                     * Render PDF pages to an off-screen canvas first, then place the
                     * finished bitmap into the editor as an <img>. This is more
                     * reliable than keeping the PDF.js canvas itself inside the
                     * draggable/resizable placement layer. On some browsers the
                     * canvas was being laid out correctly but painted as a blank
                     * white page after the surrounding flex/aspect-ratio layout
                     * recalculated.
                     */
                    pdfjsLib.getDocument({ data: bytes }).promise.then(function (pdf) {
                        editor.classList.remove('hidden');
                        pmSetSignLoading(true, 'Rendering page 1 of ' + pdf.numPages + '...');

                        var renderPage = function (pageNum) {
                            if (pageNum > pdf.numPages) {
                                pmSetSignLoading(false);
                                pmUpdatePlacementCount();
                                return Promise.resolve();
                            }

                            pmSetSignLoading(true, 'Rendering page ' + pageNum + ' of ' + pdf.numPages + '...');

                            return pdf.getPage(pageNum).then(function (page) {
                                var baseViewport = page.getViewport({ scale: 1 });

                                var availableWidth = Math.max(
                                    280,
                                    Math.min(
                                        760,
                                        (pagesContainer.clientWidth || window.innerWidth || 760) - 8
                                    )
                                );

                                var cssScale = availableWidth / baseViewport.width;
                                var cssViewport = page.getViewport({ scale: cssScale });

                                /*
                                 * Render at device-pixel-ratio resolution for a crisp
                                 * preview, but display at CSS size. The page container
                                 * uses the CSS dimensions, so signature percentages
                                 * remain accurate.
                                 */
                                var pixelRatio = Math.min(Math.max(window.devicePixelRatio || 1, 1), 2);
                                var renderViewport = page.getViewport({ scale: cssScale * pixelRatio });

                                var renderCanvas = document.createElement('canvas');
                                renderCanvas.width = Math.max(1, Math.ceil(renderViewport.width));
                                renderCanvas.height = Math.max(1, Math.ceil(renderViewport.height));

                                var renderContext = renderCanvas.getContext('2d', {
                                    alpha: false,
                                    willReadFrequently: false
                                });

                                if (!renderContext) {
                                    throw new Error('Your browser could not create the PDF preview canvas.');
                                }

                                /*
                                 * Explicit white background prevents transparent PDF
                                 * pages from appearing blank against the white editor.
                                 */
                                renderContext.save();
                                renderContext.fillStyle = '#ffffff';
                                renderContext.fillRect(0, 0, renderCanvas.width, renderCanvas.height);
                                renderContext.restore();

                                return page.render({
                                    canvasContext: renderContext,
                                    viewport: renderViewport,
                                    background: 'rgb(255,255,255)'
                                }).promise.then(function () {
                                    var pageImage = document.createElement('img');
                                    pageImage.alt = 'PDF page ' + pageNum;
                                    pageImage.draggable = false;
                                    pageImage.className = 'pm-rendered-page block w-full h-full object-fill select-none pointer-events-none';
                                    pageImage.src = renderCanvas.toDataURL('image/png');

                                    var container = pmCreatePageContainer(
                                        pageNum,
                                        cssViewport.width,
                                        cssViewport.height
                                    );

                                    container.appendChild(pageImage);
                                    pagesContainer.appendChild(container);

                                    /*
                                     * Release the large backing canvas as soon as its
                                     * bitmap has been copied into the image to keep
                                     * multi-page PDFs from consuming excessive memory.
                                     */
                                    renderCanvas.width = 1;
                                    renderCanvas.height = 1;

                                    return renderPage(pageNum + 1);
                                });
                            }).catch(function (err) {
                                pmSetSignLoading(false);
                                pmShowPreviewNotice(
                                    'Page ' + pageNum + ' could not be rendered for signature placement. ' +
                                    (err && err.message ? err.message : 'Please try the document again.')
                                );
                                throw err;
                            });
                        };

                        return renderPage(1);
                    }).catch(function (err) {
                        pmRenderPdfFallbackMessage(err);
                    });
                };
                reader.readAsArrayBuffer(file);
                return;
            }

            if ((file.type || '').indexOf('image/') === 0) {
                var img = new Image();
                img.onload = function () {
                    var width = img.naturalWidth || img.width || 1000;
                    var height = img.naturalHeight || img.height || 1400;
                    var container = pmCreatePageContainer(1, width, height);
                    var displayImg = document.createElement('img');
                    displayImg.src = img.src;
                    displayImg.alt = 'Document page 1';
                    displayImg.className = 'block w-full h-full object-contain bg-white';
                    container.appendChild(displayImg);
                    pagesContainer.appendChild(container);

                    editor.classList.remove('hidden');
                    pmSetSignLoading(false);
                };
                img.onerror = function () {
                    pmSetSignLoading(false);
                    pmShowPreviewNotice('The image was selected but could not be decoded by this browser. Try JPG, PNG or WebP.');
                };
                pmRevokeLocalDocumentUrl();
                pmLocalDocumentObjectUrl = URL.createObjectURL(file);
                img.src = pmLocalDocumentObjectUrl;
                return;
            }

            pmSetSignLoading(false);
            pmShowPreviewNotice('This file type can be uploaded, but signature placement preview supports PDF and image files only.');
        }

        function pmCreatePageContainer(pageNumber, naturalWidth, naturalHeight) {
            var wrapper = document.createElement('div');
            wrapper.className = 'pm-page-container relative border border-slate-200 rounded-lg overflow-hidden bg-white mx-auto shadow-sm';
            wrapper.dataset.pageNumber = pageNumber;

            var safeWidth = Math.max(1, Number(naturalWidth) || 1);
            var safeHeight = Math.max(1, Number(naturalHeight) || 1);
            var parentWidth = Math.max(
                280,
                Math.min(
                    760,
                    (document.getElementById('pm-pages-container')?.clientWidth || window.innerWidth || 760) - 8
                )
            );
            var displayWidth = Math.min(parentWidth, safeWidth);
            var displayHeight = displayWidth * (safeHeight / safeWidth);

            /*
             * Give the page a real width and height instead of relying only
             * on aspect-ratio. This prevents flex/grid layout from producing
             * a visible empty box while the child canvas/image has no stable
             * painted area.
             */
            wrapper.style.width = '100%';
            wrapper.style.maxWidth = Math.round(displayWidth) + 'px';
            wrapper.style.height = 'auto';
            wrapper.style.aspectRatio = safeWidth + ' / ' + safeHeight;
            wrapper.style.minHeight = Math.max(180, Math.round(displayHeight)) + 'px';
            wrapper.style.touchAction = 'none';

            wrapper.addEventListener('dragover', function (e) { e.preventDefault(); });
            wrapper.addEventListener('drop', function (e) { pmHandlePageDrop(e, wrapper); });
            wrapper.addEventListener('click', function (e) { pmHandlePageTap(e, wrapper); });

            var label = document.createElement('div');
            label.className = 'absolute top-1 left-1 bg-slate-800/70 text-white text-xs px-2 py-0.5 rounded z-20 pointer-events-none';
            label.textContent = 'Page ' + pageNumber;
            wrapper.appendChild(label);

            return wrapper;
        }

        function pmHandlePaletteDragStart(event) {
            event.dataTransfer.setData('text/plain', event.target.dataset.signatureId);
        }

        function pmArmSignature(id, url) {
            pmArmedSignature = { id: id, url: url };
            document.getElementById('pm-armed-hint').classList.remove('hidden');
        }

        function pmHandlePageDrop(event, container) {
            event.preventDefault();
            var signatureId = event.dataTransfer.getData('text/plain');
            if (!signatureId) { return; }
            var paletteImg = document.querySelector('.pm-sig-palette-item[data-signature-id="' + signatureId + '"]');
            if (!paletteImg) { return; }

            var rect = container.getBoundingClientRect();
            var x = event.clientX - rect.left;
            var y = event.clientY - rect.top;
            pmCreatePlacement(container, signatureId, paletteImg.src, x, y);
        }

        function pmHandlePageTap(event, container) {
            // Ignore taps on an existing placement (its own handlers deal
            // with those) — only bare page taps place a new signature.
            if (event.target.closest('.pm-placement')) { return; }
            if (!pmArmedSignature) { return; }

            var rect = container.getBoundingClientRect();
            var x = event.clientX - rect.left;
            var y = event.clientY - rect.top;
            pmCreatePlacement(container, pmArmedSignature.id, pmArmedSignature.url, x, y);

            pmArmedSignature = null;
            document.getElementById('pm-armed-hint').classList.add('hidden');
        }

        function pmCreatePlacement(container, signatureId, signatureUrl, dropX, dropY) {
            var containerRect = container.getBoundingClientRect();
            var defaultWidth = containerRect.width * 0.28;
            var defaultHeight = containerRect.height * 0.12;

            var left = Math.max(0, Math.min(dropX - defaultWidth / 2, containerRect.width - defaultWidth));
            var top = Math.max(0, Math.min(dropY - defaultHeight / 2, containerRect.height - defaultHeight));

            var placement = document.createElement('div');
            placement.className = 'pm-placement absolute border-2 border-[var(--brand-2)] bg-white/10 group';
            placement.dataset.signatureId = signatureId;
            placement.dataset.placementId = ++pmPlacementCounter;
            placement.style.left = left + 'px';
            placement.style.top = top + 'px';
            placement.style.width = defaultWidth + 'px';
            placement.style.height = defaultHeight + 'px';
            placement.style.touchAction = 'none';

            var img = document.createElement('img');
            img.src = signatureUrl;
            img.className = 'w-full h-full object-contain pointer-events-none';
            placement.appendChild(img);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
            removeBtn.setAttribute('aria-label', 'Remove this signature');
            removeBtn.className = 'absolute -top-3 -right-3 w-6 h-6 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center shadow';
            removeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                placement.remove();
                pmUpdatePlacementCount();
            });
            placement.appendChild(removeBtn);

            var resizeHandle = document.createElement('div');
            resizeHandle.className = 'absolute -bottom-1.5 -right-1.5 w-4 h-4 rounded-full bg-[var(--brand-2)] border-2 border-white shadow cursor-nwse-resize';
            resizeHandle.addEventListener('mousedown', function (e) { pmStartInteraction(e, placement, 'resize'); });
            resizeHandle.addEventListener('touchstart', function (e) { pmStartInteraction(e, placement, 'resize'); }, { passive: false });
            placement.appendChild(resizeHandle);

            placement.addEventListener('mousedown', function (e) {
                if (e.target === resizeHandle || e.target === removeBtn) { return; }
                pmStartInteraction(e, placement, 'move');
            });
            placement.addEventListener('touchstart', function (e) {
                if (e.target === resizeHandle || e.target === removeBtn) { return; }
                pmStartInteraction(e, placement, 'move');
            }, { passive: false });

            container.appendChild(placement);
            pmUpdatePlacementCount();
        }

        function pmStartInteraction(event, el, mode) {
            event.preventDefault();
            event.stopPropagation();
            var point = event.touches ? event.touches[0] : event;
            pmActiveInteraction = {
                el: el,
                mode: mode,
                startX: point.clientX,
                startY: point.clientY,
                startLeft: parseFloat(el.style.left),
                startTop: parseFloat(el.style.top),
                startWidth: parseFloat(el.style.width),
                startHeight: parseFloat(el.style.height),
                aspect: parseFloat(el.style.width) / parseFloat(el.style.height),
            };
        }

        function pmMoveInteraction(event) {
            if (!pmActiveInteraction) { return; }
            var point = event.touches ? event.touches[0] : event;
            var state = pmActiveInteraction;
            var container = state.el.parentElement;
            var containerRect = container.getBoundingClientRect();
            var dx = point.clientX - state.startX;
            var dy = point.clientY - state.startY;

            if (state.mode === 'move') {
                var newLeft = Math.max(0, Math.min(state.startLeft + dx, containerRect.width - state.startWidth));
                var newTop = Math.max(0, Math.min(state.startTop + dy, containerRect.height - state.startHeight));
                state.el.style.left = newLeft + 'px';
                state.el.style.top = newTop + 'px';
            } else {
                // Resize keeps the signature's aspect ratio locked 
                // free-distort resize would make signatures look warped,
                // which nobody actually wants for something meant to
                // look like handwriting.
                var newWidth = Math.max(30, state.startWidth + dx);
                newWidth = Math.min(newWidth, containerRect.width - state.startLeft);
                var newHeight = newWidth / state.aspect;
                if (state.startTop + newHeight > containerRect.height) {
                    newHeight = containerRect.height - state.startTop;
                    newWidth = newHeight * state.aspect;
                }
                state.el.style.width = newWidth + 'px';
                state.el.style.height = newHeight + 'px';
            }
        }

        function pmEndInteraction() {
            pmActiveInteraction = null;
        }

        document.addEventListener('mousemove', pmMoveInteraction);
        document.addEventListener('touchmove', function (e) {
            if (pmActiveInteraction) { e.preventDefault(); }
            pmMoveInteraction(e);
        }, { passive: false });
        document.addEventListener('mouseup', pmEndInteraction);
        document.addEventListener('touchend', pmEndInteraction);

        function pmUpdatePlacementCount() {
            var count = document.querySelectorAll('.pm-placement').length;
            var label = document.getElementById('pm-placement-count');
            if (label) {
                label.textContent = count === 0
                    ? 'No signatures placed yet.'
                    : count + ' signature' + (count === 1 ? '' : 's') + ' placed.';
            }
        }

        function pmPrepareAndSubmit() {
            var placements = [];
            document.querySelectorAll('.pm-page-container').forEach(function (container) {
                var pageNumber = parseInt(container.dataset.pageNumber, 10);
                var containerRect = container.getBoundingClientRect();

                container.querySelectorAll('.pm-placement').forEach(function (el) {
                    placements.push({
                        signature_id: el.dataset.signatureId,
                        page_number: pageNumber,
                        x_percent: (parseFloat(el.style.left) / containerRect.width) * 100,
                        y_percent: (parseFloat(el.style.top) / containerRect.height) * 100,
                        width_percent: (parseFloat(el.style.width) / containerRect.width) * 100,
                        height_percent: (parseFloat(el.style.height) / containerRect.height) * 100,
                    });
                });
            });

            if (placements.length === 0) {
                pmShowSignatureInlineAlert('Drag at least one signature onto the document before applying.');
                return false;
            }

            document.getElementById('pm-placements-input').value = JSON.stringify(placements);
            return true;
        }

        // Land on "Sign a Document" automatically when there's a preview
        // waiting; otherwise restore whichever tab the user was on
        // before a filter/search reload (see pmSubmitDocFilterForm).
        document.addEventListener('DOMContentLoaded', function () {
            @if ($pendingPreview)
                pmSelectSigTab('sign');
            @else
                var savedTab = sessionStorage.getItem('pmSigActiveTab');
                if (savedTab) {
                    sessionStorage.removeItem('pmSigActiveTab');
                    pmSelectSigTab(savedTab);
                }
            @endif
        });
    </script>

<script>
function pmOpenDocumentScanner() {
    const scanner = document.getElementById('pm-scan-document');
    if (scanner) scanner.click();
}
function pmUseScannedDocument(scannerInput) {
    if (!scannerInput || !scannerInput.files || !scannerInput.files.length) return;
    const documentInput = document.getElementById('document');
    if (!documentInput) return;

    documentInput.setAttribute('name', 'document');
    scannerInput.removeAttribute('name');

    try {
        const transfer = new DataTransfer();
        transfer.items.add(scannerInput.files[0]);
        documentInput.files = transfer.files;
        pmLoadDocumentForEditing(documentInput);
    } catch (error) {
        // Safari/older mobile browsers can make FileList read-only. Submit the
        // scanner input itself in that case, but use the same preview function.
        documentInput.removeAttribute('name');
        scannerInput.setAttribute('name', 'document');
        pmLoadDocumentForEditing(scannerInput);
    }

    const name = document.getElementById('pm-document-name');
    if (name) name.textContent = 'Scanned: ' + (scannerInput.files[0].name || 'camera image');
}
document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'document' && event.target.files && event.target.files[0]) {
        const name = document.getElementById('pm-document-name');
        if (name) name.textContent = 'Selected: ' + event.target.files[0].name;
    }
});
</script>

<script>
(function () {
    const canvas = document.getElementById('pm-signature-pad');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasInk = false;
    let last = null;

    function resize() {
        const rect = canvas.getBoundingClientRect();
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const snapshot = hasInk ? canvas.toDataURL('image/png') : null;
        canvas.width = Math.max(1, Math.round(rect.width * ratio));
        canvas.height = Math.max(1, Math.round(rect.height * ratio));
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2.4;
        if (snapshot) {
            const image = new Image();
            image.onload = () => ctx.drawImage(image, 0, 0, rect.width, rect.height);
            image.src = snapshot;
        }
    }

    function point(e) {
        const rect = canvas.getBoundingClientRect();
        return {x:e.clientX-rect.left,y:e.clientY-rect.top};
    }

    canvas.addEventListener('pointerdown', e => {
        drawing = true;
        canvas.setPointerCapture?.(e.pointerId);
        last = point(e);
        e.preventDefault();
    });
    canvas.addEventListener('pointermove', e => {
        if (!drawing) return;
        const p = point(e);
        const pressure = e.pressure && e.pressure > 0 ? e.pressure : .5;
        ctx.lineWidth = Math.max(1.5, Math.min(4.5, 1.6 + pressure * 3));
        ctx.beginPath(); ctx.moveTo(last.x,last.y); ctx.lineTo(p.x,p.y); ctx.stroke();
        last = p; hasInk = true; e.preventDefault();
    });
    ['pointerup','pointercancel','pointerleave'].forEach(name => canvas.addEventListener(name, () => { drawing=false; last=null; }));
    window.addEventListener('resize', resize);
    resize();

    window.pmClearSignaturePad = function () {
        const rect = canvas.getBoundingClientRect();
        ctx.clearRect(0,0,rect.width,rect.height);
        hasInk = false;
        document.getElementById('pm-drawn-signature').value = '';
    };
    window.pmSignatureFileChanged = function (input) {
        document.getElementById('pm-signature-file-name').textContent = input.files?.[0]?.name || 'No file selected';
    };
    window.pmPrepareSignatureSubmit = function (form) {
        const file = document.getElementById('signature-file');
        const hidden = document.getElementById('pm-drawn-signature');
        if (hasInk) hidden.value = canvas.toDataURL('image/png');
        if (!hasInk && (!file.files || !file.files.length)) {
            pmShowSignatureInlineAlert('Draw your signature with your finger/pen or upload an e-signature image.');
            return false;
        }
        return true;
    };
})();
</script>

<script>
function pmShowSignatureInlineAlert(message) {
    var container = document.getElementById('pm-signature-inline-alert');
    if (!container) return;
    container.hidden = false;
    container.innerHTML = '<div role="alert" class="pm-alert relative flex items-start gap-3 rounded-xl border px-4 py-3 text-sm bg-red-50 text-red-800 border-red-200"><i class="fa-solid fa-circle-exclamation mt-0.5 text-red-500" aria-hidden="true"></i><div class="flex-1 min-w-0"></div></div>';
    container.querySelector('.flex-1').textContent = message;
    container.scrollIntoView({behavior: 'smooth', block: 'nearest'});
}
</script>

@endsection
