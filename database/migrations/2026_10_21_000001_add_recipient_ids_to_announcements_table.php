<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an announcement go to users picked on the admin Users page.
 * Null means everyone, which is how every existing announcement was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->json('recipient_ids')->nullable()->after('send_email');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropColumn('recipient_ids');
        });
    }
};
