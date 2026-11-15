@extends('layouts.app')

@section('title', 'Device Usage Tips')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-lightbulb text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Device Usage Tips</h1>
    </div>

    {{-- Personalized tips, based on the user's OWN current data always
         visible, not tabbed, since this is the freshest/most relevant
         content on the page. --}}
    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5 mb-6 max-w-2xl">
        <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-sparkles text-amber-500" aria-hidden="true"></i>
            For You Today
        </h2>
        @if (empty($dailyTips))
            <p class="text-sm text-slate-500 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500" aria-hidden="true"></i>
                Nothing stands out right now your tracked data looks up to date. Nice work.
            </p>
        @else
            <ul class="space-y-3">
                @foreach ($dailyTips as $tip)
                    <li class="flex items-start gap-3 text-sm">
                        <span class="w-7 h-7 rounded-lg bg-{{ $tip['color'] }}-100 text-{{ $tip['color'] }}-600 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="{{ $tip['icon'] }} text-xs" aria-hidden="true"></i>
                        </span>
                        <span class="text-slate-700">{{ $tip['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div role="tablist" aria-label="Usage tip categories" class="flex gap-1 border-b border-slate-200 mb-6 max-w-2xl overflow-x-auto">
        <button type="button" role="tab" id="pm-tips-tab-phone" aria-controls="pm-tips-panel-phone"
                aria-selected="true" tabindex="0" data-tab="phone"
                onclick="pmSelectTipsTab('phone')" onkeydown="pmTipsTabKeydown(event, 'phone')"
                class="pm-tips-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-[var(--brand-1)] text-[var(--brand-1)]">
            <i class="fa-solid fa-mobile-screen" aria-hidden="true"></i>
            <span>Phone</span>
        </button>
        <button type="button" role="tab" id="pm-tips-tab-tablet" aria-controls="pm-tips-panel-tablet"
                aria-selected="false" tabindex="-1" data-tab="tablet"
                onclick="pmSelectTipsTab('tablet')" onkeydown="pmTipsTabKeydown(event, 'tablet')"
                class="pm-tips-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-tablet-screen-button" aria-hidden="true"></i>
            <span>Tablet</span>
        </button>
        <button type="button" role="tab" id="pm-tips-tab-desktop" aria-controls="pm-tips-panel-desktop"
                aria-selected="false" tabindex="-1" data-tab="desktop"
                onclick="pmSelectTipsTab('desktop')" onkeydown="pmTipsTabKeydown(event, 'desktop')"
                class="pm-tips-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-desktop" aria-hidden="true"></i>
            <span>Desktop</span>
        </button>
        <button type="button" role="tab" id="pm-tips-tab-reminders" aria-controls="pm-tips-panel-reminders"
                aria-selected="false" tabindex="-1" data-tab="reminders"
                onclick="pmSelectTipsTab('reminders')" onkeydown="pmTipsTabKeydown(event, 'reminders')"
                class="pm-tips-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span>Reminders &amp; Alarms</span>
        </button>
    </div>

    <div class="max-w-2xl">
        <div role="tabpanel" id="pm-tips-panel-phone" aria-labelledby="pm-tips-tab-phone" tabindex="0" class="pm-tips-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
            <ul class="text-sm text-slate-600 space-y-2 list-disc list-inside">
                <li>Add this app to your home screen: open it in your phone's browser, then use
                    "Add to Home Screen" (Safari) or "Install app" (Chrome) for a full-screen,
                    app-like experience without needing an app store.</li>
                <li>The floating accessibility button (bottom-right on every page) works the same
                    on mobile tap it for text size, high contrast, and reduced-motion options.</li>
                <li>Use the microphone icon on text fields to dictate instead of typing it keeps
                    listening until you tap it again or move to another field.</li>
                <li>Enable notifications when prompted so reminder alarms can reach you even when
                    the app isn't open in the foreground.</li>
            </ul>
        </div>

        <div role="tabpanel" id="pm-tips-panel-tablet" aria-labelledby="pm-tips-tab-tablet" tabindex="0" class="pm-tips-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5" hidden>
            <ul class="text-sm text-slate-600 space-y-2 list-disc list-inside">
                <li>Rotate to landscape for wider tables (Activity, module lists) they scroll
                    horizontally in portrait but show more columns at once in landscape.</li>
                <li>Split-screen with another app (e.g. your calendar) works fine the layout
                    reflows down to a single column automatically at narrower widths.</li>
            </ul>
        </div>

        <div role="tabpanel" id="pm-tips-panel-desktop" aria-labelledby="pm-tips-tab-desktop" tabindex="0" class="pm-tips-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5" hidden>
            <ul class="text-sm text-slate-600 space-y-2 list-disc list-inside">
                <li>Every tabbed page (Dashboard, module pages, Profile, Privacy, this page) supports
                    arrow-key navigation once a tab is focused Left/Right to switch, Home/End to
                    jump to the first/last tab.</li>
                <li>Keyboard users: every modal (create/edit forms, confirmations) can be closed with
                    Escape.</li>
            </ul>
        </div>

        <div role="tabpanel" id="pm-tips-panel-reminders" aria-labelledby="pm-tips-tab-reminders" tabindex="0" class="pm-tips-panel pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5" hidden>
            <ul class="text-sm text-slate-600 space-y-2 list-disc list-inside">
                <li>The bell icon on your Dashboard mutes/unmutes the in-app alarm sound instantly,
                    without touching any individual reminder's own settings.</li>
                <li>Each reminder has its own "pop up an in-app alarm" checkbox turn it off for
                    reminders you'd rather receive quietly by email only.</li>
                <li>A brand-new account gets a default "Drink water" reminder every 2 hours edit its
                    frequency or delete it from Reminders if it's not for you.</li>
                <li>You'll get a daily email around 6am with your top 3 open items for the day -
                    turn this off from your account if you'd rather not receive it.</li>
            </ul>
        </div>
    </div>

    <script>
        function pmSelectTipsTab(key) {
            document.querySelectorAll('.pm-tips-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
                if (isSelected) { btn.focus(); }
            });
            document.querySelectorAll('.pm-tips-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-tips-panel-' + key;
            });
        }

        function pmTipsTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-tips-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else if (event.key === 'Home') { nextIndex = 0; }
            else if (event.key === 'End') { nextIndex = tabs.length - 1; }
            else { return; }

            event.preventDefault();
            pmSelectTipsTab(tabs[nextIndex]);
        }
    </script>
@endsection
