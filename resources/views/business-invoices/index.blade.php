@extends('layouts.app')

@section('title', 'Business Invoices')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-file-invoice-dollar mr-2 text-emerald-600"></i>Business invoices</h1>
                <p class="mt-1 text-sm text-slate-500">Create and email invoices or payment receipts. Marking an invoice paid automatically records it in Finance → Income.</p>
            </div>
            <a href="#create-invoice" class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white"><i class="fa-solid fa-plus"></i> Create invoice</a>
        </header>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,.8fr)]">
            <div id="create-invoice" class="pm-card-bg min-w-0 rounded-2xl border border-slate-100 p-4 shadow-sm sm:p-6">
                <h2 class="mb-4 text-lg font-bold text-slate-800">New invoice</h2>
                <form method="POST" action="{{ route('business-invoices.store') }}" class="space-y-5">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="business_name" class="mb-1 block text-sm font-medium">Your business name</label><input id="business_name" name="business_name" required maxlength="255" class="pm-input" value="{{ old('business_name', $businessCard?->company ?: $businessCard?->name) }}">@error('business_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div><label for="business_email" class="mb-1 block text-sm font-medium">Business email</label><input id="business_email" name="business_email" type="email" class="pm-input" value="{{ old('business_email', $businessCard?->email) }}">@error('business_email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div><label for="business_phone" class="mb-1 block text-sm font-medium">Business phone</label><input id="business_phone" name="business_phone" class="pm-input" value="{{ old('business_phone', $businessCard?->phone) }}"></div>
                        <div><label for="currency" class="mb-1 block text-sm font-medium">Currency code</label><input id="currency" name="currency" maxlength="3" required class="pm-input uppercase" value="{{ old('currency', 'UGX') }}" aria-describedby="currency-hint"><p id="currency-hint" class="mt-1 text-xs text-slate-500">For example UGX, USD or EUR.</p></div>
                        <div class="sm:col-span-2"><label for="business_address" class="mb-1 block text-sm font-medium">Business address</label><textarea id="business_address" name="business_address" rows="2" class="pm-input">{{ old('business_address', $businessCard?->address) }}</textarea></div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="customer_name" class="mb-1 block text-sm font-medium">Customer name</label><input id="customer_name" name="customer_name" required class="pm-input" value="{{ old('customer_name') }}">@error('customer_name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div><label for="customer_email" class="mb-1 block text-sm font-medium">Customer email</label><input id="customer_email" name="customer_email" type="email" required class="pm-input" value="{{ old('customer_email') }}">@error('customer_email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div><label for="issue_date" class="mb-1 block text-sm font-medium">Issue date</label><input id="issue_date" name="issue_date" type="date" required class="pm-input" value="{{ old('issue_date', now()->toDateString()) }}"></div>
                        <div><label for="due_date" class="mb-1 block text-sm font-medium">Due date</label><input id="due_date" name="due_date" type="date" class="pm-input" value="{{ old('due_date') }}"></div>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3"><h3 class="font-semibold text-slate-800">Items</h3><button type="button" id="add-invoice-line" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-plus mr-1"></i>Add item</button></div>
                        <div id="invoice-line-items" class="space-y-3">
                            @foreach (old('line_items', [['description' => '', 'quantity' => 1, 'unit_price' => '']]) as $index => $line)
                                <div class="invoice-line grid min-w-0 grid-cols-1 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_6rem_8rem_auto]">
                                    <input name="line_items[{{ $index }}][description]" required maxlength="255" placeholder="Description" aria-label="Item description" class="pm-input" value="{{ $line['description'] ?? '' }}">
                                    <input name="line_items[{{ $index }}][quantity]" required type="number" min="0.01" step="0.01" aria-label="Quantity" class="pm-input" value="{{ $line['quantity'] ?? 1 }}">
                                    <input name="line_items[{{ $index }}][unit_price]" required type="number" min="0" step="0.01" aria-label="Unit price" placeholder="Unit price" class="pm-input" value="{{ $line['unit_price'] ?? '' }}">
                                    <button type="button" class="remove-invoice-line rounded-lg px-3 py-2 text-rose-600 hover:bg-rose-50" aria-label="Remove line item"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            @endforeach
                        </div>
                        @error('line_items')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                        @error('line_items.*.description')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                        @error('line_items.*.quantity')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                        @error('line_items.*.unit_price')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="template_id" class="mb-1 block text-sm font-medium">Invoice template</label>
                        <select id="template_id" name="template_id" class="pm-input">
                            <option value="">Standard invoice layout</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" @selected((string) old('template_id', $template->is_default ? $template->id : '') === (string) $template->id)>{{ $template->name }}{{ $template->is_default ? ' (default)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('template_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-primary rounded-lg px-5 py-2.5 text-sm font-semibold text-white"><i class="fa-solid fa-file-circle-plus mr-1"></i> Create invoice</button>
                </form>
            </div>

            <aside class="pm-card-bg h-fit rounded-2xl border border-slate-100 p-4 shadow-sm sm:p-6">
                <h2 class="text-lg font-bold text-slate-800">Invoice templates</h2>
                <p class="mt-1 text-sm text-slate-500">Upload an HTML template with placeholders to customize your invoice and receipt PDF.</p>
                <a class="mt-3 inline-flex text-sm font-semibold text-[var(--brand-1)] underline" href="{{ route('business-invoices.templates.sample') }}">Download starter template</a>
                <form method="POST" action="{{ route('business-invoices.templates.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div><label for="template-name" class="mb-1 block text-sm font-medium">Template name</label><input id="template-name" name="name" required maxlength="120" class="pm-input" value="{{ old('name') }}" placeholder="e.g. My company invoice"></div>
                    <div><label for="template-file" class="mb-1 block text-sm font-medium">HTML template file</label><input id="template-file" name="template_file" type="file" accept=".html,.htm,text/html" required class="pm-input">@error('template_file')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))> Make this the default template</label>
                    <button class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Upload template</button>
                </form>
                @if ($templates->isNotEmpty())
                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                        @foreach ($templates as $template)
                            <div class="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-slate-50 p-3">
                                <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ $template->name }}</p>@if($template->is_default)<span class="text-xs text-emerald-700">Default</span>@endif</div>
                                <div class="flex shrink-0 items-center gap-2">
                                    @unless ($template->is_default)<form method="POST" action="{{ route('business-invoices.templates.default', $template->id) }}">@csrf<button class="text-xs font-semibold text-[var(--brand-1)]">Set default</button></form>@endunless
                                    <form method="POST" action="{{ route('business-invoices.templates.destroy', $template->id) }}" data-confirm="Delete this template? Existing invoices will keep their invoice data and use the standard layout if downloaded again." data-confirm-title="Delete template?" data-confirm-text="Delete">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-600" aria-label="Delete {{ $template->name }}"><i class="fa-solid fa-trash"></i></button></form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <details class="mt-4 rounded-xl border border-slate-200 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">Supported placeholders</summary>
                    <p class="mt-2 break-words text-xs leading-5 text-slate-500">@{{business_name}}, @{{business_address}}, @{{business_email}}, @{{business_phone}}, @{{customer_name}}, @{{customer_email}}, @{{invoice_number}}, @{{issue_date}}, @{{due_date}}, @{{currency}}, @{{total}}, @{{status}}, @{{document_title}}, @{{line_items_table}}</p>
                </details>
            </aside>
        </section>

        <section class="pm-card-bg overflow-hidden rounded-2xl border border-slate-100 shadow-sm">
            <div class="border-b border-slate-100 p-4 sm:p-5"><h2 class="text-lg font-bold text-slate-800">Your invoices</h2><p class="text-sm text-slate-500">Paid invoice amounts appear in Finance → Income automatically.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($invoices as $invoice)
                            <tr class="align-top">
                                <td class="px-4 py-4"><strong>{{ $invoice->invoice_number }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $invoice->template?->name ?? 'Standard template' }}</span></td>
                                <td class="px-4 py-4">{{ $invoice->customer_name }}<span class="mt-1 block break-all text-xs text-slate-500">{{ $invoice->customer_email }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4">Issued {{ $invoice->issue_date->format('d M Y') }}<span class="mt-1 block text-xs text-slate-500">Due {{ $invoice->due_date?->format('d M Y') ?? 'on receipt' }}@if($invoice->paid_at) · Paid {{ $invoice->paid_at->format('d M Y') }}@endif</span></td>
                                <td class="whitespace-nowrap px-4 py-4 font-semibold">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($invoice->status) }}</span>@if($invoice->income)<span class="mt-2 block text-xs text-emerald-700"><i class="fa-solid fa-link"></i> In Finance</span>@endif</td>
                                <td class="min-w-52 px-4 py-4"><div class="flex flex-wrap gap-2">
                                    <a href="{{ route('business-invoices.download', $invoice->id) }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700"><i class="fa-solid fa-download mr-1"></i>PDF</a>
                                    <form method="POST" action="{{ route('business-invoices.send', $invoice->id) }}" data-confirm="Email this invoice to {{ $invoice->customer_email }}?" data-confirm-title="Send invoice?" data-confirm-text="Send"><button class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-[var(--brand-1)]"><i class="fa-solid fa-paper-plane mr-1"></i>Send</button>@csrf</form>
                                    @if ($invoice->status !== 'paid')
                                        <form method="POST" action="{{ route('business-invoices.paid', $invoice->id) }}" class="flex items-center gap-1">@csrf<input name="received_at" type="date" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="rounded-lg border border-slate-200 px-2 py-1 text-xs" aria-label="Payment received date"><button class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white"><i class="fa-solid fa-check mr-1"></i>Paid</button></form>
                                    @else
                                        <form method="POST" action="{{ route('business-invoices.send-receipt', $invoice->id) }}" data-confirm="Email a payment receipt to {{ $invoice->customer_email }}?" data-confirm-title="Send receipt?" data-confirm-text="Send receipt">@csrf<button class="rounded-lg bg-teal-700 px-2.5 py-1.5 text-xs font-semibold text-white"><i class="fa-solid fa-receipt mr-1"></i>Receipt</button></form>
                                    @endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No invoices yet. Create your first invoice above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())<div class="border-t border-slate-100 p-4">{{ $invoices->links() }}</div>@endif
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const wrap = document.getElementById('invoice-line-items');
            const add = document.getElementById('add-invoice-line');
            let next = {{ count(old('line_items', [['description' => '', 'quantity' => 1, 'unit_price' => '']])) }};
            add?.addEventListener('click', function () {
                const row = document.createElement('div');
                row.className = 'invoice-line grid min-w-0 grid-cols-1 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_6rem_8rem_auto]';
                row.innerHTML = '<input name="line_items['+next+'][description]" required maxlength="255" placeholder="Description" aria-label="Item description" class="pm-input"><input name="line_items['+next+'][quantity]" required type="number" min="0.01" step="0.01" aria-label="Quantity" class="pm-input" value="1"><input name="line_items['+next+'][unit_price]" required type="number" min="0" step="0.01" aria-label="Unit price" placeholder="Unit price" class="pm-input"><button type="button" class="remove-invoice-line rounded-lg px-3 py-2 text-rose-600" aria-label="Remove line item"><i class="fa-solid fa-trash"></i></button>';
                wrap.appendChild(row);
                next++;
            });
            wrap?.addEventListener('click', function (event) {
                const remove = event.target.closest('.remove-invoice-line');
                if (remove && wrap.querySelectorAll('.invoice-line').length > 1) remove.closest('.invoice-line').remove();
            });
        });
    </script>
@endsection
