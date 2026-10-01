@php
    $profile = $healthProfile ?? null;
    $profileComplete = $profile && $profile->weight_kg;
    $isSleep = ($routeName ?? '') === 'sleep-logs';
    $advice = $isSleep ? ($sleepAiAdvice ?? null) : ($dietAiAdvice ?? null);
@endphp


@if (!$isSleep)
    <section class="mb-6 pm-card-bg rounded-xl border border-slate-100 border-l-4 border-l-amber-400 p-4 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 shrink-0 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i class="fa-solid fa-bowl-food"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="font-bold text-slate-800">What did you eat today?</h2>
                <p class="text-sm text-slate-500 mt-1">
                    You can describe your whole day in your own words. This is useful even if you do not log every meal separately.
                </p>

                <form method="POST" action="{{ route('daily-food-journal.update') }}" class="mt-4">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="journal_date" value="{{ now()->toDateString() }}">

                    <textarea
                        name="daily_food_notes"
                        rows="4"
                        required
                        class="pm-input w-full"
                        placeholder="Example: Breakfast: tea and 2 chapatis. Lunch: rice, beans and avocado. Snack: banana. Dinner: matooke, groundnut sauce and vegetables. I drank about 6 glasses of water."
                    >{{ old('daily_food_notes', $dailyFoodJournal->daily_food_notes ?? '') }}</textarea>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mt-2">
                        <p class="text-xs text-slate-400">
                            Add portions where you can. Your daily eating guidance will consider these notes together with your meal logs.
                        </p>
                        <button type="submit"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save today's food
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endif

<div class="mb-6 grid grid-cols-1 xl:grid-cols-2 gap-4">
    <section class="pm-card-bg rounded-xl border border-slate-100 border-l-4 {{ $isSleep ? 'border-l-violet-400' : 'border-l-orange-400' }} p-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-user-shield {{ $isSleep ? 'text-violet-600' : 'text-orange-600' }}"></i>
                    <h2 class="font-bold text-slate-800">Your health profile</h2>
                </div>
                <p class="text-sm text-slate-500 mt-1">
                    Used only to personalise general wellbeing guidance. Health conditions and allergies are optional.
                </p>
            </div>
            <button type="button" onclick="document.getElementById('health-profile-dialog').showModal()"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <i class="fa-solid fa-pen"></i>
                {{ $profileComplete ? 'Update profile' : 'Set up profile' }}
            </button>
        </div>

        @if ($profileComplete)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4 text-sm">
                <div class="rounded-lg bg-slate-50 p-2">
                    <div class="text-xs text-slate-400">Weight</div>
                    <div class="font-bold text-slate-700">{{ number_format((float) $profile->weight_kg, 1) }} kg</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <div class="text-xs text-slate-400">Activity</div>
                    <div class="font-bold text-slate-700 capitalize">{{ str_replace('_', ' ', $profile->activity_level ?: 'Not set') }}</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <div class="text-xs text-slate-400">Goal</div>
                    <div class="font-bold text-slate-700 capitalize">{{ str_replace('_', ' ', $profile->health_goal ?: 'General wellbeing') }}</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <div class="text-xs text-slate-400">Allergies</div>
                    <div class="font-bold text-slate-700 truncate">{{ $profile->food_allergies ?: 'None entered' }}</div>
                </div>
            </div>
        @else
            <div class="mt-4 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2 text-sm text-amber-800">
                <i class="fa-solid fa-circle-info mr-1"></i>
                Start by adding your weight. You can optionally add allergies, current illness/health conditions, eating preferences and sleep challenges.
            </div>
        @endif
    </section>

    <section class="pm-card-bg rounded-xl border border-slate-100 border-l-4 {{ $isSleep ? 'border-l-indigo-400' : 'border-l-emerald-400' }} p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles {{ $isSleep ? 'text-indigo-600' : 'text-emerald-600' }}"></i>
                    <h2 class="font-bold text-slate-800">
                        {{ $isSleep ? 'Sleep guidance' : 'Daily eating guidance' }}
                    </h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Personal suggestions based on the details and logs you choose to share.
                </p>
            </div>
            @if ($profileComplete)
                <form method="POST" action="{{ route($isSleep ? 'health-ai.sleep.refresh' : 'health-ai.diet.refresh') }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate"></i> Refresh
                    </button>
                </form>
            @endif
        </div>

        @if (!$profileComplete)
            <p class="text-sm text-slate-500 mt-4">Add a few details about yourself to get suggestions that better fit your routine.</p>
        @elseif (!$advice)
            <p class="text-sm text-slate-500 mt-4">AI guidance is temporarily unavailable. Your logs will still save normally.</p>
        @elseif ($isSleep)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-4">
                <div class="rounded-lg bg-indigo-50 p-3">
                    <div class="text-xs text-indigo-500 font-semibold">Suggested bedtime</div>
                    <div class="font-bold text-slate-800 mt-1">{{ $advice['recommended_bedtime'] ?: 'Flexible' }}</div>
                </div>
                <div class="rounded-lg bg-violet-50 p-3">
                    <div class="text-xs text-violet-500 font-semibold">Suggested wake time</div>
                    <div class="font-bold text-slate-800 mt-1">{{ $advice['recommended_wake_time'] ?: 'Flexible' }}</div>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500 font-semibold">Sleep amount</div>
                    <div class="font-bold text-slate-800 mt-1">{{ $advice['recommended_hours'] ?: 'Based on your routine' }}</div>
                </div>
            </div>
            @if (!empty($advice['summary']))
                <p class="text-sm text-slate-700 mt-3">{{ $advice['summary'] }}</p>
            @endif
            @if (!empty($advice['tips']))
                <ul class="mt-3 space-y-1 text-sm text-slate-600 list-disc pl-5">
                    @foreach ($advice['tips'] as $tip)
                        <li>{{ $tip }}</li>
                    @endforeach
                </ul>
            @endif
        @else
            @if (!empty($advice['today_focus']))
                <div class="mt-4 rounded-lg bg-emerald-50 border border-emerald-100 p-3">
                    <div class="text-xs uppercase tracking-wide text-emerald-600 font-bold">Today's focus</div>
                    <p class="text-sm text-slate-700 mt-1">{{ $advice['today_focus'] }}</p>
                </div>
            @endif
            @if (!empty($advice['summary']))
                <p class="text-sm text-slate-700 mt-3">{{ $advice['summary'] }}</p>
            @endif
            @if (!empty($advice['meal_ideas']))
                <div class="mt-3">
                    <div class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Meal ideas</div>
                    <ul class="space-y-1 text-sm text-slate-600 list-disc pl-5">
                        @foreach ($advice['meal_ideas'] as $idea)
                            <li>{{ $idea }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        @if ($profileComplete && !empty($advice['medical_note']))
            <div class="mt-3 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                <i class="fa-solid fa-user-doctor mr-1"></i>{{ $advice['medical_note'] }}
            </div>
        @endif
    </section>
</div>


<style>
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
        max-height: calc(100dvh - 24px);
        overflow: hidden;
    }

    #health-profile-dialog .pm-modal-header,
    #health-profile-dialog .pm-modal-footer {
        flex: 0 0 auto;
    }

    #health-profile-dialog .pm-modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 24px;
    }

    #health-profile-dialog .pm-modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }

    @media (max-width: 640px) {
        #health-profile-dialog.pm-dialog {
            width: calc(100vw - 12px);
            max-height: calc(100dvh - 12px);
            border-radius: 14px;
        }

        #health-profile-dialog .pm-modal-content {
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
                    <p class="pm-modal-description">Only weight is required. Add other details only if you want them used for more relevant general advice.</p>
                </div>
            </div>
            <button type="button" class="pm-modal-close" onclick="document.getElementById('health-profile-dialog').close()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <form method="POST" action="{{ route('health-profile.update') }}">
            @csrf
            @method('PUT')
            <div class="pm-modal-body grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <option value="">Prefer not to say</option>
                        @foreach (['18-24','25-34','35-44','45-54','55-64','65+'] as $range)
                            <option value="{{ $range }}" @selected(old('age_range', $profile->age_range ?? '') === $range)>{{ $range }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Activity level</label>
                    <select class="pm-input" name="activity_level">
                        <option value="">Select</option>
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
                    <textarea class="pm-input" name="health_conditions" rows="2" placeholder="Optional — only enter this if you want AI to keep its general advice conservative around it.">{{ old('health_conditions', $profile->health_conditions ?? '') }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">AI will not diagnose or prescribe treatment from this information.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Sleep challenges</label>
                    <textarea class="pm-input" name="sleep_challenges" rows="2" placeholder="Optional — e.g. waking often, shift work, difficulty falling asleep">{{ old('sleep_challenges', $profile->sleep_challenges ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Usual bedtime</label>
                    <input class="pm-input" type="time" name="usual_bed_time" value="{{ old('usual_bed_time', isset($profile->usual_bed_time) ? substr((string) $profile->usual_bed_time, 0, 5) : '') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Usual wake time</label>
                    <input class="pm-input" type="time" name="usual_wake_time" value="{{ old('usual_wake_time', isset($profile->usual_wake_time) ? substr((string) $profile->usual_wake_time, 0, 5) : '') }}">
                </div>
            </div>
            <footer class="pm-modal-footer">
                <button type="button" class="pm-btn-cancel" onclick="document.getElementById('health-profile-dialog').close()">Cancel</button>
                <button class="pm-btn-save btn-primary text-white" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Save profile
                </button>
            </footer>
        </form>
    </div>
</dialog>
