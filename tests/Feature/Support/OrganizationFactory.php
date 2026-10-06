<?php

namespace Tests\Feature\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return ['name' => 'Regression organization', 'owner_user_id' => User::factory()];
    }
}
