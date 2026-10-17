<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Settings > Weekly Email: when the automatic weekly appreciation
 * email runs. Defaults match the previous fixed schedule (on, Monday 09:00
 * Africa/Kampala). appreciation_weekly_last_run_week is claimed atomically
 * by each run so a changed day or time can never run twice in one week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('appreciation_weekly_enabled')->default(true);
            $table->unsignedTinyInteger('appreciation_weekly_day')->default(1);
            $table->string('appreciation_weekly_time', 5)->default('09:00');
            $table->string('appreciation_weekly_last_run_week', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'appreciation_weekly_enabled', 'appreciation_weekly_day',
                'appreciation_weekly_time', 'appreciation_weekly_last_run_week',
            ]);
        });
    }
};
