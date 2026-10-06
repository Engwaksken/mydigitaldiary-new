<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTrialAccessTest extends TestCase
{
    public static function trialStates(): array
    {
        return ['trial future' => ['trial', true], 'trialing future' => ['trialing', true],
            'trial expired' => ['trial', false], 'trialing expired' => ['trialing', false]];
    }

    #[DataProvider('trialStates')]
    public function test_both_trial_spellings_obey_trial_expiry(string $status, bool $future): void
    {
        $user = User::factory()->make(['subscription_status' => $status,
            'trial_ends_at' => $future ? now()->addDays(3) : now()->subDay()]);
        $this->assertSame($future, $user->onTrial());
        $this->assertSame($future, $user->hasActiveAccess());
    }
}
