<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE life_events MODIFY COLUMN event_type ENUM(
            'inheritance', 'gift_received', 'bonus', 'redundancy_payment',
            'property_sale', 'business_sale', 'pension_lump_sum', 'lottery_windfall',
            'large_purchase', 'home_improvement', 'wedding', 'education_fees',
            'gift_given', 'medical_expense', 'custom_income', 'custom_expense',
            'divorce', 'marriage', 'new_child', 'job_loss', 'income_change'
        ) NOT NULL");
    }

    public function down(): void
    {
        // Cannot safely remove enum values if data exists
    }
};
