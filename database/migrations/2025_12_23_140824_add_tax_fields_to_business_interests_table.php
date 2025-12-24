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
        Schema::table('business_interests', function (Blueprint $table) {
            // VAT & Tax Registration
            $table->boolean('vat_registered')->default(false)->after('country');
            $table->string('vat_number')->nullable()->after('vat_registered');
            $table->string('utr_number')->nullable()->after('vat_number')->comment('Unique Tax Reference for Self Assessment');
            $table->date('tax_year_end')->nullable()->after('utr_number')->comment('Company financial year-end date');

            // Employment
            $table->unsignedInteger('employee_count')->default(0)->after('tax_year_end');
            $table->string('paye_reference')->nullable()->after('employee_count')->comment('PAYE scheme reference');

            // Trading Status
            $table->enum('trading_status', ['trading', 'dormant', 'pre_trading'])->default('trading')->after('paye_reference');

            // Exit Planning / BADR
            $table->date('acquisition_date')->nullable()->after('trading_status')->comment('Date business was acquired for BADR calculation');
            $table->decimal('acquisition_cost', 15, 2)->nullable()->after('acquisition_date')->comment('Original investment/cost basis');
            $table->boolean('bpr_eligible')->default(false)->after('acquisition_cost')->comment('Business Property Relief eligible for IHT');

            // Industry
            $table->string('industry_sector')->nullable()->after('bpr_eligible');

            // Add index for trading status
            $table->index('trading_status', 'business_interests_trading_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_interests', function (Blueprint $table) {
            $table->dropIndex('business_interests_trading_status_idx');

            $table->dropColumn([
                'vat_registered',
                'vat_number',
                'utr_number',
                'tax_year_end',
                'employee_count',
                'paye_reference',
                'trading_status',
                'acquisition_date',
                'acquisition_cost',
                'bpr_eligible',
                'industry_sector',
            ]);
        });
    }
};
