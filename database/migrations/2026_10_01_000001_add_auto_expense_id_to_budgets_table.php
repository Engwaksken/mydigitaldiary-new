<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Budgets can now be spent partially through ordinary Expenses linked by
 * expenses.budget_id. The "mark as paid" checkbox still creates ONE
 * automatic Expense, and auto_expense_id records exactly which Expense
 * that is, so reversing the checkbox never deletes user-entered partial
 * Expenses linked to the same Budget.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budgets')) {
            return;
        }

        if (! Schema::hasColumn('budgets', 'auto_expense_id')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->unsignedBigInteger('auto_expense_id')
                    ->nullable()
                    ->index();
            });
        }

        if (
            ! Schema::hasTable('expenses')
            || ! Schema::hasColumn('expenses', 'budget_id')
            || ! Schema::hasColumn('budgets', 'is_expensed')
        ) {
            return;
        }

        // Backfill: the legacy automatic Expense was always written with
        // payment_method = 'Budget'.
        DB::table('budgets')
            ->where('is_expensed', true)
            ->whereNull('auto_expense_id')
            ->orderBy('id')
            ->select(['id', 'user_id'])
            ->chunkById(500, function ($budgets) {
                foreach ($budgets as $budget) {
                    $expenseId = DB::table('expenses')
                        ->where('user_id', $budget->user_id)
                        ->where('budget_id', $budget->id)
                        ->where('payment_method', 'Budget')
                        ->orderBy('id')
                        ->value('id');

                    if ($expenseId) {
                        DB::table('budgets')
                            ->where('id', $budget->id)
                            ->update(['auto_expense_id' => $expenseId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('budgets')
            && Schema::hasColumn('budgets', 'auto_expense_id')
        ) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->dropIndex(['auto_expense_id']);
                $table->dropColumn('auto_expense_id');
            });
        }
    }
};
