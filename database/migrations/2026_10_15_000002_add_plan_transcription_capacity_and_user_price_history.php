<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('transcription_capacity_mb')->default(30);
        });

        Schema::create('user_subscription_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->char('currency_code', 3);
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
            $table->index('effective_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscription_price_overrides');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('transcription_capacity_mb');
        });
    }
};
