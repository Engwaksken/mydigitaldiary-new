<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 40);
            $table->string('business_name');
            $table->text('business_address')->nullable();
            $table->string('business_email')->nullable();
            $table->string('business_phone', 40)->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->json('line_items');
            $table->string('currency', 3)->default('UGX');
            $table->decimal('total', 14, 2);
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'invoice_number']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_invoices');
    }
};
