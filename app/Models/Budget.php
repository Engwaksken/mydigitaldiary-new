<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_budget_id',
        'category',
        'amount',
        'period',
        'month_year',
        'notes',
        'is_expensed',
        'expensed_at',
        'application_type',
        'debt_id',
        'applied_amount',
        'auto_expense_id',
        'is_archived',
        'import_source',
        'import_filename',
        'import_confidence',
        'import_metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'is_expensed' => 'boolean',
        'expensed_at' => 'date',
        'is_archived' => 'boolean',
        'import_confidence' => 'decimal:2',
        'import_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    /**
     * Every Expense linked to this Budget: user-entered partial spending
     * plus, when the "mark as paid" checkbox is ticked, the single
     * automatic Expense referenced by auto_expense_id.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Adds a spent_amount aggregate in the same query (no N+1). Call
     * appendSpending() on each loaded model to derive the other fields.
     */
    public function scopeWithSpending(Builder $query): Builder
    {
        if (! Schema::hasColumn('expenses', 'budget_id')) {
            return $query;
        }

        return $query->withSum([
            'expenses as spent_amount' => fn ($expenses) => $expenses
                ->whereColumn('expenses.user_id', 'budgets.user_id'),
        ], 'amount');
    }

    /**
     * Computes spent/remaining/overspent/completed from the withSum
     * aggregate (or lazily, for a single model loaded without it) and
     * sets them as attributes so they are serialised in JSON responses.
     *
     * Overspending is allowed: remaining_amount may be negative.
     */
    public function appendSpending(): static
    {
        $spent = array_key_exists('spent_amount', $this->attributes)
            ? (float) ($this->attributes['spent_amount'] ?? 0)
            : (Schema::hasColumn('expenses', 'budget_id')
                ? (float) $this->expenses()
                    ->where('user_id', $this->user_id)
                    ->sum('amount')
                : 0.0);

        $amount = (float) $this->amount;
        $spent = round($spent, 2);

        $this->setAttribute('spent_amount', $spent);
        $this->setAttribute('remaining_amount', round($amount - $spent, 2));
        $this->setAttribute('is_overspent', $spent > $amount);
        $this->setAttribute('is_completed', $amount > 0 && $spent >= $amount);

        return $this;
    }
}
