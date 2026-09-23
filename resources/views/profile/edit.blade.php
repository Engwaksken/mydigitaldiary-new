@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div id="profile-page" class="pm-profile-page min-w-0 max-w-full">
<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="font-bold text-slate-800">
                <i class="fa-solid fa-share-nodes mr-2 text-teal-600"></i>
                Social Media Settings
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Manage WhatsApp Status/Channel details and your Instagram,
                Facebook, TikTok and LinkedIn accounts.
            </p>
        </div>

        <a href="{{ route('profile.social-media') }}"
           class="btn-primary inline-flex w-full sm:w-auto items-center justify-center rounded-xl px-4 py-2.5 text-sm font-bold text-white">
            Manage Social Media
        </a>
    </div>
</div>
    @php
        // Which tab should be open on page load: whichever one has a
        // validation error takes priority (so a failed submission reopens
        // on the right tab), then whichever action just succeeded, else
        // defaults to the photo tab.
        $activeTab = 'photo';
        if ($errors->hasAny(['name', 'email'])) {
            $activeTab = 'info';
        } elseif ($errors->hasAny(['current_password', 'password', 'password_confirmation'])) {
            $activeTab = 'password';
        } elseif ($errors->has('theme_color')) {
            $activeTab = 'colors';
        } elseif ($errors->has('avatar')) {
            $activeTab = 'photo';
        } elseif (session('profile_status') === 'profile-updated') {
            $activeTab = 'info';
        } elseif (session('profile_status') === 'password-updated') {
            $activeTab = 'password';
        } elseif (session('profile_status') === 'theme-updated') {
            $activeTab = 'colors';
        } elseif (session('profile_status') === 'personalisation-updated' || $errors->hasAny(['ai_data_permissions','onboarding_focuses'])) {
            $activeTab = 'personalisation';
        }

        $tabs = [
            'photo' => ['label' => 'Profile Photo', 'icon' => 'fa-solid fa-image'],
            'info' => ['label' => 'Profile Information', 'icon' => 'fa-solid fa-id-card'],
            'password' => ['label' => 'Password', 'icon' => 'fa-solid fa-lock'],
            'colors' => ['label' => 'Colors', 'icon' => 'fa-solid fa-palette'],
            'personalisation' => ['label' => 'Personalisation & AI', 'icon' => 'fa-solid fa-sliders'],
            'data' => ['label' => 'Backup & Usage', 'icon' => 'fa-solid fa-database'],
        ];

        // Classic sticky-note colors — saturated enough to still read
        // clearly with white text (used across buttons/badges), rather
        // than the pale pastel versions real sticky notes often are,
        // which would look washed out there.
        $presetColors = [
            '#F9A825' => 'Yellow',
            '#D81B60' => 'Pink',
            '#689F38' => 'Green',
            '#0288D1' => 'Blue',
            '#EF6C00' => 'Orange',
            '#8E24AA' => 'Purple',
            '#E64A19' => 'Coral',
            '#00897B' => 'Mint',
        ];
    @endphp

    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-user text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">My Profile</h1>
    </div>

    <div class="w-full max-w-5xl min-w-0">

        @if (session('profile_status') === 'avatar-updated')
            <div role="status" class="rounded-md bg-emerald-100 text-emerald-800 px-4 py-3 text-sm mb-4">
                Profile photo updated.
            </div>
        @elseif (session('profile_status') === 'avatar-removed')
            <div role="status" class="rounded-md bg-emerald-100 text-emerald-800 px-4 py-3 text-sm mb-4">
                Profile photo removed.
            </div>
        @elseif (session('profile_status') === 'profile-updated')
            <div role="status" class="rounded-md bg-emerald-100 text-emerald-800 px-4 py-3 text-sm mb-4">
                Profile information updated.
            </div>
        @elseif (session('profile_status') === 'password-updated')
            <div role="status" class="rounded-md bg-emerald-100 text-emerald-800 px-4 py-3 text-sm mb-4">
                Password updated.
            </div>
        @elseif (session('profile_status') === 'theme-updated')
            <div role="status" class="rounded-md bg-emerald-100 text-emerald-800 px-4 py-3 text-sm mb-4">Accent colour updated.</div>
        @elseif (session('profile_status') === 'personalisation-updated')
            <div role="status" class="rounded-md bg-emerald-100 text-emerald-800 px-4 py-3 text-sm mb-4">Your personalisation and AI privacy choices were saved.</div>
        @endif

        {{-- Tab list --}}
        <div role="tablist" aria-label="Profile settings" class="pm-profile-tabs border-b border-slate-200 mb-6">
            @foreach ($tabs as $key => $tab)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $key }}"
                    aria-controls="panel-{{ $key }}"
                    aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                    tabindex="{{ $activeTab === $key ? '0' : '-1' }}"
                    data-tab="{{ $key }}"
                    onclick="pmSelectProfileTab('{{ $key }}')"
                    onkeydown="pmProfileTabKeydown(event, '{{ $key }}')"
                    class="pm-profile-tab inline-flex shrink-0 items-center gap-2 px-3.5 sm:px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap
                        {{ $activeTab === $key
                            ? 'border-[var(--brand-1)] text-[var(--brand-1)]'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
                >
                    <i class="{{ $tab['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $tab['label'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Profile photo --}}
        <div role="tabpanel" id="panel-photo" aria-labelledby="tab-photo" tabindex="0"
             class="pm-profile-panel pm-card-bg min-w-0 shadow-sm border border-slate-100 rounded-xl p-4 sm:p-6" @if ($activeTab !== 'photo') hidden @endif>
            <div class="flex items-center gap-4 mb-4">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="Your profile photo"
                         class="w-16 h-16 rounded-full object-cover border border-slate-200"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-[var(--brand-1)] to-[var(--brand-2)] text-white items-center justify-center text-xl font-bold hidden"
                         aria-hidden="true">
                        {{ $user->initial() }}
                    </div>
                @else
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-[var(--brand-1)] to-[var(--brand-2)] text-white flex items-center justify-center text-xl font-bold"
                         aria-hidden="true">
                        {{ $user->initial() }}
                    </div>
                @endif
                <div class="text-sm text-slate-500">
                    Shown next to your name in the sidebar once you're logged in.
                </div>
            </div>

            <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label for="avatar" class="block text-sm font-medium text-slate-700 mb-1">Upload a new photo</label>
                    <input type="file" id="avatar" name="avatar" accept="image/*"
                           aria-describedby="avatar-hint @error('avatar') avatar-error @enderror"
                           @error('avatar') aria-invalid="true" @enderror
                           class="block w-full text-sm">
                    <p id="avatar-hint" class="text-xs text-slate-400 mt-1">JPG, PNG, or GIF. Max 2MB.</p>
                    @error('avatar')
                        <p id="avatar-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    Upload photo
                </button>
            </form>

            @if ($user->avatarUrl())
                <form method="POST" action="{{ route('profile.avatar.remove') }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-slate-500 hover:underline">Remove photo</button>
                </form>
            @endif
        </div>

        {{-- Profile information --}}
        <div role="tabpanel" id="panel-info" aria-labelledby="tab-info" tabindex="0"
             class="pm-profile-panel pm-card-bg min-w-0 shadow-sm border border-slate-100 rounded-xl p-4 sm:p-6" @if ($activeTab !== 'info') hidden @endif>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                           required aria-required="true"
                           @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                           class="pm-input">
                    @error('name')
                        <p id="name-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                           required aria-required="true"
                           @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                           class="pm-input">
                    @error('email')
                        <p id="email-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    Save changes
                </button>
            </form>
        </div>

        {{-- Password --}}
        <div role="tabpanel" id="panel-password" aria-labelledby="tab-password" tabindex="0"
             class="pm-profile-panel pm-card-bg min-w-0 shadow-sm border border-slate-100 rounded-xl p-4 sm:p-6" @if ($activeTab !== 'password') hidden @endif>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block text-sm font-medium text-slate-700 mb-1">Current password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                           required aria-required="true"
                           @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror
                           class="pm-input">
                    @error('current_password')
                        <p id="current_password-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">New password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password"
                           required aria-required="true"
                           @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                           class="pm-input">
                    @error('password')
                        <p id="password-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm new password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                           required aria-required="true"
                           class="pm-input">
                </div>

                <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    Update password
                </button>
            </form>
        </div>

        {{-- Colors --}}
        <div role="tabpanel" id="panel-colors" aria-labelledby="tab-colors" tabindex="0"
             class="pm-profile-panel pm-card-bg min-w-0 shadow-sm border border-slate-100 rounded-xl p-4 sm:p-6" @if ($activeTab !== 'colors') hidden @endif>
            <p class="text-sm text-slate-500 mb-4">
                Pick both gradient colors for your sidebar, buttons, and highlights  everyone else's app keeps
                using the site default; this only changes what you see. Leave the second one blank to have it
                follow the first automatically.
            </p>

            <form method="POST" action="{{ route('profile.theme') }}" id="theme-color-form" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="theme_color" id="theme_color_input" value="{{ $user->themeColor() }}">
                <input type="hidden" name="theme_color_secondary" id="theme_color_secondary_input" value="{{ $user->theme_color_secondary ?? '' }}">

                <div>
                    <span class="block text-sm font-medium text-slate-700 mb-2">Presets</span>
                    <div class="flex flex-wrap gap-3" role="group" aria-label="Preset accent colors">
                        @foreach ($presetColors as $hex => $label)
                            <button type="button"
                                    onclick="pmSelectThemeColor('{{ $hex }}')"
                                    data-hex="{{ strtolower($hex) }}"
                                    class="pm-theme-swatch w-9 h-9 rounded-full shadow-sm ring-2 ring-offset-2 transition-transform hover:scale-110 {{ strtolower($user->themeColor()) === strtolower($hex) ? 'ring-slate-800' : 'ring-transparent' }}"
                                    style="background-color: {{ $hex }};"
                                    aria-label="Use {{ $label }} ({{ $hex }}) as my accent color"
                                    title="{{ $label }}">
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="theme_color_picker" class="block text-sm font-medium text-slate-700 mb-2">Primary color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme_color_picker" value="{{ $user->themeColor() }}"
                               oninput="pmSelectThemeColor(this.value)"
                               class="w-12 h-10 rounded border border-slate-300 cursor-pointer p-0.5"
                               aria-label="Custom primary color picker">
                        <label for="theme_color_hex_input" class="sr-only">Type a primary hex color code directly</label>
                        <input type="text" id="theme_color_hex_input"
                               value="{{ strtoupper($user->themeColor()) }}"
                               oninput="pmHandleThemeHexInput(this.value)"
                               placeholder="#00897B"
                               maxlength="7"
                               pattern="^#[0-9A-Fa-f]{6}$"
                               class="w-28 pm-input font-mono text-sm"
                               aria-describedby="theme-color-hex-hint">
                    </div>
                    <p id="theme-color-hex-hint" class="text-xs text-slate-400 mt-1">
                        Type a hex code directly (e.g. <span class="font-mono">#00897B</span>), or use the picker.
                    </p>
                </div>

                <div>
                    <label for="theme_color_secondary_picker" class="block text-sm font-medium text-slate-700 mb-2">
                        Secondary color <span class="text-slate-400 font-normal">(gradient partner)</span>
                    </label>
                    <div class="flex flex-wrap gap-3 mb-3" role="group" aria-label="Preset secondary colors">
                        @foreach ($presetColors as $hex => $label)
                            <button type="button"
                                    onclick="pmSelectThemeColorSecondary('{{ $hex }}')"
                                    data-hex-secondary="{{ strtolower($hex) }}"
                                    class="pm-theme-swatch-secondary w-9 h-9 rounded-full shadow-sm ring-2 ring-offset-2 transition-transform hover:scale-110 {{ strtolower($user->theme_color_secondary ?? '') === strtolower($hex) ? 'ring-slate-800' : 'ring-transparent' }}"
                                    style="background-color: {{ $hex }};"
                                    aria-label="Use {{ $label }} ({{ $hex }}) as my secondary color"
                                    title="{{ $label }}">
                            </button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="color" id="theme_color_secondary_picker" value="{{ $user->themeColorLight() }}"
                               oninput="pmSelectThemeColorSecondary(this.value)"
                               class="w-12 h-10 rounded border border-slate-300 cursor-pointer p-0.5"
                               aria-label="Custom secondary color picker">
                        <label for="theme_color_secondary_hex_input" class="sr-only">Type a secondary hex color code directly</label>
                        <input type="text" id="theme_color_secondary_hex_input"
                               value="{{ $user->theme_color_secondary ? strtoupper($user->theme_color_secondary) : '' }}"
                               oninput="pmHandleThemeSecondaryHexInput(this.value)"
                               placeholder="Follows primary"
                               maxlength="7"
                               pattern="^#[0-9A-Fa-f]{6}$"
                               class="w-32 pm-input font-mono text-sm"
                               aria-describedby="theme-color-secondary-hex-hint">
                        <button type="button" onclick="pmClearThemeColorSecondary()" class="text-xs text-slate-500 hover:underline whitespace-nowrap">
                            Follow primary
                        </button>
                    </div>
                    <p id="theme-color-secondary-hex-hint" class="text-xs text-slate-400 mt-1">
                        Leave blank to auto-derive a lighter shade of your primary color instead.
                    </p>
                </div>

                <div>
                    <span class="block text-sm font-medium text-slate-700 mb-2">Preview</span>
                    <div id="theme-gradient-preview" class="h-16 rounded-xl shadow-sm"
                         style="background: linear-gradient(135deg, {{ $user->themeColor() }}, {{ $user->themeColorLight() }});"></div>
                </div>

                @error('theme_color')
                    <p role="alert" class="text-sm text-rose-600 flex items-center gap-1">
                        <i class="fa-solid fa-circle-exclamation text-xs" aria-hidden="true"></i>
                        {{ $message }}
                    </p>
                @enderror
                @error('theme_color_secondary')
                    <p role="alert" class="text-sm text-rose-600 flex items-center gap-1">
                        <i class="fa-solid fa-circle-exclamation text-xs" aria-hidden="true"></i>
                        {{ $message }}
                    </p>
                @enderror

                <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
                    <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                        Save Colors
                    </button>
                    @if ($user->theme_color || $user->theme_color_secondary)
                        <button type="submit" onclick="document.getElementById('theme_color_input').value = ''; document.getElementById('theme_color_secondary_input').value = '';"
                                class="text-sm text-slate-500 hover:underline">
                            Reset both to default
                        </button>
                    @endif
                </div>
            </form>
        </div>

        <section id="panel-personalisation" role="tabpanel" aria-labelledby="tab-personalisation" tabindex="0" data-panel="personalisation" class="pm-profile-panel" @if ($activeTab !== 'personalisation') hidden @endif>
            @php
                $selectedAi = old('ai_data_permissions', $user->aiDataPermissions());
                $selectedFocus = old('onboarding_focuses', $user->onboarding_focuses ?? []);
                $aiOptions = [
                    'planning' => ['Planning & tasks','fa-list-check'], 'finance' => ['Finance','fa-wallet'], 'goals' => ['Goals','fa-bullseye'],
                    'health' => ['Health','fa-heart-pulse'], 'wellbeing' => ['Exercise, diet & sleep','fa-person-running'], 'spiritual' => ['Spiritual Growth','fa-hands-praying'],
                    'notes' => ['Personal Notes','fa-note-sticky'], 'meetings' => ['Meetings','fa-video'], 'network' => ['Network Contacts','fa-address-book'],
                    'education' => ['Education','fa-graduation-cap'], 'relationships' => ['Relationships','fa-people-group'],
                ];
                $focusOptions = ['money'=>'Manage my money','day'=>'Organise my day','goals'=>'Reach my goals','health'=>'Improve my health','work'=>'Manage work/business','growth'=>'Personal growth','everything'=>'Everything'];
            @endphp
            <form method="POST" action="{{ route('profile.personalisation') }}" class="space-y-6">
                @csrf @method('PUT')
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                        <div><h2 class="font-bold text-slate-900">What may AI Planner use?</h2><p class="text-sm text-slate-500 mt-1">You stay in control. Untick any part of your diary you do not want included in AI context.</p></div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach($aiOptions as $key => [$label,$icon])
                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 hover:border-[var(--brand-1)] cursor-pointer">
                                <input type="checkbox" name="ai_data_permissions[]" value="{{ $key }}" class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]" @checked(in_array($key, $selectedAi))>
                                <i class="fa-solid {{ $icon }} text-slate-400 w-5 text-center"></i><span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                    <h2 class="font-bold text-slate-900">What should My Digital Diary help you with most?</h2>
                    <p class="text-sm text-slate-500 mt-1 mb-4">These choices help us prioritise shortcuts, onboarding and guidance. You can change them any time.</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($focusOptions as $key => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="onboarding_focuses[]" value="{{ $key }}" class="peer sr-only" @checked(in_array($key, $selectedFocus))>
                                <span class="inline-flex px-3 py-2 rounded-full border border-slate-200 text-sm text-slate-600 peer-checked:bg-[var(--brand-1)] peer-checked:border-[var(--brand-1)] peer-checked:text-white">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @php
                    $notifyPrefs = old('engagement_notification_preferences', $user->engagementNotificationPreferences());
                    $notifyOptions = [
                        'goal_progress' => ['Goal progress alerts','When important plans, savings goals or projects need attention.','fa-bullseye'],
                        'monthly_review' => ['Monthly review ready','A reminder when your new Month in Review is ready.','fa-calendar-check'],
                        'finance_insights' => ['Finance insights','Useful financial-health and spending/saving nudges.','fa-chart-line'],
                        'productivity_nudges' => ['Productivity nudges','Occasional next-best-action reminders when something is genuinely due.','fa-list-check'],
                        'spiritual_insights' => ['Spiritual Growth insights','Allow spiritual reflection prompts to appear in personalised insights.','fa-hands-praying'],
                        'daily_affirmations' => ['Daily affirmations','Show a fresh motivational affirmation in Today’s Insight rotation.','fa-sparkles'],
                        'subscription_reminders' => ['Subscription reminders','Keep important expiry and renewal reminders enabled.','fa-credit-card'],
                    ];
                @endphp
                <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                    <h2 class="font-bold text-slate-900">Smart notification preferences</h2>
                    <p class="text-sm text-slate-500 mt-1 mb-4">Choose the helpful nudges you want. Essential security and account messages are not controlled here.</p>
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach($notifyOptions as $key => [$label,$help,$icon])
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-[var(--brand-1)] cursor-pointer">
                                <input type="hidden" name="engagement_notification_preferences[{{ $key }}]" value="0">
                                <input type="checkbox" name="engagement_notification_preferences[{{ $key }}]" value="1" class="mt-1 rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-1)]" @checked((bool)($notifyPrefs[$key] ?? true))>
                                <i class="fa-solid {{ $icon }} text-slate-400 w-5 text-center mt-1"></i>
                                <span><span class="block text-sm font-semibold text-slate-700">{{ $label }}</span><span class="block text-xs text-slate-500 mt-0.5">{{ $help }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold">Save personalisation</button>
            </form>
        </section>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-600 mt-6">
            Want to export or permanently delete your account instead?
            Head to <a href="{{ route('privacy.show') }}" class="text-[var(--brand-1)] hover:underline font-medium">Privacy &amp; Data</a>.
        </div>
        <section id="panel-data" role="tabpanel" aria-labelledby="tab-data" tabindex="0" data-panel="data" class="pm-profile-panel" @if ($activeTab !== 'data') hidden @endif>
            <div class="apple-surface">
                <div class="flex items-start gap-4">
                    <div class="apple-icon-chip light"><i class="fa-solid fa-database"></i></div>
                    <div class="flex-1"><h2 class="text-lg font-bold text-slate-900">Your data & activity</h2><p class="text-sm text-slate-500 mt-1">Review activity, download a private backup, see usage progress, and restore recently deleted records.</p>
                    <div class="grid sm:grid-cols-2 gap-3 mt-5"><a href="{{ route('activity') }}" class="apple-btn justify-start"><i class="fa-solid fa-clock-rotate-left"></i> Activity log</a><a href="{{ route('account-data.index') }}" class="apple-btn apple-btn-primary justify-start"><i class="fa-solid fa-cloud-arrow-down"></i> Backup, Trash & Usage</a></div></div>
                </div>
            </div>
        </section>
    </div>


    <style>
        /* ================================================================
           MY DIGITAL DIARY — PROFILE PHONE RESPONSIVENESS
           Isolated from global label/span/grid rules.
        ================================================================= */

        #profile-page,
        #profile-page * {
            box-sizing: border-box;
        }

        #profile-page {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            overflow-x: clip;
        }

        #profile-page .pm-profile-tabs {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: stretch !important;
            gap: .25rem !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            white-space: nowrap !important;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
            overscroll-behavior-inline: contain;
        }

        #profile-page .pm-profile-tab {
            display: inline-flex !important;
            flex: 0 0 auto !important;
            width: auto !important;
            min-width: max-content !important;
            max-width: none !important;
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            scroll-snap-align: start;
        }

        #profile-page .pm-profile-tab span,
        #profile-page .pm-profile-tab i {
            display: inline !important;
            width: auto !important;
            min-width: 0 !important;
            max-width: none !important;
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
        }

        #profile-page .pm-profile-panel {
            display: block;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
        }

        #profile-page .pm-profile-panel[hidden] {
            display: none !important;
        }

        #profile-page .pm-profile-panel form,
        #profile-page .pm-profile-panel fieldset,
        #profile-page .pm-profile-panel > div {
            min-width: 0;
            max-width: 100%;
        }

        #profile-page input:not([type="checkbox"]):not([type="radio"]),
        #profile-page select,
        #profile-page textarea {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        #profile-page label,
        #profile-page p,
        #profile-page h1,
        #profile-page h2,
        #profile-page h3,
        #profile-page span:not(.sr-only) {
            word-break: normal !important;
            overflow-wrap: normal !important;
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
        }

        #profile-page img {
            max-width: 100%;
            height: auto;
        }

        #profile-page .apple-btn {
            min-width: 0 !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: anywhere !important;
        }

        @media (max-width: 767px) {
            #profile-page {
                padding-inline: .25rem;
            }

            #profile-page .pm-profile-tabs {
                margin-inline: 0 !important;
                padding-bottom: .35rem;
            }

            #profile-page .pm-profile-panel {
                padding: 1rem !important;
                border-radius: 1rem !important;
            }

            #profile-page .pm-profile-panel .grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            #profile-page .pm-profile-panel .flex:not(.pm-profile-tab):not(.pm-profile-tabs) {
                min-width: 0;
            }

            #profile-page .pm-profile-panel button[type="submit"],
            #profile-page .pm-profile-panel a.apple-btn {
                max-width: 100%;
            }

            #profile-page input[type="file"] {
                font-size: .82rem;
            }

            #profile-page .profile-photo-row {
                align-items: flex-start !important;
            }
        }

        @media (max-width: 420px) {
            #profile-page .pm-profile-tab {
                padding-left: .7rem !important;
                padding-right: .7rem !important;
                font-size: .78rem !important;
            }

            #profile-page .pm-profile-tab i {
                font-size: .78rem !important;
            }
        }
    </style>

    <script>
        function pmUpdateGradientPreview() {
            var preview = document.getElementById('theme-gradient-preview');
            if (!preview) return;
            var primary = document.getElementById('theme_color_input').value;
            var secondary = document.getElementById('theme_color_secondary_input').value || pmLightenForPreview(primary);
            preview.style.background = 'linear-gradient(135deg, ' + primary + ', ' + secondary + ')';
        }

        // Client-side approximation only, purely for the live preview 
        // the REAL derived color (if the user leaves the secondary field
        // blank) is computed server-side by User::themeColorLight() when
        // the form is actually saved. This just avoids the preview
        // looking wrong before that round-trip happens.
        function pmLightenForPreview(hex) {
            var r = parseInt(hex.slice(1, 3), 16);
            var g = parseInt(hex.slice(3, 5), 16);
            var b = parseInt(hex.slice(5, 7), 16);
            var mix = function (channel) { return Math.round(channel + (255 - channel) * 0.45); };
            var toHex = function (channel) { return channel.toString(16).padStart(2, '0'); };
            return '#' + toHex(mix(r)) + toHex(mix(g)) + toHex(mix(b));
        }

        function pmSelectThemeColor(hex) {
            hex = hex.toLowerCase();
            document.getElementById('theme_color_input').value = hex;
            document.getElementById('theme_color_picker').value = hex;
            var hexField = document.getElementById('theme_color_hex_input');
            hexField.value = hex.toUpperCase();
            hexField.setCustomValidity('');
            document.querySelectorAll('.pm-theme-swatch').forEach(function (btn) {
                var matches = btn.dataset.hex === hex;
                btn.classList.toggle('ring-slate-800', matches);
                btn.classList.toggle('ring-transparent', !matches);
            });
            pmUpdateGradientPreview();
        }

        // Typing a hex code directly: only commit it (updating the picker,
        // hidden field, and swatch highlight) once it's a complete, valid
        // #RRGGBB — otherwise the user couldn't type past the 2nd
        // character without every other control fighting an incomplete value.
        function pmHandleThemeHexInput(value) {
            var hexField = document.getElementById('theme_color_hex_input');
            var isValid = /^#[0-9A-Fa-f]{6}$/.test(value);

            if (!isValid) {
                hexField.setCustomValidity('Enter a full hex color, e.g. #00897B');
                return;
            }

            hexField.setCustomValidity('');
            var hex = value.toLowerCase();
            document.getElementById('theme_color_input').value = hex;
            document.getElementById('theme_color_picker').value = hex;
            document.querySelectorAll('.pm-theme-swatch').forEach(function (btn) {
                var matches = btn.dataset.hex === hex;
                btn.classList.toggle('ring-slate-800', matches);
                btn.classList.toggle('ring-transparent', !matches);
            });
            pmUpdateGradientPreview();
        }

        function pmSelectThemeColorSecondary(hex) {
            hex = hex.toLowerCase();
            document.getElementById('theme_color_secondary_input').value = hex;
            document.getElementById('theme_color_secondary_picker').value = hex;
            var hexField = document.getElementById('theme_color_secondary_hex_input');
            hexField.value = hex.toUpperCase();
            hexField.setCustomValidity('');
            document.querySelectorAll('.pm-theme-swatch-secondary').forEach(function (btn) {
                var matches = btn.dataset.hexSecondary === hex;
                btn.classList.toggle('ring-slate-800', matches);
                btn.classList.toggle('ring-transparent', !matches);
            });
            pmUpdateGradientPreview();
        }

        function pmHandleThemeSecondaryHexInput(value) {
            var hexField = document.getElementById('theme_color_secondary_hex_input');

            // Blank is valid here — it means "follow the primary color".
            if (value === '') {
                hexField.setCustomValidity('');
                document.getElementById('theme_color_secondary_input').value = '';
                pmUpdateGradientPreview();
                return;
            }

            var isValid = /^#[0-9A-Fa-f]{6}$/.test(value);
            if (!isValid) {
                hexField.setCustomValidity('Enter a full hex color, e.g. #6D9773, or leave blank');
                return;
            }

            hexField.setCustomValidity('');
            var hex = value.toLowerCase();
            document.getElementById('theme_color_secondary_input').value = hex;
            document.getElementById('theme_color_secondary_picker').value = hex;
            pmUpdateGradientPreview();
        }

        function pmClearThemeColorSecondary() {
            document.getElementById('theme_color_secondary_input').value = '';
            document.getElementById('theme_color_secondary_hex_input').value = '';
            document.getElementById('theme_color_secondary_picker').value = pmLightenForPreview(document.getElementById('theme_color_input').value);
            pmUpdateGradientPreview();
        }

        function pmSelectProfileTab(key) {
            document.querySelectorAll('.pm-profile-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) {
                    btn.focus();
                    if (typeof btn.scrollIntoView === 'function') {
                        btn.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest',
                            inline: 'center'
                        });
                    }
                }
            });
            document.querySelectorAll('.pm-profile-panel').forEach(function (panel) {
                var shouldHide = panel.id !== 'panel-' + key;
                panel.hidden = shouldHide;
                panel.classList.remove('hidden');
                panel.setAttribute('aria-hidden', shouldHide ? 'true' : 'false');
            });
        }

        // Standard WAI-ARIA tabs keyboard pattern: Left/Right/Home/End move
        // focus AND activate the tab (not just focus it).
        function pmProfileTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-profile-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectProfileTab(tabs[nextIndex]);
        }
    </script>
</div>
@endsection
