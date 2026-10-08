<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every page with headline numbers uses the shared compact summary strip
 * (<x-summary-strip>, .pm-summary) rather than its own stat-card style.
 */
class SummaryStripPagesTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ])->save();

        return $user;
    }

    public static function userPages(): array
    {
        return [
            'budgets' => ['budgets.index'],
            'annual plans' => ['annual-plans.index'],
            'daily planner' => ['daily-planner.index'],
            'spiritual practices' => ['spiritual-practices.index'],
        ];
    }

    #[DataProvider('userPages')]
    public function test_user_page_renders_summary_strip(string $routeName): void
    {
        $this->actingAs($this->subscriber())
            ->get(route($routeName))
            ->assertOk()
            ->assertSee('class="pm-summary', false)
            ->assertSee('pm-summary-cell', false);
    }

    public function test_ai_plans_page_renders_summary_strip(): void
    {
        $user = $this->subscriber();
        AiPlan::create(['user_id' => $user->id, 'content' => 'Plan body', 'provider' => 'test', 'used_shared_key' => false]);

        $this->actingAs($user)
            ->get(route('ai-plans.index'))
            ->assertOk()
            ->assertSee('class="pm-summary', false)
            ->assertSee('Total generated');
    }

    public static function adminPages(): array
    {
        return [
            'users' => ['admin.users.index'],
            'enterprise inquiries' => ['admin.enterprise-inquiries.index'],
            'billing logs' => ['admin.billing-logs.index'],
            'payments' => ['admin.payments.index'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_admin_page_renders_summary_strip(string $routeName): void
    {
        $admin = $this->subscriber(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route($routeName))
            ->assertOk()
            ->assertSee('class="pm-summary', false)
            ->assertSee('pm-summary-cell', false);
    }

    public function test_admin_user_detail_page_renders_summary_strip(): void
    {
        $admin = $this->subscriber(['role' => 'admin']);
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $member))
            ->assertOk()
            ->assertSee('class="pm-summary', false)
            ->assertSee('Recoverable items');
    }

    public function test_admin_payments_cells_link_to_status_filters(): void
    {
        $admin = $this->subscriber(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('href="' . e(route('admin.payments.index', ['status' => 'pending'])) . '" class="pm-summary-cell"', false);
    }
}
