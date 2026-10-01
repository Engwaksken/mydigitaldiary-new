<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A subscription can be paid by one user (user_id, the payer, who keeps
 * the invoice/receipt) on behalf of another existing user
 * (beneficiary_user_id), whose subscription is activated. NULL means the
 * payer is paying for themselves, exactly as before.
 */
return new class extends Migration
{
    private array $tables = [
        'iotec_subscription_transactions',
        'payments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (
                ! Schema::hasTable($tableName)
                || Schema::hasColumn($tableName, 'beneficiary_user_id')
            ) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('beneficiary_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->index()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (
                Schema::hasTable($tableName)
                && Schema::hasColumn($tableName, 'beneficiary_user_id')
            ) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('beneficiary_user_id');
                });
            }
        }
    }
};
