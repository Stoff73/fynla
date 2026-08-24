<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filing due dates read from the Companies House Public Data API.
 *
 * company_number already exists on this table — these three columns cache the
 * answer so the daily alert sweep does not hit the API once per user per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_interests', function (Blueprint $table) {
            $table->date('accounts_due_on')
                ->nullable()
                ->after('company_number')
                ->comment('Companies House: next annual accounts filing deadline');

            $table->date('confirmation_statement_due_on')
                ->nullable()
                ->after('accounts_due_on')
                ->comment('Companies House: next confirmation statement deadline');

            $table->timestamp('companies_house_synced_at')
                ->nullable()
                ->after('confirmation_statement_due_on')
                ->comment('When the two dates above were last read from Companies House');
        });
    }

    public function down(): void
    {
        Schema::table('business_interests', function (Blueprint $table) {
            $table->dropColumn([
                'accounts_due_on',
                'confirmation_statement_due_on',
                'companies_house_synced_at',
            ]);
        });
    }
};
