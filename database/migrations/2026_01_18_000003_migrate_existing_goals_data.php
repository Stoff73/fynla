<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrates existing SavingsGoal and InvestmentGoal records to the new unified goals table.
     */
    public function up(): void
    {
        // Migrate savings_goals
        if (Schema::hasTable('savings_goals')) {
            $savingsGoals = DB::table('savings_goals')->get();

            foreach ($savingsGoals as $oldGoal) {
                DB::table('goals')->insert([
                    'user_id' => $oldGoal->user_id,
                    'goal_name' => $oldGoal->goal_name,
                    'goal_type' => 'custom',
                    'custom_goal_type_name' => null,
                    'description' => null,
                    'target_amount' => $oldGoal->target_amount,
                    'current_amount' => $oldGoal->current_saved ?? 0,
                    'target_date' => $oldGoal->target_date,
                    'start_date' => $oldGoal->created_at,
                    'assigned_module' => 'savings',
                    'module_override' => false,
                    'priority' => $oldGoal->priority ?? 'medium',
                    'is_essential' => false,
                    'status' => 'active',
                    'monthly_contribution' => $oldGoal->auto_transfer_amount,
                    'contribution_frequency' => 'monthly',
                    'contribution_streak' => 0,
                    'longest_streak' => 0,
                    'last_contribution_date' => null,
                    'linked_account_ids' => $oldGoal->linked_account_id ? json_encode([$oldGoal->linked_account_id]) : null,
                    'linked_savings_account_id' => $oldGoal->linked_account_id,
                    'risk_preference' => null,
                    'use_global_risk_profile' => true,
                    'ownership_type' => 'individual',
                    'joint_owner_id' => null,
                    'ownership_percentage' => 100,
                    'property_location' => null,
                    'property_type' => null,
                    'is_first_time_buyer' => null,
                    'estimated_property_price' => null,
                    'deposit_percentage' => null,
                    'stamp_duty_estimate' => null,
                    'additional_costs_estimate' => null,
                    'milestones' => null,
                    'projection_data' => null,
                    'completed_at' => null,
                    'completion_notes' => null,
                    'created_at' => $oldGoal->created_at,
                    'updated_at' => $oldGoal->updated_at,
                ]);
            }
        }

        // Migrate investment_goals
        if (Schema::hasTable('investment_goals')) {
            $investmentGoals = DB::table('investment_goals')->get();

            foreach ($investmentGoals as $oldGoal) {
                // Map old goal_type to new goal_type
                $goalType = match ($oldGoal->goal_type ?? 'custom') {
                    'retirement' => 'retirement',
                    'education' => 'education',
                    'property' => 'property_purchase',
                    'wealth' => 'wealth_accumulation',
                    default => 'custom',
                };

                // Determine assigned module based on goal type
                $assignedModule = match ($goalType) {
                    'retirement' => 'retirement',
                    'property_purchase' => 'property',
                    default => 'investment',
                };

                DB::table('goals')->insert([
                    'user_id' => $oldGoal->user_id,
                    'goal_name' => $oldGoal->goal_name,
                    'goal_type' => $goalType,
                    'custom_goal_type_name' => $goalType === 'custom' ? $oldGoal->goal_type : null,
                    'description' => null,
                    'target_amount' => $oldGoal->target_amount,
                    'current_amount' => 0,
                    'target_date' => $oldGoal->target_date,
                    'start_date' => $oldGoal->created_at,
                    'assigned_module' => $assignedModule,
                    'module_override' => false,
                    'priority' => $oldGoal->priority ?? 'medium',
                    'is_essential' => $oldGoal->is_essential ?? false,
                    'status' => 'active',
                    'monthly_contribution' => null,
                    'contribution_frequency' => 'monthly',
                    'contribution_streak' => 0,
                    'longest_streak' => 0,
                    'last_contribution_date' => null,
                    'linked_account_ids' => $oldGoal->linked_account_ids,
                    'linked_savings_account_id' => null,
                    'risk_preference' => null,
                    'use_global_risk_profile' => true,
                    'ownership_type' => 'individual',
                    'joint_owner_id' => null,
                    'ownership_percentage' => 100,
                    'property_location' => null,
                    'property_type' => null,
                    'is_first_time_buyer' => null,
                    'estimated_property_price' => null,
                    'deposit_percentage' => null,
                    'stamp_duty_estimate' => null,
                    'additional_costs_estimate' => null,
                    'milestones' => null,
                    'projection_data' => null,
                    'completed_at' => null,
                    'completion_notes' => null,
                    'created_at' => $oldGoal->created_at,
                    'updated_at' => $oldGoal->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Simply truncate the goals table - the original tables are preserved
        DB::table('goals')->truncate();
    }
};
