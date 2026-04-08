<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 20)->unique();
            $table->enum('status', ['draft', 'issued', 'void'])->default('issued');
            $table->integer('subtotal_amount');
            $table->integer('discount_amount')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total_amount');
            $table->string('currency', 3)->default('GBP');
            $table->string('discount_code', 50)->nullable();
            $table->string('discount_description', 100)->nullable();
            $table->string('plan_name', 100);
            $table->string('billing_cycle', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('next_renewal_date')->nullable();
            $table->timestamp('issued_at');
            $table->string('pdf_path', 255)->nullable();
            $table->string('billing_name', 255)->nullable();
            $table->string('billing_email', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
