<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marriage_allowance_eligible')
                ->nullable()
                ->after('signup_source')
                ->comment('Set true when spouse_works=no during savetax campaign onboarding');
            $table->string('household_calculation_mode', 32)
                ->nullable()
                ->after('marriage_allowance_eligible')
                ->comment('single | dual_earner | single_earner_couple — set by capture_spouse_work_status tool');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marriage_allowance_eligible', 'household_calculation_mode']);
        });
    }
};
