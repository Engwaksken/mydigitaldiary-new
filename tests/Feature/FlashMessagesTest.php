<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSubscribedOrOrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_flash_is_rendered_once_when_a_page_has_a_legacy_success_alert(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->withoutMiddleware(EnsureSubscribedOrOrganizationMember::class)
            ->actingAs($user)
            ->withSession(['success' => 'Plan added successfully.'])
            ->get('/annual-plans');

        $response->assertOk();

        $this->assertSame(
            1,
            substr_count($response->getContent(), 'Plan added successfully.')
        );
    }

    public function test_page_success_flash_auto_dismisses_after_five_seconds(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->withoutMiddleware(EnsureSubscribedOrOrganizationMember::class)
            ->actingAs($user)
            ->withSession(['success' => 'Plan added successfully.'])
            ->get('/annual-plans');

        $response->assertOk();
        $response->assertSee('data-auto-dismiss="5000"', false);
    }
}
