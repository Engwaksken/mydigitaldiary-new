<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per user per ISO week for the weekly appreciation email.
 *
 * The unique (user_id, week_key) pair is what keeps appreciation:send-weekly
 * idempotent: a row is claimed before the email is queued, so a re-run, an
 * overlapping run or a retried schedule can never send twice in one week.
 * The stored message also lets next week's generation avoid repeating it.
 *
 * The opt-out itself is not a column here: it lives with the user's other
 * email preferences in users.engagement_notification_preferences
 * ("weekly_appreciation").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appreciation_email_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('week_key', 10);
            $table->string('source', 20)->default('ai');
            $table->string('theme', 80)->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'week_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appreciation_email_logs');
    }
};
