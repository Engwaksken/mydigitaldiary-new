<?php

namespace Tests\Feature\Api;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetPartialSpendingTest extends TestCase
{
    use RefreshDatabase;

    private function budget(User $user, float $amount = 1000): Budget
    {
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/budgets', [
            'category' => 'Groceries',
            'amount' => $amount,
            'period' => 'monthly',
            'month_year' => now()->format('Y-m'),
        ])->assertCreated()->json('id');

        return Budget::findOrFail($id);
    }

    private function spend(Budget $budget, float $amount): void
    {
        $this->postJson('/api/expenses', [
            'category' => 'Groceries',
            'amount' => $amount,
            'spent_at' => now()->toDateString(),
            'budget_id' => $budget->id,
        ])->assertCreated();
    }

    public function test_linked_expenses_report_spent_and_remaining_amounts(): void
    {
        $user = User::factory()->create();
        $budget = $this->budget($user);

        $this->spend($budget, 300);
        $this->spend($budget, 200);

        $this->getJson("/api/budgets/{$budget->id}")
            ->assertOk()
            ->assertJsonPath('spent_amount', 500)
            ->assertJsonPath('remaining_amount', 500)
            ->assertJsonPath('is_overspent', false)
            ->assertJsonPath('is_completed', false);

        $this->spend($budget, 600);

        $this->getJson('/api/budgets')
            ->assertOk()
            ->assertJsonPath('data.0.spent_amount', 1100)
            ->assertJsonPath('data.0.remaining_amount', -100)
            ->assertJsonPath('data.0.is_overspent', true)
            ->assertJsonPath('data.0.is_completed', true);
    }

    public function test_expense_cannot_link_to_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $budget = $this->budget($owner);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/expenses', [
            'category' => 'Groceries',
            'amount' => 50,
            'spent_at' => now()->toDateString(),
            'budget_id' => $budget->id,
        ])->assertStatus(422)->assertJsonValidationErrors('budget_id');
    }

    public function test_marking_paid_tops_up_and_unmarking_keeps_partial_expenses(): void
    {
        $user = User::factory()->create();
        $budget = $this->budget($user);

        $this->spend($budget, 300);

        $this->postJson("/api/budgets/{$budget->id}/expense-status", ['is_expensed' => true])
            ->assertOk()
            ->assertJsonPath('data.spent_amount', 1000)
            ->assertJsonPath('data.remaining_amount', 0);

        $autoId = $budget->fresh()->auto_expense_id;
        $this->assertNotNull($autoId);
        $this->assertEquals(700, (float) Expense::findOrFail($autoId)->amount);

        $this->postJson("/api/budgets/{$budget->id}/expense-status", ['is_expensed' => false])
            ->assertOk()
            ->assertJsonPath('data.spent_amount', 300);

        $this->assertDatabaseMissing('expenses', ['id' => $autoId]);
        $this->assertSame(1, Expense::where('budget_id', $budget->id)->count());
        $this->assertNull($budget->fresh()->auto_expense_id);
    }

    public function test_marking_paid_when_already_fully_spent_creates_no_automatic_expense(): void
    {
        $user = User::factory()->create();
        $budget = $this->budget($user);

        $this->spend($budget, 1000);

        $this->postJson("/api/budgets/{$budget->id}/expense-status", ['is_expensed' => true])
            ->assertOk()
            ->assertJsonPath('data.spent_amount', 1000);

        $this->assertNull($budget->fresh()->auto_expense_id);
        $this->assertSame(1, Expense::where('budget_id', $budget->id)->count());
    }
}
