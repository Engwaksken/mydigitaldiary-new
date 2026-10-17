<?php

namespace Tests\Feature\Auth;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Email verification is required everywhere a signed-in user can go, on
 * the web app and the mobile API, apart from the routes needed to verify
 * or to leave.
 */
class EmailVerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public static function protectedWebPages(): array
    {
        return [
            'dashboard (subscribed group)' => ['/dashboard'],
            'subscription (formerly auth-only group)' => ['/subscription'],
            'profile (formerly auth-only group)' => ['/profile'],
            'privacy (formerly auth-only group)' => ['/privacy'],
            'diet history (health-ai routes)' => ['/daily-eating-history'],
            'account data (provider routes)' => ['/account-data'],
        ];
    }

    #[DataProvider('protectedWebPages')]
    public function test_unverified_user_is_sent_to_the_verification_notice(string $uri): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($uri)->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_admin_is_sent_to_the_verification_notice(): void
    {
        $admin = User::factory()->unverified()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/users')->assertRedirect(route('verification.notice'));
        $this->actingAs($admin)->get('/admin/support')->assertRedirect(route('verification.notice'));
    }

    public function test_verified_admin_still_reaches_the_admin_area(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_notice_page_shows_the_address_and_a_resend_button(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'pending@example.com']);

        $this->actingAs($user)->get('/verify-email')
            ->assertOk()
            ->assertSee('pending@example.com')
            ->assertSee(route('verification.send'), false)
            ->assertSee(route('verification.email.update'), false)
            ->assertSee(route('logout'), false);
    }

    public function test_resend_route_sends_a_new_link_and_is_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        // A second click inside the cooldown gets a message, not another email.
        $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHasErrors('verification');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_unverified_user_can_correct_their_email_and_get_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'typo@exmaple.com']);

        $this->actingAs($user)->from('/verify-email')->put('/verify-email/email', ['email' => 'fixed@example.com'])
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-sent');

        $this->assertSame('fixed@example.com', $user->fresh()->email);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_can_log_out_and_delete_their_account(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $other = User::factory()->unverified()->create();
        $this->actingAs($other)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
        $this->assertNull(User::find($other->id));
    }

    public function test_verify_link_works_without_a_web_session(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create(['email' => 'phone@example.com']);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        // Opened in a phone browser by a mobile-app user: no session at all.
        $this->get($url)
            ->assertOk()
            ->assertSee('Email verified')
            ->assertSee('return to the', false);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_verify_link_rejects_a_bad_hash_or_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $badHash = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);
        $this->get($badHash)->assertForbidden();

        $unsigned = route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($unsigned)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_web_login_code_counts_as_email_verification(): void
    {
        $user = User::factory()->unverified()->create();
        LoginOtp::create([
            'user_id' => $user->id,
            'code' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['otp.user.id' => $user->id])
            ->post('/login/otp', ['code' => '123456'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_api_returns_a_coded_403_for_unverified_users(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());

        // No Accept: application/json header, as an HTTP client may omit it.
        foreach (['/api/dashboard', '/api/growth/dashboard', '/api/team-chat/conversations', '/api/profile/currency', '/api/organization/access-context'] as $uri) {
            $this->get($uri)
                ->assertForbidden()
                ->assertExactJson([
                    'message' => 'Please verify your email address to continue.',
                    'code' => 'email_unverified',
                ]);
        }

        $this->postJson('/api/ai/form-assist', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'email_unverified');
    }

    public function test_api_auth_endpoints_stay_reachable_for_unverified_users(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'mobile@example.com']);
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonPath('user.email_verified_at', null);

        $this->getJson('/api/email/verification-status')
            ->assertOk()
            ->assertExactJson(['email_verified' => false, 'email' => 'mobile@example.com']);

        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertExactJson(['message' => 'Verification link sent.', 'email_verified' => false]);

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->postJson('/api/logout')->assertOk();
    }

    public function test_api_resend_reports_an_already_verified_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertExactJson(['message' => 'Email already verified.', 'email_verified' => true]);

        $this->getJson('/api/email/verification-status')->assertJsonPath('email_verified', true);
        Notification::assertNothingSent();
    }

    public function test_api_register_and_login_report_email_verified(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Mobile User',
            'email' => 'new-mobile@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'data_consent' => true,
        ])->assertCreated()->assertJsonPath('email_verified', false);

        $user = User::where('email', 'new-mobile@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->postJson('/api/login', ['email' => 'new-mobile@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('email_verified', false);

        $verified = User::factory()->create(['email' => 'old-mobile@example.com']);
        $this->postJson('/api/login', ['email' => 'old-mobile@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('email_verified', true);
    }

    public function test_api_login_code_verifies_the_email_and_returns_a_token(): void
    {
        $user = User::factory()->unverified()->create();
        LoginOtp::create([
            'user_id' => $user->id,
            'code' => Hash::make('654321'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/verify-otp', ['user_id' => $user->id, 'code' => '654321'])
            ->assertOk()
            ->assertJsonPath('email_verified', true)
            ->assertJsonPath('user.email_verified', true);

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull($response->json('user.email_verified_at'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_staff_migration_verifies_staff_but_not_regular_users(): void
    {
        $admin = User::factory()->unverified()->create(['role' => 'admin']);
        $support = User::factory()->unverified()->create(['role' => 'support']);
        $finance = User::factory()->unverified()->create(['role' => 'user', 'system_role' => 'finance']);
        $superAdmin = User::factory()->unverified()->create(['role' => 'user', 'system_role' => 'super_admin']);
        $member = User::factory()->unverified()->create(['role' => 'user', 'system_role' => 'user']);
        $alreadyVerifiedAt = now()->subYear()->startOfSecond();
        $verifiedAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => $alreadyVerifiedAt]);

        $migration = require database_path('migrations/2026_10_20_000001_verify_existing_staff_email_addresses.php');
        $migration->up();

        foreach ([$admin, $support, $finance, $superAdmin] as $staff) {
            $this->assertTrue($staff->fresh()->hasVerifiedEmail(), "Staff user {$staff->id} should be verified.");
        }

        $this->assertFalse($member->fresh()->hasVerifiedEmail());
        $this->assertTrue($verifiedAdmin->fresh()->email_verified_at->equalTo($alreadyVerifiedAt));
    }
}
