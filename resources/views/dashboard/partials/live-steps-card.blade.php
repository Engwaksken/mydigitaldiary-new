@php
    $stepData = is_array($stepData ?? null) ? $stepData : [];
    $steps = max(0, (int) ($stepData['steps'] ?? 0));
    $goal = max(1, (int) ($stepData['daily_goal'] ?? 5000));
    $progress = min(100, max(0, (int) ($stepData['progress_percent'] ?? round(($steps / $goal) * 100))));
    $remaining = max(0, (int) ($stepData['remaining_steps'] ?? ($goal - $steps)));
    $distanceM = max(0, (int) ($stepData['distance_m'] ?? 0));
    $distanceKm = max(0, (float) ($stepData['distance_km'] ?? 0));
    $distanceLabel = $distanceM > 0
        ? ($distanceM < 1000
            ? number_format($distanceM).' m'
            : number_format($distanceKm, 1).' km')
        : null;
    $tracking = (bool) ($stepData['is_tracking'] ?? false);
@endphp

<section
    id="md-live-steps-card"
    class="td-card md-dashboard-section"
    style="padding:14px 16px"
    data-url="{{ url('/wellbeing/steps/live') }}"
    title="Counted by the mobile app and synced here automatically."
>
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-shoe-prints"></i>
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2 flex-wrap">
                <span id="md-step-count" class="text-xl font-extrabold text-slate-900">{{ number_format($steps) }}</span>
                <span class="text-xs font-semibold text-slate-500">steps</span>
                <span id="md-step-distance"
                      class="text-xs font-bold text-emerald-700 {{ $steps > 0 && $distanceLabel ? '' : 'hidden' }}">
                    ≈ {{ $distanceLabel }}
                </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-slate-100 overflow-hidden">
                <div
                    id="md-step-progress"
                    class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                    style="width: {{ $progress }}%"
                ></div>
            </div>
            <div class="mt-1 flex items-center justify-between gap-3 text-[11px] text-slate-500">
                <span><span id="md-step-status" class="{{ $tracking ? 'text-emerald-700' : 'text-slate-500' }}">{{ $tracking ? 'Tracking' : 'Paused' }}</span> · <span id="md-step-remaining">{{ number_format($remaining) }} remaining</span></span>
                <span id="md-step-updated" class="truncate">
                    @if(!empty($stepData['last_synced_at']))
                        Synced {{ \Illuminate\Support\Carbon::parse($stepData['last_synced_at'])->format('g:i A') }}
                    @else
                        Waiting for phone
                    @endif
                </span>
            </div>
        </div>

        <div class="text-right shrink-0">
            <div id="md-step-percent" class="text-base font-extrabold text-slate-800">{{ $progress }}%</div>
            <div class="text-[10px] text-slate-400">of {{ number_format($goal) }}</div>
        </div>
    </div>

    {{-- Kept for the live-sync script; status text is shown above instead. --}}
    <div id="md-step-sync-note" class="hidden"></div>

    <div id="md-step-next-goal"
         class="mt-2 text-[11px] font-bold text-emerald-700 {{ !empty($stepData['goal_achieved']) ? '' : 'hidden' }}">
        @if(!empty($stepData['goal_achieved']))
            Goal reached. Next target: {{ number_format((int) ($stepData['next_daily_goal'] ?? $goal)) }} steps.
        @endif
    </div>
</section>

@once
<script>
(() => {
    const card = document.getElementById('md-live-steps-card');
    if (!card) return;

    const url = card.dataset.url;
    const count = document.getElementById('md-step-count');
    const distance = document.getElementById('md-step-distance');
    const status = document.getElementById('md-step-status');
    const percent = document.getElementById('md-step-percent');
    const progress = document.getElementById('md-step-progress');
    const remaining = document.getElementById('md-step-remaining');
    const updated = document.getElementById('md-step-updated');
    const note = document.getElementById('md-step-sync-note');
    const nextGoal = document.getElementById('md-step-next-goal');

    let requestRunning = false;
    let lastSeenSteps = Number(String(count?.textContent || '0').replace(/,/g, '')) || 0;

    const number = value => new Intl.NumberFormat().format(Number(value || 0));

    async function refresh() {
        if (requestRunning || document.hidden) return;
        requestRunning = true;

        try {
            const response = await fetch(`${url}?_=${Date.now()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                note.textContent = `Live step refresh failed (${response.status}).`;
                return;
            }

            const json = await response.json();
            const data = json?.data || {};

            const steps = Math.max(0, Number(data.steps || 0));
            const goal = Math.max(1, Number(data.daily_goal || 5000));
            const pct = Math.min(100, Math.max(0, Number(
                data.progress_percent ?? Math.round((steps / goal) * 100)
            )));
            const tracking = Boolean(data.is_tracking);

            count.textContent = number(steps);
            percent.textContent = `${pct}%`;
            progress.style.width = `${pct}%`;
            remaining.textContent = `${number(Math.max(0, goal - steps))} remaining`;

            if (distance) {
                const km = Number(data.distance_km || 0);
                distance.textContent = `≈ ${km.toLocaleString(undefined, {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                })} km`;
                distance.classList.toggle('hidden', steps === 0 || km <= 0);
            }

            status.textContent = tracking ? 'Tracking' : 'Paused';
            status.className = tracking ? 'text-emerald-700' : 'text-slate-500';

            if (data.last_synced_at) {
                const synced = new Date(data.last_synced_at);
                updated.textContent = `Synced ${synced.toLocaleTimeString([], {
                    hour: 'numeric',
                    minute: '2-digit',
                })}`;
            } else {
                updated.textContent = 'Waiting for phone';
            }

            note.textContent = steps !== lastSeenSteps
                ? 'Updated from your phone just now.'
                : 'Live sync active — waiting for your next phone step update.';

            if (nextGoal) {
                const achieved = Boolean(data.goal_achieved);
                nextGoal.classList.toggle('hidden', !achieved);
                if (achieved) {
                    nextGoal.textContent =
                        `Goal reached. Next target: ${number(data.next_daily_goal || goal)} steps.`;
                }
            }

            lastSeenSteps = steps;
        } catch (_) {
            note.textContent = 'Live sync is temporarily unavailable. The last saved count is shown.';
        } finally {
            requestRunning = false;
        }
    }

    refresh();
    const timer = setInterval(refresh, 2000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    window.addEventListener('focus', refresh);
    window.addEventListener('pageshow', refresh);
    window.addEventListener('beforeunload', () => clearInterval(timer), { once: true });
})();
</script>
@endonce
