<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily "Plan your day" / "Close your day" phone reminders.
 *
 * 1. device_tokens.platform was enum('ios','android'); the installed web app
 *    (PWA) now registers FCM web tokens too, so it becomes a short string.
 * 2. communication_preferences already holds morning/evening on-off + times
 *    (exposed to the mobile app via /api/growth/preferences). It gains a
 *    per-day "sent on" marker for each slot so the scheduler is idempotent,
 *    and its defaults move to 07:00 / 20:00.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_tokens') && Schema::hasColumn('device_tokens', 'platform')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->string('platform', 20)->nullable()->change();
            });
        }

        if (! Schema::hasTable('communication_preferences')) {
            return;
        }

        Schema::table('communication_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_preferences', 'morning_sent_on')) {
                $table->date('morning_sent_on')->nullable();
            }
            if (! Schema::hasColumn('communication_preferences', 'evening_sent_on')) {
                $table->date('evening_sent_on')->nullable();
            }
        });

        Schema::table('communication_preferences', function (Blueprint $table) {
            $table->time('morning_time')->default('07:00:00')->change();
            $table->time('evening_time')->default('20:00:00')->change();
        });

        // Rows still holding BOTH old column defaults were created by
        // GrowthStrategyService::ensureDefaults(), never chosen by a person
        // (the times were not editable on the web before this change), so
        // they move to the new defaults.
        DB::table('communication_preferences')
            ->where('morning_time', 'like', '08:00%')
            ->where('evening_time', 'like', '20:30%')
            ->update(['morning_time' => '07:00:00', 'evening_time' => '20:00:00']);
    }

    public function down(): void
    {
        if (Schema::hasTable('communication_preferences')) {
            Schema::table('communication_preferences', function (Blueprint $table) {
                foreach (['morning_sent_on', 'evening_sent_on'] as $column) {
                    if (Schema::hasColumn('communication_preferences', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // device_tokens.platform stays a string: narrowing back to the enum
        // would fail for rows registered as 'web'.
    }
};
