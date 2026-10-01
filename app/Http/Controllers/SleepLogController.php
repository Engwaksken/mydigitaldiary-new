<?php

namespace App\Http\Controllers;

use App\Models\SleepLog;
use App\Services\HealthAiCoachService;
use App\Services\DailyWellbeingSyncService;
use Illuminate\Http\Request;

class SleepLogController extends CrudController
{
    protected string $model = SleepLog::class;
    protected string $routeName = 'sleep-logs';
    protected string $title = 'Sleep Log';
    protected string $icon = 'fa-solid fa-bed';
    protected string $accent = 'violet';
    protected string $dateField = 'sleep_date';

    protected array $fields = [
        ['name' => 'sleep_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
        [
            'name' => 'bed_time',
            'label' => 'Bed time',
            'type' => 'select',
            'required' => true,
            'options' => [
                '00:00' => '12:00 AM',
                '00:30' => '12:30 AM',
                '01:00' => '1:00 AM',
                '01:30' => '1:30 AM',
                '02:00' => '2:00 AM',
                '02:30' => '2:30 AM',
                '03:00' => '3:00 AM',
                '03:30' => '3:30 AM',
                '04:00' => '4:00 AM',
                '04:30' => '4:30 AM',
                '05:00' => '5:00 AM',
                '05:30' => '5:30 AM',
                '06:00' => '6:00 AM',
                '06:30' => '6:30 AM',
                '07:00' => '7:00 AM',
                '07:30' => '7:30 AM',
                '08:00' => '8:00 AM',
                '08:30' => '8:30 AM',
                '09:00' => '9:00 AM',
                '09:30' => '9:30 AM',
                '10:00' => '10:00 AM',
                '10:30' => '10:30 AM',
                '11:00' => '11:00 AM',
                '11:30' => '11:30 AM',
                '12:00' => '12:00 PM',
                '12:30' => '12:30 PM',
                '13:00' => '1:00 PM',
                '13:30' => '1:30 PM',
                '14:00' => '2:00 PM',
                '14:30' => '2:30 PM',
                '15:00' => '3:00 PM',
                '15:30' => '3:30 PM',
                '16:00' => '4:00 PM',
                '16:30' => '4:30 PM',
                '17:00' => '5:00 PM',
                '17:30' => '5:30 PM',
                '18:00' => '6:00 PM',
                '18:30' => '6:30 PM',
                '19:00' => '7:00 PM',
                '19:30' => '7:30 PM',
                '20:00' => '8:00 PM',
                '20:30' => '8:30 PM',
                '21:00' => '9:00 PM',
                '21:30' => '9:30 PM',
                '22:00' => '10:00 PM',
                '22:30' => '10:30 PM',
                '23:00' => '11:00 PM',
                '23:30' => '11:30 PM',
            ],
        ],
        [
            'name' => 'wake_time',
            'label' => 'Wake time',
            'type' => 'select',
            'required' => true,
            'options' => [
                '00:00' => '12:00 AM',
                '00:30' => '12:30 AM',
                '01:00' => '1:00 AM',
                '01:30' => '1:30 AM',
                '02:00' => '2:00 AM',
                '02:30' => '2:30 AM',
                '03:00' => '3:00 AM',
                '03:30' => '3:30 AM',
                '04:00' => '4:00 AM',
                '04:30' => '4:30 AM',
                '05:00' => '5:00 AM',
                '05:30' => '5:30 AM',
                '06:00' => '6:00 AM',
                '06:30' => '6:30 AM',
                '07:00' => '7:00 AM',
                '07:30' => '7:30 AM',
                '08:00' => '8:00 AM',
                '08:30' => '8:30 AM',
                '09:00' => '9:00 AM',
                '09:30' => '9:30 AM',
                '10:00' => '10:00 AM',
                '10:30' => '10:30 AM',
                '11:00' => '11:00 AM',
                '11:30' => '11:30 AM',
                '12:00' => '12:00 PM',
                '12:30' => '12:30 PM',
                '13:00' => '1:00 PM',
                '13:30' => '1:30 PM',
                '14:00' => '2:00 PM',
                '14:30' => '2:30 PM',
                '15:00' => '3:00 PM',
                '15:30' => '3:30 PM',
                '16:00' => '4:00 PM',
                '16:30' => '4:30 PM',
                '17:00' => '5:00 PM',
                '17:30' => '5:30 PM',
                '18:00' => '6:00 PM',
                '18:30' => '6:30 PM',
                '19:00' => '7:00 PM',
                '19:30' => '7:30 PM',
                '20:00' => '8:00 PM',
                '20:30' => '8:30 PM',
                '21:00' => '9:00 PM',
                '21:30' => '9:30 PM',
                '22:00' => '10:00 PM',
                '22:30' => '10:30 PM',
                '23:00' => '11:00 PM',
                '23:30' => '11:30 PM',
            ],
        ],
        ['name' => 'quality', 'label' => 'Quality', 'type' => 'select', 'required' => true, 'options' => [
            'poor' => 'Poor', 'fair' => 'Fair', 'good' => 'Good', 'excellent' => 'Excellent',
        ]],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'placeholder' => 'Awakenings, caffeine, stress, illness or anything that affected your sleep.'],
    ];

    protected array $rules = [
        'sleep_date' => 'required|date',
        'bed_time' => 'required|date_format:H:i',
        'wake_time' => 'required|date_format:H:i',
        'quality' => 'required|in:poor,fair,good,excellent',
        'notes' => 'nullable|string|max:3000',
    ];

    public function index(Request $request)
    {
        $coach = app(HealthAiCoachService::class);
        $healthProfile = $coach->profile($request->user());

        $sleepTab = in_array(
            (string) $request->query('sleep_tab', 'guidance'),
            ['guidance', 'profile', 'stats'],
            true
        )
            ? (string) $request->query('sleep_tab', 'guidance')
            : 'guidance';

        // Statistics & Logs must not wait on an AI provider.
        $sleepAiAdvice = $sleepTab === 'guidance'
            ? $coach->sleepAdvice($request->user())
            : null;

        $query = SleepLog::where('user_id', $request->user()->id);

        return $this->renderIndex($request, $query, compact(
            'healthProfile',
            'sleepAiAdvice',
            'sleepTab'
        ));
    }

    protected function afterSave(Request $request, $item, bool $wasCreated): void
    {
        try {
            app(HealthAiCoachService::class)->invalidateSleep($request->user());
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            app(DailyWellbeingSyncService::class)->sync(
                $request->user(),
                $item->sleep_date ?? now()
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $item=SleepLog::where('user_id',$request->user()->id)->findOrFail($id);
        $sleepDate=$item->sleep_date;
        $item->delete();

        try {
            app(DailyWellbeingSyncService::class)->sync($request->user(),$sleepDate??now());
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success','Sleep Log deleted.');
    }

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();
        $sevenDaysAgo = now()->subDays(6)->toDateString();

        $summary = SleepLog::query()
            ->where('user_id', $userId)
            ->selectRaw(
                'SUM(CASE WHEN DATE(sleep_date) = ? THEN COALESCE(duration_minutes, 0) ELSE 0 END) as today_minutes,
                 AVG(CASE WHEN DATE(sleep_date) >= ? THEN duration_minutes END) as avg_minutes,
                 SUM(CASE WHEN DATE(sleep_date) >= ? THEN 1 ELSE 0 END) as nights_7d',
                [$today, $sevenDaysAgo, $sevenDaysAgo]
            )
            ->first();

        $todayMinutes = (int) ($summary->today_minutes ?? 0);
        $avgMinutes = (float) ($summary->avg_minutes ?? 0);
        $nights7d = (int) ($summary->nights_7d ?? 0);

        return [
            [
                'label' => 'Sleep today',
                'value' => $todayMinutes > 0 ? number_format($todayMinutes / 60, 1).' hrs' : '—',
                'icon' => 'fa-solid fa-bed',
                'color' => 'violet',
            ],
            [
                'label' => 'Avg sleep (7d)',
                'value' => $avgMinutes > 0 ? number_format($avgMinutes / 60, 1).' hrs' : '—',
                'icon' => 'fa-solid fa-moon',
                'color' => 'indigo',
            ],
            [
                'label' => 'Nights logged (7d)',
                'value' => (string) $nights7d,
                'icon' => 'fa-solid fa-calendar-check',
                'color' => 'slate',
            ],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $startDate = now()->subDays(13)->toDateString();
        $endDate = now()->toDateString();

        $rows = SleepLog::query()
            ->where('user_id', $userId)
            ->whereBetween('sleep_date', [$startDate, $endDate])
            ->selectRaw('DATE(sleep_date) as day, SUM(COALESCE(duration_minutes, 0)) as minutes')
            ->groupByRaw('DATE(sleep_date)')
            ->pluck('minutes', 'day');

        $days = collect(range(13, 0))
            ->map(fn ($d) => now()->subDays($d)->startOfDay());

        $totals = $days->map(
            fn ($day) => round(((int) ($rows[$day->toDateString()] ?? 0)) / 60, 1)
        );

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'line',
            'title' => 'Sleep Hours (last 14 nights)',
            'labels' => $days->map(fn ($d) => $d->format('M j'))->all(),
            'datasets' => [['label' => 'Hours', 'data' => $totals->all()]],
        ];
    }

}
