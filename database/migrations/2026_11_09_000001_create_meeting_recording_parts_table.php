<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Long browser recordings are uploaded in fixed-length parts while the
     * meeting continues, so each part can be transcribed in the background
     * and only the last part is left to transcribe when the meeting ends.
     */
    public function up(): void
    {
        Schema::create('meeting_recording_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_recording_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('part_number');
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->longText('transcript')->nullable();
            $table->json('transcript_segments')->nullable();
            $table->string('transcription_status', 20)->default('pending');
            $table->text('transcription_error')->nullable();
            $table->timestamps();

            $table->unique(['meeting_recording_id', 'part_number'], 'meeting_recording_parts_number_unique');
        });

        // Lets the per-meeting minute allowance be shared by every part of
        // one recording instead of being granted afresh to each part.
        Schema::table('transcription_minute_usages', function (Blueprint $table): void {
            $table->foreignId('meeting_recording_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transcription_minute_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('meeting_recording_id');
        });

        Schema::dropIfExists('meeting_recording_parts');
    }
};
