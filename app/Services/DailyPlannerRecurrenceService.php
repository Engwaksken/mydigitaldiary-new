<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\DailyPlanItemOccurrence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

class DailyPlannerRecurrenceService
{
    public const REPEAT_TYPES = [
        'once',
        'daily',
        'specific_days',
        'weekly',
        'monthly',
    ];

    public const WEEK_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * Return the actual tasks due on one date.
     *
     * Recurring items are NOT duplicated in daily_plan_items.
     * We resolve the master series definition at read time and then merge the
     * occurrence completion/override row for the requested date.
     */
    public function itemsForDate(
        int $userId,
        CarbonInterface $date
    ): SupportCollection {
        $date = Carbon::parse($date)->startOfDay();
        $dateString = $date->toDateString();

        $candidates = DailyPlanItem::query()
            ->with([
                'plan',
                'personalGoal',
                'occurrences' => fn ($query) =>
                    $query->whereDate('occurrence_date', $dateString),
            ])
            ->whereHas(
                'plan',
                fn ($query) =>
                    $query->where('user_id', $userId)
            )
            ->where(function ($query) use ($dateString) {
                $query
                    ->where(function ($once) use ($dateString) {
                        $once->where(function ($q) {
                            $q->whereNull('repeat_type')
                                ->orWhere('repeat_type', 'once');
                        })->whereHas(
                            'plan',
                            fn ($plan) =>
                                $plan->whereDate(
                                    'plan_date',
                                    $dateString
                                )
                        );
                    })
                    ->orWhere(function ($recurring) use ($dateString) {
                        $recurring
                            ->whereNotNull('repeat_type')
                            ->where('repeat_type', '!=', 'once')
                            ->where(function ($starts) use ($dateString) {
                                $starts
                                    ->whereNull('repeat_starts_on')
                                    ->orWhereDate(
                                        'repeat_starts_on',
                                        '<=',
                                        $dateString
                                    );
                            })
                            ->where(function ($ends) use ($dateString) {
                                $ends
                                    ->whereNull('repeat_ends_on')
                                    ->orWhereDate(
                                        'repeat_ends_on',
                                        '>=',
                                        $dateString
                                    );
                            });
                    });
            })
            ->get();

        return $candidates
            ->filter(
                fn (DailyPlanItem $item) =>
                    $item->occursOn($date)
            )
            ->map(function (DailyPlanItem $item) use ($dateString) {
                $occurrence = $item->occurrences->first();

                if ($occurrence?->is_skipped) {
                    return null;
                }

                // Runtime-only date-resolved attributes.
                $item->setAttribute(
                    'occurrence_date',
                    $dateString
                );

                $item->setAttribute(
                    'is_completed',
                    $item->isRecurring()
                        ? (bool) ($occurrence?->is_completed ?? false)
                        : (bool) $item->getRawOriginal('is_completed')
                );

                $item->setAttribute(
                    'completed_at',
                    $item->isRecurring()
                        ? $occurrence?->completed_at
                        : $item->getRawOriginal('completed_at')
                );

                if ($occurrence) {
                    if (filled($occurrence->title_override)) {
                        $item->setAttribute(
                            'title',
                            $occurrence->title_override
                        );
                    }

                    if ($occurrence->description_override !== null) {
                        $item->setAttribute(
                            'description',
                            $occurrence->description_override
                        );
                    }

                    if (filled($occurrence->priority_override)) {
                        $item->setAttribute(
                            'priority',
                            $occurrence->priority_override
                        );
                    }

                    if ($occurrence->start_time_override !== null) {
                        $item->setAttribute(
                            'start_time',
                            $occurrence->start_time_override
                        );
                    }

                    if ($occurrence->end_time_override !== null) {
                        $item->setAttribute(
                            'end_time',
                            $occurrence->end_time_override
                        );
                    }

                    if ($occurrence->personal_goal_id_override !== null) {
                        $item->setAttribute(
                            'personal_goal_id',
                            $occurrence->personal_goal_id_override
                        );
                    }
                }

                $item->setAttribute(
                    'repeat_label',
                    $item->repeatLabel()
                );

                return $item;
            })
            ->filter()
            ->sortBy([
                fn ($a, $b) =>
                    filled($a->start_time) === filled($b->start_time)
                        ? 0
                        : (filled($a->start_time) ? -1 : 1),
                fn ($a, $b) =>
                    strcmp(
                        (string) $a->start_time,
                        (string) $b->start_time
                    ),
                fn ($a, $b) =>
                    ((int) $a->sort_order) <=> ((int) $b->sort_order),
                fn ($a, $b) =>
                    ((int) $a->id) <=> ((int) $b->id),
            ])
            ->values();
    }

    /**
     * Task totals for every date in [from, to], resolved exactly like
     * itemsForDate() (recurring series expanded per date, skipped
     * occurrences dropped, per-occurrence completion) but with ONE candidate
     * query and ONE occurrence query for the whole range instead of one
     * request/query set per day. Used by the week/month reviews.
     *
     * @return array{total:int, completed:int, days:int}
     */
    public function statisticsForRange(
        int $userId,
        CarbonInterface $from,
        CarbonInterface $to
    ): array {
        // Date-only in the app timezone, like GET /daily-planner?date=…, so
        // occursOn()'s same-day checks see the same calendar dates.
        $from = Carbon::parse($from->toDateString())->startOfDay();
        $to = Carbon::parse($to->toDateString())->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $fromString = $from->toDateString();
        $toString = $to->toDateString();

        $candidates = DailyPlanItem::query()
            ->with([
                'plan',
                'occurrences' => fn ($query) => $query
                    ->whereDate('occurrence_date', '>=', $fromString)
                    ->whereDate('occurrence_date', '<=', $toString),
            ])
            ->whereHas(
                'plan',
                fn ($query) => $query->where('user_id', $userId)
            )
            ->where(function ($query) use ($fromString, $toString) {
                $query
                    ->where(function ($once) use ($fromString, $toString) {
                        $once->where(function ($q) {
                            $q->whereNull('repeat_type')
                                ->orWhere('repeat_type', 'once');
                        })->whereHas(
                            'plan',
                            fn ($plan) => $plan
                                ->whereDate('plan_date', '>=', $fromString)
                                ->whereDate('plan_date', '<=', $toString)
                        );
                    })
                    ->orWhere(function ($recurring) use ($fromString, $toString) {
                        $recurring
                            ->whereNotNull('repeat_type')
                            ->where('repeat_type', '!=', 'once')
                            ->where(function ($starts) use ($toString) {
                                $starts
                                    ->whereNull('repeat_starts_on')
                                    ->orWhereDate('repeat_starts_on', '<=', $toString);
                            })
                            ->where(function ($ends) use ($fromString) {
                                $ends
                                    ->whereNull('repeat_ends_on')
                                    ->orWhereDate('repeat_ends_on', '>=', $fromString);
                            });
                    });
            })
            ->get();

        // item id => [Y-m-d => occurrence]
        $occurrences = [];
        foreach ($candidates as $item) {
            foreach ($item->occurrences as $occurrence) {
                $key = $occurrence->occurrence_date?->toDateString();
                if ($key !== null) {
                    $occurrences[$item->id][$key] = $occurrence;
                }
            }
        }

        $total = 0;
        $completed = 0;
        $days = 0;

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayTotal = 0;

            foreach ($candidates as $item) {
                if (! $item->occursOn($date)) {
                    continue;
                }

                $occurrence = $occurrences[$item->id][$dateString] ?? null;

                if ($occurrence?->is_skipped) {
                    continue;
                }

                $isCompleted = $item->isRecurring()
                    ? (bool) ($occurrence?->is_completed ?? false)
                    : (bool) $item->getRawOriginal('is_completed');

                $dayTotal++;
                if ($isCompleted) {
                    $completed++;
                }
            }

            $total += $dayTotal;
            if ($dayTotal > 0) {
                $days++;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'days' => $days,
        ];
    }

    public function statistics(
        SupportCollection $items
    ): array {
        $total = $items->count();
        $completed = $items
            ->where('is_completed', true)
            ->count();

        $timed = $items
            ->filter(fn ($item) => filled($item->start_time))
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => max(0, $total - $completed),
            'timed' => $timed,
            'progress' => $total
                ? (int) round(($completed / $total) * 100)
                : 0,
        ];
    }

    public function normalizeRecurrence(
        array $data,
        string $fallbackDate
    ): array {
        $repeatType = $data['repeat_type'] ?? 'once';

        if (! in_array(
            $repeatType,
            self::REPEAT_TYPES,
            true
        )) {
            $repeatType = 'once';
        }

        $days = collect($data['repeat_days'] ?? [])
            ->map(fn ($day) => strtolower(trim((string) $day)))
            ->filter(
                fn ($day) =>
                    in_array($day, self::WEEK_DAYS, true)
            )
            ->unique()
            ->values()
            ->all();

        if ($repeatType !== 'specific_days') {
            $days = [];
        }

        return [
            'repeat_type' => $repeatType,
            'repeat_days' => $days ?: null,
            'repeat_interval' => max(
                1,
                min(52, (int) ($data['repeat_interval'] ?? 1))
            ),
            'repeat_starts_on' =>
                $repeatType === 'once'
                    ? null
                    : ($data['repeat_starts_on'] ?? $fallbackDate),
            'repeat_ends_on' =>
                $repeatType === 'once'
                    ? null
                    : ($data['repeat_ends_on'] ?? null),
            'recurrence_group_id' =>
                $repeatType === 'once'
                    ? null
                    : ($data['recurrence_group_id'] ?? (string) Str::uuid()),
        ];
    }

    public function toggleOccurrence(
        DailyPlanItem $item,
        int $userId,
        CarbonInterface $date
    ): DailyPlanItemOccurrence|DailyPlanItem {
        if (! $item->isRecurring()) {
            $completed = ! (bool) $item->is_completed;

            $item->update([
                'is_completed' => $completed,
                'completed_at' => $completed ? now() : null,
            ]);

            return $item->fresh();
        }

        $date = Carbon::parse($date)->startOfDay();

        abort_unless(
            $item->occursOn($date),
            422,
            'This recurring task is not scheduled for the selected date.'
        );

        $occurrence = DailyPlanItemOccurrence::firstOrCreate(
            [
                'daily_plan_item_id' => $item->id,
                'user_id' => $userId,
                'occurrence_date' => $date->toDateString(),
            ]
        );

        $completed = ! (bool) $occurrence->is_completed;

        $occurrence->update([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
            'is_skipped' => false,
        ]);

        return $occurrence->fresh();
    }

    public function completeOccurrence(
        DailyPlanItem $item,
        int $userId,
        CarbonInterface $date
    ): bool {
        if (! $item->isRecurring()) {
            if ($item->is_completed) {
                return false;
            }

            $item->update(['is_completed' => true, 'completed_at' => now()]);

            return true;
        }

        abort_unless($item->occursOn($date), 422,
            'This recurring task is not scheduled for the selected date.');

        $occurrence = DailyPlanItemOccurrence::query()
            ->where('daily_plan_item_id', $item->id)
            ->where('user_id', $userId)
            ->whereDate('occurrence_date', $date->toDateString())
            ->first() ?? DailyPlanItemOccurrence::create([
                'daily_plan_item_id' => $item->id,
                'user_id' => $userId,
                'occurrence_date' => $date->toDateString(),
            ]);

        if ($occurrence->is_completed || $occurrence->is_skipped) {
            return false;
        }

        $occurrence->update(['is_completed' => true, 'completed_at' => now()]);

        return true;
    }

    public function skipOccurrence(
        DailyPlanItem $item,
        int $userId,
        CarbonInterface $date
    ): void {
        DailyPlanItemOccurrence::updateOrCreate(
            [
                'daily_plan_item_id' => $item->id,
                'user_id' => $userId,
                'occurrence_date' =>
                    Carbon::parse($date)->toDateString(),
            ],
            [
                'is_skipped' => true,
                'is_completed' => false,
                'completed_at' => null,
            ]
        );
    }

    public function updateOccurrenceOnly(
        DailyPlanItem $item,
        int $userId,
        CarbonInterface $date,
        array $data
    ): DailyPlanItemOccurrence {
        return DailyPlanItemOccurrence::updateOrCreate(
            [
                'daily_plan_item_id' => $item->id,
                'user_id' => $userId,
                'occurrence_date' =>
                    Carbon::parse($date)->toDateString(),
            ],
            [
                'is_skipped' => false,
                'title_override' => $data['title'] ?? null,
                'description_override' =>
                    array_key_exists('description', $data)
                        ? $data['description']
                        : null,
                'priority_override' => $data['priority'] ?? null,
                'start_time_override' =>
                    $data['start_time'] ?? null,
                'end_time_override' =>
                    $data['end_time'] ?? null,
                'personal_goal_id_override' =>
                    $data['personal_goal_id'] ?? null,
            ]
        );
    }
}
