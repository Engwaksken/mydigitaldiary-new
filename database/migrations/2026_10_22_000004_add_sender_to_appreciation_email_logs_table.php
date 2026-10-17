<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who sent each appreciation email: sent_by is the admin (null for the
 * weekly automatic run) and campaign_id the admin send it belonged to.
 * Plain indexed columns, so existing rows are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appreciation_email_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('sent_by')->nullable()->after('message');
            $table->unsignedBigInteger('campaign_id')->nullable()->after('sent_by');
            $table->index('campaign_id');
            $table->index(['week_key', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appreciation_email_logs', function (Blueprint $table): void {
            $table->dropIndex(['campaign_id']);
            $table->dropIndex(['week_key', 'sent_at']);
            $table->dropColumn(['sent_by', 'campaign_id']);
        });
    }
};
