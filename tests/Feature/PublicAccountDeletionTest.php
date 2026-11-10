<?php

namespace Tests\Feature;

use App\Mail\AccountDeletionConfirmationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_is_public(): void
    {
        $this->get('/delete-account')
            ->assertOk()
            ->assertSee('Delete your account');
    }

    public function test_known_email_gets_signed_link(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->post('/delete-account', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSent(AccountDeletionConfirmationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && str_contains($mail->url, '/delete-account/confirm/' . $user->id);
        });
    }

    public function test_unknown_email_gets_same_message_and_no_mail(): void
    {
        Mail::fake();

        $this->post('/delete-account', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    public function test_confirm_link_requires_signature(): void
    {
        $user = User::factory()->create();

        $this->get('/delete-account/confirm/' . $user->id)->assertForbidden();
        $this->post('/delete-account/confirm/' . $user->id, ['confirm_delete' => '1'])->assertForbidden();

        $this->assertNull($user->fresh()->scheduled_deletion_at);
    }

    public function test_get_does_not_schedule_deletion(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute('account-deletion.confirm', now()->addHour(), ['user' => $user->id]);

        $this->get($url)->assertOk()->assertSee('Delete my account');

        $this->assertNull($user->fresh()->scheduled_deletion_at);
    }

    public function test_post_schedules_deletion_in_30_days(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute('account-deletion.confirm', now()->addHour(), ['user' => $user->id]);

        $this->post($url, ['confirm_delete' => '1', 'reason' => 'No longer needed'])
            ->assertOk()
            ->assertSee('scheduled for permanent deletion');

        $user->refresh();
        $this->assertNotNull($user->deletion_requested_at);
        $this->assertSame('No longer needed', $user->deletion_reason);
        $this->assertTrue($user->scheduled_deletion_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_post_requires_confirmation_checkbox(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute('account-deletion.confirm', now()->addHour(), ['user' => $user->id]);

        $this->post($url, [])->assertSessionHasErrors('confirm_delete');

        $this->assertNull($user->fresh()->scheduled_deletion_at);
    }
}
