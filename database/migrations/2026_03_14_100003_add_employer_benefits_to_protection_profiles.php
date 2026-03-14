<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('protection_profiles', 'death_in_service_multiple')) {
            return;
        }

        Schema::table('protection_profiles', function (Blueprint $table) {
            $table->decimal('death_in_service_multiple', 5, 2)->nullable()->after('smoker_status');
            $table->decimal('group_ip_benefit_percent', 5, 2)->nullable()->after('death_in_service_multiple');
            $table->integer('group_ip_benefit_months')->nullable()->after('group_ip_benefit_percent');
            $table->string('group_ip_definition', 50)->nullable()->after('group_ip_benefit_months');
            $table->decimal('group_ci_amount', 15, 2)->nullable()->after('group_ip_definition');
            $table->boolean('has_employer_pmi')->default(false)->after('group_ci_amount');
            $table->string('employer_name')->nullable()->after('has_employer_pmi');
        });
    }

    public function down(): void
    {
        Schema::table('protection_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'death_in_service_multiple',
                'group_ip_benefit_percent',
                'group_ip_benefit_months',
                'group_ip_definition',
                'group_ci_amount',
                'has_employer_pmi',
                'employer_name',
            ]);
        });
    }
};
