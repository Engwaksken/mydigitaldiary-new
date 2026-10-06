<?php

namespace Tests\Feature\Support;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(),
            'name' => 'Test Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
        ];
    }
}
