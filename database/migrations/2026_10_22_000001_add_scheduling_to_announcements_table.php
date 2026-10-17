<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling and status for announcements.
 *
 * Every announcement stored before this migration was sent the moment it
 * was created, so the column default is "sent" and sent_at is back-filled
 * from created_at. Nothing is resent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('status', 20)->default('sent')->after('recipient_ids');
            $table->dateTime('scheduled_for')->nullable()->after('status');
            $table->dateTime('sent_at')->nullable()->after('scheduled_for');
            $table->boolean('skip_already_told')->default(false)->after('sent_at');
            $table->unsignedInteger('skipped_count')->default(0)->after('skip_already_told');
            $table->index(['status', 'scheduled_for']);
        });

        DB::table('announcements')->whereNull('sent_at')->update(['sent_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex(['status', 'scheduled_for']);
            $table->dropColumn(['status', 'scheduled_for', 'sent_at', 'skip_already_told', 'skipped_count']);
        });
    }
};
