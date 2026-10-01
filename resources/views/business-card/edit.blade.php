@extends('layouts.app')

@section('title', 'Business Card')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-id-card text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Digital Business Card</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-5xl">
        <div class="lg:col-span-2 pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
            <form method="POST" action="{{ route('business-card.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                @php
                    // Open the tab holding a validation error, otherwise Profile.
                    $cardTab = $errors->hasAny(['phone', 'whatsapp_phone', 'email', 'website', 'address']) ? 1
                        : ($errors->hasAny(['social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram']) ? 2
                        : ($errors->hasAny(['card_color', 'card_color_secondary']) ? 3 : 0));
                @endphp

                <div class="pm-form-tabs" role="tablist" aria-label="Business card sections" data-pm-form-tabs>
                    <button type="button" role="tab" id="card-tab-0" aria-controls="card-panel-0" data-pm-form-tab="0"
                            aria-selected="{{ $cardTab === 0 ? 'true' : 'false' }}" tabindex="{{ $cardTab === 0 ? 0 : -1 }}"
                            class="pm-form-tab {{ $cardTab === 0 ? 'is-active' : '' }}">Profile</button>
                    <button type="button" role="tab" id="card-tab-1" aria-controls="card-panel-1" data-pm-form-tab="1"
                            aria-selected="{{ $cardTab === 1 ? 'true' : 'false' }}" tabindex="{{ $cardTab === 1 ? 0 : -1 }}"
                            class="pm-form-tab {{ $cardTab === 1 ? 'is-active' : '' }}">Contact</button>
                    <button type="button" role="tab" id="card-tab-2" aria-controls="card-panel-2" data-pm-form-tab="2"
                            aria-selected="{{ $cardTab === 2 ? 'true' : 'false' }}" tabindex="{{ $cardTab === 2 ? 0 : -1 }}"
                            class="pm-form-tab {{ $cardTab === 2 ? 'is-active' : '' }}">Social Links</button>
                    <button type="button" role="tab" id="card-tab-3" aria-controls="card-panel-3" data-pm-form-tab="3"
                            aria-selected="{{ $cardTab === 3 ? 'true' : 'false' }}" tabindex="{{ $cardTab === 3 ? 0 : -1 }}"
                            class="pm-form-tab {{ $cardTab === 3 ? 'is-active' : '' }}">Card Colors</button>
                </div>

                <div class="pm-form-panel" role="tabpanel" id="card-panel-0" aria-labelledby="card-tab-0" data-pm-form-panel="0" @if ($cardTab !== 0) hidden @endif>
                    <div>
                        <label for="photo" class="block text-sm font-medium text-slate-700 mb-1">Photo</label>
                        @if ($card?->photoDataUri())
                            <img src="{{ $card->photoDataUri() }}" alt="" class="w-16 h-16 rounded-full object-cover mb-2">
                        @endif
                        <input type="file" id="photo" name="photo" accept="image/*" class="block w-full text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Logo</label>
                        @if ($card?->logoDataUri())
                            <img src="{{ $card->logoDataUri() }}" alt="" class="h-10 mb-2">
                            <label class="flex items-center gap-2 text-xs text-slate-500 mb-1 cursor-pointer">
                                <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300">
                                Remove existing logo
                            </label>
                        @endif
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name <span class="text-rose-500" aria-hidden="true">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $card?->name) }}" required aria-required="true" class="pm-input">
                            @error('name') <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Job Title</label>
                            <input type="text" id="title" name="title" value="{{ old('title', $card?->title) }}" placeholder="e.g. Marketing Manager" class="pm-input">
                        </div>
                    </div>

                    <div>
                        <label for="company" class="block text-sm font-medium text-slate-700 mb-1">Company</label>
                        <input type="text" id="company" name="company" value="{{ old('company', $card?->company) }}" class="pm-input">
                    </div>

                    <div>
                        <label for="bio" class="block text-sm font-medium text-slate-700 mb-1">Short Bio</label>
                        <textarea id="bio" name="bio" rows="3" class="pm-input">{{ old('bio', $card?->bio) }}</textarea>
                    </div>
                </div>

                <div class="pm-form-panel" role="tabpanel" id="card-panel-1" aria-labelledby="card-tab-1" data-pm-form-panel="1" @if ($cardTab !== 1) hidden @endif>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone', $card?->phone) }}" placeholder="+256 700 000000" class="pm-input">
                        </div>
                        <div>
                            <label for="whatsapp_phone" class="block text-sm font-medium text-slate-700 mb-1">WhatsApp Number</label>
                            <input type="text" id="whatsapp_phone" name="whatsapp_phone" value="{{ old('whatsapp_phone', $card?->whatsapp_phone) }}" placeholder="Include country code" class="pm-input">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $card?->email) }}" class="pm-input">
                            @error('email') <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="website" class="block text-sm font-medium text-slate-700 mb-1">Website</label>
                            <input type="text" id="website" name="website" value="{{ old('website', $card?->website) }}" placeholder="yourcompany.com" class="pm-input">
                        </div>
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address', $card?->address) }}" class="pm-input">
                    </div>
                </div>

                <div class="pm-form-panel" role="tabpanel" id="card-panel-2" aria-labelledby="card-tab-2" data-pm-form-panel="2" @if ($cardTab !== 2) hidden @endif>
                    <fieldset>
                        <legend class="sr-only">Social links</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="social_facebook" class="block text-xs text-slate-500 mb-1"><i class="fa-brands fa-facebook" aria-hidden="true"></i> Facebook</label>
                                <input type="text" id="social_facebook" name="social_facebook" value="{{ old('social_facebook', $card?->social_links['facebook'] ?? '') }}" class="pm-input text-sm">
                            </div>
                            <div>
                                <label for="social_twitter" class="block text-xs text-slate-500 mb-1"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i> X / Twitter</label>
                                <input type="text" id="social_twitter" name="social_twitter" value="{{ old('social_twitter', $card?->social_links['twitter'] ?? '') }}" class="pm-input text-sm">
                            </div>
                            <div>
                                <label for="social_linkedin" class="block text-xs text-slate-500 mb-1"><i class="fa-brands fa-linkedin" aria-hidden="true"></i> LinkedIn</label>
                                <input type="text" id="social_linkedin" name="social_linkedin" value="{{ old('social_linkedin', $card?->social_links['linkedin'] ?? '') }}" class="pm-input text-sm">
                            </div>
                            <div>
                                <label for="social_instagram" class="block text-xs text-slate-500 mb-1"><i class="fa-brands fa-instagram" aria-hidden="true"></i> Instagram</label>
                                <input type="text" id="social_instagram" name="social_instagram" value="{{ old('social_instagram', $card?->social_links['instagram'] ?? '') }}" class="pm-input text-sm">
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div class="pm-form-panel" role="tabpanel" id="card-panel-3" aria-labelledby="card-tab-3" data-pm-form-panel="3" @if ($cardTab !== 3) hidden @endif>
                    <fieldset>
                        <legend class="sr-only">Card colors</legend>

                        <input type="hidden" name="card_color" id="card_color_input" value="{{ old('card_color', $card?->cardColor() ?? '#00897B') }}">
                        <input type="hidden" name="card_color_secondary" id="card_color_secondary_input" value="{{ old('card_color_secondary', $card?->card_color_secondary ?? '') }}">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-3">
                            <div>
                                <label for="card_color_picker" class="block text-xs text-slate-500 mb-1">Primary</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" id="card_color_picker" value="{{ old('card_color', $card?->cardColor() ?? '#00897B') }}"
                                           oninput="pmSelectCardColor(this.value)" class="w-10 h-9 rounded border border-slate-300 cursor-pointer p-0.5">
                                    <input type="text" id="card_color_hex_input" value="{{ strtoupper(old('card_color', $card?->cardColor() ?? '#00897B')) }}"
                                           oninput="pmHandleCardColorHexInput(this.value)" maxlength="7" class="w-24 pm-input font-mono text-xs">
                                </div>
                            </div>
                            <div>
                                <label for="card_color_secondary_picker" class="block text-xs text-slate-500 mb-1">Secondary</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" id="card_color_secondary_picker" value="{{ old('card_color_secondary', $card?->cardColorSecondary() ?? '#73BEB6') }}"
                                           oninput="pmSelectCardColorSecondary(this.value)" class="w-10 h-9 rounded border border-slate-300 cursor-pointer p-0.5">
                                    <input type="text" id="card_color_secondary_hex_input" value="{{ old('card_color_secondary', $card?->card_color_secondary ? strtoupper($card->card_color_secondary) : '') }}"
                                           oninput="pmHandleCardColorSecondaryHexInput(this.value)" placeholder="Follows primary" maxlength="7" class="w-24 pm-input font-mono text-xs">
                                </div>
                            </div>
                        </div>

                        <div id="card-color-preview" class="h-12 rounded-lg" style="background: linear-gradient(135deg, {{ old('card_color', $card?->cardColor() ?? '#00897B') }}, {{ old('card_color_secondary', $card?->cardColorSecondary() ?? '#73BEB6') }});"></div>

                        @error('card_color')
                            <p role="alert" class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                        @error('card_color_secondary')
                            <p role="alert" class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </fieldset>
                </div>

                <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    Save Card
                </button>
            </form>
        </div>

        <div class="space-y-4">
            @if ($card)
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 text-center">
                    <h2 class="font-semibold text-slate-800 mb-3">Your QR Code</h2>
                    <img src="{{ $card->qrCodeUrl() }}" alt="QR code linking to your business card" class="mx-auto mb-3 rounded-lg border border-slate-100">
                    <a href="{{ $card->qrCodeUrl(600) }}" download="business-card-qr.png" class="text-sm text-[var(--brand-1)] hover:underline">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> Download QR
                    </a>
                </div>

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                    <h2 class="font-semibold text-slate-800 mb-3">Scans</h2>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="text-center">
                            <p class="text-xs text-slate-500">Today</p>
                            <p class="text-lg font-bold text-slate-800">{{ $scanStats['today'] }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-slate-500">This Week</p>
                            <p class="text-lg font-bold text-slate-800">{{ $scanStats['this_week'] }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-slate-500">All Time</p>
                            <p class="text-lg font-bold text-slate-800">{{ $scanStats['total'] }}</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('business-card.edit') }}" class="space-y-2" onsubmit="return true;">
                        <label for="scan-period" class="sr-only">Filter scans by date</label>
                        <select id="scan-period" name="scan_period" onchange="pmToggleScanDateRange(this)" class="pm-input text-sm">
                            <option value="" @selected(!$scanStats['period'])>Choose a period...</option>
                            <option value="daily" @selected($scanStats['period'] === 'daily')>Today</option>
                            <option value="weekly" @selected($scanStats['period'] === 'weekly')>This week</option>
                            <option value="monthly" @selected($scanStats['period'] === 'monthly')>This month</option>
                            <option value="range" @selected($scanStats['period'] === 'range')>Custom range...</option>
                        </select>
                        <div id="scan-date-range" class="flex items-center gap-2" style="{{ $scanStats['period'] === 'range' ? '' : 'display: none;' }}">
                            <input type="date" name="scan_from" value="{{ $scanStats['from'] }}" class="pm-input text-sm">
                            <span class="text-slate-400 text-sm">to</span>
                            <input type="date" name="scan_to" value="{{ $scanStats['to'] }}" class="pm-input text-sm">
                        </div>
                        <button type="submit" class="w-full btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                            Check Scans
                        </button>
                    </form>

                    @if ($scanStats['period'])
                        <p class="text-sm text-slate-600 mt-3 text-center">
                            <span class="font-bold text-slate-800">{{ $scanStats['filtered'] }}</span> scan{{ $scanStats['filtered'] === 1 ? '' : 's' }}
                            in that period.
                        </p>
                    @endif
                </div>

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                    <h2 class="font-semibold text-slate-800 mb-3">Share</h2>
                    <div class="flex items-center gap-2 mb-4">
                        <input type="text" readonly value="{{ $card->publicUrl() }}" id="card-public-url" class="pm-input text-xs">
                        <button type="button" onclick="pmCopyCardLink()" class="shrink-0 text-sm text-[var(--brand-1)] hover:underline">Copy</button>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <a href="mailto:?subject={{ urlencode('My digital business card') }}&body={{ urlencode($card->publicUrl()) }}" class="flex flex-col items-center gap-1 text-slate-600 hover:text-[var(--brand-1)]">
                            <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                            Email
                        </a>
                        <a href="https://wa.me/?text={{ urlencode($card->publicUrl()) }}" target="_blank" rel="noopener noreferrer" class="flex flex-col items-center gap-1 text-slate-600 hover:text-[var(--brand-1)]">
                            <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
                            WhatsApp
                        </a>
                        <a href="sms:?body={{ urlencode($card->publicUrl()) }}" class="flex flex-col items-center gap-1 text-slate-600 hover:text-[var(--brand-1)]">
                            <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-comment-sms" aria-hidden="true"></i></span>
                            SMS
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($card->publicUrl()) }}" target="_blank" rel="noopener noreferrer" class="flex flex-col items-center gap-1 text-slate-600 hover:text-[var(--brand-1)]">
                            <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-brands fa-facebook" aria-hidden="true"></i></span>
                            Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode($card->publicUrl()) }}" target="_blank" rel="noopener noreferrer" class="flex flex-col items-center gap-1 text-slate-600 hover:text-[var(--brand-1)]">
                            <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></span>
                            X
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($card->publicUrl()) }}" target="_blank" rel="noopener noreferrer" class="flex flex-col items-center gap-1 text-slate-600 hover:text-[var(--brand-1)]">
                            <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-brands fa-linkedin" aria-hidden="true"></i></span>
                            LinkedIn
                        </a>
                    </div>
                    <a href="{{ route('business-card.pdf') }}" class="mt-4 block text-center btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Download as PDF
                    </a>
                </div>

                <form method="POST" action="{{ route('business-card.toggle-published') }}">
                    @csrf
                    <button type="submit" class="w-full text-sm text-center px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors {{ $card->is_published ? 'text-slate-600' : 'text-amber-600' }}">
                        <i class="fa-solid {{ $card->is_published ? 'fa-eye' : 'fa-eye-slash' }}" aria-hidden="true"></i>
                        {{ $card->is_published ? 'Public — tap to hide' : 'Hidden — tap to make public' }}
                    </button>
                </form>
            @else
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 text-sm text-slate-500">
                    Save your details to get your public link and QR code.
                </div>
            @endif
        </div>
    </div>

    <script>
        function pmCopyCardLink() {
            var input = document.getElementById('card-public-url');
            input.select();
            navigator.clipboard?.writeText(input.value);
        }

        function pmToggleScanDateRange(select) {
            var wrapper = document.getElementById('scan-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        function pmUpdateCardColorPreview() {
            var preview = document.getElementById('card-color-preview');
            if (!preview) return;
            var primary = document.getElementById('card_color_input').value;
            var secondary = document.getElementById('card_color_secondary_input').value || pmLightenForCardPreview(primary);
            preview.style.background = 'linear-gradient(135deg, ' + primary + ', ' + secondary + ')';
        }

        function pmLightenForCardPreview(hex) {
            var r = parseInt(hex.slice(1, 3), 16);
            var g = parseInt(hex.slice(3, 5), 16);
            var b = parseInt(hex.slice(5, 7), 16);
            var mix = function (c) { return Math.round(c + (255 - c) * 0.45); };
            var toHex = function (c) { return c.toString(16).padStart(2, '0'); };
            return '#' + toHex(mix(r)) + toHex(mix(g)) + toHex(mix(b));
        }

        function pmSelectCardColor(hex) {
            hex = hex.toLowerCase();
            document.getElementById('card_color_input').value = hex;
            document.getElementById('card_color_picker').value = hex;
            document.getElementById('card_color_hex_input').value = hex.toUpperCase();
            pmUpdateCardColorPreview();
        }

        function pmHandleCardColorHexInput(value) {
            if (!/^#[0-9A-Fa-f]{6}$/.test(value)) return;
            pmSelectCardColor(value);
        }

        function pmSelectCardColorSecondary(hex) {
            hex = hex.toLowerCase();
            document.getElementById('card_color_secondary_input').value = hex;
            document.getElementById('card_color_secondary_picker').value = hex;
            document.getElementById('card_color_secondary_hex_input').value = hex.toUpperCase();
            pmUpdateCardColorPreview();
        }

        function pmHandleCardColorSecondaryHexInput(value) {
            if (value === '') {
                document.getElementById('card_color_secondary_input').value = '';
                pmUpdateCardColorPreview();
                return;
            }
            if (!/^#[0-9A-Fa-f]{6}$/.test(value)) return;
            pmSelectCardColorSecondary(value);
        }
    </script>
@include('partials.form-tabs')
@endsection
