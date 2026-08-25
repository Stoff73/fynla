<?php

declare(strict_types=1);

use App\Support\SharedOwnership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair rows the joint-share write bug produced (W-0014).
 *
 * Investment and savings writes only supplied the 50% joint default when
 * `ownership_percentage` was ABSENT from the payload. Every form sends it,
 * initialised to the individual default of 100, so shared assets were stored
 * 100/0 — the primary owner shown as owning the whole thing and the joint
 * owner nothing.
 *
 * `CalculatesOwnershipShare` was silently rewriting a stored 100 to 50 on read,
 * which hid the bad data on every surface that used the trait while surfaces
 * doing their own arithmetic showed the raw 100 (W-0015). That rewrite is gone,
 * so the stored value has to be right.
 *
 * These rows were ALREADY being treated as 50/50 by every trait consumer
 * (net worth, wealth summary, estate, household), so this migration changes no
 * displayed figure on those surfaces — it makes the stored value agree with
 * what was already being shown.
 *
 * business_interests is deliberately excluded: there `ownership_percentage` is a
 * shareholding, and 100% of a company you own outright is a legitimate value.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'properties',
        'savings_accounts',
        'investment_accounts',
        'mortgages',
        'chattels',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)
                ->whereIn('ownership_type', SharedOwnership::SHARED_TYPES)
                ->where('ownership_percentage', SharedOwnership::INDIVIDUAL_PERCENTAGE)
                ->update(['ownership_percentage' => SharedOwnership::DEFAULT_PERCENTAGE]);
        }
    }

    public function down(): void
    {
        // Not reversible: a 50/50 shared asset written deliberately is
        // indistinguishable from one this migration corrected, and restoring
        // 100/0 would reinstate the double-count.
    }
};
