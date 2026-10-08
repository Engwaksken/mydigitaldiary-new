<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseBudgetLinkTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ])->save();

        return $user;
    }

    private function budgetItem(User $user, string $category = 'Groceries', float $amount = 1000): Budget
    {
        return Budget::create([
            'user_id' => $user->id,
            'category' => $category,
            'amount' => $amount,
            'period' => 'monthly',
            'month_year' => now()->format('Y-m'),
            'application_type' => 'expense',
            'is_expensed' => false,
            'applied_amount' => 0,
        ]);
    }

    public function test_add_expense_form_lists_budget_items_and_add_new_option(): void
    {
        $user = $this->subscriber();
        $this->budgetItem($user, 'Rent', 500);

        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('data-pm-budget-picker', false)
            ->assertSee('<option value="">Choose from budget or add new (optional)</option>', false)
            ->assertSee('<optgroup label="' . now()->format('F Y') . ' budget">', false)
            ->assertSee('data-category="Rent"', false)
            ->assertSee('+ Add new item')
            ->assertSee('data-pm-form-tabs', false);
    }

    public function test_selecting_a_budget_item_links_the_expense_and_updates_spending(): void
    {
        $user = $this->subscriber();
        $budget = $this->budgetItem($user, 'Groceries', 1000);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'budget_id' => (string) $budget->id,
                'category' => 'Groceries',
                'amount' => 300,
                'spent_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHasNoErrors();

        $expense = Expense::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($budget->id, (int) $expense->budget_id);

        $fresh = Budget::query()->withSpending()->findOrFail($budget->id)->appendSpending();
        $this->assertEquals(300.0, $fresh->spent_amount);
        $this->assertEquals(700.0, $fresh->remaining_amount);
    }

    public function test_add_new_item_saves_without_a_link_by_default(): void
    {
        $user = $this->subscriber();
        $this->budgetItem($user);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'budget_id' => 'new',
                'category' => 'Taxi',
                'amount' => 40,
                'spent_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHasNoErrors();

        $expense = Expense::where('category', 'Taxi')->firstOrFail();
        $this->assertNull($expense->budget_id);
        $this->assertSame(1, Budget::where('user_id', $user->id)->count());
    }

    public function test_add_new_item_can_also_be_added_to_the_month_budget(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'budget_id' => 'new',
                'add_to_budget' => '1',
                'category' => 'Gym',
                'amount' => 75,
                'spent_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHasNoErrors();

        $budget = Budget::where('user_id', $user->id)->where('category', 'Gym')->firstOrFail();
        $this->assertSame(now()->format('Y-m'), $budget->month_year);
        $this->assertEquals(75.0, (float) $budget->amount);
        $this->assertSame($budget->id, (int) Expense::where('category', 'Gym')->value('budget_id'));
    }

    public function test_cannot_link_an_expense_to_another_users_budget_item(): void
    {
        $owner = $this->subscriber();
        $budget = $this->budgetItem($owner);
        $intruder = $this->subscriber();

        $this->actingAs($intruder)
            ->post(route('expenses.store'), [
                'budget_id' => (string) $budget->id,
                'category' => 'Groceries',
                'amount' => 50,
                'spent_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('budget_id');

        $this->assertSame(0, Expense::count());
    }

    public function test_update_cannot_relink_to_another_users_budget_item(): void
    {
        $owner = $this->subscriber();
        $foreign = $this->budgetItem($owner);
        $user = $this->subscriber();
        $expense = Expense::create([
            'user_id' => $user->id,
            'category' => 'Lunch',
            'amount' => 10,
            'spent_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->put(route('expenses.update', $expense->id), [
                'budget_id' => (string) $foreign->id,
                'category' => 'Lunch',
                'amount' => 10,
                'spent_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('budget_id');

        $this->assertNull($expense->fresh()->budget_id);
    }

    public function test_expense_list_shows_linked_budget_item_without_sideways_table(): void
    {
        $user = $this->subscriber();
        $budget = $this->budgetItem($user, 'Utilities', 200);
        Expense::create([
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'category' => 'Power bill',
            'amount' => 120,
            'spent_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('pm-dt-wrap', false)
            ->assertSee('Power bill')
            ->assertSee('Utilities')
            ->assertSee('pm-summary', false);
    }
}
