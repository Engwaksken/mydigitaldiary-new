<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track how often a part has been picked up and when, so a part whose
     * request was killed mid-transcription is noticed and retried instead
     * of staying "processing" forever.
     */
    public function up(): void
    {
        Schema::table('meeting_recording_parts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('transcription_attempts')->default(0)->after('transcription_status');
            $table->timestamp('processing_started_at')->nullable()->after('transcription_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_recording_parts', function (Blueprint $table): void {
            $table->dropColumn(['transcription_attempts', 'processing_started_at']);
        });
    }
};
