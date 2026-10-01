@php
    $profile = $healthProfile ?? null;
    $profileComplete = $profile && $profile->weight_kg;
    $advice = $dietAiAdvice ?? null;
    $requestedDietTab = $dietTab ?? request('diet_tab');
    $defaultDietTab = in_array($requestedDietTab, ['today', 'profile', 'guidance', 'history', 'stats'], true)
        ? $requestedDietTab
        : ((request()->filled('q') || request()->filled('period') || request()->filled('page')) ? 'stats' : 'today');
@endphp

<div id="diet-log-tabs" data-default-tab="{{ $defaultDietTab }}" class="mb-6">
    <div class="overflow-x-auto">
        <div role="tablist" aria-label="Meal Log sections"
             class="flex min-w-max gap-1 border-b border-slate-200">
            <button type="button" role="tab" data-diet-tab="today"
                    class="diet-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-bowl-food mr-1.5"></i>What did you eat today?
            </button>
            <button type="button" role="tab" data-diet-tab="profile"
                    class="diet-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-heart-pulse mr-1.5"></i>Your health profile
            </button>
            <button type="button" role="tab" data-diet-tab="guidance"
                    class="diet-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-leaf mr-1.5"></i>Daily eating guidance
            </button>
            <button type="button" role="tab" data-diet-tab="history"
                    class="diet-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-clock-rotate-left mr-1.5"></i>Daily eating history
            </button>
            <button type="button" role="tab" data-diet-tab="stats"
                    class="diet-main-tab px-4 py-3 text-sm font-semibold border-b-2 -mb-px">
                <i class="fa-solid fa-chart-line mr-1.5"></i>Statistics & logs
            </button>
        </div>
    </div>

    <section data-diet-tab-panel="today" class="mt-5">
        <div class="pm-card-bg rounded-2xl border border-slate-100 border-l-4 border-l-amber-400 shadow-sm p-5">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="max-w-2xl">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-lg font-bold text-slate-800">What did you eat today?</h2>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">
                        Add food as you go through the day. Each save becomes a separate history entry, so breakfast will not be overwritten when you later add lunch or dinner.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 shrink-0">
                    <div class="rounded-xl bg-orange-50 px-3 py-2 min-w-[120px]">
                        <p class="text-[11px] uppercase tracking-wide font-semibold text-orange-600">Meals logged</p>
                        <p class="text-lg font-bold text-slate-800">{{ $todayMealsCount ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-3 py-2 min-w-[120px]">
                        <p class="text-[11px] uppercase tracking-wide font-semibold text-emerald-600">Est. calories</p>
                        <p class="text-lg font-bold text-slate-800">{{ number_format((int) ($todayCalories ?? 0)) }}</p>
                    </div>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('daily-food-journal.update') }}"
                  class="mt-5"
                  id="daily-food-structured-form">
                @csrf
                @method('PUT')

                <input type="hidden"
                       name="journal_date"
                       value="{{ now(auth()->user()->timezone ?: config('app.timezone', 'Africa/Kampala'))->toDateString() }}">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="daily-meal-type"
                               class="block text-sm font-semibold text-slate-700 mb-1">
                            Meal category
                        </label>
                        <select id="daily-meal-type"
                                name="meal_type"
                                class="pm-input"
                                required>
                            <option value="">Select meal</option>
                            <option value="breakfast" @selected(old('meal_type') === 'breakfast')>Breakfast</option>
                            <option value="lunch" @selected(old('meal_type') === 'lunch')>Lunch</option>
                            <option value="snack" @selected(old('meal_type') === 'snack')>Snacks</option>
                            <option value="dinner" @selected(old('meal_type') === 'dinner')>Dinner</option>
                            <option value="supper" @selected(old('meal_type') === 'supper')>Supper</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <label class="block text-sm font-semibold text-slate-700">
                                Food eaten
                            </label>
                            <button type="button"
                                    id="add-daily-food-item"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-600 hover:text-amber-700">
                                <i class="fa-solid fa-plus"></i>
                                Add another item
                            </button>
                        </div>

                        <div id="daily-food-items" class="space-y-2">
                            @php
                                $oldFoodItems = old('food_items', ['']);
                                if (!is_array($oldFoodItems) || count($oldFoodItems) === 0) {
                                    $oldFoodItems = [''];
                                }
                            @endphp

                            @foreach ($oldFoodItems as $oldFoodItem)
                                <div class="daily-food-item-row flex items-center gap-2">
                                    <input type="text"
                                           name="food_items[]"
                                           value="{{ $oldFoodItem }}"
                                           class="pm-input flex-1"
                                           placeholder="e.g. 2 chapatis, beans, banana, water"
                                           maxlength="500"
                                           required>
                                    <button type="button"
                                            class="remove-daily-food-item inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                            aria-label="Remove food item">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <p class="text-xs text-slate-400 mt-2">
                            Add each food or drink separately. You can add as many items as you ate for this meal.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4">
                    <p class="text-xs text-slate-400">
                        Save Breakfast, Lunch, Snacks, Dinner and Supper separately. You can edit them later if you forgot something.
                    </p>

                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600">
                        <i class="fa-solid fa-plus"></i>
                        Save meal
                    </button>
                </div>
            </form>

            @if (($todayJournalEntries ?? collect())->isNotEmpty())
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <h3 class="text-sm font-bold text-slate-700">Saved today</h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($todayJournalEntries as $entry)
                            @php
                                $entryText = trim((string) $entry->daily_food_notes);
                            @endphp
                            @php
                                $entryItems = is_array($entry->food_items)
                                    ? $entry->food_items
                                    : [];
                                $entryMeal = $entry->meal_type
                                    ?: \Illuminate\Support\Str::before($entryText, ' — ');
                            @endphp
                            <div class="rounded-xl bg-slate-50 px-3 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700 capitalize">
                                        {{ $entryMeal ?: 'Meal' }}
                                    </span>
                                    <span class="text-xs font-semibold text-slate-400">
                                        {{ optional($entry->created_at)->format('g:i A') }}
                                    </span>
                                </div>

                                @if (count($entryItems))
                                    <ul class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-sm text-slate-700">
                                        @foreach ($entryItems as $foodItem)
                                            <li class="flex gap-2">
                                                <i class="fa-solid fa-circle text-[5px] mt-2 text-amber-400"></i>
                                                <span>{{ $foodItem }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-2 text-sm text-slate-700">{{ $entryText }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section data-diet-tab-panel="profile" class="mt-5" hidden>
        <div class="pm-card-bg rounded-2xl border border-slate-100 border-l-4 border-l-orange-400 shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Your health profile</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Keep these details up to date so your food suggestions better match your routine and preferences.
                    </p>
                </div>
                <button type="button"
                        onclick="document.getElementById('health-profile-dialog').showModal()"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-pen"></i>{{ $profileComplete ? 'Update profile' : 'Set up profile' }}
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
                        <p class="text-xs text-slate-400">Goal</p>
                        <p class="font-bold text-slate-800 mt-1 capitalize">{{ str_replace('_', ' ', $profile->health_goal ?: 'General wellbeing') }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-400">Allergies</p>
                        <p class="font-bold text-slate-800 mt-1 truncate" title="{{ $profile->food_allergies ?: 'None entered' }}">
                            {{ $profile->food_allergies ?: 'None entered' }}
                        </p>
                    </div>
                </div>

                @if (filled($profile->dietary_preferences) || filled($profile->health_conditions))
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 text-sm">
                        @if (filled($profile->dietary_preferences))
                            <div class="rounded-xl border border-slate-100 p-3">
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Preferences</p>
                                <p class="text-slate-700 mt-1">{{ $profile->dietary_preferences }}</p>
                            </div>
                        @endif
                        @if (filled($profile->health_conditions))
                            <div class="rounded-xl border border-slate-100 p-3">
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Health notes</p>
                                <p class="text-slate-700 mt-1">{{ $profile->health_conditions }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            @else
                <div class="mt-5 rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800">
                    Add your weight first. Other details are optional.
                </div>
            @endif
        </div>
    </section>

    <section data-diet-tab-panel="guidance" class="mt-5" hidden>
        <div class="pm-card-bg rounded-2xl border border-slate-100 border-l-4 border-l-emerald-400 shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Daily eating guidance</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        Suggestions based on the food, preferences and wellbeing details you choose to record.
                    </p>
                </div>
                @if ($profileComplete)
                    <form method="POST" action="{{ route('health-ai.diet.refresh') }}">
                        @csrf
                        <button class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-rotate"></i>Refresh guidance
                        </button>
                    </form>
                @endif
            </div>

            @if (!$profileComplete)
                <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600">
                    Add a few details in <strong>Your health profile</strong> to get suggestions that better fit your routine.
                </div>
            @elseif (!$advice)
                <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600">
                    Guidance is temporarily unavailable. Your meal and eating history will still save normally.
                </div>
            @else
                @if (!empty($advice['today_focus']))
                    <div class="mt-5 rounded-xl bg-emerald-50 border border-emerald-100 p-4">
                        <p class="text-xs uppercase tracking-wide text-emerald-600 font-bold">Today’s focus</p>
                        <p class="text-sm text-slate-700 mt-1">{{ $advice['today_focus'] }}</p>
                    </div>
                @endif

                @if (!empty($advice['summary']))
                    <p class="text-sm text-slate-700 mt-4 leading-6">{{ $advice['summary'] }}</p>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                    @if (!empty($advice['meal_ideas']))
                        <div class="rounded-xl border border-slate-100 p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-2">Meal ideas</p>
                            <ul class="space-y-2 text-sm text-slate-600 list-disc pl-5">
                                @foreach ($advice['meal_ideas'] as $idea)
                                    <li>{{ $idea }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($advice['habits']))
                        <div class="rounded-xl border border-slate-100 p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-2">Helpful habits</p>
                            <ul class="space-y-2 text-sm text-slate-600 list-disc pl-5">
                                @foreach ($advice['habits'] as $habit)
                                    <li>{{ $habit }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                @if (!empty($advice['allergy_note']))
                    <div class="mt-4 rounded-xl bg-amber-50 border border-amber-100 p-3 text-sm text-amber-800">
                        <i class="fa-solid fa-shield-heart mr-1"></i>{{ $advice['allergy_note'] }}
                    </div>
                @endif

                @if (!empty($advice['medical_note']))
                    <div class="mt-3 rounded-xl bg-sky-50 border border-sky-100 p-3 text-sm text-sky-800">
                        <i class="fa-solid fa-user-doctor mr-1"></i>{{ $advice['medical_note'] }}
                    </div>
                @endif
            @endif
        </div>
    </section>

    <section data-diet-tab-panel="history" class="mt-5" hidden>
        @include('crud.extras.diet-logs-history')
    </section>
</div>

<style>
    .diet-main-tab {
        border-color: transparent;
        color: #64748b;
        white-space: nowrap;
    }
    .diet-main-tab[aria-selected="true"] {
        border-color: var(--brand-1);
        color: var(--brand-1);
    }

    #health-profile-dialog.pm-dialog {
        width: min(760px, calc(100vw - 24px));
        max-width: 760px;
        max-height: calc(100dvh - 24px);
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 18px;
    }
    #health-profile-dialog .pm-modal-content {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: min(760px, calc(100dvh - 24px));
        max-height: calc(100dvh - 24px);
        min-height: 0;
        overflow: hidden;
    }
    #health-profile-dialog .health-profile-form {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }
    #health-profile-dialog .pm-modal-header,
    #health-profile-dialog .pm-modal-footer {
        flex: 0 0 auto;
    }
    #health-profile-dialog .pm-modal-body {
        flex: 1 1 auto;
        min-height: 0;
        max-height: none !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 32px;
    }
    #health-profile-dialog .pm-modal-footer {
        position: relative;
        z-index: 3;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }

    @media (max-width: 640px) {
        #health-profile-dialog.pm-dialog {
            width: calc(100vw - 12px);
            max-height: calc(100dvh - 12px);
        }
        #health-profile-dialog .pm-modal-content {
            height: calc(100dvh - 12px);
            max-height: calc(100dvh - 12px);
        }
    }
</style>

<dialog id="health-profile-dialog" class="pm-dialog pm-modal-shell">
    <div class="pm-modal-content">
        <header class="pm-modal-header">
            <div class="pm-modal-heading">
                <div class="pm-modal-icon"><i class="fa-solid fa-heart-pulse"></i></div>
                <div>
                    <h2 class="pm-modal-title">Health & wellbeing profile</h2>
                    <p class="pm-modal-description">Only weight is required. Add other details if you want them considered in your suggestions.</p>
                </div>
            </div>
            <button type="button" class="pm-modal-close"
                    onclick="document.getElementById('health-profile-dialog').close()"
                    aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <form method="POST"
              action="{{ route('health-profile.update') }}"
              class="health-profile-form">
            @csrf
            @method('PUT')

            <div class="pm-modal-body grid grid-cols-1 sm:grid-cols-2 gap-4">
                @php
                    $bedRaw = old('usual_bed_time', isset($profile->usual_bed_time) ? substr((string) $profile->usual_bed_time, 0, 5) : '');
                    $wakeRaw = old('usual_wake_time', isset($profile->usual_wake_time) ? substr((string) $profile->usual_wake_time, 0, 5) : '');

                    $timeParts12h = function ($value) {
                        if (! $value) {
                            return ['hour' => '', 'minute' => '00', 'period' => 'AM'];
                        }

                        try {
                            $time = \Carbon\Carbon::createFromFormat('H:i', substr((string) $value, 0, 5));
                            return [
                                'hour' => $time->format('g'),
                                'minute' => $time->format('i'),
                                'period' => $time->format('A'),
                            ];
                        } catch (\Throwable $e) {
                            return ['hour' => '', 'minute' => '00', 'period' => 'AM'];
                        }
                    };

                    $bed12 = $timeParts12h($bedRaw);
                    $wake12 = $timeParts12h($wakeRaw);
                @endphp

                <div class="pm-form-tabs sm:col-span-2" role="tablist" aria-label="Form sections" data-pm-form-tabs>
                    <button type="button" role="tab" id="diet-profile-tab-0" aria-controls="diet-profile-panel-0" aria-selected="true" tabindex="0" data-pm-form-tab="0" class="pm-form-tab is-active">Body</button>
                    <button type="button" role="tab" id="diet-profile-tab-1" aria-controls="diet-profile-panel-1" aria-selected="false" tabindex="-1" data-pm-form-tab="1" class="pm-form-tab">Food &amp; Health</button>
                    <button type="button" role="tab" id="diet-profile-tab-2" aria-controls="diet-profile-panel-2" aria-selected="false" tabindex="-1" data-pm-form-tab="2" class="pm-form-tab">Sleep</button>
                </div>

                <div class="pm-form-panel sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4" role="tabpanel" id="diet-profile-panel-0" aria-labelledby="diet-profile-tab-0" data-pm-form-panel="0">
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

                <div class="pm-form-panel sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4" role="tabpanel" id="diet-profile-panel-1" aria-labelledby="diet-profile-tab-1" data-pm-form-panel="1" hidden>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Food allergies or intolerances</label>
                        <textarea class="pm-input" name="food_allergies" rows="2" placeholder="e.g. peanuts, milk, eggs">{{ old('food_allergies', $profile->food_allergies ?? '') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Dietary preferences</label>
                        <textarea class="pm-input" name="dietary_preferences" rows="2" placeholder="e.g. vegetarian, halal, foods you avoid">{{ old('dietary_preferences', $profile->dietary_preferences ?? '') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Current illness or known health conditions</label>
                        <textarea class="pm-input" name="health_conditions" rows="2" placeholder="Optional — add this only if you want it considered in your general suggestions.">{{ old('health_conditions', $profile->health_conditions ?? '') }}</textarea>
                    </div>
                </div>

                <div class="pm-form-panel sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4" role="tabpanel" id="diet-profile-panel-2" aria-labelledby="diet-profile-tab-2" data-pm-form-panel="2" hidden>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sleep challenges</label>
                        <textarea class="pm-input" name="sleep_challenges" rows="2" placeholder="Optional">{{ old('sleep_challenges', $profile->sleep_challenges ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Usual bedtime</label>
                        <input type="hidden" name="usual_bed_time" value="{{ $bedRaw }}" data-time12-output="bed">
                        <div class="grid grid-cols-3 gap-2" data-time12-group="bed">
                            <select class="pm-input" data-time12-hour>
                                <option value="">Hour</option>
                                @for ($hour = 1; $hour <= 12; $hour++)
                                    <option value="{{ $hour }}" @selected((string) $bed12['hour'] === (string) $hour)>{{ $hour }}</option>
                                @endfor
                            </select>
                            <select class="pm-input" data-time12-minute>
                                @foreach (['00','05','10','15','20','25','30','35','40','45','50','55'] as $minute)
                                    <option value="{{ $minute }}" @selected($bed12['minute'] === $minute)>{{ $minute }}</option>
                                @endforeach
                            </select>
                            <select class="pm-input" data-time12-period>
                                <option value="AM" @selected($bed12['period'] === 'AM')>AM</option>
                                <option value="PM" @selected($bed12['period'] === 'PM')>PM</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Usual wake time</label>
                        <input type="hidden" name="usual_wake_time" value="{{ $wakeRaw }}" data-time12-output="wake">
                        <div class="grid grid-cols-3 gap-2" data-time12-group="wake">
                            <select class="pm-input" data-time12-hour>
                                <option value="">Hour</option>
                                @for ($hour = 1; $hour <= 12; $hour++)
                                    <option value="{{ $hour }}" @selected((string) $wake12['hour'] === (string) $hour)>{{ $hour }}</option>
                                @endfor
                            </select>
                            <select class="pm-input" data-time12-minute>
                                @foreach (['00','05','10','15','20','25','30','35','40','45','50','55'] as $minute)
                                    <option value="{{ $minute }}" @selected($wake12['minute'] === $minute)>{{ $minute }}</option>
                                @endforeach
                            </select>
                            <select class="pm-input" data-time12-period>
                                <option value="AM" @selected($wake12['period'] === 'AM')>AM</option>
                                <option value="PM" @selected($wake12['period'] === 'PM')>PM</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="pm-modal-footer">
                <button type="button" class="pm-btn-cancel"
                        onclick="document.getElementById('health-profile-dialog').close()">Cancel</button>
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
    function initialiseDietTabs() {
        const root = document.getElementById('diet-log-tabs');
        if (!root || root.dataset.tabsInitialised === '1') return;

        root.dataset.tabsInitialised = '1';

        const buttons = Array.from(root.querySelectorAll('[data-diet-tab]'));

        function panels() {
            return Array.from(document.querySelectorAll('[data-diet-tab-panel]'));
        }

        function openDietTab(name, updateUrl) {
            const valid = buttons.some(button => button.dataset.dietTab === name);
            if (!valid) name = 'today';

            buttons.forEach(button => {
                const active = button.dataset.dietTab === name;
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.tabIndex = active ? 0 : -1;
            });

            /*
             * Query panels at the moment the tab opens, not when this partial
             * first executes. The Statistics & logs panel is rendered later by
             * crud/index.blade.php, so it does not exist yet while this top
             * partial is being parsed.
             */
            panels().forEach(panel => {
                panel.hidden = panel.dataset.dietTabPanel !== name;
            });

            try {
                sessionStorage.setItem('myDigitalDiary.dietTab', name);
            } catch (_) {}

            if (updateUrl && window.history?.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('diet_tab', name);
                history.replaceState({}, '', url);
            }

            window.dispatchEvent(new CustomEvent('mdd:diet-tab-opened', {
                detail: { tab: name }
            }));
        }

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                openDietTab(button.dataset.dietTab, true);
            });
        });

        let initial = root.dataset.defaultTab || 'today';

        if (!new URL(window.location.href).searchParams.has('diet_tab')) {
            try {
                const remembered = sessionStorage.getItem('myDigitalDiary.dietTab');
                if (remembered) initial = remembered;
            } catch (_) {}
        }

        openDietTab(initial, false);
    }

    /*
     * This script lives inside the "-top" partial, but the stats panel is
     * printed AFTER that partial by the generic CRUD view. Initialising
     * immediately therefore misses the stats panel on a direct URL such as
     * ?diet_tab=stats. Wait until the full DOM is available.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseDietTabs, {
            once: true
        });
    } else {
        initialiseDietTabs();
    }
})();
</script>


<script>
(function () {
    function sync12HourGroup(group) {
        const name = group.getAttribute('data-time12-group');
        const output = document.querySelector('[data-time12-output="' + name + '"]');
        const hourField = group.querySelector('[data-time12-hour]');
        const minuteField = group.querySelector('[data-time12-minute]');
        const periodField = group.querySelector('[data-time12-period]');
        if (!output || !hourField || !minuteField || !periodField) return;

        const hour12 = parseInt(hourField.value || '0', 10);
        if (!hour12) {
            output.value = '';
            return;
        }

        let hour24 = hour12 % 12;
        if (periodField.value === 'PM') hour24 += 12;

        output.value = String(hour24).padStart(2, '0') + ':' + (minuteField.value || '00');
    }

    document.querySelectorAll('[data-time12-group]').forEach(function (group) {
        group.addEventListener('change', function () {
            sync12HourGroup(group);
        });
        sync12HourGroup(group);
    });

    document.querySelectorAll('form').forEach(function (form) {
        if (!form.querySelector('[data-time12-group]')) return;
        form.addEventListener('submit', function () {
            form.querySelectorAll('[data-time12-group]').forEach(sync12HourGroup);
        });
    });
})();
</script>



<script>
(function () {
    const container = document.getElementById('daily-food-items');
    const addButton = document.getElementById('add-daily-food-item');

    if (!container || !addButton) return;

    function bindRemoveButtons() {
        container.querySelectorAll('.remove-daily-food-item').forEach(function (button) {
            button.onclick = function () {
                const rows = container.querySelectorAll('.daily-food-item-row');

                if (rows.length <= 1) {
                    const input = button.closest('.daily-food-item-row')?.querySelector('input');
                    if (input) input.value = '';
                    return;
                }

                button.closest('.daily-food-item-row')?.remove();
            };
        });
    }

    addButton.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'daily-food-item-row flex items-center gap-2';
        row.innerHTML = `
            <input type="text"
                   name="food_items[]"
                   class="pm-input flex-1"
                   placeholder="e.g. avocado, tea, fruit, water"
                   maxlength="500"
                   required>
            <button type="button"
                    class="remove-daily-food-item inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                    aria-label="Remove food item">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        container.appendChild(row);
        bindRemoveButtons();
        row.querySelector('input')?.focus();
    });

    bindRemoveButtons();
})();
</script>
