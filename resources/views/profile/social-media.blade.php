@extends('layouts.app')

@section('title', 'Social Media Accounts')

@section('content')
@php
    $platformLabels = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'x' => 'X',
        'tiktok' => 'TikTok',
        'linkedin' => 'LinkedIn',
    ];
@endphp

<div class="space-y-4" id="social-media-accounts">
    @include('social-media-planner.partials.navigation-tabs')

    <div>
        <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">Social Media Settings</div>
        <h1 class="mt-1 text-xl font-black text-slate-900">Accounts</h1>
        <p class="mt-1 text-sm text-slate-500">Manage saved accounts, add platforms and configure WhatsApp.</p>
    </div>

    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if($errors->any())
        <x-alert type="error" :dismissible="false" :autoDismiss="false">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-alert>
    @endif

    @unless($accountsTableReady ?? true)
        <x-alert type="warning" :dismissible="false" :autoDismiss="false">
            Social media account storage is not ready yet. Run the latest database migrations, then reload this page.
        </x-alert>
    @endunless

    <section class="apple-surface rounded-2xl overflow-hidden">
        <div class="px-4 pt-2 sm:px-5">
            <div class="smp-subtabs" role="tablist" aria-label="Social media account settings">
                <button type="button" class="smp-subtab is-active" data-smp-account-tab="saved" role="tab" aria-selected="true">
                    <i class="fa-solid fa-link"></i> Saved Accounts
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px]">{{ $accounts->count() }}</span>
                </button>
                <button type="button" class="smp-subtab" data-smp-account-tab="add" role="tab" aria-selected="false">
                    <i class="fa-solid fa-plus"></i> Add Account
                </button>
                <button type="button" class="smp-subtab" data-smp-account-tab="whatsapp" role="tab" aria-selected="false">
                    <i class="fa-brands fa-whatsapp"></i> WhatsApp
                </button>
            </div>
        </div>

        <div class="p-4 sm:p-5">
            <div class="smp-tab-panel" data-smp-account-panel="saved">
                <div class="mb-4">
                    <h2 class="text-base font-black text-slate-900">Saved Accounts</h2>
                    <p class="mt-1 text-xs text-slate-500">Accounts available when planning and publishing posts.</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Platform</th><th class="px-4 py-3">Account</th><th class="px-4 py-3">Username</th>
                                <th class="px-4 py-3">Status</th><th class="px-4 py-3">Publishing</th><th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($accounts as $account)
                                @php
                                    $platform = strtolower((string) ($account->platform ?? ''));
                                    $active = (bool) ($account->is_active ?? true);
                                    $auto = (bool) ($account->auto_publish_enabled ?? false);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-bold text-slate-700">{{ $platformLabels[$platform] ?? ucfirst($platform ?: 'Account') }}</td>
                                    <td class="px-4 py-3 font-black text-slate-900">{{ $account->account_name ?? 'Social account' }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $account->username ?: '—' }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[11px] font-bold {{ $active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="px-4 py-3 text-xs font-bold {{ $auto ? 'text-sky-700' : 'text-slate-400' }}">{{ $auto ? 'Automatic' : 'Manual' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button
                                                type="button"
                                                class="rounded-lg px-3 py-2 text-xs font-bold text-teal-700 hover:bg-teal-50"
                                                data-edit-social-account
                                                data-id="{{ $account->id }}"
                                                data-platform="{{ $platform }}"
                                                data-account-name="{{ e((string) ($account->account_name ?? '')) }}"
                                                data-username="{{ e((string) ($account->username ?? '')) }}"
                                                data-active="{{ $active ? '1' : '0' }}"
                                                data-auto-publish="{{ $auto ? '1' : '0' }}"
                                            >
                                                <i class="fa-solid fa-pen-to-square mr-1"></i>
                                                Edit
                                            </button>

                                            <form method="POST" action="{{ route('profile.social-media.accounts.destroy', $account->id) }}" class="inline" data-confirm="Remove this social media account?" data-confirm-title="Remove social media account" data-confirm-text="Remove">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-empty-state icon="fa-solid fa-link-slash" title="No social media accounts have been added yet." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="smp-tab-panel" data-smp-account-panel="add" hidden>
                <div class="mb-4"><h2 class="text-base font-black text-slate-900">Add Account</h2><p class="mt-1 text-xs text-slate-500">Save another platform or profile for use in the planner.</p></div>
                <form method="POST" action="{{ route('profile.social-media.accounts.store') }}" class="grid gap-4 md:grid-cols-3">
                    @csrf
                    <div><label class="text-xs font-bold text-slate-700">Platform</label><select name="platform" required class="pm-input mt-1 w-full"><option value="">Select platform</option>@foreach($platformLabels as $value => $label)<option value="{{ $value }}" @selected(old('platform') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-bold text-slate-700">Account name</label><input name="account_name" value="{{ old('account_name') }}" required maxlength="120" class="pm-input mt-1 w-full" placeholder="e.g. My Digital Diary"></div>
                    <div><label class="text-xs font-bold text-slate-700">Username / handle</label><input name="username" value="{{ old('username') }}" maxlength="180" class="pm-input mt-1 w-full" placeholder="@username"></div>
                    <div class="md:col-span-3 flex justify-end"><button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"><i class="fa-solid fa-plus mr-1"></i> Add Account</button></div>
                </form>
            </div>

            <div class="smp-tab-panel" data-smp-account-panel="whatsapp" hidden>
                <div class="mb-4"><h2 class="text-base font-black text-slate-900">WhatsApp Settings</h2><p class="mt-1 text-xs text-slate-500">Configure WhatsApp Status and Channel posting details.</p></div>
                <form method="POST" action="{{ route('profile.social-media.whatsapp') }}" class="grid gap-4 md:grid-cols-3">
                    @csrf @method('PUT')
                    <div><label class="text-xs font-bold text-slate-700">WhatsApp number</label><input name="whatsapp_number" value="{{ old('whatsapp_number', auth()->user()->whatsapp_number ?? '') }}" class="pm-input mt-1 w-full" placeholder="+256..."></div>
                    <div><label class="text-xs font-bold text-slate-700">Channel name</label><input name="whatsapp_channel_name" value="{{ old('whatsapp_channel_name', auth()->user()->whatsapp_channel_name ?? '') }}" class="pm-input mt-1 w-full"></div>
                    <div><label class="text-xs font-bold text-slate-700">Channel URL</label><input type="url" name="whatsapp_channel_url" value="{{ old('whatsapp_channel_url', auth()->user()->whatsapp_channel_url ?? '') }}" class="pm-input mt-1 w-full" placeholder="https://whatsapp.com/channel/..."></div>
                    <div class="md:col-span-3 flex justify-end"><button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white">Save WhatsApp Settings</button></div>
                </form>
            </div>
        </div>
    </section>
    <dialog id="social-account-edit-dialog" class="smp-edit-account-dialog">
        <form
            method="POST"
            id="social-account-edit-form"
            action=""
            class="smp-edit-account-card"
        >
            @csrf
            @method('PUT')

            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                        Social Media Account
                    </div>
                    <h2 class="mt-1 text-lg font-black text-slate-900">
                        Edit Account
                    </h2>
                </div>

                <button
                    type="button"
                    id="social-account-edit-close"
                    class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200"
                    aria-label="Close"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-4 px-5 py-4">
                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Platform
                    </label>
                    <select
                        name="platform"
                        id="social-account-edit-platform"
                        required
                        class="pm-input mt-1 w-full"
                    >
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="x">X</option>
                        <option value="tiktok">TikTok</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="whatsapp_status">WhatsApp Status</option>
                        <option value="whatsapp_channel">WhatsApp Channel</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Account name
                    </label>
                    <input
                        type="text"
                        name="account_name"
                        id="social-account-edit-name"
                        required
                        maxlength="120"
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-700">
                        Username / handle
                    </label>
                    <input
                        type="text"
                        name="username"
                        id="social-account-edit-username"
                        maxlength="180"
                        class="pm-input mt-1 w-full"
                    >
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            id="social-account-edit-active"
                            class="rounded"
                        >
                        <span>
                            <span class="block text-sm font-bold text-slate-800">
                                Active
                            </span>
                            <span class="block text-xs text-slate-500">
                                Available in the planner
                            </span>
                        </span>
                    </label>

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                        <input
                            type="checkbox"
                            name="auto_publish_enabled"
                            value="1"
                            id="social-account-edit-auto"
                            class="rounded"
                        >
                        <span>
                            <span class="block text-sm font-bold text-slate-800">
                                Automatic publishing
                            </span>
                            <span class="block text-xs text-slate-500">
                                Use authorised provider API when available
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button
                    type="button"
                    id="social-account-edit-cancel"
                    class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
                >
                    <i class="fa-solid fa-floppy-disk mr-1"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </dialog>

    <style>
        .smp-edit-account-dialog {
            width: min(94vw, 540px);
            max-width: 540px;
            padding: 0;
            border: 0;
            border-radius: 20px;
            background: transparent;
        }

        .smp-edit-account-dialog::backdrop {
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(3px);
        }

        .smp-edit-account-card {
            overflow: hidden;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 26px 80px rgba(15, 23, 42, .25);
        }
    </style>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('[data-smp-account-tab]');
    const panels = document.querySelectorAll('[data-smp-account-panel]');
    function activate(name) {
        tabs.forEach(tab => {
            const active = tab.dataset.smpAccountTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(panel => panel.hidden = panel.dataset.smpAccountPanel !== name);
        try { sessionStorage.setItem('mdd-social-account-tab', name); } catch (_) {}
    }
    tabs.forEach(tab => tab.addEventListener('click', () => activate(tab.dataset.smpAccountTab)));
    let initial = 'saved';
    try { initial = sessionStorage.getItem('mdd-social-account-tab') || initial; } catch (_) {}
    @if($errors->any()) initial = 'add'; @endif
    activate(initial);

    const editDialog = document.getElementById('social-account-edit-dialog');
    const editForm = document.getElementById('social-account-edit-form');
    const editPlatform = document.getElementById('social-account-edit-platform');
    const editName = document.getElementById('social-account-edit-name');
    const editUsername = document.getElementById('social-account-edit-username');
    const editActive = document.getElementById('social-account-edit-active');
    const editAuto = document.getElementById('social-account-edit-auto');

    function closeEditDialog() {
        if (!editDialog) return;
        if (typeof editDialog.close === 'function' && editDialog.open) {
            editDialog.close();
        } else {
            editDialog.removeAttribute('open');
        }
    }

    document.querySelectorAll('[data-edit-social-account]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.dataset.id;

            if (!id || !editDialog || !editForm) return;

            editForm.action =
                @json(route('profile.social-media.accounts.update', ['account' => '__ACCOUNT__']))
                    .replace('__ACCOUNT__', encodeURIComponent(id));

            editPlatform.value = button.dataset.platform || '';
            editName.value = button.dataset.accountName || '';
            editUsername.value = button.dataset.username || '';
            editActive.checked = button.dataset.active === '1';
            editAuto.checked = button.dataset.autoPublish === '1';

            if (typeof editDialog.showModal === 'function') {
                editDialog.showModal();
            } else {
                editDialog.setAttribute('open', 'open');
            }
        });
    });

    document.getElementById('social-account-edit-close')
        ?.addEventListener('click', closeEditDialog);

    document.getElementById('social-account-edit-cancel')
        ?.addEventListener('click', closeEditDialog);

});
</script>
@endsection
