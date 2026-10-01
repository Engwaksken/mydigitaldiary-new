@extends('layouts.app')

@section('title', 'Social Media APIs')

@section('content')
<div class="space-y-5 max-w-7xl">
    <div class="apple-surface rounded-2xl p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                    Administration
                </div>
                <h1 class="mt-1 text-2xl font-black text-slate-900">Social Media APIs</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    Configure platform/provider credentials once. Users only add their account or session details.
                    Provider secrets are never displayed on user pages.
                </p>
            </div>

            <a href="{{ route('admin.settings.edit') }}"
               class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                <i class="fa-solid fa-arrow-left mr-1"></i> Settings
            </a>
        </div>
    </div>

    <section class="apple-surface rounded-2xl p-5">
        <h2 class="font-black text-lg">Add API Provider</h2>

        <form method="POST"
              action="{{ route('admin.social-media-providers.store') }}"
              class="mt-4 grid gap-4 md:grid-cols-2">
            @csrf

            <div>
                <label class="text-xs font-bold">Platform</label>
                <select name="platform" class="pm-input mt-1 w-full" required>
                    <option value="whatsapp_status">WhatsApp Status</option>
                    <option value="whatsapp_channel">WhatsApp Channel</option>
                    <option value="facebook">Facebook</option>
                    <option value="instagram">Instagram</option>
                    <option value="x">X (Twitter)</option>
                    <option value="tiktok">TikTok</option>
                    <option value="linkedin">LinkedIn</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-bold">Provider name</label>
                <input name="provider_name"
                       placeholder="e.g. WhatsScale"
                       required
                       class="pm-input mt-1 w-full">
            </div>

            <div>
                <label class="text-xs font-bold">Driver</label>
                <select name="driver" class="pm-input mt-1 w-full" required>
                    <option value="whatsscale">WhatsScale</option>
                    <option value="waha">WAHA</option>
                    <option value="meta">Meta</option>
                    <option value="x">X</option>
                    <option value="tiktok">TikTok</option>
                    <option value="linkedin">LinkedIn</option>
                    <option value="generic">Generic API</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-bold">Connection mode</label>
                <select name="connection_mode" class="pm-input mt-1 w-full" required>
                    <option value="shared_api_key">Admin API key + user account/session</option>
                    <option value="user_oauth">User OAuth authorisation required</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-bold">Provider API Base URL</label>
                <input type="url"
                       name="base_url"
                       placeholder="https://proxy.whatsscale.com"
                       class="pm-input mt-1 w-full">
            </div>

            <div>
                <label class="text-xs font-bold">Authentication</label>
                <select name="auth_type" class="pm-input mt-1 w-full">
                    <option value="header">API key header</option>
                    <option value="bearer">Bearer token</option>
                    <option value="none">None</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-bold">API key header</label>
                <input name="auth_header"
                       value="X-Api-Key"
                       class="pm-input mt-1 w-full">
            </div>

            <div>
                <label class="text-xs font-bold">API key / token</label>
                <input type="password"
                       name="api_key"
                       autocomplete="new-password"
                       class="pm-input mt-1 w-full">
            </div>

            <div>
                <label class="text-xs font-bold">API secret</label>
                <input type="password"
                       name="api_secret"
                       autocomplete="new-password"
                       class="pm-input mt-1 w-full" placeholder="Optional">
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-bold">Advanced settings JSON</label>
                <textarea name="settings_json"
                          rows="4"
                          class="pm-input mt-1 w-full"
                          placeholder='{"test_endpoint":"/api/auth/test"}'></textarea>
            </div>

            <div class="md:col-span-2 flex flex-wrap items-center gap-5">
                <label class="flex items-center gap-2 text-xs font-bold">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1" checked>
                    Enabled
                </label>

                <label class="flex items-center gap-2 text-xs font-bold">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1">
                    Default for this platform
                </label>
            </div>

            <div class="md:col-span-2">
                <button class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                    <i class="fa-solid fa-plug mr-1"></i> Save Provider
                </button>
            </div>
        </form>
    </section>

    <section class="apple-surface rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h2 class="font-black">Configured Providers</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1000px] w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left">Platform</th>
                        <th class="px-4 py-3 text-left">Provider</th>
                        <th class="px-4 py-3 text-left">Mode</th>
                        <th class="px-4 py-3 text-left">Base URL</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($providers as $provider)
                        <tr>
                            <td class="px-4 py-3 font-bold">
                                {{ ucwords(str_replace('_', ' ', $provider->platform)) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold">{{ $provider->provider_name }}</div>
                                <div class="text-xs text-slate-500">{{ $provider->driver }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $provider->connection_mode === 'shared_api_key'
                                    ? 'Admin API key'
                                    : 'User OAuth' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $provider->base_url ?: '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span class="rounded-full px-2 py-1 text-[10px] font-black
                                        {{ $provider->is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $provider->is_enabled ? 'ENABLED' : 'DISABLED' }}
                                    </span>
                                    @if($provider->is_default)
                                        <span class="rounded-full bg-sky-50 px-2 py-1 text-[10px] font-black text-sky-700">
                                            DEFAULT
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <form method="POST"
                                          action="{{ route('admin.social-media-providers.test', $provider) }}">
                                        @csrf
                                        <button class="text-xs font-bold text-indigo-700">Test</button>
                                    </form>

                                    <form method="POST"
                                          action="{{ route('admin.social-media-providers.toggle', $provider) }}">
                                        @csrf
                                        <button class="text-xs font-bold text-sky-700">
                                            {{ $provider->is_enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>

                                    @unless($provider->is_default)
                                        <form method="POST"
                                              action="{{ route('admin.social-media-providers.default', $provider) }}">
                                            @csrf
                                            <button class="text-xs font-bold text-emerald-700">Make default</button>
                                        </form>
                                    @endunless

                                    <form method="POST"
                                          action="{{ route('admin.social-media-providers.destroy', $provider) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-bold text-rose-600">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                No social media API providers configured yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">
        <strong>OAuth platforms:</strong>
        Facebook, Instagram, LinkedIn, X and TikTok may still require each user to authorise their own social account.
        Admin credentials configure the application/provider, but cannot legally or technically replace the user's platform authorisation.
    </div>
</div>
@endsection
