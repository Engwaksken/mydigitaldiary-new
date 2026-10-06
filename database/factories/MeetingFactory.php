<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->startOfHour();

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'start_at' => $startsAt,
            'end_at' => $startsAt->copy()->addHour(),
            'status' => 'scheduled',
            'meeting_status' => 'scheduled',
            'is_archived' => false,
        ];
    }
}
