<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goals')) {
            return;
        }

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('goal_name', 100);
            $table->enum('goal_type', [
                'emergency_fund',
                'property_purchase',
                'home_deposit',
                'education',
                'retirement',
                'wealth_accumulation',
                'wedding',
                'holiday',
                'car_purchase',
                'debt_repayment',
                'custom',
            ]);
            $table->string('custom_goal_type_name', 100)->nullable();
            $table->text('description')->nullable();

            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->date('target_date');
            $table->date('start_date')->nullable();

            $table->enum('assigned_module', ['savings', 'investment', 'property', 'retirement']);
            $table->boolean('module_override')->default(false);

            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->boolean('is_essential')->default(false);
            $table->enum('status', ['active', 'paused', 'completed', 'abandoned'])->default('active');

            $table->decimal('monthly_contribution', 12, 2)->nullable();
            $table->enum('contribution_frequency', ['weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->unsignedInteger('contribution_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_contribution_date')->nullable();

            $table->json('linked_account_ids')->nullable();
            $table->foreignId('linked_savings_account_id')->nullable()->constrained('savings_accounts')->onDelete('set null');

            $table->unsignedTinyInteger('risk_preference')->nullable();
            $table->boolean('use_global_risk_profile')->default(true);

            $table->enum('ownership_type', ['individual', 'joint'])->default('individual');
            $table->foreignId('joint_owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('ownership_percentage', 5, 2)->default(100);

            $table->string('property_location', 255)->nullable();
            $table->enum('property_type', ['house', 'flat', 'bungalow', 'terraced', 'semi_detached', 'detached', 'other'])->nullable();
            $table->boolean('is_first_time_buyer')->nullable();
            $table->decimal('estimated_property_price', 15, 2)->nullable();
            $table->decimal('deposit_percentage', 5, 2)->nullable();
            $table->decimal('stamp_duty_estimate', 12, 2)->nullable();
            $table->decimal('additional_costs_estimate', 12, 2)->nullable();

            $table->json('milestones')->nullable();
            $table->json('projection_data')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'assigned_module']);
            $table->index(['user_id', 'goal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
