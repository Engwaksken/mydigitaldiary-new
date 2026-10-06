<?php

namespace Tests\Feature\Support;

use App\Models\PersonalGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalGoalFactory extends Factory
{
    protected $model = PersonalGoal::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'module' => 'exercise', 'title' => 'Daily exercise',
            'progress_percent' => 0, 'status' => 'not_started', 'priority' => 'medium'];
    }
}
