<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_id',
        'personal_goal_id',
        'title',
        'description',
        'achievements',
        'challenges',
        'priority',
        'start_time',
        'end_time',
        'is_completed',
        'completed_at',
        'sort_order',

        'repeat_type',
        'repeat_days',
        'repeat_interval',
        'repeat_starts_on',
        'repeat_ends_on',
        'recurrence_group_id',
        'series_parent_id',

        'reminder_enabled',
        'reminder_offset_minutes',
        'reminder_custom_at',
        'reminder_channels',
        'reminder_id',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'repeat_days' => 'array',
        'repeat_starts_on' => 'date',
        'repeat_ends_on' => 'date',
        'reminder_enabled' => 'boolean',
        'reminder_offset_minutes' => 'integer',
        'reminder_custom_at' => 'datetime',
        'reminder_channels' => 'array',
    ];

    public function personalGoal()
    {
        return $this->belongsTo(
            PersonalGoal::class,
            'personal_goal_id'
        );
    }

    public function plan()
    {
        return $this->belongsTo(DailyPlan::class, 'daily_plan_id');
    }

    public function occurrences()
    {
        return $this->hasMany(
            DailyPlanItemOccurrence::class,
            'daily_plan_item_id'
        );
    }

    public function seriesParent()
    {
        return $this->belongsTo(
            self::class,
            'series_parent_id'
        );
    }

    public function isRecurring(): bool
    {
        return ($this->repeat_type ?: 'once') !== 'once';
    }

    public function repeatLabel(): string
    {
        return match ($this->repeat_type ?: 'once') {
            'daily' => 'Every day',
            'weekly' => 'Every week',
            'monthly' => 'Every month',
            'specific_days' => $this->specificDaysLabel(),
            default => 'Once',
        };
    }

    public function occursOn(CarbonInterface $date): bool
    {
        if (! $this->isRecurring()) {
            return $this->plan
                && $this->plan->plan_date
                && $this->plan->plan_date->isSameDay($date);
        }

        $starts = $this->repeat_starts_on
            ?: $this->plan?->plan_date;

        if (! $starts || $date->lt($starts->startOfDay())) {
            return false;
        }

        if (
            $this->repeat_ends_on
            && $date->gt($this->repeat_ends_on->endOfDay())
        ) {
            return false;
        }

        $interval = max(1, (int) ($this->repeat_interval ?: 1));

        return match ($this->repeat_type) {
            'daily' =>
                $starts->diffInDays($date) % $interval === 0,

            'weekly' =>
                $date->dayOfWeekIso === $starts->dayOfWeekIso
                && intdiv($starts->diffInDays($date), 7) % $interval === 0,

            'specific_days' =>
                in_array(
                    strtolower($date->englishDayOfWeek),
                    array_map(
                        'strtolower',
                        $this->repeat_days ?: []
                    ),
                    true
                )
                && intdiv($starts->diffInDays($date), 7) % $interval === 0,

            'monthly' =>
                $date->day === min(
                    $starts->day,
                    $date->daysInMonth
                )
                && $starts->diffInMonths($date) % $interval === 0,

            default => false,
        };
    }

    private function specificDaysLabel(): string
    {
        $labels = [
            'monday' => 'Mon',
            'tuesday' => 'Tue',
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
            'sunday' => 'Sun',
        ];

        $days = collect($this->repeat_days ?: [])
            ->map(fn ($day) => $labels[strtolower((string) $day)] ?? null)
            ->filter()
            ->values()
            ->all();

        return $days
            ? implode(' • ', $days)
            : 'Specific days';
    }
}
