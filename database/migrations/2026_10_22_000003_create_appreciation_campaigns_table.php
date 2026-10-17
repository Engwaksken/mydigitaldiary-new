<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An admin's appreciation email send, either now or scheduled. drafts holds
 * the subject and paragraphs the admin previewed, keyed by user ID, so the
 * previewed text is exactly what is sent even after the draft cache expires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appreciation_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('user_ids');
            $table->dateTime('scheduled_for')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('week_key', 10)->nullable();
            $table->json('drafts')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appreciation_campaigns');
    }
};
