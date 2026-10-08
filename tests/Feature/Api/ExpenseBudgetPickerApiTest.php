<?php

namespace Tests\Feature\Api;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseBudgetPickerApiTest extends TestCase
{
    use RefreshDatabase;

    private function budgetItem(User $user, string $category = 'Groceries', float $amount = 1000, ?string $month = null): Budget
    {
        return Budget::create([
            'user_id' => $user->id,
            'category' => $category,
            'amount' => $amount,
            'period' => 'monthly',
            'month_year' => $month ?? now()->format('Y-m'),
            'application_type' => 'expense',
            'is_expensed' => false,
            'applied_amount' => 0,
        ]);
    }

    public function test_budget_options_are_grouped_with_planned_and_remaining(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $rent = $this->budgetItem($user, 'Rent', 500);
        $this->budgetItem($user, 'Fuel', 200, now()->subMonthNoOverflow()->format('Y-m'));
        $this->budgetItem($other, 'Not mine', 999);

        Expense::create([
            'user_id' => $user->id,
            'budget_id' => $rent->id,
            'category' => 'Rent',
            'amount' => 120,
            'spent_at' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/expenses/budget-options')
            ->assertOk()
            ->assertJsonPath('data.current_month', now()->format('Y-m'))
            ->assertJsonPath('data.current_month_label', now()->format('F Y'))
            ->assertJsonPath('data.groups.0.label', now()->format('F Y').' budget')
            ->assertJsonPath('data.groups.0.items.0.id', $rent->id)
            ->assertJsonPath('data.groups.0.items.0.category', 'Rent')
            ->assertJsonPath('data.groups.0.items.0.planned', 500)
            ->assertJsonPath('data.groups.0.items.0.remaining', 380)
            ->assertJsonPath('data.groups.1.label', now()->subMonthNoOverflow()->format('F Y').' budget');

        $this->assertStringContainsString('Rent — ', $response->json('data.groups.0.items.0.label'));
        $this->assertStringContainsString('planned', $response->json('data.groups.0.items.0.label'));
        $this->assertStringNotContainsString('Not mine', $response->getContent());
    }

    public function test_budget_options_can_include_an_older_linked_item(): void
    {
        $user = User::factory()->create();
        $old = $this->budgetItem($user, 'Old item', 50, '2020-01');

        Sanctum::actingAs($user);

        $this->getJson('/api/expenses/budget-options')
            ->assertOk()
            ->assertJsonMissing(['category' => 'Old item']);

        $this->getJson('/api/expenses/budget-options?include[]='.$old->id)
            ->assertOk()
            ->assertJsonFragment(['category' => 'Old item']);
    }

    public function test_picking_a_budget_item_links_the_expense(): void
    {
        $user = User::factory()->create();
        $budget = $this->budgetItem($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/expenses', [
            'budget_id' => $budget->id,
            'category' => 'Groceries',
            'amount' => 300,
            'spent_at' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('budget_id', $budget->id);
    }

    public function test_add_new_item_without_budget_saves_unlinked(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/expenses', [
            'budget_id' => 'new',
            'category' => 'Taxi',
            'amount' => 40,
            'spent_at' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('budget_id', null);

        $this->assertSame(0, Budget::where('user_id', $user->id)->count());
    }

    public function test_add_new_item_can_also_be_added_to_the_month_budget(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/expenses', [
            'budget_id' => 'new',
            'add_to_budget' => true,
            'category' => 'Gym',
            'amount' => 75,
            'spent_at' => now()->toDateString(),
        ])->assertCreated();

        $budget = Budget::where('user_id', $user->id)->where('category', 'Gym')->firstOrFail();
        $this->assertSame(now()->format('Y-m'), $budget->month_year);
        $this->assertEquals(75.0, (float) $budget->amount);
        $response->assertJsonPath('budget_id', $budget->id);
    }

    public function test_update_cannot_relink_to_another_users_budget_item(): void
    {
        $owner = User::factory()->create();
        $foreign = $this->budgetItem($owner);
        $user = User::factory()->create();
        $expense = Expense::create([
            'user_id' => $user->id,
            'category' => 'Lunch',
            'amount' => 10,
            'spent_at' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/expenses/{$expense->id}", [
            'budget_id' => $foreign->id,
            'category' => 'Lunch',
            'amount' => 10,
            'spent_at' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('budget_id');

        $this->assertNull($expense->fresh()->budget_id);
    }

    public function test_summary_strip_and_nudge(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/expenses/summary')
            ->assertOk()
            ->assertJsonPath('data.nudge', 'No spending logged this month yet.')
            ->assertJsonPath('data.stats.1.value', 'No budget yet');

        $this->budgetItem($user, 'Food', 1000);
        Expense::create([
            'user_id' => $user->id,
            'category' => 'Food',
            'amount' => 250,
            'spent_at' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/expenses/summary')
            ->assertOk()
            ->assertJsonCount(4, 'data.stats')
            ->assertJsonPath('data.stats.2.value', 'Food');

        $this->assertStringContainsString('25% of', $response->json('data.stats.1.value'));
        $this->assertStringContainsString("left in this month's budget.", $response->json('data.nudge'));
    }

    public function test_list_includes_linked_budget_item(): void
    {
        $user = User::factory()->create();
        $budget = $this->budgetItem($user, 'Utilities', 200);
        Expense::create([
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'category' => 'Power bill',
            'amount' => 120,
            'spent_at' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonPath('data.0.budget.category', 'Utilities');
    }
}
