<?php

namespace Tests\Unit\Support;

use App\Models\DailyPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyPlanItemFactory extends Factory
{
    protected $model = DailyPlanItem::class;

    public function definition(): array
    {
        return ['title' => 'Recurring task', 'priority' => 'medium', 'repeat_type' => 'monthly',
            'repeat_interval' => 1, 'repeat_starts_on' => '2026-01-31'];
    }
}
