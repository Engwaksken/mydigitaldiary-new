<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReviewAccountLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableReviewAccount(string $email = 'playreview@example.com', string $code = '246810'): void
    {
        config([
            'auth.review_account.email' => $email,
            'auth.review_account.otp' => $code,
        ]);
    }

    public function test_review_account_signs_in_with_fixed_code_and_no_email(): void
    {
        Notification::fake();
        $this->enableReviewAccount();
        $user = User::factory()->create(['email' => 'PlayReview@example.com']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user_id', $user->id);

        Notification::assertNotSentTo($user, LoginOtpNotification::class);

        $response = $this->postJson('/api/verify-otp', ['user_id' => $user->id, 'code' => '246810'])
            ->assertOk();

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_review_account_still_needs_the_right_password(): void
    {
        $this->enableReviewAccount();
        User::factory()->create(['email' => 'playreview@example.com']);

        $this->postJson('/api/login', ['email' => 'playreview@example.com', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_other_accounts_cannot_use_the_review_code(): void
    {
        Notification::fake();
        $this->enableReviewAccount();
        User::factory()->create(['email' => 'playreview@example.com']);
        $other = User::factory()->create(['email' => 'someone@example.com']);

        $this->postJson('/api/login', ['email' => $other->email, 'password' => 'password'])->assertOk();

        Notification::assertSentTo($other, LoginOtpNotification::class);

        $this->postJson('/api/verify-otp', ['user_id' => $other->id, 'code' => '246810'])
            ->assertStatus(422);
    }

    public function test_disabled_when_code_is_missing_or_invalid(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'playreview@example.com']);

        foreach (['', '12345', 'abcdef'] as $code) {
            $this->enableReviewAccount('playreview@example.com', $code);
            $this->travel(1)->minutes();

            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        }

        Notification::assertSentToTimes($user, LoginOtpNotification::class, 3);
    }
}
