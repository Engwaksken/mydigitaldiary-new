<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanItemOccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_item_id',
        'user_id',
        'occurrence_date',
        'is_completed',
        'completed_at',
        'is_skipped',
        'title_override',
        'description_override',
        'priority_override',
        'start_time_override',
        'end_time_override',
        'personal_goal_id_override',
    ];

    protected $casts = [
        'occurrence_date' => 'date',
        'is_completed' => 'boolean',
        'is_skipped' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function setOccurrenceDateAttribute(mixed $value): void
    {
        // Match the DATE column and the date-only firstOrCreate lookup.
        // The default date cast otherwise writes a timestamp on SQLite.
        $this->attributes['occurrence_date'] = $value === null
            ? null
            : $this->asDate($value)->format('Y-m-d');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DailyPlanItem::class, 'daily_plan_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personalGoalOverride(): BelongsTo
    {
        return $this->belongsTo(
            PersonalGoal::class,
            'personal_goal_id_override'
        );
    }
}
