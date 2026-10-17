<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminUserListColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_list_shows_join_date_and_email_verification_status(): void
    {
        $verified = User::factory()->create(['name' => 'Verified Person', 'created_at' => Carbon::parse('2026-03-14 10:00:00')]);
        $unverified = User::factory()->unverified()->create(['name' => 'Pending Person', 'created_at' => Carbon::parse('2026-09-02 10:00:00')]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeInOrder(['<th class="px-4 py-3 text-left">Joined</th>', '<th class="px-4 py-3 text-left">Email</th>'], false)
            ->assertSee('14 Mar 2026')
            ->assertSee('02 Sep 2026')
            ->assertSee('Verified')
            ->assertSee('Unverified');

        $this->assertNotNull($verified->email_verified_at);
        $this->assertNull($unverified->email_verified_at);
    }

    public function test_user_list_filters_by_email_verification(): void
    {
        User::factory()->create(['email' => 'confirmed@example.com']);
        User::factory()->unverified()->create(['email' => 'pending@example.com']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'email' => 'boss@example.com']));

        $this->get(route('admin.users.index', ['verification' => 'unverified']))
            ->assertOk()
            ->assertSee('pending@example.com')
            ->assertDontSee('confirmed@example.com');

        $this->get(route('admin.users.index', ['verification' => 'verified']))
            ->assertOk()
            ->assertSee('confirmed@example.com')
            ->assertDontSee('pending@example.com');
    }
}
