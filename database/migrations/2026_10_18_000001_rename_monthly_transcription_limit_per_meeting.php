<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->renameColumn('monthly_transcription_minutes', 'max_transcription_minutes_per_meeting');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->renameColumn('max_transcription_minutes_per_meeting', 'monthly_transcription_minutes');
        });
    }
};
