<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per announcement per user, claimed before anything is sent, so a
 * retried or repeated SendAnnouncementJob never reaches a person twice.
 * Each channel's time is stored when it succeeds; failed_channels lists the
 * ones that did not (null when none failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('in_app_at')->nullable();
            $table->dateTime('push_at')->nullable();
            $table->dateTime('email_at')->nullable();
            $table->json('failed_channels')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_deliveries');
    }
};
