@php
    $profile = $healthProfile ?? null;
    $profileComplete = $profile && $profile->weight_kg;
    $advice = $sleepAiAdvice ?? null;

    $requestedSleepTab = request('sleep_tab');
    $defaultSleepTab = in_array($requestedSleepTab, ['guidance', 'profile', 'stats'], true)
        ? $requestedSleepTab
        : ((request()->filled('q') || request()->filled('period') || request()->filled('page')) ? 'stats' : 'guidance');
@endphp

<div id="sleep-log-tabs" data-default-tab="{{ $defaultSleepTab }}" class="mb-6">
    <div class="overflow-x-auto">
        <div role="tablist" aria-label="Sleep Log sections"
             class="flex min-w-max gap-1 border-b border-slate-200">
            <button type="button" role="tab" data-sleep-tab="guidance"
                    class="sleep-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-moon mr-1.5"></i>Sleep guidance
            </button>

            <button type="button" role="tab" data-sleep-tab="profile"
                    class="sleep-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-heart-pulse mr-1.5"></i>Your health profile
            </button>

            <button type="button" role="tab" data-sleep-tab="stats"
                    class="sleep-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-chart-line mr-1.5"></i>Statistics & logs
            </button>
        </div>
    </div>

    <section data-sleep-tab-panel="guidance" class="mt-5">
        <div class="pm-card-bg rounded-2xl border border-slate-100 border-l-4 border-l-indigo-400 shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Sleep guidance</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Suggestions based on your sleep records and the wellbeing details you choose to add.
                    </p>
                </div>

                @if ($profileComplete)
                    <form method="POST" action="{{ route('health-ai.sleep.refresh') }}">
                        @csrf
                        <button class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-rotate"></i>Refresh guidance
                        </button>
                    </form>
                @endif
            </div>

            @if (!$profileComplete)
                <div class="mt-5 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800">
                    Add your weight first, then optionally add your usual sleep times, sleep challenges and other details that may help personalise your routine.
                </div>
            @elseif (!$advice)
                <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600">
                    Sleep guidance is temporarily unavailable. Your sleep records will still save normally.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-5">
                    <div class="rounded-xl bg-indigo-50 p-4">
                        <p class="text-xs uppercase tracking-wide font-bold text-indigo-600">Suggested bedtime</p>
                        <p class="text-lg font-bold text-slate-800 mt-1">{{ $advice['recommended_bedtime'] ?: 'Flexible' }}</p>
                    </div>

                    <div class="rounded-xl bg-violet-50 p-4">
                        <p class="text-xs uppercase tracking-wide font-bold text-violet-600">Suggested wake time</p>
                        <p class="text-lg font-bold text-slate-800 mt-1">{{ $advice['recommended_wake_time'] ?: 'Flexible' }}</p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-wide font-bold text-slate-500">Sleep amount</p>
                        <p class="text-lg font-bold text-slate-800 mt-1">{{ $advice['recommended_hours'] ?: 'Based on your routine' }}</p>
                    </div>
                </div>

                @if (!empty($advice['summary']))
                    <p class="text-sm text-slate-700 mt-4 leading-6">{{ $advice['summary'] }}</p>
                @endif

                @if (!empty($advice['tips']))
                    <div class="mt-4 rounded-xl border border-slate-100 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-2">Helpful sleep habits</p>
                        <ul class="space-y-2 text-sm text-slate-600 list-disc pl-5">
                            @foreach ($advice['tips'] as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (!empty($advice['medical_note']))
                    <div class="mt-4 rounded-xl bg-sky-50 border border-sky-100 p-3 text-sm text-sky-800">
                        <i class="fa-solid fa-user-doctor mr-1"></i>{{ $advice['medical_note'] }}
                    </div>
                @endif
            @endif
        </div>
    </section>

    <section data-sleep-tab-panel="profile" class="mt-5" hidden>
        <div class="pm-card-bg rounded-2xl border border-slate-100 border-l-4 border-l-violet-400 shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Your health profile</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Keep these details up to date so your sleep suggestions better match your routine.
                    </p>
                </div>

                <button type="button"
                        onclick="document.getElementById('sleep-health-profile-dialog').showModal()"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-pen"></i>
                    {{ $profileComplete ? 'Update profile' : 'Set up profile' }}
                </button>
            </div>

            @if ($profileComplete)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-5">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-400">Weight</p>
                        <p class="font-bold text-slate-800 mt-1">{{ number_format((float) $profile->weight_kg, 1) }} kg</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-400">Activity</p>
                        <p class="font-bold text-slate-800 mt-1 capitalize">{{ str_replace('_', ' ', $profile->activity_level ?: 'Not set') }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-400">Usual bedtime</p>
                        <p class="font-bold text-slate-800 mt-1">
                            @if ($profile->usual_bed_time)
                                {{ \Illuminate\Support\Carbon::parse((string) $profile->usual_bed_time)->format('g:i A') }}
                            @else
                                Not set
                            @endif
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-400">Usual wake time</p>
                        <p class="font-bold text-slate-800 mt-1">
                            @if ($profile->usual_wake_time)
                                {{ \Illuminate\Support\Carbon::parse((string) $profile->usual_wake_time)->format('g:i A') }}
                            @else
                                Not set
                            @endif
                        </p>
                    </div>
                </div>

                @if (filled($profile->sleep_challenges))
                    <div class="mt-3 rounded-xl border border-slate-100 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400 font-bold">Sleep challenges</p>
                        <p class="text-sm text-slate-700 mt-1">{{ $profile->sleep_challenges }}</p>
                    </div>
                @endif
            @else
                <div class="mt-5 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800">
                    Start by adding your weight. Other details are optional.
                </div>
            @endif
        </div>
    </section>
</div>

<style>
    .sleep-main-tab {
        border-color: transparent;
        color: #64748b;
        white-space: nowrap;
    }

    .sleep-main-tab[aria-selected="true"] {
        border-color: var(--brand-1);
        color: var(--brand-1);
    }

    /*
     * Important: the form itself must be a constrained flex column.
     * Previously only the dialog wrapper was flex, so the form kept growing
     * beyond the viewport and the modal body could not scroll to Save/Cancel.
     */
    #sleep-health-profile-dialog.pm-dialog {
        width: min(760px, calc(100vw - 24px));
        max-width: 760px;
        height: auto;
        max-height: calc(100dvh - 24px);
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 18px;
    }

    #sleep-health-profile-dialog .pm-modal-content {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-height: calc(100dvh - 24px);
        overflow: hidden;
    }

    #sleep-health-profile-dialog .sleep-profile-form {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    #sleep-health-profile-dialog .pm-modal-header,
    #sleep-health-profile-dialog .pm-modal-footer {
        flex: 0 0 auto;
    }

    #sleep-health-profile-dialog .pm-modal-body {
        display: grid;
        flex: 1 1 auto;
        min-height: 0;
        max-height: none !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 32px;
    }

    #sleep-health-profile-dialog .pm-modal-footer {
        position: relative;
        z-index: 3;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }

    @media (max-width: 640px) {
        #sleep-health-profile-dialog.pm-dialog {
            width: calc(100vw - 12px);
            max-height: calc(100dvh - 12px);
            border-radius: 14px;
        }

        #sleep-health-profile-dialog .pm-modal-content {
            max-height: calc(100dvh - 12px);
        }
    }
</style>

<dialog id="sleep-health-profile-dialog" class="pm-dialog pm-modal-shell">
    <div class="pm-modal-content">
        <header class="pm-modal-header">
            <div class="pm-modal-heading">
                <div class="pm-modal-icon"><i class="fa-solid fa-heart-pulse"></i></div>
                <div>
                    <h2 class="pm-modal-title">Health & wellbeing profile</h2>
                    <p class="pm-modal-description">Only weight is required. Add other details if you want them considered in your suggestions.</p>
                </div>
            </div>

            <button type="button"
                    class="pm-modal-close"
                    onclick="document.getElementById('sleep-health-profile-dialog').close()"
                    aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <form method="POST" action="{{ route('health-profile.update') }}" class="sleep-profile-form">
            @csrf
            @method('PUT')

            <div class="pm-modal-body grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="pm-form-tabs sm:col-span-2" role="tablist" aria-label="Form sections" data-pm-form-tabs>
                    <button type="button" role="tab" id="sleep-profile-tab-0" aria-controls="sleep-profile-panel-0" aria-selected="true" tabindex="0" data-pm-form-tab="0" class="pm-form-tab is-active">Body</button>
                    <button type="button" role="tab" id="sleep-profile-tab-1" aria-controls="sleep-profile-panel-1" aria-selected="false" tabindex="-1" data-pm-form-tab="1" class="pm-form-tab">Sleep</button>
                    <button type="button" role="tab" id="sleep-profile-tab-2" aria-controls="sleep-profile-panel-2" aria-selected="false" tabindex="-1" data-pm-form-tab="2" class="pm-form-tab">Food &amp; Health</button>
                </div>

                <div class="pm-form-panel sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4" role="tabpanel" id="sleep-profile-panel-0" aria-labelledby="sleep-profile-tab-0" data-pm-form-panel="0">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Weight (kg) *</label>
                        <input class="pm-input" type="number" step="0.1" min="20" max="350" name="weight_kg"
                               value="{{ old('weight_kg', $profile->weight_kg ?? '') }}" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Height (cm)</label>
                        <input class="pm-input" type="number" step="0.1" min="100" max="250" name="height_cm"
                               value="{{ old('height_cm', $profile->height_cm ?? '') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Age range</label>
                        <select class="pm-input" name="age_range">
                            <option value="">Choose age range (optional)</option>
                            @foreach (['18-24','25-34','35-44','45-54','55-64','65+'] as $range)
                                <option value="{{ $range }}" @selected(old('age_range', $profile->age_range ?? '') === $range)>{{ $range }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Activity level</label>
                        <select class="pm-input" name="activity_level">
                            <option value="">Choose activity level (optional)</option>
                            @foreach (['low'=>'Mostly seated','light'=>'Lightly active','moderate'=>'Moderately active','high'=>'Very active'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('activity_level', $profile->activity_level ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Main wellbeing goal</label>
                        <select class="pm-input" name="health_goal">
                            @foreach ([
                                'general_wellbeing'=>'General wellbeing',
                                'maintain_weight'=>'Maintain weight',
                                'gain_weight'=>'Gain weight gradually',
                                'lose_weight'=>'Lose weight gradually',
                                'better_sleep'=>'Sleep better',
                                'more_energy'=>'Improve daily energy'
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(old('health_goal', $profile->health_goal ?? 'general_wellbeing') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="pm-form-panel sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4" role="tabpanel" id="sleep-profile-panel-1" aria-labelledby="sleep-profile-tab-1" data-pm-form-panel="1" hidden>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sleep challenges</label>
                        <textarea class="pm-input" name="sleep_challenges" rows="2"
                                  placeholder="Optional — e.g. waking often, shift work, difficulty falling asleep">{{ old('sleep_challenges', $profile->sleep_challenges ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Usual bedtime</label>
                        <input class="pm-input" type="time" name="usual_bed_time"
                               value="{{ old('usual_bed_time', isset($profile->usual_bed_time) ? substr((string) $profile->usual_bed_time, 0, 5) : '') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Usual wake time</label>
                        <input class="pm-input" type="time" name="usual_wake_time"
                               value="{{ old('usual_wake_time', isset($profile->usual_wake_time) ? substr((string) $profile->usual_wake_time, 0, 5) : '') }}">
                    </div>
                </div>

                <div class="pm-form-panel sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4" role="tabpanel" id="sleep-profile-panel-2" aria-labelledby="sleep-profile-tab-2" data-pm-form-panel="2" hidden>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Current illness or known health conditions</label>
                        <textarea class="pm-input" name="health_conditions" rows="2"
                                  placeholder="Optional — add this only if you want it considered in your general suggestions.">{{ old('health_conditions', $profile->health_conditions ?? '') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Food allergies or intolerances</label>
                        <textarea class="pm-input" name="food_allergies" rows="2"
                                  placeholder="e.g. peanuts, milk, eggs">{{ old('food_allergies', $profile->food_allergies ?? '') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Dietary preferences</label>
                        <textarea class="pm-input" name="dietary_preferences" rows="2"
                                  placeholder="e.g. vegetarian, halal, foods you avoid">{{ old('dietary_preferences', $profile->dietary_preferences ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <footer class="pm-modal-footer">
                <button type="button" class="pm-btn-cancel"
                        onclick="document.getElementById('sleep-health-profile-dialog').close()">Cancel</button>
                <button class="pm-btn-save btn-primary text-white" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i>Save profile
                </button>
            </footer>
        </form>
    </div>
</dialog>

@include('partials.form-tabs')

<script>
(function () {
    function initialiseSleepTabs() {
        const root = document.getElementById('sleep-log-tabs');
        if (!root || root.dataset.tabsInitialised === '1') return;

        root.dataset.tabsInitialised = '1';

        const buttons = Array.from(root.querySelectorAll('[data-sleep-tab]'));

        function panels() {
            return Array.from(document.querySelectorAll('[data-sleep-tab-panel]'));
        }

        function openSleepTab(name, updateUrl) {
            const valid = buttons.some(button => button.dataset.sleepTab === name);
            if (!valid) name = 'guidance';

            buttons.forEach(button => {
                const active = button.dataset.sleepTab === name;
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.tabIndex = active ? 0 : -1;
            });

            /*
             * The Statistics & logs panel is rendered after this top partial
             * by the generic CRUD view, so always query panels dynamically.
             */
            panels().forEach(panel => {
                panel.hidden = panel.dataset.sleepTabPanel !== name;
            });

            try {
                sessionStorage.setItem('myDigitalDiary.sleepTab', name);
            } catch (_) {}

            if (updateUrl && window.history?.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('sleep_tab', name);
                history.replaceState({}, '', url);
            }

            window.dispatchEvent(new CustomEvent('mdd:sleep-tab-opened', {
                detail: { tab: name }
            }));
        }

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                openSleepTab(button.dataset.sleepTab, true);
            });
        });

        let initial = root.dataset.defaultTab || 'guidance';

        if (!new URL(window.location.href).searchParams.has('sleep_tab')) {
            try {
                const remembered = sessionStorage.getItem('myDigitalDiary.sleepTab');
                if (remembered) initial = remembered;
            } catch (_) {}
        }

        openSleepTab(initial, false);
    }

    /*
     * Do not initialise while this top partial is still being parsed.
     * On a direct ?sleep_tab=stats request, the generic stats/table panel
     * has not yet been added to the DOM at that moment.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseSleepTabs, {
            once: true
        });
    } else {
        initialiseSleepTabs();
    }
})();
</script>
