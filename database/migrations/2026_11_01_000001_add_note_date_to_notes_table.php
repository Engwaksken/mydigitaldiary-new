<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notes', 'note_date')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table): void {
            $table->date('note_date')->nullable();
        });

        // Backfill existing notes from the day they were created, so a diary
        // entry never shows a blank date after the upgrade.
        DB::table('notes')
            ->whereNull('note_date')
            ->update(['note_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->dropColumn('note_date');
        });
    }
};
