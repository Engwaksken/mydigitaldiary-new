<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_invoice_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('storage_path');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('business_invoices', function (Blueprint $table): void {
            $table->foreignId('template_id')->nullable()->after('user_id')
                ->constrained('business_invoice_templates')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('sent_at');
        });

        Schema::table('incomes', function (Blueprint $table): void {
            $table->foreignId('business_invoice_id')->nullable()->after('user_id');
            $table->foreign('business_invoice_id')->references('id')->on('business_invoices')->nullOnDelete();
            $table->unique('business_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            $table->dropForeign(['business_invoice_id']);
            $table->dropUnique(['business_invoice_id']);
            $table->dropColumn('business_invoice_id');
        });

        Schema::table('business_invoices', function (Blueprint $table): void {
            $table->dropForeign(['template_id']);
            $table->dropColumn(['template_id', 'paid_at']);
        });

        Schema::dropIfExists('business_invoice_templates');
    }
};
