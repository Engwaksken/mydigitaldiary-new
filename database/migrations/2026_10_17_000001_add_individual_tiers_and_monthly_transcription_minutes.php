<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->string('individual_tier', 20)->nullable()->after('category');
            $table->unsignedInteger('monthly_transcription_minutes')->default(60)->after('transcription_capacity_mb');
        });

        DB::table('subscription_plans')->where('category', 'individual')->orderBy('id')->each(function ($plan): void {
            $identity = strtolower(($plan->key ?? '').' '.($plan->name ?? ''));
            $tier = match (true) {
                str_contains($identity, 'gold') => 'gold',
                str_contains($identity, 'premium') => 'premium',
                str_contains($identity, 'custom') => 'custom',
                default => 'starter',
            };
            $minutes = match ($tier) {
                'gold' => 300,
                'premium' => 1200,
                default => 60,
            };

            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'individual_tier' => $tier,
                'monthly_transcription_minutes' => $minutes,
            ]);
        });

        Schema::create('transcription_minute_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('plan_seconds')->default(0);
            $table->unsignedInteger('extra_minutes')->default(0);
            $table->date('period_start');
            $table->string('status', 20)->default('reserved');
            $table->timestamps();

            $table->index(['user_id', 'period_start', 'status'], 'transcription_usage_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcription_minute_usages');

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn(['individual_tier', 'monthly_transcription_minutes']);
        });
    }
};
