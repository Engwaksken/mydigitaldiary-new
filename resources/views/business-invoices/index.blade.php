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

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,.8fr)]">
            <aside class="pm-card-bg min-w-0 rounded-2xl border border-slate-100 p-4 shadow-sm sm:p-6">
                <div class="flex items-start gap-3"><i class="fa-solid fa-file-pdf mt-1 text-xl text-rose-600" aria-hidden="true"></i><div><h2 class="text-lg font-bold text-slate-800">Invoice templates</h2><p class="mt-1 text-sm text-slate-500">Upload safe HTML templates or PDF stationery (up to 10 MB). PDF template pages are included before the generated invoice.</p></div></div>
                <a class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[var(--brand-1)] underline" href="{{ route('business-invoices.templates.sample') }}"><i class="fa-solid fa-download" aria-hidden="true"></i> Download HTML starter</a>
                <form method="POST" action="{{ route('business-invoices.templates.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div><label for="template-name" class="mb-1 block text-sm font-medium">Template name</label><input id="template-name" name="name" required maxlength="120" class="pm-input" value="{{ old('name') }}" placeholder="e.g. Company invoice"></div>
                    <div><label for="template-file" class="mb-1 block text-sm font-medium">HTML or PDF file</label><input id="template-file" name="template_file" type="file" accept=".html,.htm,.pdf,text/html,application/pdf" required class="pm-input">@error('template_file')<p role="alert" class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))> Make this the default template</label>
                    <button class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-upload mr-1" aria-hidden="true"></i> Upload template</button>
                </form>
                @if ($templates->isNotEmpty())
                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                        @foreach ($templates as $template)
                            <div class="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-slate-50 p-3">
                                <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ $template->name }}</p><p class="text-xs text-slate-500">{{ strtolower(pathinfo($template->storage_path, PATHINFO_EXTENSION)) === 'pdf' ? 'PDF stationery' : 'HTML layout' }}{{ $template->is_default ? ' · Default' : '' }}</p></div>
                                <div class="flex shrink-0 items-center gap-3">
                                    @unless ($template->is_default)<form method="POST" action="{{ route('business-invoices.templates.default', $template->id) }}">@csrf<button class="text-xs font-semibold text-[var(--brand-1)]">Set default</button></form>@endunless
                                    <form method="POST" action="{{ route('business-invoices.templates.destroy', $template->id) }}" data-confirm="Delete this template? Invoices using it will use the standard layout." data-confirm-title="Delete template?" data-confirm-text="Delete">@csrf @method('DELETE')<button class="text-rose-600" aria-label="Delete {{ $template->name }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <details class="mt-4 rounded-xl border border-slate-200 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">HTML placeholders</summary>
                    <p class="mt-2 break-words text-xs leading-5 text-slate-500">@{{business_name}}, @{{business_address}}, @{{business_email}}, @{{business_phone}}, @{{customer_name}}, @{{customer_email}}, @{{invoice_number}}, @{{issue_date}}, @{{due_date}}, @{{currency}}, @{{total}}, @{{status}}, @{{document_title}}, @{{line_items_table}}</p>
                </details>
            </aside>
        </section>

        <section class="pm-card-bg overflow-hidden rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="text-lg font-bold text-slate-800">Your invoices</h2><p class="text-sm text-slate-500">Paid invoice amounts appear in Finance <i class="fa-solid fa-arrow-right mx-1" aria-hidden="true"></i> Income.</p></div><button type="button" data-open-create class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white"><i class="fa-solid fa-plus" aria-hidden="true"></i> New invoice</button></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($invoices as $invoice)
                            <tr class="align-top">
                                <td class="px-4 py-4"><strong>{{ $invoice->invoice_number }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $invoice->template?->name ?? 'Standard layout' }}</span></td>
                                <td class="px-4 py-4">{{ $invoice->customer_name }}<span class="mt-1 block break-all text-xs text-slate-500">{{ $invoice->customer_email }}</span></td>
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
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No invoices yet. Use Create invoice to get started.</td></tr>
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
            let nextLine = lines.querySelectorAll('.invoice-line').length;
            const defaults = {
                business_name: @json($businessCard?->company ?: $businessCard?->name ?: ''),
                business_email: @json($businessCard?->email ?: ''),
                business_phone: @json($businessCard?->phone ?: ''),
                business_address: @json($businessCard?->address ?: ''),
                currency: 'UGX', issue_date: @json(now()->toDateString()), due_date: '',
                customer_name: '', customer_email: '', template_id: @json($templates->firstWhere('is_default', true)?->id ?: ''),
            };

            function setValue(name, value) {
                const input = form.elements.namedItem(name);
                if (input) input.value = value ?? '';
            }

            function setLines(items) {
                lines.replaceChildren();
                (items?.length ? items : [{description: '', quantity: 1, unit_price: ''}]).forEach((item) => {
                    const row = document.getElementById('invoice-line-template').content.firstElementChild.cloneNode(true);
                    row.querySelectorAll('[data-field]').forEach((input) => {
                        const field = input.dataset.field;
                        input.name = `line_items[${nextLine++}][${field}]`;
                        input.value = item[field] ?? (field === 'quantity' ? 1 : '');
                    });
                    lines.appendChild(row);
                });
            }

            function openCreate() {
                form.reset();
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
                if (remove && lines.querySelectorAll('.invoice-line').length > 1) remove.closest('.invoice-line').remove();
            });

            document.querySelectorAll('[data-delete-invoice]').forEach(button => button.addEventListener('click', function () {
                document.getElementById('invoice-delete-form').action = this.dataset.deleteUrl;
                document.getElementById('delete-invoice-number').textContent = this.dataset.invoiceNumber;
                deleteModal.showModal();
            }));
            document.getElementById('cancel-invoice-delete').addEventListener('click', () => deleteModal.close());

            @if ($errors->any() && in_array($restoreMode, ['create', 'edit'], true))
                modal.showModal();
            @endif
        });
    </script>
@endsection
