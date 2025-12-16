<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_product_reference', function (Blueprint $table) {
            $table->id();
            $table->string('product_category');  // 'investment' or 'savings'
            $table->string('product_type');      // 'isa', 'gia', 'cash_isa', etc.
            $table->string('tax_aspect');        // 'income_tax', 'cgt', 'iht', 'allowances', 'special_features'
            $table->string('title');             // Display title
            $table->text('summary');             // Bullet point summary
            $table->string('status');            // 'exempt', 'taxable', 'deferred', 'relief', 'limit'
            $table->string('status_icon')->nullable();  // Icon identifier for frontend
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['product_category', 'product_type']);
            $table->index(['product_type', 'tax_aspect']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_product_reference');
    }
};
