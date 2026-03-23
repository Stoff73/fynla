<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make all non-nullable enum columns nullable to prevent
     * insert/update errors when the field is not provided.
     */
    public function up(): void
    {
        $alterations = [
            // goals table (v2)
            ['goals', 'goal_type', "MODIFY COLUMN `goal_type` ENUM('emergency_fund','property_purchase','home_deposit','education','retirement','wealth_accumulation','wedding','holiday','car_purchase','debt_repayment','custom') NULL"],
            ['goals', 'assigned_module', "MODIFY COLUMN `assigned_module` ENUM('savings','investment','property','retirement') NULL"],

            // goal_contributions table (v2)
            ['goal_contributions', 'contribution_type', "MODIFY COLUMN `contribution_type` ENUM('manual','automatic','lump_sum','interest','adjustment') NULL"],

            // client_activities table
            ['client_activities', 'activity_type', "MODIFY COLUMN `activity_type` ENUM('email','phone','meeting','letter','suitability_report','review','note') NULL"],

            // user_assumptions table
            ['user_assumptions', 'assumption_type', "MODIFY COLUMN `assumption_type` ENUM('pensions','investments') NULL"],

            // ai_messages table
            ['ai_messages', 'role', "MODIFY COLUMN `role` ENUM('user','assistant','system','tool_result') NULL"],

            // lpa_attorneys table
            ['lpa_attorneys', 'attorney_type', "MODIFY COLUMN `attorney_type` ENUM('primary','replacement') NULL"],

            // will_documents table
            ['will_documents', 'will_type', "MODIFY COLUMN `will_type` ENUM('simple','mirror') NULL"],

            // device_tokens table
            ['device_tokens', 'platform', "MODIFY COLUMN `platform` ENUM('ios','android') NULL"],

            // life_events table
            ['life_events', 'event_type', "MODIFY COLUMN `event_type` ENUM('inheritance','gift_received','bonus','redundancy_payment','property_sale','business_sale','pension_lump_sum','lottery_windfall','large_purchase','home_improvement','wedding','education_fees','gift_given','medical_expense','custom_income','custom_expense') NULL"],
            ['life_events', 'impact_type', "MODIFY COLUMN `impact_type` ENUM('income','expense') NULL"],

            // subscriptions table
            ['subscriptions', 'plan', "MODIFY COLUMN `plan` ENUM('student','standard','pro') NULL"],
            ['subscriptions', 'billing_cycle', "MODIFY COLUMN `billing_cycle` ENUM('monthly','yearly') NULL"],

            // lasting_powers_of_attorney table
            ['lasting_powers_of_attorney', 'lpa_type', "MODIFY COLUMN `lpa_type` ENUM('property_financial','health_welfare') NULL"],

            // life_event_allocations table
            ['life_event_allocations', 'allocation_step', "MODIFY COLUMN `allocation_step` ENUM('goals','isa','pension','bond','cash') NULL"],
        ];

        foreach ($alterations as [$table, $column, $sql]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` {$sql}");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $reversals = [
            ['goals', 'goal_type', "MODIFY COLUMN `goal_type` ENUM('emergency_fund','property_purchase','home_deposit','education','retirement','wealth_accumulation','wedding','holiday','car_purchase','debt_repayment','custom') NOT NULL"],
            ['goals', 'assigned_module', "MODIFY COLUMN `assigned_module` ENUM('savings','investment','property','retirement') NOT NULL"],
            ['goal_contributions', 'contribution_type', "MODIFY COLUMN `contribution_type` ENUM('manual','automatic','lump_sum','interest','adjustment') NOT NULL"],
            ['client_activities', 'activity_type', "MODIFY COLUMN `activity_type` ENUM('email','phone','meeting','letter','suitability_report','review','note') NOT NULL"],
            ['user_assumptions', 'assumption_type', "MODIFY COLUMN `assumption_type` ENUM('pensions','investments') NOT NULL"],
            ['ai_messages', 'role', "MODIFY COLUMN `role` ENUM('user','assistant','system','tool_result') NOT NULL"],
            ['lpa_attorneys', 'attorney_type', "MODIFY COLUMN `attorney_type` ENUM('primary','replacement') NOT NULL"],
            ['will_documents', 'will_type', "MODIFY COLUMN `will_type` ENUM('simple','mirror') NOT NULL"],
            ['device_tokens', 'platform', "MODIFY COLUMN `platform` ENUM('ios','android') NOT NULL"],
            ['life_events', 'event_type', "MODIFY COLUMN `event_type` ENUM('inheritance','gift_received','bonus','redundancy_payment','property_sale','business_sale','pension_lump_sum','lottery_windfall','large_purchase','home_improvement','wedding','education_fees','gift_given','medical_expense','custom_income','custom_expense') NOT NULL"],
            ['life_events', 'impact_type', "MODIFY COLUMN `impact_type` ENUM('income','expense') NOT NULL"],
            ['subscriptions', 'plan', "MODIFY COLUMN `plan` ENUM('student','standard','pro') NOT NULL"],
            ['subscriptions', 'billing_cycle', "MODIFY COLUMN `billing_cycle` ENUM('monthly','yearly') NOT NULL"],
            ['lasting_powers_of_attorney', 'lpa_type', "MODIFY COLUMN `lpa_type` ENUM('property_financial','health_welfare') NOT NULL"],
            ['life_event_allocations', 'allocation_step', "MODIFY COLUMN `allocation_step` ENUM('goals','isa','pension','bond','cash') NOT NULL"],
        ];

        foreach ($reversals as [$table, $column, $sql]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` {$sql}");
            }
        }
    }
};
