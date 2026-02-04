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
        Schema::create('life_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Event details
            $table->string('event_name', 100);
            $table->enum('event_type', [
                // Income events (positive)
                'inheritance',
                'gift_received',
                'bonus',
                'redundancy_payment',
                'property_sale',
                'business_sale',
                'pension_lump_sum',
                'lottery_windfall',
                // Expenditure events (negative)
                'large_purchase',
                'home_improvement',
                'wedding',
                'education_fees',
                'gift_given',
                'medical_expense',
                // Custom
                'custom_income',
                'custom_expense',
            ]);
            $table->text('description')->nullable();

            // Financial
            $table->decimal('amount', 15, 2);
            $table->enum('impact_type', ['income', 'expense']);
            $table->date('expected_date');
            $table->enum('certainty', ['confirmed', 'likely', 'possible', 'speculative'])->default('likely');

            // Display settings
            $table->string('icon', 50)->nullable();
            $table->boolean('show_in_projection')->default(true);
            $table->boolean('show_in_household_view')->default(true);

            // Ownership (single-record pattern for joint assets)
            $table->enum('ownership_type', ['individual', 'joint'])->default('individual');
            $table->foreignId('joint_owner_id')->nullable()->constrained('users');
            $table->decimal('ownership_percentage', 5, 2)->default(100);

            // Status
            $table->enum('status', ['expected', 'confirmed', 'completed', 'cancelled'])->default('expected');
            $table->timestamp('occurred_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'expected_date']);
            $table->index(['user_id', 'impact_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('life_events');
    }
};
