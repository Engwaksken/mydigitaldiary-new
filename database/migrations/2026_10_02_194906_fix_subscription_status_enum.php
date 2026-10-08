<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original column was enum('trialing','active','canceled','expired'),
     * but the app now writes trial/inactive/suspended/cancelled, which the
     * enum rejects. Widen it to a plain string and store canonical values.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'subscription_status')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_status', 30)->default('trial')->change();
        });

        DB::table('users')
            ->whereRaw("LOWER(TRIM(COALESCE(subscription_status, ''))) IN ('', 'trialing')")
            ->update(['subscription_status' => 'trial']);

        DB::table('users')
            ->whereRaw("LOWER(TRIM(subscription_status)) = 'canceled'")
            ->update(['subscription_status' => 'cancelled']);
    }

    public function down(): void
    {
        // Intentionally non-destructive: narrowing back to the old enum would
        // fail or truncate the newer status values.
    }
};
