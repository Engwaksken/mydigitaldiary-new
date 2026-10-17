<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * High-water mark for each business's invoice sequence, so a deleted invoice
 * never frees its number for reuse. No backfill is needed: the next number is
 * always max(this counter, highest existing invoice) + 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('last_business_invoice_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_business_invoice_number');
        });
    }
};
