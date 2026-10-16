@extends('layouts.app')

@section('title', 'Business Invoices')

@section('content')
    @php
        $restoreMode = old('_form_mode', 'create');
        $restoreInvoice = $restoreMode === 'edit' ? $invoices->firstWhere('id', old('_invoice_id')) : null;
        $restoreLines = old('line_items', $restoreInvoice?->line_items ?? [['description' => '', 'quantity' => 1, 'unit_price' => '']]);
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-file-invoice-dollar mr-2 text-emerald-600" aria-hidden="true"></i>Business invoices</h1>
                <p class="mt-1 text-sm text-slate-500">Create invoices, email receipts, and record paid invoices in Finance <i class="fa-solid fa-arrow-right mx-1" aria-hidden="true"></i> Income.</p>
            </div>
            <button type="button" data-open-create class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create invoice</button>
        </header>

        <section aria-label="Invoice statistics" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            @foreach ([['Total invoices', $invoiceStats['total'], 'fa-file-invoice', 'text-slate-700'], ['Unpaid', $invoiceStats['unpaid'], 'fa-hourglass-half', 'text-amber-700'], ['Paid', $invoiceStats['paid'], 'fa-circle-check', 'text-emerald-700'], ['Overdue', $invoiceStats['overdue'], 'fa-triangle-exclamation', 'text-rose-700']] as [$label, $value, $icon, $color])
                <article class="pm-card-bg rounded-xl border border-slate-100 p-4 shadow-sm sm:p-5"><div class="flex items-center justify-between gap-2"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><i class="fa-solid {{ $icon }} {{ $color }}" aria-hidden="true"></i></div><p class="mt-2 text-2xl font-bold {{ $color }}">{{ $value }}</p></article>
            @endforeach
        </section>

        <div role="tablist" aria-label="Business invoice sections" class="flex gap-2 overflow-x-auto border-b border-slate-200" data-invoice-page-tabs>
            <button type="button" role="tab" id="invoice-page-tab-list" aria-controls="invoice-page-panel-list" aria-selected="true" tabindex="0" data-page-tab="list" class="whitespace-nowrap border-b-2 border-[var(--brand-1)] px-4 py-3 text-sm font-semibold text-[var(--brand-1)]">Your Invoices</button>
            <button type="button" role="tab" id="invoice-page-tab-templates" aria-controls="invoice-page-panel-templates" aria-selected="false" tabindex="-1" data-page-tab="templates" class="whitespace-nowrap border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-slate-500">Invoice templates</button>
        </div>

        <section id="invoice-page-panel-templates" role="tabpanel" aria-labelledby="invoice-page-tab-templates" data-page-panel="templates" hidden style="display: none" class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,.8fr)]">
            <aside class="pm-card-bg min-w-0 rounded-2xl border border-slate-100 p-4 shadow-sm sm:p-6">
                <div class="flex items-start gap-3"><i class="fa-solid fa-file-pdf mt-1 text-xl text-rose-600" aria-hidden="true"></i><div><h2 class="text-lg font-bold text-slate-800">Invoice templates</h2><p class="mt-1 text-sm text-slate-500">Upload safe HTML templates or PDF stationery (up to 10 MB). Invoice details are overlaid on your PDF pages.</p></div></div>
                <a class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-1)] underline" href="{{ route('business-invoices.templates.sample') }}"><i class="fa-solid fa-download" aria-hidden="true"></i> Download HTML starter</a>
                <form method="POST" action="{{ route('business-invoices.templates.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div><label for="template-name" class="mb-1 block text-sm font-medium">Template name</label><input id="template-name" name="name" required maxlength="120" class="pm-input" value="{{ old('name') }}" placeholder="e.g. Company invoice"></div>
                    <div><label for="template-file" class="mb-1 block text-sm font-medium">HTML or PDF file</label><input id="template-file" name="template_file" type="file" accept=".html,.htm,.pdf,text/html,application/pdf" required class="pm-input">@error('template_file')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))> Make this the default template</label>
                    <button class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-upload mr-1" aria-hidden="true"></i> Upload template</button>
                </form>
                <div class="mt-5 overflow-x-auto border-t border-slate-100 pt-4">
                    <table class="w-full min-w-[600px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Template</th><th class="px-3 py-3">Format</th><th class="px-3 py-3">Default</th><th class="px-3 py-3">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($templates as $template)
                                <tr>
                                    <td class="max-w-64 truncate px-3 py-3 font-semibold text-slate-800">{{ $template->name }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ strtolower(pathinfo($template->storage_path, PATHINFO_EXTENSION)) === 'pdf' ? 'PDF stationery' : 'HTML layout' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ $template->is_default ? 'Yes' : 'No' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3"><div class="flex items-center gap-4">
                                        @unless ($template->is_default)<form method="POST" action="{{ route('business-invoices.templates.default', $template->id) }}">@csrf<button class="text-xs font-semibold text-[var(--brand-1)]">Set default</button></form>@endunless
                                        <form method="POST" action="{{ route('business-invoices.templates.destroy', $template->id) }}" data-confirm="Delete this template? Invoices using it will use the standard layout." data-confirm-title="Delete template?" data-confirm-text="Delete">@csrf @method('DELETE')<button class="text-rose-600" aria-label="Delete {{ $template->name }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></form>
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No invoice templates yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <details class="mt-4 rounded-xl border border-slate-200 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">HTML placeholders</summary>
                    <p class="mt-2 break-words text-xs leading-5 text-slate-500">@{{business_logo}}, @{{business_logo_watermark}}, @{{business_name}}, @{{business_address}}, @{{business_email}}, @{{business_phone}}, @{{customer_name}}, @{{customer_email}}, @{{invoice_number}}, @{{issue_date}}, @{{due_date}}, @{{currency}}, @{{total}}, @{{status}}, @{{document_title}}, @{{line_items_table}}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Add your logo in Business Card first. Put @{{business_logo}} where the logo should appear and @{{business_logo_watermark}} where the faint watermark should appear. Both placeholders safely render your own logo in the PDF.</p>
                </details>
            </aside>
        </section>

        <section id="invoice-page-panel-list" role="tabpanel" aria-labelledby="invoice-page-tab-list" data-page-panel="list" class="pm-card-bg overflow-hidden rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="text-lg font-bold text-slate-800">Your invoices</h2><p class="text-sm text-slate-500">Paid invoice amounts appear in Finance <i class="fa-solid fa-arrow-right mx-1" aria-hidden="true"></i> Income.</p></div><button type="button" data-open-create class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white"><i class="fa-solid fa-plus" aria-hidden="true"></i> New invoice</button></div>
            <form id="invoice-bulk-delete-form" method="POST" action="{{ route('business-invoices.bulk-delete') }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 p-3 sm:px-5">
                @csrf
                <p class="text-sm text-slate-600"><span id="invoice-selection-count">0</span> selected <span class="text-xs text-slate-500">· Paid invoices cannot be bulk deleted</span></p>
                <button id="invoice-bulk-delete-button" type="submit" disabled class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-600 enabled:hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"><i class="fa-solid fa-trash mr-1" aria-hidden="true"></i> Delete selected</button>
            </form>
            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[850px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="w-10 px-4 py-3"><input type="checkbox" id="select-visible-invoices" aria-label="Select all deletable invoices on this page" class="rounded border-slate-300"></th><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($invoices as $invoice)
                            <tr class="align-top">
                                <td class="px-4 py-4"><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" form="invoice-bulk-delete-form" data-invoice-select @disabled($invoice->status === 'paid' || $invoice->income) aria-label="Select {{ $invoice->invoice_number }}" class="rounded border-slate-300"></td>
                                <td class="px-4 py-4"><strong>{{ $invoice->invoice_number }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $invoice->template?->name ?? 'Standard layout' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4">{{ $invoice->customer_name }}<span class="mt-1 block max-w-56 truncate text-xs text-slate-500" title="{{ $invoice->customer_email }}">{{ $invoice->customer_email }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4">Issued {{ $invoice->issue_date->format('d M Y') }}<span class="mt-1 block text-xs text-slate-500">Due {{ $invoice->due_date?->format('d M Y') ?? 'on receipt' }}@if($invoice->paid_at) · Paid {{ $invoice->paid_at->format('d M Y') }}@endif</span></td>
                                <td class="whitespace-nowrap px-4 py-4 font-semibold">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($invoice->status) }}</span>@if($invoice->income)<span class="mt-2 block text-xs text-emerald-700"><i class="fa-solid fa-link" aria-hidden="true"></i> In Finance</span>@endif</td>
                                <td class="min-w-64 px-4 py-4"><div class="flex flex-wrap gap-2">
                                    <a href="{{ route('business-invoices.download', $invoice->id) }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700"><i class="fa-solid fa-download mr-1" aria-hidden="true"></i> PDF</a>
                                    @if ($invoice->status !== 'paid')
                                        <button type="button" data-edit-invoice data-invoice-id="{{ $invoice->id }}" data-update-url="{{ route('business-invoices.update', $invoice->id) }}" data-customer-name="{{ $invoice->customer_name }}" data-customer-email="{{ $invoice->customer_email }}" data-business-name="{{ $invoice->business_name }}" data-business-email="{{ $invoice->business_email }}" data-business-phone="{{ $invoice->business_phone }}" data-business-address="{{ $invoice->business_address }}" data-currency="{{ $invoice->currency }}" data-issue-date="{{ $invoice->issue_date->toDateString() }}" data-due-date="{{ $invoice->due_date?->toDateString() }}" data-template-id="{{ $invoice->template_id }}" data-line-items="{{ json_encode($invoice->line_items) }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-[var(--brand-1)]"><i class="fa-solid fa-pen-to-square mr-1" aria-hidden="true"></i> Edit</button>
                                        <form method="POST" action="{{ route('business-invoices.send', $invoice->id) }}" data-confirm="Email this invoice to {{ $invoice->customer_email }}?" data-confirm-title="Send invoice?" data-confirm-text="Send"><button class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-[var(--brand-1)]"><i class="fa-solid fa-paper-plane mr-1" aria-hidden="true"></i> Send</button>@csrf</form>
                                        <form method="POST" action="{{ route('business-invoices.paid', $invoice->id) }}" class="flex items-center gap-1">@csrf<input name="received_at" type="date" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="rounded-lg border border-slate-200 px-2 py-1 text-xs" aria-label="Payment received date"><button class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white"><i class="fa-solid fa-check mr-1" aria-hidden="true"></i> Paid</button></form>
                                        <button type="button" data-delete-invoice data-delete-url="{{ route('business-invoices.destroy', $invoice->id) }}" data-invoice-number="{{ $invoice->invoice_number }}" class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600"><i class="fa-solid fa-trash mr-1" aria-hidden="true"></i> Delete</button>
                                    @else
                                        <form method="POST" action="{{ route('business-invoices.send-receipt', $invoice->id) }}" data-confirm="Email a payment receipt to {{ $invoice->customer_email }}?" data-confirm-title="Send receipt?" data-confirm-text="Send receipt">@csrf<button class="rounded-lg bg-teal-700 px-2.5 py-1.5 text-xs font-semibold text-white"><i class="fa-solid fa-receipt mr-1" aria-hidden="true"></i> Receipt</button></form>
                                    @endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No invoices yet. Use Create invoice to get started.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())<div class="border-t border-slate-100 p-4">{{ $invoices->links() }}</div>@endif
        </section>
    </div>

    <dialog id="invoice-modal" aria-labelledby="invoice-modal-title" class="w-[min(94vw,900px)] max-h-[92dvh] max-w-none overflow-y-auto rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/60">
        <form id="invoice-form" method="POST" action="{{ $restoreMode === 'edit' && $restoreInvoice ? route('business-invoices.update', $restoreInvoice->id) : route('business-invoices.store') }}" class="space-y-5 p-4 sm:p-6">
            @csrf
            <input type="hidden" name="_form_mode" id="invoice-form-mode" value="{{ $restoreMode }}">
            <input type="hidden" name="_invoice_id" id="invoice-id" value="{{ old('_invoice_id', $restoreInvoice?->id) }}">
            <input type="hidden" name="_method" id="invoice-method" value="PUT" @disabled($restoreMode !== 'edit')>
            <div class="sticky top-0 z-10 -mx-4 -mt-4 flex items-center justify-between gap-3 border-b border-slate-100 bg-white/95 p-4 backdrop-blur sm:-mx-6 sm:-mt-6 sm:px-6">
                <div><h2 id="invoice-modal-title" class="text-lg font-bold text-slate-900">{{ $restoreMode === 'edit' ? 'Edit invoice' : 'Create invoice' }}</h2><p class="text-xs text-slate-500">Amounts are calculated from the line items.</p></div>
                <button type="button" data-close-invoice class="grid h-10 w-10 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close invoice form"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div role="tablist" aria-label="Invoice form sections" class="-mx-4 flex gap-2 overflow-x-auto border-b border-slate-200 px-4 sm:-mx-6 sm:px-6" data-invoice-form-tabs>
                <button type="button" role="tab" id="invoice-form-tab-details" aria-controls="invoice-form-panel-details" aria-selected="true" tabindex="0" data-form-tab="details" class="whitespace-nowrap border-b-2 border-[var(--brand-1)] px-3 py-3 text-sm font-semibold text-[var(--brand-1)]">Business &amp; customer</button>
                <button type="button" role="tab" id="invoice-form-tab-items" aria-controls="invoice-form-panel-items" aria-selected="false" tabindex="-1" data-form-tab="items" class="whitespace-nowrap border-b-2 border-transparent px-3 py-3 text-sm font-semibold text-slate-500">Items &amp; template</button>
            </div>
            <section id="invoice-form-panel-details" role="tabpanel" aria-labelledby="invoice-form-tab-details" data-form-panel="details" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="business_name" class="mb-1 block text-sm font-medium">Your business name</label><input id="business_name" name="business_name" required maxlength="255" class="pm-input" value="{{ old('business_name', $restoreInvoice?->business_name ?? ($businessCard?->company ?: $businessCard?->name)) }}">@error('business_name')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label for="business_email" class="mb-1 block text-sm font-medium">Business email</label><input id="business_email" name="business_email" type="email" class="pm-input" value="{{ old('business_email', $restoreInvoice?->business_email ?? $businessCard?->email) }}"></div>
                <div><label for="business_phone" class="mb-1 block text-sm font-medium">Business phone</label><input id="business_phone" name="business_phone" class="pm-input" value="{{ old('business_phone', $restoreInvoice?->business_phone ?? $businessCard?->phone) }}"></div>
                <div><label for="currency" class="mb-1 block text-sm font-medium">Currency code</label><input id="currency" name="currency" maxlength="3" required class="pm-input uppercase" value="{{ old('currency', $restoreInvoice?->currency ?? 'UGX') }}"></div>
                <div class="sm:col-span-2"><label for="business_address" class="mb-1 block text-sm font-medium">Business address</label><textarea id="business_address" name="business_address" rows="2" class="pm-input">{{ old('business_address', $restoreInvoice?->business_address ?? $businessCard?->address) }}</textarea></div>
                <div><label for="customer_name" class="mb-1 block text-sm font-medium">Customer name</label><input id="customer_name" name="customer_name" required class="pm-input" value="{{ old('customer_name', $restoreInvoice?->customer_name) }}">@error('customer_name')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label for="customer_email" class="mb-1 block text-sm font-medium">Customer email</label><input id="customer_email" name="customer_email" type="email" required class="pm-input" value="{{ old('customer_email', $restoreInvoice?->customer_email) }}">@error('customer_email')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label for="issue_date" class="mb-1 block text-sm font-medium">Issue date</label><input id="issue_date" name="issue_date" type="date" required class="pm-input" value="{{ old('issue_date', $restoreInvoice?->issue_date?->toDateString() ?? now()->toDateString()) }}"></div>
                <div><label for="due_date" class="mb-1 block text-sm font-medium">Due date</label><input id="due_date" name="due_date" type="date" class="pm-input" value="{{ old('due_date', $restoreInvoice?->due_date?->toDateString()) }}"></div>
            </div>
            </section>
            <section id="invoice-form-panel-items" role="tabpanel" aria-labelledby="invoice-form-tab-items" data-form-panel="items" hidden style="display: none" class="space-y-5">
            <div>
                <div class="mb-2 flex items-center justify-between gap-3"><h3 class="font-semibold text-slate-800">Items</h3><button type="button" id="add-invoice-line" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700"><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Add item</button></div>
                <div id="invoice-line-items" class="space-y-3">
                    @foreach ($restoreLines as $index => $line)
                        <div class="invoice-line grid min-w-0 grid-cols-1 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_6rem_8rem_auto]">
                            <input name="line_items[{{ $index }}][description]" required maxlength="255" placeholder="Description" aria-label="Item description" class="pm-input" value="{{ $line['description'] ?? '' }}">
                            <input name="line_items[{{ $index }}][quantity]" required type="number" min="0.01" step="0.01" aria-label="Quantity" class="pm-input" value="{{ $line['quantity'] ?? 1 }}">
                            <input name="line_items[{{ $index }}][unit_price]" required type="number" min="0" step="0.01" aria-label="Unit price" placeholder="Unit price" class="pm-input" value="{{ $line['unit_price'] ?? '' }}">
                            <button type="button" class="remove-invoice-line rounded-lg px-3 py-2 text-rose-600" aria-label="Remove line item"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                        </div>
                    @endforeach
                </div>
                @error('line_items')<p role="alert" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('line_items.*.description')<p role="alert" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('line_items.*.quantity')<p role="alert" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('line_items.*.unit_price')<p role="alert" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div><label for="invoice-template-id" class="mb-1 block text-sm font-medium">Invoice template</label><select id="invoice-template-id" name="template_id" class="pm-input"><option value="">Standard invoice layout</option>@foreach ($templates as $template)<option value="{{ $template->id }}" @selected((string) old('template_id', $restoreInvoice?->template_id ?? ($template->is_default ? $template->id : '')) === (string) $template->id)>{{ $template->name }}{{ $template->is_default ? ' (default)' : '' }}</option>@endforeach</select>@error('template_id')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
            </section>
            <div class="flex flex-col-reverse justify-end gap-2 border-t border-slate-100 pt-4 sm:flex-row"><button type="button" data-close-invoice class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</button><button type="submit" class="btn-primary rounded-lg px-5 py-2.5 text-sm font-semibold text-white"><i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i><span id="invoice-submit-label">{{ $restoreMode === 'edit' ? 'Save changes' : 'Create invoice' }}</span></button></div>
        </form>
    </dialog>

    <dialog id="invoice-delete-modal" aria-labelledby="invoice-delete-title" class="w-[min(92vw,440px)] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/60">
        <form id="invoice-delete-form" method="POST" class="space-y-4 p-5 sm:p-6">
            @csrf @method('DELETE')
            <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600"><i class="fa-solid fa-trash" aria-hidden="true"></i></span><div><h2 id="invoice-delete-title" class="font-bold text-slate-900">Delete invoice?</h2><p class="mt-1 text-sm text-slate-500">This will permanently delete <strong id="delete-invoice-number"></strong>. Paid invoices cannot be deleted.</p></div></div>
            <div class="flex justify-end gap-2"><button type="button" id="cancel-invoice-delete" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button><button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white"><i class="fa-solid fa-trash mr-1" aria-hidden="true"></i>Delete</button></div>
        </form>
    </dialog>

    <template id="invoice-line-template"><div class="invoice-line grid min-w-0 grid-cols-1 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_6rem_8rem_auto]"><input data-field="description" required maxlength="255" placeholder="Description" aria-label="Item description" class="pm-input"><input data-field="quantity" required type="number" min="0.01" step="0.01" aria-label="Quantity" class="pm-input" value="1"><input data-field="unit_price" required type="number" min="0" step="0.01" aria-label="Unit price" placeholder="Unit price" class="pm-input"><button type="button" class="remove-invoice-line rounded-lg px-3 py-2 text-rose-600" aria-label="Remove line item"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div></template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('invoice-modal');
            const form = document.getElementById('invoice-form');
            const method = document.getElementById('invoice-method');
            const mode = document.getElementById('invoice-form-mode');
            const lines = document.getElementById('invoice-line-items');
            const deleteModal = document.getElementById('invoice-delete-modal');
            const pageTabs = document.querySelector('[data-invoice-page-tabs]');
            const formTabs = document.querySelector('[data-invoice-form-tabs]');
            const defaults = {
                business_name: @json($businessCard?->company ?: $businessCard?->name ?: ''),
                business_email: @json($businessCard?->email ?: ''),
                business_phone: @json($businessCard?->phone ?: ''),
                business_address: @json($businessCard?->address ?: ''),
                currency: 'UGX', issue_date: @json(now()->toDateString()), due_date: '',
                customer_name: '', customer_email: '', template_id: @json($templates->firstWhere('is_default', true)?->id ?: ''),
            };

            function activateTab(tabList, tabAttribute, panelAttribute, value) {
                tabList.querySelectorAll(`[${tabAttribute}]`).forEach(tab => {
                    const active = tab.getAttribute(tabAttribute) === value;
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                    tab.classList.toggle('border-[var(--brand-1)]', active);
                    tab.classList.toggle('text-[var(--brand-1)]', active);
                    tab.classList.toggle('border-transparent', !active);
                    tab.classList.toggle('text-slate-500', !active);
                });
                document.querySelectorAll(`[${panelAttribute}]`).forEach(panel => {
                    const active = panel.getAttribute(panelAttribute) === value;
                    panel.hidden = !active;
                    panel.style.display = active ? (panel.classList.contains('grid') ? 'grid' : 'block') : 'none';
                });
            }

            pageTabs.addEventListener('click', event => {
                const tab = event.target.closest('[data-page-tab]');
                if (tab) activateTab(pageTabs, 'data-page-tab', 'data-page-panel', tab.dataset.pageTab);
            });
            formTabs.addEventListener('click', event => {
                const tab = event.target.closest('[data-form-tab]');
                if (tab) activateTab(formTabs, 'data-form-tab', 'data-form-panel', tab.dataset.formTab);
            });
            @if ($errors->has('line_items') || $errors->has('template_id'))
                activateTab(formTabs, 'data-form-tab', 'data-form-panel', 'items');
            @endif
            @if ($errors->has('template_file') || $errors->has('name') || $errors->has('is_default'))
                activateTab(pageTabs, 'data-page-tab', 'data-page-panel', 'templates');
            @endif

            function setValue(name, value) {
                const input = form.elements.namedItem(name);
                if (input) input.value = value ?? '';
            }

            function setLines(items) {
                lines.replaceChildren();
                (items?.length ? items : [{description: '', quantity: 1, unit_price: ''}]).forEach((item, index) => {
                    const row = document.getElementById('invoice-line-template').content.firstElementChild.cloneNode(true);
                    row.querySelectorAll('[data-field]').forEach((input) => {
                        const field = input.dataset.field;
                        input.name = `line_items[${index}][${field}]`;
                        input.value = item[field] ?? (field === 'quantity' ? 1 : '');
                    });
                    lines.appendChild(row);
                });
            }

            function openCreate() {
                form.reset();
                activateTab(formTabs, 'data-form-tab', 'data-form-panel', 'details');
                form.action = @json(route('business-invoices.store'));
                method.disabled = true;
                mode.value = 'create';
                document.getElementById('invoice-id').value = '';
                document.getElementById('invoice-modal-title').textContent = 'Create invoice';
                document.getElementById('invoice-submit-label').textContent = 'Create invoice';
                Object.entries(defaults).forEach(([key, value]) => setValue(key, value));
                setLines([{description: '', quantity: 1, unit_price: ''}]);
                modal.showModal();
            }

            document.querySelectorAll('[data-open-create]').forEach(button => button.addEventListener('click', openCreate));
            document.querySelectorAll('[data-edit-invoice]').forEach(button => button.addEventListener('click', function () {
                const data = this.dataset;
                form.action = data.updateUrl;
                method.disabled = false;
                mode.value = 'edit';
                document.getElementById('invoice-id').value = data.invoiceId;
                document.getElementById('invoice-modal-title').textContent = 'Edit invoice';
                document.getElementById('invoice-submit-label').textContent = 'Save changes';
                ['businessName','businessEmail','businessPhone','businessAddress','customerName','customerEmail','currency','issueDate','dueDate'].forEach((key) => setValue(key.replace(/[A-Z]/g, letter => '_' + letter.toLowerCase()), data[key]));
                setValue('template_id', data.templateId);
                try { setLines(JSON.parse(data.lineItems)); } catch (_) { setLines([]); }
                modal.showModal();
            }));

            document.querySelectorAll('[data-close-invoice]').forEach(button => button.addEventListener('click', () => modal.close()));
            document.getElementById('add-invoice-line').addEventListener('click', () => setLines([...Array.from(lines.querySelectorAll('.invoice-line')).map(row => ({
                description: row.querySelector('[data-field="description"]')?.value ?? '',
                quantity: row.querySelector('[data-field="quantity"]')?.value ?? 1,
                unit_price: row.querySelector('[data-field="unit_price"]')?.value ?? '',
            })), {description: '', quantity: 1, unit_price: ''}]));
            lines.addEventListener('click', event => {
                const remove = event.target.closest('.remove-invoice-line');
                if (remove && lines.querySelectorAll('.invoice-line').length > 1) {
                    const rowToRemove = remove.closest('.invoice-line');
                    const remainingItems = Array.from(lines.querySelectorAll('.invoice-line'))
                        .filter(row => row !== rowToRemove)
                        .map(row => ({
                            description: row.querySelector('[data-field="description"]')?.value ?? '',
                            quantity: row.querySelector('[data-field="quantity"]')?.value ?? 1,
                            unit_price: row.querySelector('[data-field="unit_price"]')?.value ?? '',
                        }));
                    setLines(remainingItems);
                }
            });

            document.querySelectorAll('[data-delete-invoice]').forEach(button => button.addEventListener('click', function () {
                document.getElementById('invoice-delete-form').action = this.dataset.deleteUrl;
                document.getElementById('delete-invoice-number').textContent = this.dataset.invoiceNumber;
                deleteModal.showModal();
            }));
            document.getElementById('cancel-invoice-delete').addEventListener('click', () => deleteModal.close());

            const bulkForm = document.getElementById('invoice-bulk-delete-form');
            const selectVisible = document.getElementById('select-visible-invoices');
            const bulkButton = document.getElementById('invoice-bulk-delete-button');
            const selectionCount = document.getElementById('invoice-selection-count');
            const selectableInvoices = Array.from(document.querySelectorAll('[data-invoice-select]:not(:disabled)'));
            function refreshInvoiceSelection() {
                const checkedCount = selectableInvoices.filter(input => input.checked).length;
                selectionCount.textContent = checkedCount;
                bulkButton.disabled = checkedCount === 0;
                selectVisible.checked = selectableInvoices.length > 0 && checkedCount === selectableInvoices.length;
                selectVisible.indeterminate = checkedCount > 0 && checkedCount < selectableInvoices.length;
            }
            selectVisible.addEventListener('change', () => {
                selectableInvoices.forEach(input => input.checked = selectVisible.checked);
                refreshInvoiceSelection();
            });
            selectableInvoices.forEach(input => input.addEventListener('change', refreshInvoiceSelection));
            bulkForm.addEventListener('submit', event => {
                if (!selectableInvoices.some(input => input.checked)) {
                    event.preventDefault();
                    return;
                }
                if (!window.confirm('Delete the selected unpaid invoices? This cannot be undone.')) event.preventDefault();
            });

            @if ($errors->any() && in_array($restoreMode, ['create', 'edit'], true))
                modal.showModal();
            @endif
        });
    </script>
@endsection
