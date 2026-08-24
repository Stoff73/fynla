<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Charitable giving had no monthly column, unlike every other expenditure
 * category. The Expenditure form has carried a "Charitable Donations" monthly
 * line since it was built, but `charitable_donations` was neither a column nor
 * in the endpoint's validation list, so the figure was discarded on every save.
 * Its only persistence was a side-channel write of `annual_charitable_donations
 * = monthly * 12` that nothing ever read back — so the field showed 0 on reload
 * and, with Gift Aid on, the next save wrote 0 over whatever was there.
 *
 * The monthly figure becomes the stored one, like `gifts_charity` beside it.
 * `annual_charitable_donations` stays and is derived (x12) on write, because
 * IHT planning, ResolvesIncome and PersonalAccountsService all read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'charitable_donations')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->decimal('charitable_donations', 12, 2)->nullable()->after('gifts_charity');
            });
        }

        // Seed the new monthly column from the annual figure already on file so
        // the form shows what the user (or Fyn) previously recorded instead of
        // a blank that the next save would commit as zero.
        DB::table('users')
            ->whereNull('charitable_donations')
            ->whereNotNull('annual_charitable_donations')
            ->update([
                'charitable_donations' => DB::raw('annual_charitable_donations / 12'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'charitable_donations')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('charitable_donations');
        });
    }
};
