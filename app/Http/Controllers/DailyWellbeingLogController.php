<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DailyWellbeingLog;
use App\Services\HealthWellbeingSummaryService;
use App\Services\DailyWellbeingSyncService;
use Illuminate\Http\Request;

class DailyWellbeingLogController extends CrudController
{
    protected string $model = DailyWellbeingLog::class;
    protected string $routeName = 'wellbeing';
    protected string $title = 'Daily Wellbeing';
    protected string $icon = 'fa-solid fa-heart-pulse';
    protected string $accent = 'teal';
    protected string $dateField = 'log_date';

    protected array $fields = [
        ['tab'=>'Check-in','name'=>'entry_frequency','label'=>'Check-in Frequency','type'=>'select','required'=>true,'options'=>[
            'once'=>'Once',
            'daily'=>'Daily',
            'weekly'=>'Weekly',
            'monthly'=>'Monthly',
        ]],
        ['tab'=>'Check-in','table'=>'primary','name'=>'log_date','label'=>'Check-in Date','type'=>'date','required'=>true],
        ['tab'=>'Check-in','table'=>'meta','name'=>'water_ml','label'=>'Water Drunk (ml)','type'=>'number','required'=>true],
        ['tab'=>'Check-in','name'=>'water_target_ml','label'=>'Daily Water Target (ml)','type'=>'number','required'=>true],
        ['tab'=>'Activity','name'=>'exercise_minutes','label'=>'Exercise minutes','type'=>'readonly'],
        ['tab'=>'Activity','name'=>'steps','label'=>'Steps','type'=>'readonly'],
        ['tab'=>'Activity','name'=>'meals_logged','label'=>'Meals logged','type'=>'readonly'],
        ['tab'=>'Activity','name'=>'calories_logged','label'=>'Estimated calories','type'=>'readonly'],
        ['tab'=>'Activity','name'=>'sleep_minutes','label'=>'Sleep minutes','type'=>'readonly'],
        ['tab'=>'Mood & Health','table'=>'meta','name'=>'mood','label'=>'Mood','type'=>'select','options'=>['low'=>'Low','okay'=>'Okay','good'=>'Good','great'=>'Great']],
        ['tab'=>'Mood & Health','name'=>'energy_level','label'=>'Energy (1–5)','type'=>'number'],
        ['tab'=>'Mood & Health','name'=>'stress_level','label'=>'Stress (1–5)','type'=>'number'],
        ['tab'=>'Mood & Health','name'=>'pain_level','label'=>'Pain / discomfort (0–10)','type'=>'number'],
        ['tab'=>'Mood & Health','table'=>'meta','name'=>'wellbeing_score','label'=>'Overall wellbeing (1–10)','type'=>'number'],
        ['tab'=>'Mood & Health','name'=>'symptoms','label'=>'Symptoms / how you feel (optional)','type'=>'textarea'],
        ['tab'=>'Self-care','name'=>'self_care_done','label'=>'Self-care completed','type'=>'select','options'=>['1'=>'Yes','0'=>'No']],
        ['tab'=>'Self-care','name'=>'screen_break_done','label'=>'Took a screen break','type'=>'select','options'=>['1'=>'Yes','0'=>'No']],
        ['tab'=>'Self-care','name'=>'reflection_done','label'=>'Reflection / prayer / meditation','type'=>'select','options'=>['1'=>'Yes','0'=>'No']],
        ['tab'=>'Self-care','name'=>'self_care_activity','label'=>'Self-care activity','type'=>'text'],
        ['tab'=>'Self-care','name'=>'notes','label'=>'Notes','type'=>'textarea'],
    ];

    protected array $rules = [
        'entry_frequency'=>'required|in:once,daily,weekly,monthly',
        'log_date'=>'nullable|date',
        'water_ml'=>'nullable|integer|min:0|max:20000',
        'water_target_ml'=>'nullable|integer|min:250|max:20000',
        'mood'=>'nullable|in:low,okay,good,great',
        'energy_level'=>'nullable|integer|min:1|max:5',
        'stress_level'=>'nullable|integer|min:1|max:5',
        'pain_level'=>'nullable|integer|min:0|max:10',
        'wellbeing_score'=>'nullable|integer|min:1|max:10',
        'symptoms'=>'nullable|string|max:2000',
        'self_care_done'=>'nullable|boolean',
        'screen_break_done'=>'nullable|boolean',
        'reflection_done'=>'nullable|boolean',
        'self_care_activity'=>'nullable|string|max:255',
        'notes'=>'nullable|string|max:5000',
    ];

    public function index(Request $request)
    {
        $sync = app(DailyWellbeingSyncService::class);
        $sync->syncRecent($request->user(), 7);

        $connectedSummary = app(HealthWellbeingSummaryService::class)
            ->summary($request->user(), null, 7);

        $query = DailyWellbeingLog::where('user_id', $request->user()->id);

        /*
         * Only user-created wellbeing check-ins are listed here. Connected
         * daily source snapshots remain in daily_wellbeing_logs for health
         * statistics but no longer clutter the page as a new row every day.
         */
        if (\Illuminate\Support\Facades\Schema::hasColumn(
            'daily_wellbeing_logs',
            'manual_entry'
        )) {
            $query->where('manual_entry', true);
        }

        $perPage = (int) $request->query('per_page', 10);

        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        return $this->renderIndex(
            $request,
            $query,
            compact('connectedSummary', 'perPage'),
            null,
            $perPage
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules);

        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $date = \Illuminate\Support\Carbon::parse(
            $data['log_date'] ?? now($timezone)->toDateString(),
            $timezone
        )->startOfDay();

        $frequency = $data['entry_frequency'] ?? 'once';

        /*
         * Once    = one manual check-in on the selected date.
         * Daily   = one manual check-in per selected calendar day.
         * Weekly  = one manual check-in for that calendar week.
         * Monthly = one manual check-in for that calendar month.
         *
         * IMPORTANT:
         * daily_wellbeing_logs may already contain an automatic connected
         * health snapshot for the same user/date. Reuse that row and mark it
         * manual instead of inserting a second row. This avoids duplicate-key
         * database errors when the table has a unique user/date constraint.
         */
        $periodStart = match ($frequency) {
            'weekly' => $date->copy()->startOfWeek(),
            'monthly' => $date->copy()->startOfMonth(),
            'daily', 'once' => $date->copy()->startOfDay(),
            default => $date->copy()->startOfDay(),
        };

        $periodEnd = match ($frequency) {
            'weekly' => $date->copy()->endOfWeek(),
            'monthly' => $date->copy()->endOfMonth(),
            'daily', 'once' => $date->copy()->endOfDay(),
            default => $date->copy()->endOfDay(),
        };

        /*
         * First reuse ANY row already stored for the exact date, including an
         * automatic source snapshot. This is the critical save fix: older
         * schemas commonly enforce one row per user/date.
         */
        $existing = DailyWellbeingLog::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('log_date', $date->toDateString())
            ->first();

        /*
         * If there is no exact-date row and this is Weekly/Monthly, look for
         * the user's existing manual check-in for the same period so Save acts
         * as an update rather than creating duplicate periodic check-ins.
         */
        if (! $existing && in_array($frequency, ['weekly', 'monthly'], true)) {
            $existing = DailyWellbeingLog::query()
                ->where('user_id', $request->user()->id)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn(
                        'daily_wellbeing_logs',
                        'manual_entry'
                    ),
                    fn ($query) => $query->where('manual_entry', true)
                )
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn(
                        'daily_wellbeing_logs',
                        'entry_frequency'
                    ),
                    fn ($query) => $query->where(
                        'entry_frequency',
                        $frequency
                    )
                )
                ->whereBetween('log_date', [
                    $periodStart->toDateString(),
                    $periodEnd->toDateString(),
                ])
                ->first();
        }

        $payload = [
            'user_id' => $request->user()->id,
            'log_date' => $date->toDateString(),
            'water_ml' => array_key_exists('water_ml', $data)
                ? (int) ($data['water_ml'] ?? 0)
                : (int) ($existing?->water_ml ?? 0),
            'water_target_ml' => array_key_exists('water_target_ml', $data)
                ? (int) ($data['water_target_ml'] ?? 2000)
                : (int) ($existing?->water_target_ml ?? 2000),
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn(
            'daily_wellbeing_logs',
            'entry_frequency'
        )) {
            $payload['entry_frequency'] = $frequency;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn(
            'daily_wellbeing_logs',
            'manual_entry'
        )) {
            $payload['manual_entry'] = true;
        }

        foreach ([
            'mood', 'energy_level', 'stress_level', 'pain_level',
            'wellbeing_score', 'symptoms', 'self_care_done',
            'screen_break_done', 'reflection_done', 'self_care_activity',
            'notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        try {
            if ($existing) {
                $existing->forceFill($payload)->save();
            } else {
                DailyWellbeingLog::create($payload);
            }
        } catch (\Illuminate\Database\QueryException $exception) {
            /*
             * Defensive fallback for installations with a legacy unique
             * user/date key. If another daily snapshot appeared between the
             * lookup and insert, update that row instead of showing an error
             * page to the user.
             */
            $sameDate = DailyWellbeingLog::query()
                ->where('user_id', $request->user()->id)
                ->whereDate('log_date', $date->toDateString())
                ->first();

            if (! $sameDate) {
                throw $exception;
            }

            $sameDate->forceFill($payload)->save();
        }

        /*
         * Connected Steps/Diet/Sleep/Exercise enrichment is useful, but a
         * secondary sync failure must never turn a successfully saved manual
         * wellbeing check-in into an application error page.
         */
        try {
            app(DailyWellbeingSyncService::class)
                ->sync($request->user(), $date);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $message = match ($frequency) {
            'daily' => 'Daily wellbeing check-in saved.',
            'weekly' => 'Weekly wellbeing check-in saved.',
            'monthly' => 'Monthly wellbeing check-in saved.',
            default => 'Wellbeing check-in saved.',
        };

        return redirect()
            ->route('wellbeing.index', ['per_page' => $request->query('per_page', 10)])
            ->with('success', $message);
    }

    protected function stats(Request $request): array
    {
        $summary = app(HealthWellbeingSummaryService::class)->summary($request->user(), null, 7);
        $today = $summary['daily'];

        return [
            ['label'=>'Water today','value'=>(string)($today['water_percent'] ?? 0).'%','icon'=>'fa-solid fa-droplet','color'=>'sky'],
            ['label'=>'Exercise today','value'=>(string)($today['exercise_minutes'] ?? 0).' min','icon'=>'fa-solid fa-person-running','color'=>'emerald'],
            ['label'=>'Sleep','value'=>$today['sleep_hours'] !== null ? $today['sleep_hours'].' hrs' : '—','icon'=>'fa-solid fa-bed','color'=>'violet'],
            ['label'=>'Wellbeing','value'=>$today['wellbeing_score'] !== null ? $today['wellbeing_score'].'/10' : '—','icon'=>'fa-solid fa-heart-pulse','color'=>'rose'],
        ];
    }
}
